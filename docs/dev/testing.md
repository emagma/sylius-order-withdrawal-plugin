# Testing

The plugin uses the shared [`sylius/test-application`](https://github.com/Sylius/TestApplication)
as its test environment. There is no test application bundled in this repository.

## Setting up

```bash
composer install
```

Any PHP version matching the declared `php: ^8.2` works — 8.2, 8.3 and 8.4 are all supported
and covered by CI.

## The quality gate

Four commands. All four must pass before a pull request is merged.

```bash
vendor/bin/ecs check        # coding standard (Sylius Labs + an enforced file header)
vendor/bin/phpstan analyse  # static analysis, level max on src/
vendor/bin/phpunit          # unit tests
vendor/bin/behat            # acceptance tests — see the requirements below
```

`vendor/bin/ecs check --fix` fixes most style findings, including the license header on every PHP
file.

`vendor/bin/rector process --dry-run` is available too, but is not part of the gate — it is a
migration aid, not a check.

## Unit tests

Fast, no database, no browser. This is where most of your contribution's coverage should live.

```bash
vendor/bin/phpunit                                # everything
vendor/bin/phpunit --filter WithdrawalRequestTest # one class
```

They cover the entity, both enums, the configuration tree, the recipient resolver, the emailer and
both notifiers — the pure-logic core. Add a test here for any service you touch.

## Acceptance tests

Four Behat suites drive the real UI, one per feature file:

| Suite | Covers |
| --- | --- |
| `emagma_order_withdrawal_requesting` | submitting the form: valid cases, every validation rule, both emails |
| `emagma_order_withdrawal_accessing` | reaching the form from the shop footer |
| `emagma_order_withdrawal_browsing` | the admin grid: listing, filtering, opening a request |
| `emagma_order_withdrawal_processing` | the admin transitions and the confirmation email |

```bash
vendor/bin/behat --suite=emagma_order_withdrawal_requesting
vendor/bin/behat --suite=emagma_order_withdrawal_processing
```

Running bare `vendor/bin/behat` also picks up Sylius's own core suites, which are imported by
`behat.yml.dist` — usually not what you want. Pass `--suite=`.

### What they need

- **A MySQL/MariaDB database** reachable at `DATABASE_URL` (see `tests/TestApplication/.env`).
- **A headless Chrome** on `http://127.0.0.1:9222` for the `@javascript` scenarios — the three
  admin transition scenarios in `emagma_order_withdrawal_processing`. Everything else runs on the
  `symfony` session and needs no browser.
- **Your own `behat.yml`.** `behat.yml.dist` is a template pointing at an internal development
  host. Copy it and set `Behat\MinkExtension.base_url` to whatever serves your test environment:

  ```bash
  cp behat.yml.dist behat.yml
  ```

  `behat.yml` is gitignored, so your local URL never lands in a commit.

### Known failures

Four scenarios in the `emagma_order_withdrawal_requesting` suite still fail — the shop-side
confirmation assertion and the after-sales notification email. The other non-JavaScript suites
pass. Please don't file a duplicate issue for these.

<!-- TODO(maintainers): fix the four requesting scenarios, then move behat into ci.yml. -->

## Running everything in Docker

The demo image ships the full `vendor/` toolchain, so you can run the suites inside it against its
baked SQLite database without installing anything locally:

```bash
docker build -f docker/demo/Dockerfile -t owp-demo .
docker run --rm owp-demo vendor/bin/phpunit
```

See [docker/demo/README.md](../../docker/demo/README.md).

## Testing your own extension

- **A notifier or a listener** → a unit test. Both shipped notifiers have one you can copy from
  (`tests/Unit/Notifier/`).
- **A new transition** → a scenario in `features/admin/processing_withdrawal_request.feature`, and
  the matching step in `ManagingWithdrawalRequestsContext`.
- **A new validation rule** → a scenario in `features/shop/requesting_order_withdrawal.feature`.
  The existing rejection scenarios all follow one shape; match it.

Write the feature file in the language of the customer's or administrator's intent, not the
implementation — that is the house style in `features/`, and it is what makes those files readable
as a specification of what the plugin promises.

## Next

- [Contributing](../../CONTRIBUTING.md)
- [Architecture](architecture.md) — what to cover, and where it runs
