# Upgrade guide

Breaking changes and the steps they need, newest first.

There are none yet — `2.0.0` is the first public release. This file will carry an entry for every
release that requires more than `composer update`.

For the list of what changed in each release, see [CHANGELOG.md](CHANGELOG.md).

## Routine upgrades

```bash
composer update emagma/sylius-order-withdrawal-plugin
bin/console doctrine:migrations:migrate -n
bin/console cache:clear
```

Check the changelog for new configuration keys or migrations before deploying.

## Moving between Sylius lines

Each Sylius line has its own branch and its own release series — see
[Branching](CONTRIBUTING.md#branching). Upgrading your shop from Sylius 1.14 to 2.x means moving
from this plugin's `1.14` series to its `2.x` series in the same step.

The two lines are the same plugin: same entity, same table, same configuration keys, same
extension points. The differences are in the templates and the admin integration, which follow
whatever the underlying Sylius admin uses. If you overrode any template, expect to redo the
override against the 2.x version.
