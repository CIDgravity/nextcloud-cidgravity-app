#!/bin/sh
# Nextcloud "before-starting" entrypoint hook.
#
# Runs as root on every container boot, after the image has finished any
# install/upgrade step and just before Apache starts. We drop to www-data to
# run occ. Everything here is idempotent, so it is safe to run on each boot
# (fresh install, restart, or existing volume + newly added app).
#
# APP_ID and EXTRA_APPS are passed in via the container environment.
set -eu

occ() { runuser -u www-data -- php /var/www/html/occ "$@"; }

# Nextcloud may not be installed yet (e.g. fresh volume taking the web-wizard
# path instead of auto-install). Skip cleanly rather than erroring.
if ! occ status --output=json 2>/dev/null | grep -q '"installed":true'; then
	echo "[enable-app] Nextcloud not installed yet; skipping app enable."
	exit 0
fi

# Enable dependency apps first (e.g. files_external for CIDgravity).
for app in ${EXTRA_APPS:-}; do
	echo "[enable-app] enabling dependency app: $app"
	occ app:enable "$app" || echo "[enable-app] could not enable dependency: $app"
done

# Enable the app under test.
if [ -n "${APP_ID:-}" ]; then
	echo "[enable-app] enabling app under test: $APP_ID"
	if ! occ app:enable "$APP_ID"; then
		echo "[enable-app] FAILED to enable '$APP_ID'."
		echo "[enable-app] Is the frontend built (js/ present) and the Nextcloud version within the app's min/max range?"
	fi
fi
