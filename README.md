<h1 align="center">Emagma SyliusOrderWithdrawal Plugin</h1>

<p align="center">Order withdrawal management for Sylius</p>

## Installation

```bash
composer require emagma/sylius-order-withdrawal-plugin
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Emagma\SyliusOrderWithdrawalPlugin\EmagmaSyliusOrderWithdrawalPlugin::class => ['all' => true],
];
```

## Front-end assets

This plugin ships its front-end code under `assets/{admin,shop}/entrypoint.js`. Composer
installs the PHP code but **does not build JavaScript** — your application's own Webpack
Encore build must compile the plugin's entrypoints, and any npm packages they import must be
installed in **your** app (Composer manages PHP, not npm — there is no automatic propagation).

In your Sylius application:

1. **Install the npm dependencies this plugin imports** (none required by default — add any
   your customisations introduce), e.g.:

   ```bash
   yarn add swiper
   ```

2. **Register the plugin's entrypoints in your `webpack.config.js`**, pointing at the
   installed package under `vendor/`:

   ```js
   Encore
       // ... your existing admin config
       .addEntry('emagma-sylius-order-withdrawal-admin', './vendor/emagma/sylius-order-withdrawal-plugin/assets/admin/entrypoint.js');

   Encore
       // ... your existing shop config
       .addEntry('emagma-sylius-order-withdrawal-shop', './vendor/emagma/sylius-order-withdrawal-plugin/assets/shop/entrypoint.js');
   ```

3. **Include the built entries** in your admin/shop layout (or via a Twig hook on Sylius 2.x):

   ```twig
   {{ encore_entry_script_tags('emagma-sylius-order-withdrawal-admin') }}
   ```

4. Rebuild: `yarn install && yarn encore dev` (or `production`).

> For the plugin's **own** development/CI build, dependencies are declared in
> `tests/TestApplication/package.json` instead (the bundled `sylius/test-application` merges
> and builds them automatically) — see the `dev` skill. That file is for this repo's build
> only; it does **not** reach consumers, so keep this section in sync with it.

## Development

This plugin uses the shared [`sylius/test-application`](https://github.com/Sylius/TestApplication)
as its test environment — there is no bundled test application.

```bash
composer install
vendor/bin/ecs check          # coding standard
vendor/bin/phpstan analyse    # static analysis
vendor/bin/phpunit            # unit tests
vendor/bin/phpspec run        # specs
vendor/bin/behat              # acceptance tests (all suites — Sylius core suites need a DB/browser)
```

A ready-to-run example acceptance scenario ships in `features/plugin_test_harness.feature`
(context: `tests/Behat/Context/SmokeContext.php`). It needs no database or browser — run
just that suite to confirm the Behat harness works and to see the pattern:

```bash
vendor/bin/behat --suite=sylius_order_withdrawal_smoke
```

## Development with Docker (local)

The dev Docker stack (FrankenPHP + yarn + Mailpit + MariaDB) is **not committed** —
regenerate it in this directory with `emagma:scaffold-sylius-plugin` (it detects the
existing plugin and adds only `.docker/`), then:

```bash
docker compose -f .docker/compose.yaml up -d --build
```

- App: `https://plugin.sylius.wip` · Test env: `https://test.plugin.sylius.wip` · Mailpit: `https://mail.plugin.sylius.wip`
- Local `.wip` domains are mapped by the `docker-etc-hosts` sidecar (no published ports);
  it pulls from `registry.gitlab.com`, so `docker login registry.gitlab.com` first.

## Branching

This repository follows the official Sylius compatibility-branch model:

- `main` — newest supported Sylius line.
- `X.Y` branches (e.g. `1.14`, `2.0`) — one per supported Sylius line, differing
  only in the `sylius/sylius` and `sylius/test-application` constraints.

## License

This plugin is released under the MIT license.
