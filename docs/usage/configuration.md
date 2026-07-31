# Configuration reference

The plugin has two configuration options. Everything else is customised through Symfony's normal
extension points — services, templates, translations — not through configuration.

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
emagma_sylius_order_withdrawal:
    notification_email: 'notifications@example.com'
    prefer_channel_contact_email: true
```

## `notification_email`

| | |
| --- | --- |
| Type | string |
| Default | `notifications@example.com` |
| Validated | must be a valid email address; cannot be empty |

The fallback address for the shop-side notification. Used whenever
`prefer_channel_contact_email` is `false`, or when the request's channel has no contact email.

The default is a placeholder that will bounce — **set it**.

## `prefer_channel_contact_email`

| | |
| --- | --- |
| Type | boolean |
| Default | `true` |

When `true`, notifications go to the contact email of the channel the request came from, falling
back to `notification_email`. When `false`, they always go to `notification_email`.

Leave it on for multi-channel shops so each shop's requests reach its own team. Turn it off when
one team handles every channel.

Full routing logic in [emails.md](emails.md#where-the-notification-goes).

## Container parameters

The extension exposes both values as parameters, if you need to reference them from your own
services:

- `emagma_sylius_order_withdrawal.notification_email`
- `emagma_sylius_order_withdrawal.prefer_channel_contact_email`
- `emagma_sylius_order_withdrawal.model.withdrawal_request.class` — the entity class, set by
  SyliusResourceBundle and the one to use if you extend the entity

## Things that look like configuration but aren't

| You want to… | Do this instead |
| --- | --- |
| Change the form URL | Import `config/routes/shop.yaml` yourself with your own path, instead of the bundled `config/routes.yaml` |
| Change any wording | Override the translation key — [translating.md](../dev/translating.md) |
| Change the email layout | Override the template — [emails.md](emails.md#changing-the-templates) |
| Add a state or a transition | Override the workflow — [extending.md](../dev/extending.md#the-state-machine) |
| Send notifications elsewhere | Implement a notifier — [extending.md](../dev/extending.md#adding-a-notifier) |
| Move the footer link | Reconfigure the Twig hook — [extending.md](../dev/extending.md#moving-or-removing-the-footer-link) |

## Verifying what's active

```bash
bin/console debug:config emagma_sylius_order_withdrawal
```
