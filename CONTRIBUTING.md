# Contributing

Thanks for considering it. This project takes contributions as **issues, not pull requests**: you
describe the problem and the change you propose, a maintainer validates the approach, then the
change is implemented by a maintainer, or by an agent working under their review. The same rule
applies to humans and to AI agents.

Pull requests are open to repository collaborators only. An issue with a clear proposal is the part
that matters most, and it is all we ask of you.

## Before you start

**Bug reports and feature requests** go to [GitHub issues](../../issues), using one of the
templates.

**Support questions are better placed elsewhere.** "How do I install this", "it doesn't work with
my theme", "can you help me integrate X" — the [Sylius Slack](https://sylius.com/slack) will
usually get you an answer faster, because the whole community is there rather than a couple of
maintainers. For hands-on help on your own project, <support@emagma.fr> covers pro support.

## Writing a good proposal

Whether you write it yourself or with an agent, an issue is ready to work on when it answers these:

- **The problem.** What you are trying to do and what blocks you today. For a bug: the plugin,
  Sylius and PHP versions, the steps to reproduce, what you expected and what happened instead.
- **The proposal.** The behaviour you want, described as something a user or an integrator can
  observe. A sketch of the approach (which service, event, template or configuration key is
  involved) is welcome, not required.
- **The scope.** What is in, and what is explicitly out. One concern per issue: a bug fix plus a
  refactor plus a rename is three issues.
- **How we will know it works.** Acceptance criteria, ideally concrete scenarios ("given…, when…,
  then…") that can become a Behat scenario or a unit test.
- **The line it concerns.** Sylius 2 (`main`), Sylius 1.14 (`1.0.x-dev`), or both.
- **What you already tried.** The extension points you looked at (an event listener, a notifier, a
  template override, a redefined workflow) and why they did not cover it.

### If an agent helped you write it

Agent-assisted issues are welcome, on the same terms as any other:

- **You own it.** Read it before posting, and post only what you would defend yourself. A
  maintainer will answer you, not the agent.
- **Ground it in this repository.** Name real files, services, configuration keys and versions.
  Proposals built on APIs that do not exist here get closed.
- **Keep it short.** State the problem and the proposal; leave out generic explanations of Sylius
  or Symfony.
- **Describe the change rather than pasting a patch.** A diff without the reasoning behind it is
  hard to validate. Snippets that illustrate a point are fine; the implementation follows once the
  approach is agreed.
- **Say so.** One line noting that the issue was drafted with an agent is enough.

## Branching

The repository has two long-lived branches:

| Branch           | Targets                                 |
|------------------|-----------------------------------------|
| `main`           | the whole Sylius 2 line — Sylius `~2.0.0 \|\| ~2.1.0 \|\| ~2.2.0` |
| `1.0.x-dev`      | Sylius `^1.14.0` (Sylius LTS), maintenance only |

You do not need to pick a branch: say in the issue which line it concerns.

For collaborators, **every pull request targets `main`**, whichever Sylius 2 minor the problem was
found on: `main` supports all of them from a single code base. `1.0.x-dev` is an isolated
maintenance branch, not a PR target. It shares no history with `main`, and it receives only
`git cherry-pick -x` ports of `src/`-only commits, done by a maintainer. Branch names:
`feature/short-description`, `fix/short-description`.

## Implementing a change

The rest of this guide is for whoever implements an agreed issue: maintainers, collaborators, and
the agent working under their review. It is also what to check a proposal against.

## Getting set up

```bash
git clone https://github.com/emagma/sylius-order-withdrawal-plugin.git
cd sylius-order-withdrawal-plugin
composer install
```

### PHP version

The floor is **PHP 8.1** — `composer.json` declares `php: ^8.1`, matching Sylius 1.14. CI runs the
coding standard, static analysis, the acceptance suites and the migrations on 8.1 (`PHP_MINIMUM` in
the workflows), and the unit matrix covers 8.1, 8.2 and 8.3.

**Whatever PHP you have locally will not catch a floor violation.** Anything 8.2 or newer parses
happily on your machine and breaks only for a consumer on 8.1. `readonly class` is the one that
bites — it is 8.2+, so it looks correct on all but the oldest supported PHP. That is why
`phpstan.neon` declares the range explicitly instead of following the PHP it runs under:

```neon
phpVersion:
    min: 80100
    max: 80399
```

So `vendor/bin/phpstan analyse` reports syntax too new for 8.1 *and* deprecations at 8.3, on any PHP
you happen to have installed. PHPStan would otherwise infer only `config.platform.php` from
`composer.json`, which this package does not set. If the supported range moves, keep it in step with
the matrix in `.github/workflows/ci.yml`.

One consequence worth knowing if you build it: the demo image below **cannot** be built on 8.1 —
FrankenPHP publishes no `php8.1` image, 8.2 is its minimum.

The fastest way to see your change in a running shop is the demo image:

```bash
docker build -f docker/demo/Dockerfile -t emagma/owp-demo .
docker run --rm -p 8080:80 -p 8025:8025 emagma/owp-demo
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
headless Chrome. The [developer guide](docs/developer-guide.md#testing) covers the setup and the
four suite names.

If you genuinely cannot run Behat locally, say so in the PR rather than skipping it silently.

**If you touched the entity mapping**, the schema change ships as *two* migrations — one for
MySQL/MariaDB and one for PostgreSQL. CI applies both on all three platforms and fails if either
falls out of step with the mapping, so a PR that updates only one half will go red. See
[Database migrations](docs/developer-guide.md#database-migrations).

## What a good pull request looks like

- **A link to the issue it implements** (`Closes #123`). No issue, no pull request: the approach
  is agreed there first.
- **One concern per PR.** A bug fix plus a refactor plus a rename is three reviews in a trench
  coat.
- **Tests for the behaviour you changed.** A unit test for a service, a Behat scenario for anything
  a user can see. `tests/Unit/Notifier/` and `features/` have shapes to copy.
- **Documentation updated** if you changed something a user can observe — the
  [user guide](docs/user-guide.md) or the [developer guide](docs/developer-guide.md), and
  `README.md` if it changes what the plugin does.
- **A `CHANGELOG.md` entry** under `## [Unreleased]`.
- **Translation keys, not literals.** No user-visible English in a template or a PHP file. Add the
  key to `translations/messages.en.yaml` and `messages.fr.yaml` — if you can't write the French,
  copy the English and say so; a maintainer will fix it.
- **A description that explains *why*.** The diff already shows what.

## Code style

Sylius Labs coding standard, enforced by ECS, plus:

- `declare(strict_types=1);` in every PHP file.
- The license header on every PHP file — `ecs check --fix` adds it.
- Services are `final`, with `readonly` on the promoted constructor properties — **not**
  `final readonly class`, which is PHP 8.2+ and does not parse on this line's 8.1 floor.
- Type everything. PHPStan runs at level `max` on `src/`.
- Translation keys are namespaced under `emagma_sylius_order_withdrawal.`.

## Translations

Adding a locale is the easiest useful contribution, and it needs no PHP: three files under
`translations/`, one per domain (see the [developer guide](docs/developer-guide.md#translating)).
Open an issue naming the locale and attach the three translated files; a maintainer adds them.

## Reporting a security issue

Do **not** open a public issue. See [SECURITY.md](SECURITY.md).

## Review and merge

Maintainers triage issues when they can. This is not anyone's full-time job, so a few days of
silence is normal rather than a snub. Expect questions about the approach in the issue before any
code is written, and questions about style only once there is a pull request.

Pull requests are squashed or rebased on merge, so don't worry about tidying the history.

## Licensing

By contributing, you agree that your contribution is licensed under the
[MIT license](LICENSE), like the rest of the project.
