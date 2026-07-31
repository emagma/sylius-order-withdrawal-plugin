# Emails

The plugin sends three emails. All three go through Sylius's own mailer
(`sylius.email_sender`), so they inherit your sender address, your layout and your mail transport.

| Email | Sent when | To |
| --- | --- | --- |
| **Acknowledgement** | the customer submits the form | the address the customer typed |
| **Notification** | the customer submits the form | the shop (see [routing](#where-the-notification-goes)) |
| **Confirmation** | an administrator confirms the request | the address the customer typed |

Rejecting a request sends nothing — see [Admin guide → rejecting](admin-guide.md#rejecting-a-request).

## What each one contains

**Acknowledgement** — the durable-medium record. Greets the customer by name, recaps the order
number, order date, scope and any detail, states the **date and time** of the request, and says a
confirmation will follow once the request has been processed.

**Notification** — the operational one. Everything the acknowledgement has, plus the customer's
email address and the reception status. The subject carries the order number so it threads and
searches well in a shared inbox.

**Confirmation** — deliberately short. Greets the customer and states that the withdrawal for the
given order has been processed.

All three are rendered in the **locale the request was submitted in**, not the locale of whoever
triggered the send. An administrator confirming a French customer's request sends a French email.

## Where the notification goes

Two settings decide, in this order:

1. If `prefer_channel_contact_email` is `true` (the default) **and** the request's channel has a
   contact email → that address.
2. Otherwise → `notification_email`.
3. If `notification_email` is empty → **no notification is sent at all**, silently.

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
emagma_sylius_order_withdrawal:
    notification_email: 'returns@example.com'
    prefer_channel_contact_email: true
```

The default for `notification_email` is `notifications@example.com` — a placeholder that will
bounce. Set it, or set a contact email on every channel.

Multi-channel shops usually want the default (`true`): each shop's requests reach its own team,
with `notification_email` as the safety net.

## Changing the templates

Override them the standard Symfony way, by shadowing the bundle's templates in your app:

```
templates/bundles/EmagmaSyliusOrderWithdrawalPlugin/email/
├── acknowledgement.html.twig
├── notification.html.twig
└── confirmation.html.twig
```

Each template extends `@SyliusShop/email/layout.html.twig` and defines two blocks, `subject` and
`content`. Available variables:

| Variable | |
| --- | --- |
| `withdrawalRequest` | the `WithdrawalRequest` entity |
| `channel` | the channel it was submitted through (may be `null`) |
| `localeCode` | the locale to render in — pass it to every `trans` call |

If you only want to reword something, don't override the template: change the translation key
instead. See [translating.md](../dev/translating.md).

## Changing the recipients

For anything more elaborate than one address — a distribution list, a per-country routing table,
a lookup against your CRM — implement `RecipientResolverInterface`:

```php
namespace App\Withdrawal;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Mailer\RecipientResolverInterface;

final class TeamRecipientResolver implements RecipientResolverInterface
{
    /** @return string[] */
    public function getRecipients(WithdrawalRequest $request): array
    {
        return $request->getScope()->value === 'partial'
            ? ['returns@example.com', 'warehouse@example.com']
            : ['returns@example.com'];
    }
}
```

```yaml
# config/services.yaml
services:
    Emagma\SyliusOrderWithdrawalPlugin\Mailer\RecipientResolverInterface:
        alias: App\Withdrawal\TeamRecipientResolver
```

Returning an empty array suppresses the notification.

## Sending something else entirely

Email is just one notifier. To also post to Slack, open a ticket, or push to a webhook, implement
`WithdrawalRequestNotifierInterface` — it is autoconfigured and chained alongside the email one.

See [Extending → notifiers](../dev/extending.md#adding-a-notifier).

## Next

- [Configuration reference](configuration.md)
- [Extending](../dev/extending.md)
- [Translating](../dev/translating.md)
