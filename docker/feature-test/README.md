# Feature-test image

A single, self-contained image that boots the Sylius test application with **this plugin
mounted**, so anyone can exercise the withdrawal feature end to end without the full dev
stack. Everything is baked in:

- **FrankenPHP** — serves the shop (and admin) on port `80`.
- **Mailpit** — captures every outgoing email; web UI on port `8025`, SMTP sink on `1025`.
- **SQLite** — file-backed database baked into the image; no external DB service.
- **Fixtures** — the default Sylius sample data (channel, products, admin user…), loaded at
  build time so the container starts ready to use.

## Build

Dependencies are resolved fresh inside the image for the Symfony line passed via
`--build-arg SYMFONY_VERSION` (Flex reads it from `extra.symfony.require`), so the same
Dockerfile produces one image per supported Symfony line on Sylius 2.0 (core itself requires
`^6.4.1 || ^7.1`). The build context is the repository root; `vendor/` and front-end assets
are built in the image.

Tag scheme is `<branch>-sf<major>`:

```bash
IMAGE=registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin

# Symfony 6.4 · Sylius 2.0
docker build -f docker/feature-test/Dockerfile \
  --build-arg SYMFONY_VERSION="6.4.*" -t "$IMAGE:2.0-sf6" .

# Symfony 7.1 · Sylius 2.0
docker build -f docker/feature-test/Dockerfile \
  --build-arg SYMFONY_VERSION="7.1.*" -t "$IMAGE:2.0-sf7" .
```

CI builds and pushes both variants automatically — see `.gitlab-ci.yml` at the repo root (the
`build-feature-test-image` matrix job).

## Push / pull

```bash
docker login registry.gitlab.com
docker push registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin:2.0-sf6
# teammates / CI then:
docker pull registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin:2.0-sf6
```

## Run — manual feature testing

```bash
docker run --rm -p 8080:80 -p 8025:8025 \
  registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin:2.0-sf6
```

- Shop / withdrawal form → <http://localhost:8080/en_US/withdrawal-request>
- Footer link "Withdrawal request" is on every shop page.
- Mailpit (acknowledgement + SAV notification emails) → <http://localhost:8025>
- Admin → <http://localhost:8080/admin> (default fixtures user: `sylius` / `sylius`)

## Run — CI

The image ships the full `vendor/` toolchain, so CI can either:

**Use it as a service** to run external smoke/e2e checks against the provisioned shop:

```bash
docker run -d --rm -p 8080:80 --name shop \
  registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin:2.0-sf6
# … curl/playwright against http://localhost:8080 …
```

**Override the entrypoint** to run the suite inside the container (any argument is exec'd
instead of the server), against the baked SQLite database:

```bash
IMAGE=registry.gitlab.com/emagma/sylius-plugins/order-withdrawal-plugin:2.0-sf6
docker run --rm "$IMAGE" vendor/bin/phpunit
docker run --rm "$IMAGE" vendor/bin/console doctrine:query:sql \
  "SELECT COUNT(*) FROM emagma_order_withdrawal_request"
```

## Notes

- The schema is created from ORM metadata (`doctrine:schema:create`) rather than via
  migrations, because the core + plugin migrations are MySQL-specific.
- `APP_ENV=dev`, `APP_DEBUG=0`. The SQLite file lives at `/app/var/withdrawal.sqlite`.
- This image is for testing only — not a production artifact.
