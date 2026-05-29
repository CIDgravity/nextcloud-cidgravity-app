#!/bin/sh
# Nextcloud entrypoint hook: enable the app under test (+ its deps) and,
# optionally, disable everything else for a lighter test instance.
#
# Mounted into BOTH post-installation and before-starting:
#   - post-installation runs once, right after a fresh unattended install.
#   - before-starting runs on every boot (covers restarts and existing volumes).
# Both are idempotent. Also runnable on demand via `make docker-minimal`
# (which sets FORCE_MINIMAL=1).
#
# NOTE: the official image runs hooks as www-data (via `su -p www-data`), so we
# call occ directly here -- do NOT wrap it in runuser/su (that needs root and
# would fail). custom_apps ownership is fixed as root in the compose entrypoint.
#
# Env (from docker-compose.yml): APP_ID, EXTRA_APPS, MINIMAL_APPS, KEEP_APPS.
set -eu

occ() { php /var/www/html/occ "$@"; }

# The install state isn't always settled the instant a hook fires, so wait
# (bounded) rather than giving up immediately. If it never reports installed
# (e.g. the web-wizard path was chosen), skip cleanly without blocking boot.
i=0
until occ status --output=json 2>/dev/null | grep -q '"installed":true'; do
	i=$((i + 1))
	if [ "$i" -ge 30 ]; then
		echo "[enable-app] Nextcloud not installed after waiting; skipping."
		echo "[enable-app] Enable manually with: make docker-occ CMD=\"app:enable ${APP_ID:-<app>}\""
		exit 0
	fi
	sleep 1
done

# Enable dependency apps first, then the app under test.
for app in ${EXTRA_APPS:-}; do
	echo "[enable-app] enabling dependency app: $app"
	occ app:enable "$app" || echo "[enable-app] could not enable dependency: $app"
done
if [ -n "${APP_ID:-}" ]; then
	echo "[enable-app] enabling app under test: $APP_ID"
	occ app:enable "$APP_ID" || echo "[enable-app] FAILED to enable '$APP_ID' (frontend built? version in range?)."
fi

# Minimal mode: disable every enabled app except the ones needed to test APP_ID.
# Runs once on a fresh install (post-installation hook) or on demand
# (FORCE_MINIMAL=1). Core apps that cannot be disabled (files, dav, settings,
# theming, ...) are skipped silently -- that's the irreducible minimum.
if [ "${MINIMAL_APPS:-false}" = "true" ]; then
	case "${FORCE_MINIMAL:-}${0}" in
	1* | *post-installation*)
		echo "[enable-app] MINIMAL_APPS=true -> disabling apps not needed to test ${APP_ID:-the app}"
		keep=" ${APP_ID:-} ${EXTRA_APPS:-} ${KEEP_APPS:-} "
		enabled=$(occ app:list --output=json 2>/dev/null \
			| php -r '$d=json_decode(stream_get_contents(STDIN),true); echo implode(" ", array_keys($d["enabled"] ?? []));')
		for app in $enabled; do
			case "$keep" in *" $app "*) continue ;; esac
			occ app:disable "$app" >/dev/null 2>&1 && echo "[enable-app] disabled $app" || true
		done
		;;
	esac
fi

# Optionally create ONE external storage mount (idempotent). Driven entirely by
# env so the stack stays app-agnostic; .env pre-fills the CIDgravity defaults.
# Skipped unless EXT_STORAGE_MOUNT is set. Config values must not contain spaces.
if [ -n "${EXT_STORAGE_MOUNT:-}" ]; then
	exists=$(occ files_external:list --output=json 2>/dev/null | php -r '
		$d = json_decode(stream_get_contents(STDIN), true) ?: [];
		$mp = "/" . getenv("EXT_STORAGE_MOUNT");
		foreach ($d as $m) { if (($m["mount_point"] ?? "") === $mp) { echo "yes"; break; } }')
	if [ "$exists" = "yes" ]; then
		echo "[enable-app] external storage '$EXT_STORAGE_MOUNT' already exists; leaving it."
	else
		echo "[enable-app] creating external storage '$EXT_STORAGE_MOUNT' (${EXT_STORAGE_BACKEND:-cidgravity})"
		( set --
		  for kv in ${EXT_STORAGE_CONFIG:-}; do set -- "$@" -c "$kv"; done
		  occ files_external:create "$EXT_STORAGE_MOUNT" \
			"${EXT_STORAGE_BACKEND:-cidgravity}" "${EXT_STORAGE_AUTH:-password::password}" "$@"
		) || echo "[enable-app] could not create external storage '$EXT_STORAGE_MOUNT'"
	fi
fi
