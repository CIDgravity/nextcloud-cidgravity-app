# Local Nextcloud test stack

A disposable Docker Compose stack to test this app against a real Nextcloud,
without maintaining a permanent dev server.

**Services:** Nextcloud (Apache) + PostgreSQL + Redis — a standard, production-ish
setup. The repo is mounted into `custom_apps/` and the app is enabled
automatically on first boot.

## Requirements

- Docker + the Docker Compose plugin (`docker compose`, v2).
- The app's frontend must be built (the `js/` folder must exist). It is already
  built in a normal checkout; rebuild with `npm ci && npm run build` from the
  repo root if it's missing.

## Quick start

```sh
cd docker
cp .env.example .env          # optionally tweak version / port / credentials
docker compose up -d          # first boot installs NC + enables files_external + cidgravity
```

Then open <http://localhost:8080> and log in with `admin` / `admin`
(or whatever you set in `.env`).

First boot pulls images and installs Nextcloud, so it takes a minute or two.
Follow along with:

```sh
docker compose logs -f nextcloud      # watch for the [enable-app] lines
```

## Make shortcuts

From the repo root (these wrap the `docker compose` commands below):

| Target | Action |
| --- | --- |
| `make docker-up` | Create `docker/.env` if missing, then start the stack in the background |
| `make docker-down` | Stop + remove containers and network (keeps data volumes) |
| `make docker-stop` | Pause containers without removing them |
| `make docker-restart` | Restart just the Nextcloud container (e.g. after PHP changes) |
| `make docker-ps` | Show container status |
| `make docker-logs` | Follow the Nextcloud logs |
| `make docker-occ CMD="app:list"` | Run an occ command |
| `make docker-shell` | Open a shell in the Nextcloud container as `www-data` |
| `make docker-minimal` | Disable every app not needed to test the app (lighter instance) |
| `make docker-clean` | Tear down **including data volumes** (next up = clean install) |
| `make docker-reset` | Wipe volumes **and** start fresh — apply changed `.env` from scratch |

### Applying `.env` changes

`docker-down`/`docker-up` keep the volumes, and a lot of state lives in the
**database** — enabled apps, system config, and external-storage mounts. So:

- **System config (`NC_CONFIG`) and app enable/minimal** are re-applied on every
  boot, so `make docker-up` (which recreates the container with the new env)
  picks those up.
- **External-storage mounts (`EXT_STORAGE_*`)** are created idempotently — an
  existing mount is *kept*, so editing its credentials/URLs in `.env` does **not**
  update the mount already in the DB. New mount names (new slots) are created on
  the next `docker-up`, but changes to existing ones need a clean DB.

To apply changed `EXT_STORAGE_*` (or DB/admin values), reset from scratch:

```sh
make docker-reset      # = down -v && up -d
```

(Or edit the mount directly in the UI / `make docker-occ CMD="files_external:..."`
if you don't want to wipe the instance.)

## Testing the app

1. Log in as admin.
2. Go to **Settings → Administration → External storage** — both the
   **CIDgravity** and **CIDgravity Gateway** backends appear in the "Add storage"
   dropdown. (The gateway is enabled by default via `NC_CONFIG`; see below to
   turn it off.)

## Nextcloud system config

To change Nextcloud system config without editing `config.php` inside the
container, list `key=value` pairs in `NC_CONFIG`; they're applied on every boot.
Type is inferred — `true`/`false` → boolean, digits → integer, otherwise string.
By default it enables the gateway backend; append more keys as needed:

```sh
NC_CONFIG=cidgravity_gateway_external_storage_enabled=true loglevel=0
```

Empty `NC_CONFIG` to disable the gateway backend (only the plain CIDgravity
backend remains).

- Values must not contain spaces.
- **Changing `.env` needs a recreate, not a restart:** run `make docker-up`
  again (Compose recreates the container with the new env). `make docker-restart`
  keeps the old values.
- For a quick one-off change to the running instance, use `make docker-occ
  CMD="config:system:set …"` — it takes effect immediately, no recreate.

## Auto-create external storage mounts

Instead of adding storage by hand in the UI every time, you can have mounts
created automatically on boot. Define up to 3 in `.env` via indexed slots
`EXT_STORAGE_<i>_{MOUNT,BACKEND,AUTH,CONFIG}`. `.env.example` ships two by
default — one `cidgravity` and one `cidgravityGateway`:

```sh
EXT_STORAGE_1_MOUNT=CIDgravity
EXT_STORAGE_1_BACKEND=cidgravity
EXT_STORAGE_1_CONFIG=host=https://nextcloud.twinquasar.io secure=true root=PublicFilecoin default_ipfs_gateway=https://ipfs.io/ipfs user=YOUR_USER password=YOUR_PASSWORD

EXT_STORAGE_2_MOUNT=CIDgravityGateway
EXT_STORAGE_2_BACKEND=cidgravityGateway
EXT_STORAGE_2_CONFIG=host=https://gateway.example.com secure=true metadata_endpoint=https://gateway.example.com/metadata default_ipfs_gateway=https://ipfs.io/ipfs auto_create_user_folder=true user=YOUR_USER password=YOUR_PASSWORD
```

Config keys match the backends
([CIDgravityBackendService](../lib/Service/Backend/CIDgravityBackendService.php),
[CIDgravityGatewayBackendService](../lib/Service/Backend/CIDgravityGatewayBackendService.php)).
`_AUTH` defaults to `password::password`. Each mount is a **system mount
available to all users** and is created idempotently — existing mounts are kept.

Notes:
- Creation stops at the first empty `_MOUNT`, so empty a slot to skip it.
- Fill in your real credentials / gateway URLs — the defaults are placeholders.
- Values in `_CONFIG` must not contain spaces. Credentials live only in your
  local (gitignored) `.env`.
- Generic — point a slot's `_BACKEND`/`_AUTH`/`_CONFIG` at any external-storage
  backend to reuse for another app. Need more than 3? Add slots to both `.env`
  and the `environment:` block in `docker-compose.yml`.
- Verify with `make docker-occ CMD="files_external:list"`.

## Minimal instance

To keep the instance light, `MINIMAL_APPS` is **on by default** (set
`MINIMAL_APPS=false` to opt out). It disables every default/onboarding/sharing
app that isn't needed to test the app
— dashboard, firstrunwizard, activity, photos, text, files_sharing, etc. Only
the app under test (`APP_ID`), its dependencies (`EXTRA_APPS`), anything in
`KEEP_APPS`, and Nextcloud's always-enabled core (files, dav, settings, theming,
…) stay enabled.

- It runs automatically on a **fresh install**. Re-run anytime with
  `make docker-minimal` (idempotent).
- Keep extra apps by listing them in `KEEP_APPS`, e.g.
  `KEEP_APPS=files_versions files_trashbin`.
- Turn it off with `MINIMAL_APPS=false` to get a stock Nextcloud.
- Apps are *disabled*, not removed: shipped apps live in the image and would be
  restored on a clean reinstall, so disabling is the right (and reversible) lever.

> If you already have a `docker/.env` from before this option existed, add
> `MINIMAL_APPS=true` to it (or delete `docker/.env` and re-run `make docker-up`
> to regenerate it from `.env.example`).

## Frontend dev loop

The repo is bind-mounted, so rebuilt assets are picked up live. From the repo root:

```sh
npm run watch                 # rebuilds js/ on change
```

Then hard-refresh the browser (Ctrl/Cmd+Shift+R) to bypass cached assets.
After changing **PHP** code, reload the app:

```sh
docker compose exec --user www-data nextcloud php occ app:disable cidgravity
docker compose exec --user www-data nextcloud php occ app:enable  cidgravity
```

## Useful commands

```sh
# Run any occ command
docker compose exec --user www-data nextcloud php occ <command>

# Confirm the app is enabled
docker compose exec --user www-data nextcloud php occ app:list | grep cidgravity

# Stop (keep data)
docker compose down

# Full reset — wipe NC install + DB, next `up` is a clean install
docker compose down -v
```

## Testing other Nextcloud versions

The app declares support for Nextcloud 29–32. To test another version, set
`NEXTCLOUD_VERSION` in `.env`, then recreate from a clean install:

```sh
docker compose down -v
NEXTCLOUD_VERSION=31 docker compose up -d   # or just edit .env
```

> In-place version *upgrades* (e.g. bumping the var without `down -v`) only work
> one major at a time and aren't the point here — wipe and reinstall instead.

## Reusing this stack for another app

This folder is app-agnostic. Copy `docker/` into another Nextcloud app repo and
change two values in `.env`:

- `APP_ID` → that app's `<id>` from its `appinfo/info.xml`
- `EXTRA_APPS` → its dependency apps (space-separated; empty if none)

Nothing in `docker-compose.yml` or `hooks/enable-app.sh` needs editing — the app
is mounted at `custom_apps/${APP_ID}` and enabled by id.

## Troubleshooting

- **App won't enable / not listed.** Check `docker compose logs nextcloud` for the
  `[enable-app]` output. Most common causes: `js/` not built, or
  `NEXTCLOUD_VERSION` outside the app's min/max range.
- **"Cannot read app" / permission errors on the mount.** `www-data` (uid 33)
  inside the container must be able to read the checkout. Make it world-readable:
  `chmod -R a+rX ..` (run from this folder).
- **Edited `hooks/enable-app.sh` but nothing changed?** Single-file bind mounts
  pin the file's inode, so a *running* container keeps the old copy. Recreate it:
  `docker compose up -d --force-recreate nextcloud` (or `make docker-down && make docker-up`).
  Editing the app's own PHP/JS is unaffected — that's a directory mount and updates live.
- **Port already in use.** Change `NC_PORT` in `.env`.
