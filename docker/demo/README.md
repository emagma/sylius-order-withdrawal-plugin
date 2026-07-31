# Demo image

A single, self-contained image that boots a Sylius shop with **this plugin mounted**, so you can
try the withdrawal feature end to end without installing PHP, Node or a database. Everything is
baked in:

- **FrankenPHP** — serves the shop and the admin panel on port `80`.
- **Mailpit** — captures every outgoing email; web UI on port `8025`, SMTP sink on `1025`.
- **SQLite** — file-backed database baked into the image; no external DB service.
- **Fixtures** — the default Sylius sample data (channel, products, admin user…), loaded at build
  time, so the container starts ready to use.

It is meant for **evaluating the plugin and developing against it** — not a production artifact,
and not something you run from inside your own project.

## Build

The build context is the repository root, so you need a clone:

```bash
git clone https://github.com/emagma/sylius-order-withdrawal-plugin.git
cd sylius-order-withdrawal-plugin
docker build -f docker/demo/Dockerfile --build-arg SYMFONY_VERSION="7.4.*" -t owp-demo .
```

Expect several minutes on a cold build, and roughly **1.3 GB** — dependencies are resolved,
front-end assets are built and fixtures are loaded inside the image.

### Choosing a Symfony line

Dependencies are resolved fresh for whatever `SYMFONY_VERSION` you pass (Flex reads it from
`extra.symfony.require`), so the same Dockerfile covers both Symfony lines Sylius 2.x supports —
core requires `^6.4 || ^7.4`, so Symfony 8 is not an option yet:

```bash
docker build -f docker/demo/Dockerfile --build-arg SYMFONY_VERSION="7.4.*" -t owp-demo:sf74 .
docker build -f docker/demo/Dockerfile --build-arg SYMFONY_VERSION="6.4.*" -t owp-demo:sf64 .
```

Other build args you can override: `PHP_VERSION` (default `8.3` — Sylius's dev dependencies do not
support 8.4 yet), `FRANKENPHP_VERSION`, `NODEJS_VERSION`, `MAILPIT_VERSION`.

## Run

```bash
docker run --rm -p 8080:80 -p 8025:8025 owp-demo
```

| | |
| --- | --- |
| Withdrawal form | <http://localhost:8080/en_US/withdrawal-request> |
| Shop | <http://localhost:8080> — the footer carries a *Withdrawal request* link on every page |
| Admin | <http://localhost:8080/admin> — `sylius` / `sylius` |
| Emails | <http://localhost:8025> |

### The five-minute tour

1. Open the withdrawal form and submit it with any order number — it is free text, and nothing is
   checked against a real order.
2. Check Mailpit: two emails arrive — an **acknowledgement** to the address you typed, and a
   **notification** to the channel's contact email.
3. Log into the admin panel; the request is listed under **Customers → Withdrawal requests** in
   state *New*.
4. Open it and press **Confirm withdrawal** — the state changes, and a third email, the
   **confirmation**, lands in Mailpit.

## Run the test suites inside the image

The image ships the full `vendor/` toolchain, and the entrypoint `exec`s any argument you pass
instead of starting the server. So the suites can run against the already-provisioned database
without installing anything locally:

```bash
docker run --rm owp-demo vendor/bin/phpunit
docker run --rm owp-demo vendor/bin/console doctrine:query:sql \
  "SELECT COUNT(*) FROM emagma_order_withdrawal_request"
```

Useful in CI too — start it as a service and drive the provisioned shop from outside:

```bash
docker run -d --rm -p 8080:80 --name shop owp-demo
# … curl / Playwright against http://localhost:8080 …
```

## Notes

- The schema is created from ORM metadata (`doctrine:schema:create`) rather than through
  migrations, because the core and plugin migrations are MySQL-specific while this image uses
  SQLite. The image therefore does **not** exercise the shipped migration — test that against
  MySQL.
- `APP_ENV=dev`, `APP_DEBUG=0`. The SQLite file lives at `/app/var/withdrawal.sqlite`.
- No TLS: the shop is served over plain HTTP on a single port. See `Caddyfile` for why.
- The image is not published to any registry — build it locally from this repository.
