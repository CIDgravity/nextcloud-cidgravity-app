app_name=cidgravity
project_dir=$(CURDIR)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=master

# Local Nextcloud test stack (see docker/README.md)
compose_file=$(CURDIR)/docker/docker-compose.yml
docker_compose=docker compose -f $(compose_file)

all: dev-setup build-js-production

dev-setup: clean-dev npm-init

dependabot: dev-setup npm-update build-js-production

release: appstore create-tag

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

test:
	npm run test:unit

lint:
	npm run lint

lint-fix:
	npm run lint:fix

npm-init:
	npm ci

npm-update:
	npm update

clean:
	rm -rf js/*
	rm -rf $(build_dir)

clean-dev: clean
	rm -rf node_modules

# --- Local Nextcloud test stack ---------------------------------------------

# Create docker/.env from the example on first use (won't overwrite an existing one).
docker-env:
	@test -f $(CURDIR)/docker/.env || cp $(CURDIR)/docker/.env.example $(CURDIR)/docker/.env

# Start the stack in the background (installs Nextcloud + enables the app on first boot).
docker-up: docker-env
	$(docker_compose) up -d

# Stop and remove containers + network, keeping data volumes (fast to bring back up).
docker-down:
	$(docker_compose) down

# Pause the containers without removing them.
docker-stop:
	$(docker_compose) stop

# Restart only the Nextcloud container (e.g. after changing PHP code).
docker-restart:
	$(docker_compose) restart nextcloud

# Show container status.
docker-ps:
	$(docker_compose) ps

# Follow the Nextcloud logs (Ctrl-C to stop following).
docker-logs:
	$(docker_compose) logs -f nextcloud

# Run an occ command, e.g. `make docker-occ CMD="app:list"`.
docker-occ:
	$(docker_compose) exec -T --user www-data nextcloud php occ $(CMD)

# Open a shell in the Nextcloud container as the web user.
docker-shell:
	$(docker_compose) exec --user www-data nextcloud bash

# Disable every app not needed to test the app (lighter instance). Re-runnable;
# honours APP_ID / EXTRA_APPS / KEEP_APPS. Core apps that can't be disabled stay.
docker-minimal:
	$(docker_compose) exec -T -e FORCE_MINIMAL=1 -e MINIMAL_APPS=true --user www-data \
		nextcloud /docker-entrypoint-hooks.d/before-starting/enable-app.sh

# Tear everything down INCLUDING data volumes (next `docker-up` is a clean install).
docker-clean:
	$(docker_compose) down -v

# Apply a changed .env from scratch: wipe volumes (DB + data), then reinstall.
# Use this after editing EXT_STORAGE_* / DB / admin values, which live in the DB
# and are otherwise kept across docker-down/up.
docker-reset: docker-env
	$(docker_compose) down -v
	$(docker_compose) up -d

create-tag:
	git tag -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

appstore:
	rm -rf $(build_dir)
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=babel.config.js \
	--exclude=/build \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=docker \
	--exclude=docs \
	--exclude=.drone.yml \
	--exclude=.eslintignore \
	--exclude=.eslintrc.js \
	--exclude=.git \
	--exclude=.gitattributes \
	--exclude=.github \
	--exclude=.gitignore \
	--exclude=jest.config.js \
	--exclude=.l10nignore \
	--exclude=mkdocs.yml \
	--exclude=Makefile \
	--exclude=node_modules \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=.php_cs.dist \
	--exclude=.php_cs.cache \
	--exclude=README.md \
	--exclude=src \
	--exclude=.stylelintignore \
	--exclude=stylelint.config.js \
	--exclude=.tx \
	--exclude=tests \
	--exclude=vendor \
	--exclude=webpack.*.js \
	$(project_dir)/  $(sign_dir)/$(app_name)

	php ./bin/tools/file_from_env.php "APP_PRIVATE_KEY" "$(cert_dir)/$(app_name).key"
	php ./bin/tools/file_from_env.php "APP_PUBLIC_CRT" "$(cert_dir)/$(app_name).crt"

	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing app files…"; \
		php ../../occ integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(sign_dir)/$(app_name); \
	fi
	tar -czf $(build_dir)/$(app_name).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name).tar.gz | openssl base64; \
	fi