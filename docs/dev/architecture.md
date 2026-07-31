# Architecture

Where things live, and what a submission actually touches.

The plugin is deliberately thin: one entity, one form, one workflow, and a handful of small
services. There is no custom controller — the shop and admin routes both use SyliusResourceBundle's
generic controller.

## Layout

```
src/
├── Entity/WithdrawalRequest.php          the only entity
├── Model/                                WithdrawalScope, WithdrawalRequestState (PHP enums)
├── Form/Type/WithdrawalRequestType.php   the storefront form
├── Event/WithdrawalRequestSubmitted.php  the domain event you listen to
├── EventListener/                        wires submission and transitions to side effects
├── Mailer/                               the three transactional emails + recipient resolution
├── Notifier/                             the pluggable "tell someone" layer
├── Menu/AdminMenuListener.php            the admin menu entry
├── Migrations/                           the MySQL migration
└── DependencyInjection/                  extension + configuration tree

config/
├── config.yaml                           the single import consumers use
├── routes.yaml → routes/{shop,admin}.yaml
├── services.xml
├── app/{resources,grids,mailer}.yaml
├── doctrine/WithdrawalRequest.orm.xml
├── validation/WithdrawalRequest.xml
├── workflow/state_machine.yaml
└── twig_hooks/{shop/footer,admin/withdrawal_request/show}.yaml

templates/  shop form · footer link · admin show & grid fields · the three emails
translations/  en + fr, for messages / validators / flashes
```

## The submission flow

A customer posts the form at `/{_locale}/withdrawal-request`:

1. **`ResourceController::createAction`** builds `WithdrawalRequestType`, binds it to a new
   `WithdrawalRequest`, and validates it against `config/validation/WithdrawalRequest.xml`
   (validation group `sylius`).

2. **`pre_create`** — `WithdrawalRequestListener::onPreCreate` stamps what the form does not carry:
   `submittedAt` (now), `channel` (from `ChannelContextInterface`) and `localeCode` (from
   `LocaleContextInterface`). Each is only set if still null, so a programmatically-built request
   can supply its own.

3. The entity is persisted. Its initial state is `new`, from the workflow's `initial_marking`.

4. **`post_create`** — `onPostCreate` does three things, in order:
   - `WithdrawalRequestEmailerInterface::sendAcknowledgementEmail()` → the customer;
   - `WithdrawalRequestNotifierAggregatorInterface::notify()` → the chain of notifiers, of which
     the email one is the only shipped member;
   - dispatches `WithdrawalRequestSubmitted` on the event dispatcher — **your** extension point.

5. The controller redirects back to the form with a flash message.

Steps 2 and 4 hang off SyliusResourceBundle's resource events, named after the resource alias:
`emagma_sylius_order_withdrawal.withdrawal_request.{pre,post}_create`.

> All of step 4 runs **synchronously, inside the request**. Three emails' worth of latency is
> usually fine; if your notifiers do anything slow, dispatch a message instead — see
> [extending.md](extending.md#doing-work-asynchronously).

## The transition flow

Admin transition routes call `ResourceController::applyStateMachineTransitionAction` with the graph
and transition named in `_sylius.state_machine`. Symfony's workflow applies it, and
`onTransitionCompleted` reacts to `workflow.emagma_sylius_order_withdrawal_request.completed`:

- it always calls `$request->touch()`, refreshing `updatedAt`;
- on the `confirm` transition only, it sends the confirmation email.

Because the listener keys off the transition **name**, adding a transition to the workflow does not
require touching it — and adding your own listener on the same event is how you attach a side
effect to a new transition.

## The notifier layer

```
WithdrawalRequestNotifierAggregatorInterface
        └── ChainWithdrawalRequestNotifier      (iterates a tagged_iterator)
                └── EmailWithdrawalRequestNotifier   (the only one shipped)
```

Anything implementing `WithdrawalRequestNotifierInterface` is autoconfigured with the tag
`emagma_sylius_order_withdrawal.notifier` and joins the chain. The chain is deliberately dumb: it
calls every notifier in order and swallows nothing, so a throwing notifier aborts the submission
response. Guard your own.

## Emails vs notifiers

Two similar-looking layers, different jobs:

| | `Mailer\` | `Notifier\` |
| --- | --- | --- |
| Audience | the **customer** | the **shop** and anything else you plug in |
| Members | acknowledgement, confirmation | a chain, extensible |
| Recipient | the address on the request | `RecipientResolverInterface` |

Both use `sylius.email_sender`, so both inherit your Sylius mailer configuration.

### One subtlety worth knowing

`WithdrawalRequestEmailer` resolves the channel from **the request**, not from the ambient channel
context, falling back to the context only if the request has none. This matters for the
confirmation email: it is triggered from the admin backend, which has no storefront channel of its
own, and the customer's email must be branded with the channel they actually bought from.

## Data model

One table, `emagma_order_withdrawal_request`. No relation to `sylius_order` — `orderNumber` is a
plain string (see the README on [what the plugin does not do](../../README.md#what-it-does-not-do)).
The only relation is a nullable `many-to-one` to `sylius_channel`, with `ON DELETE SET NULL`, so
deleting a channel never destroys the record of a customer's notification.

Both `scope` and `state` are persisted as strings backed by PHP enums (Doctrine `enum-type`). Note
that `WithdrawalRequest::getState()` returns a **string** — the workflow's `method` marking store
requires it — while `getStateEnum()` gives you the enum.

## Next

- [Extending](extending.md) — the practical version of all of the above
- [Testing](testing.md) — how the flows above are covered
