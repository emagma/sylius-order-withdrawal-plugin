# Getting started

Install the plugin, confirm it works, and submit your first withdrawal request.

Takes about five minutes on an existing Sylius 2.x shop.

## 1. Install the package

```bash
composer require emagma/sylius-order-withdrawal-plugin
```

The plugin has no npm dependencies and ships no compiled assets, so there is nothing to build.

## 2. Register the bundle

```php
// config/bundles.php
return [
    // ...
    Emagma\SyliusOrderWithdrawalPlugin\EmagmaSyliusOrderWithdrawalPlugin::class => ['all' => true],
];
```

## 3. Import the configuration

The plugin does not ship a Flex recipe, so this import is manual. It pulls in the resource
definition, the grid, the mailer templates, the state machine and the Twig hooks in one go.

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
imports:
    - { resource: '@EmagmaSyliusOrderWithdrawalPlugin/config/config.yaml' }
```

## 4. Import the routes

```yaml
# config/routes/emagma_sylius_order_withdrawal.yaml
emagma_sylius_order_withdrawal:
    resource: '@EmagmaSyliusOrderWithdrawalPlugin/config/routes.yaml'
```

That single resource mounts both sides:

| Side | Prefix | What it adds |
| --- | --- | --- |
| Shop | `/{_locale}` | `GET|POST /withdrawal-request` |
| Admin | `/%sylius_admin.path_name%` | the grid, the detail page, and the three transition endpoints |

## 5. Create the table

```bash
bin/console doctrine:migrations:migrate -n
bin/console cache:clear
```

This creates `emagma_order_withdrawal_request`, with a nullable foreign key to `sylius_channel`
(`ON DELETE SET NULL` — deleting a channel keeps the requests).

> The shipped migration is **MySQL-specific**. On another platform, generate your own from the ORM
> metadata instead.

## 6. Verify

Three checks, in order of how much they prove:

```bash
# The routes are mounted
bin/console debug:router | grep emagma_sylius_order_withdrawal

# The table exists
bin/console doctrine:query:sql "SELECT COUNT(*) FROM emagma_order_withdrawal_request"
```

Then, in a browser:

1. Open any storefront page — the footer shows a **Withdrawal request** link.
2. Follow it, or go straight to `/{locale}/withdrawal-request`.
3. Log into the admin panel — **Withdrawal requests** appears under *Customers*.

## 7. Submit your first request

Fill the form with any order number (it is free text — nothing is checked against your real
orders), tick the legal notice, submit.

You should then see:

- a flash message confirming the request was sent;
- an **acknowledgement** email in the customer's inbox;
- a **notification** email at the shop address (see below);
- a new row in the admin grid, in state **New**.

If you have no mail catcher configured, set `MAILER_DSN` to one — Mailpit or Maildev both work.

## Where the notification email goes

By default it goes to the **contact email of the channel** the request came from, falling back to
the plugin's `notification_email` setting when the channel has none.

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
emagma_sylius_order_withdrawal:
    notification_email: 'returns@example.com'
    prefer_channel_contact_email: true
```

Both options are documented in [configuration.md](configuration.md), and the routing logic — plus
how to replace it entirely — in [emails.md](emails.md).

## Troubleshooting

**The footer link doesn't appear.** It is injected via the Twig hook
`sylius_shop.base.footer.content` at priority 150. If your theme overrides the shop footer without
rendering that hook, add the link yourself:

```twig
<a href="{{ path('emagma_sylius_order_withdrawal_shop_request') }}">
    {{ 'emagma_sylius_order_withdrawal.shop.footer_link'|trans }}
</a>
```

**The admin menu entry doesn't appear.** `AdminMenuListener` adds it under the `customers` child,
creating that child if your menu has none. A menu built from scratch by another listener can
displace it — check the order of your `sylius.menu.admin.main` listeners.

**"No route found for GET /withdrawal-request".** The shop route is prefixed with `/{_locale}`, so
it lives at `/en_US/withdrawal-request`, not at the bare path.

**Labels show as raw keys** (`emagma_sylius_order_withdrawal.shop.title`). Clear the cache; if it
persists, your locale has no translation file — see [translating.md](../dev/translating.md).

## Next

- [The customer journey](customer-journey.md) — what the form asks and what it rejects
- [Admin guide](admin-guide.md) — triaging the requests that arrive
- [Extending](../dev/extending.md) — plugging your own logic into a submission
