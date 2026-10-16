# Upgrade guide

Breaking changes and the steps they need, newest first.

For the list of what changed in each release, see [CHANGELOG.md](CHANGELOG.md).

## 2.1 → 2.2

**No action required unless you redefined or altered `WithdrawalRequestListener`** (see
[below](#if-you-redefined-withdrawalrequestlistener)). Nothing else changes until you enable the API,
which is off by default.

### Enabling the API (optional)

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
emagma_sylius_order_withdrawal:
    rate_limiter_service: 'limiter.withdrawal_request'   # required by api.shop
    api:
        admin: true
        shop: true
```

The two switches are independent and compile-time (`true`, `false` or a `%parameter%`; not
`%env()%`). The shop one opens a public endpoint and needs a rate limiter. Full details in the
[developer guide](docs/developer-guide.md#api).

### If you redefined `WithdrawalRequestListener`

An application that redefined the `WithdrawalRequestListener` service with the 2.1 arguments keeps
working, but now triggers a deprecation. Pass the two new services:
`WithdrawalRequestSubmissionHandlerInterface` and `PendingConfirmationQueueInterface`. They are
required in 3.0. Without them, requests confirmed through the API get no confirmation email.

Three related points:

- When the plugin wires the listener itself, its first eight arguments are no longer used for side
  effects. To customise them, decorate `WithdrawalRequestSubmissionHandlerInterface` (or the emailer
  or the notifier) rather than the listener's arguments. A compiler pass that replaces listener
  arguments no longer has an effect.
- A listener redefined without autoconfigure loses its `kernel.reset` tag. In worker mode, tag it
  `kernel.reset` with `method: reset`.
- The plugin now prepends
  `sylius_state_machine_abstraction.graphs_to_adapters_mapping.emagma_sylius_order_withdrawal_request: symfony_workflow`.
  A mapping of your own for that graph still wins.

## Moving between Sylius lines

The Sylius 1 and Sylius 2 lines have separate branches and separate release series: `1.0.x-dev`
for Sylius `^1.14.0`, `main` for the whole Sylius 2 line — see
[Branching](CONTRIBUTING.md#branching). Upgrading your shop from Sylius 1.14 to 2.x means moving
from this plugin's `1.x` series to its `2.x` series in the same step.

The two lines are originally the same plugin, but only they may differ with time since effort and features will target latests Sylius versions.
