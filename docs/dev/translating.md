# Translating

The plugin ships **English** (`en`) and **French** (`fr`). Everything visible — form labels, the
legal notice, validation errors, flash messages, admin labels and the three emails — is a
translation key. No wording is hard-coded.

## The three domains

| File | Contains |
| --- | --- |
| `translations/messages.<locale>.yaml` | form labels, shop copy, admin labels, state and transition names, email bodies |
| `translations/validators.<locale>.yaml` | the plugin's own validation messages |
| `translations/flashes.<locale>.yaml` | the two flash messages |

All keys live under a single root, `emagma_sylius_order_withdrawal`.

## Changing a single label

Do **not** edit the plugin's files in `vendor/`. Declare the same key in your application's
`translations/` — Symfony merges by key, and the application wins.

```yaml
# translations/messages.en.yaml
emagma_sylius_order_withdrawal:
    form:
        legal_notice: 'I confirm that I am withdrawing from this purchase under our returns policy.'
    shop:
        intro: 'Changed your mind? Tell us here and we will take it from there.'
```

Only the keys you list are overridden; everything else falls back to the plugin's file.

The keys most shops want to reword:

| Key | |
| --- | --- |
| `form.legal_notice` | the withdrawal statement the customer accepts — **review this one** |
| `shop.intro` | the paragraph above the form, which mentions the 14-day window |
| `shop.title` · `shop.cta` · `shop.footer_link` | page title, submit button, footer link |
| `email.*.subject` | the three subject lines |

## Adding a locale

Copy the three English files into your application's `translations/`, rename them, translate the
values, and clear the cache:

```bash
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/messages.en.yaml    translations/messages.de.yaml
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/validators.en.yaml  translations/validators.de.yaml
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/flashes.en.yaml     translations/flashes.de.yaml
bin/console cache:clear
```

Make sure the locale is enabled in Sylius (**Configuration → Locales**) and assigned to the
channel.

### Placeholders

Three keys carry placeholders. Keep them exactly as they are:

| Key | Placeholder |
| --- | --- |
| `email.greeting` | `%name%` |
| `email.notification.subject` | `%order%` |
| `email.confirmation.body` | `%order%` |

## Which locale an email uses

The one **the request was submitted in**, recorded on the request as `localeCode`, not the locale
of whoever triggered the send. An administrator confirming a German customer's request sends a
German email.

If a key is missing in that locale, Symfony falls back to your configured fallback locale.

## Checking your work

```bash
bin/console debug:translation en EmagmaSyliusOrderWithdrawalPlugin
bin/console debug:translation de EmagmaSyliusOrderWithdrawalPlugin
```

Missing and unused keys are listed per domain.

## Contributing a locale

New locales are very welcome, and they are the easiest possible first contribution. Add the three
files under `translations/` in the plugin itself and open a pull request — see
[CONTRIBUTING.md](../../CONTRIBUTING.md).

Please translate **all three** domains, keep the key order identical to the English files so diffs
stay readable, and say in the PR description whether you are a native speaker.

<!-- TODO(maintainers): list the locales as they land — currently en, fr. -->
