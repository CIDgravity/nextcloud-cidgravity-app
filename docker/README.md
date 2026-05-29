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
| `make docker-clean` | Tear down **including data volumes** (next up = clean install) |

## Testing the app

1. Log in as admin.
2. Go to **Settings → Administration → External storage** — the **CIDgravity**
   backend should appear in the "Add storage" dropdown.
3. The optional gateway backend only shows up after enabling its flag:

   ```sh
   docker compose exec --user www-data nextcloud \
     php occ config:system:set cidgravity_gateway_external_storage_enabled --value=true --type=boolean
   ```

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
- **Port already in use.** Change `NC_PORT` in `.env`.
