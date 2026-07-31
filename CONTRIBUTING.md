# Contributing

Thanks for considering it. This document is meant to be enough on its own — you should not need to
read three other projects' guides to open a good pull request here.

## Before you start

**Bug reports and feature requests** go to [GitHub issues](../../issues), using one of the
templates.

**Support questions do not.** "How do I install this", "it doesn't work with my theme", "can you
help me integrate X" — those go to the [Sylius Slack](https://sylius.com/slack), or to
<support@emagma.fr> if you want paid help. Support issues get closed and redirected. This is
not unfriendliness: the plugin is open-sourced as is, and the maintainers' time for free
one-to-one support is zero.

**For anything larger than a bug fix, open an issue first.** A short "here's what I want to do and
roughly how" saves you from writing a PR that gets turned down on approach.

## Branching

This repository follows the Sylius compatibility-branch model:

| Branch | Targets |
| --- | --- |
| `main` | the newest supported Sylius line |
| `2.0` | Sylius `^2.0` |
| `1.14` | Sylius `^1.14` |

**Target the oldest branch your change applies to.** A bug fix that affects both lines goes to
`1.14`; a feature that only makes sense on Sylius 2 goes to the 2.x branch. Maintainers forward-port
from there — please don't open the same PR against several branches.

If you're unsure, target `main` and say so in the description.

Branch names: `feature/short-description`, `fix/short-description`. Nothing enforced.

## Getting set up

```bash
git clone <your fork>
cd order-withdrawal-plugin
composer install
```

> **Use PHP 8.2 or 8.3.** The dev dependency `phpspec/phpspec ^7.5` does not support PHP 8.4, so
> `composer install` fails there. `composer install --ignore-platform-req=php` works around it.

The fastest way to see your change in a running shop is the demo image:

```bash
docker build -f docker/demo/Dockerfile -t owp-demo .
docker run --rm -p 8080:80 -p 8025:8025 owp-demo
```

Shop on <http://localhost:8080>, admin on `/admin` (`sylius` / `sylius`), emails on
<http://localhost:8025>. Full details in [docker/demo/README.md](docker/demo/README.md).

## The quality gate

Run these before you push. All four must pass:

```bash
vendor/bin/ecs check        # coding standard
vendor/bin/phpstan analyse  # static analysis, level max
vendor/bin/phpunit          # unit tests
vendor/bin/behat --suite=…  # acceptance tests
```

`vendor/bin/ecs check --fix` fixes most style issues automatically, including the license header
every PHP file must carry.

Behat needs a MySQL database, a `behat.yml` of your own, and — for the `@javascript` scenarios — a
headless Chrome. [docs/dev/testing.md](docs/dev/testing.md) covers the setup, the four suite names,
and a known incompatibility with Sylius ≥ 2.2.

If you genuinely cannot run Behat locally, say so in the PR rather than skipping it silently.

## What a good pull request looks like

- **One concern per PR.** A bug fix plus a refactor plus a rename is three reviews in a trench
  coat.
- **Tests for the behaviour you changed.** A unit test for a service, a Behat scenario for anything
  a user can see. `tests/Unit/Notifier/` and `features/` have shapes to copy.
- **Documentation updated** if you changed something a user can observe — the `docs/` page that
  covers it, and `README.md` if it changes what the plugin does.
- **A `CHANGELOG.md` entry** under `## [Unreleased]`.
- **Translation keys, not literals.** No user-visible English in a template or a PHP file. Add the
  key to `translations/messages.en.yaml` and `messages.fr.yaml` — if you can't write the French,
  copy the English and say so; a maintainer will fix it.
- **A description that explains *why*.** The diff already shows what.

## Code style

Sylius Labs coding standard, enforced by ECS, plus:

- `declare(strict_types=1);` in every PHP file.
- The license header on every PHP file — `ecs check --fix` adds it.
- Services are `final readonly` unless there's a reason not to be.
- Type everything. PHPStan runs at level `max` on `src/`.
- Translation keys are namespaced under `emagma_sylius_order_withdrawal.`.

## Translations

Adding a locale is the easiest useful contribution, and it needs no PHP. Three files under
`translations/`, one per domain. See
[docs/dev/translating.md](docs/dev/translating.md#contributing-a-locale).

## Reporting a security issue

Do **not** open a public issue. See [SECURITY.md](SECURITY.md).

## Review and merge

Maintainers review when they can — this is not anyone's full-time job, so a few days of silence is
normal rather than a snub. Expect questions about approach before questions about style.

Commits are squashed on merge, so don't worry about tidying your history.

## Licensing

By contributing, you agree that your contribution is licensed under the
[MIT license](LICENSE), like the rest of the project.
