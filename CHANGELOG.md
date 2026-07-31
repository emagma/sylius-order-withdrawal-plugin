# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Each Sylius line has its own branch — see [Branching](CONTRIBUTING.md#branching).

## [Unreleased]

<!-- Add your entry here, under Added / Changed / Deprecated / Removed / Fixed / Security. -->

## [2.0.0] — unreleased

<!-- TODO(release): set the date on tagging. -->

First public release, targeting Sylius `^2.0`.

### Added

- Public withdrawal request form at `/{_locale}/withdrawal-request`, with no account required.
- Storefront footer link, injected through the `sylius_shop.base.footer.content` Twig hook.
- Full and partial withdrawal scopes; a partial withdrawal requires a written detail.
- Validation: mandatory identity, order and contact fields; a valid email address; order and
  reception dates that cannot be in the future; explicit acceptance of the legal notice.
- Acknowledgement email to the customer on submission, timestamped, on a durable medium.
- Notification email to the shop, routed to the channel contact email by default with a
  configurable fallback.
- Confirmation email to the customer when an administrator confirms the request.
- Admin grid under *Customers → Withdrawal requests*, with filters on last name, order number,
  status, channel and submission date.
- Admin detail page with the available state-machine transitions.
- State machine `emagma_sylius_order_withdrawal_request`: `new → in_progress → confirmed | rejected`.
- Channel and locale recorded on every request; emails render in the submission locale.
- Extension points: the `WithdrawalRequestSubmitted` event, `WithdrawalRequestNotifierInterface`,
  `RecipientResolverInterface`, `WithdrawalRequestEmailerInterface`, and a `sector_conditions` Twig
  block on the storefront form.
- Configuration: `notification_email`, `prefer_channel_contact_email`.
- English and French translations for messages, validators and flash messages.
- Doctrine migration creating `emagma_order_withdrawal_request`.
- Self-contained Docker demo image (FrankenPHP + Mailpit + SQLite + fixtures).

[Unreleased]: https://github.com/emagma/sylius-order-withdrawal-plugin/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/emagma/sylius-order-withdrawal-plugin/releases/tag/v2.0.0
