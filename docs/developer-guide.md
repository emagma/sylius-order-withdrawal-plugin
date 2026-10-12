# Developer guide

| I want to… | How |
| --- | --- |
| Understand what a submission touches | [Architecture](#architecture) |
| React when a request is submitted | [Domain event](#reacting-to-a-submission) |
| Notify something other than email | [Notifier](#adding-a-notifier) |
| React when a request changes state | [Workflow listener](#reacting-to-a-transition) |
| Add a state or transition | [Workflow override](#the-state-machine) |
| Add or change a validation rule | [Validation mapping](#adding-validation) |
| Change the form's fields | [Form extension](#extending-the-form) |
| Change any template | [Template overriding](#overriding-templates) |
| Move the storefront link | [sylius_ui event](#moving-or-removing-the-footer-link) |
| Change who the notification email reaches | [Recipient resolver](#emails-and-recipients) |
| Store extra fields | [Entity](#extending-the-entity) |
| Ship your own JavaScript | [Front-end assets](#front-end-assets) |
| Add a locale | [Translating](#translating) |
| Run the suites | [Testing](#testing) |

## Architecture

The plugin is deliberately thin: one entity, one form, one workflow and a handful of small
services. There is no custom controller — the shop and admin routes both use
SyliusResourceBundle's generic controller.

### Layout

```
src/
├── Entity/WithdrawalRequest.php          the only entity
├── Model/                                WithdrawalScope, WithdrawalRequestState (PHP enums)
├── Form/Type/WithdrawalRequestType.php   the storefront form
├── Event/WithdrawalRequestSubmitted.php  the domain event you listen to
├── EventListener/                        wires submission and transitions to side effects
├── Mailer/                               WithdrawalRequestEmailer + its interface — the customer emails
├── Resolver/                             DefaultRecipientResolver + RecipientResolverInterface
├── Notifier/                             the pluggable "tell someone" layer
├── Menu/AdminMenuListener.php            the admin menu entry
├── Migrations/                           the MySQL and PostgreSQL migrations
└── DependencyInjection/                  extension + configuration tree

config/
├── config.yaml                           the single import consumers use
├── routes.yaml → routes/{shop,admin}.yaml
├── services.xml
├── app/{resources,grids,mailer}.yaml
├── doctrine/WithdrawalRequest.orm.xml
├── validation/WithdrawalRequest.xml
├── app/state_machine.yaml                the winzou graph
└── app/sylius_ui.yaml                    the storefront footer event

templates/  shop form · footer link · admin show & grid fields · the three emails
translations/  en + fr, for messages / validators / flashes
```

The storefront footer link is declared in `config/app/sylius_ui.yaml` as a `sylius_ui` template
event — see [moving or removing the footer link](#moving-or-removing-the-footer-link).

### The submission flow

A customer posts the form at `/{_locale}/withdrawal-request`:

1. **`ResourceController::createAction`** builds `WithdrawalRequestType`, binds it to a new
   `WithdrawalRequest`, and validates it against `config/validation/WithdrawalRequest.xml`
   (validation group `sylius`).
2. **`pre_create`** — `WithdrawalRequestListener::onPreCreate` stamps what the form does not carry:
   `submittedAt` (now), `channel` (from `ChannelContextInterface`) and `localeCode` (from
   `LocaleContextInterface`). Each is only set if still null, so a programmatically-built request
   can supply its own.
3. The entity is persisted. Its initial state is `new`, the entity's own default.
4. **`post_create`** — `onPostCreate` does three things, in order:
   - `WithdrawalRequestEmailerInterface::sendAcknowledgementEmail()` → the customer;
   - `WithdrawalRequestNotifierAggregatorInterface::notify()` → the chain of notifiers, of which
     the email one is the only shipped member;
   - dispatches `WithdrawalRequestSubmitted` on the event dispatcher — **your** extension point.

   Each of the three steps is isolated: a failure in one is caught and logged, and does not stop
   the ones after it or turn the customer's already-saved submission into an error response.
5. The controller redirects back to the form with a flash message.

Steps 2 and 4 hang off SyliusResourceBundle's resource events, named after the resource alias:
`emagma_sylius_order_withdrawal.withdrawal_request.{pre,post}_create`.

> All of step 4 runs **synchronously, inside the request**. If your notifiers do anything slow,
> dispatch a Messenger message instead — see [reacting to a submission](#reacting-to-a-submission).

### The transition flow

Admin transition routes call `ResourceController::applyStateMachineTransitionAction` with the graph
and transition named in `_sylius.state_machine`. The winzou state machine applies it and dispatches
`winzou.state_machine.post_transition`, which `WithdrawalRequestListener::onTransitionApplied()`
handles:

- it always calls `$request->touch()`, refreshing `updatedAt` from the injected clock;
- on the `confirm` transition only, it *queues* the request.

The graph itself declares **no `callbacks`**. That is deliberate: redeclaring a winzou graph
replaces its callbacks wholesale, so a host application adding a single state would silently lose
both behaviours. An event survives any redefinition of the graph.

Because the event is dispatched for *every* winzou graph in the application — Sylius core ships
eight — the listener guards on the graph name and on the subject type before doing anything.

The confirmation email itself is **not** sent from the callback. `ResourceController` flushes the
new state after the transition and then dispatches
`emagma_sylius_order_withdrawal.withdrawal_request.post_confirm` (the admin route sets
`_sylius.event: confirm`), and `onPostConfirm()` drains the queue there — so the mail only goes out
once the confirmed state is actually committed.

To attach a side effect to a new transition, add your own callback to the graph.

### The notifier layer

```
WithdrawalRequestNotifierAggregatorInterface
        └── ChainWithdrawalRequestNotifier      (iterates a tagged_iterator)
                └── EmailWithdrawalRequestNotifier   (the only one shipped)
```

Anything implementing `WithdrawalRequestNotifierInterface` is autoconfigured with the tag
`emagma_sylius_order_withdrawal.notifier` and joins the chain. The interface itself carries
`#[AutoconfigureTag('emagma_sylius_order_withdrawal.notifier')]`, so this works container-wide —
including for a notifier declared in a **host application's own** `services.yaml`/`services.xml`
with plain `autoconfigure: true`. You never need a manual `tags:` entry, and you don't need to
register anything in the plugin's own configuration for your class to be picked up.

### Data model

One table, `emagma_order_withdrawal_request`. No relation to `sylius_order` — `orderNumber` is a
plain string. The only relation is a nullable `many-to-one` to `sylius_channel`, with
`ON DELETE SET NULL`, so deleting a channel never destroys the record of a customer's notification.

Both `scope` and `state` are persisted as strings backed by PHP enums (Doctrine `enum-type`).

## Extension points

### Reacting to a submission

`WithdrawalRequestSubmitted` is dispatched after the request is persisted. The acknowledgement
email and the notifier chain are attempted first, but each is guarded independently — a failure in
either is logged, not propagated — so the event still fires even if the acknowledgement email or a
notifier throws. Don't assume receiving this event means the email went out; if you need that
guarantee, check it yourself.

```php
use Emagma\SyliusOrderWithdrawalPlugin\Event\WithdrawalRequestSubmitted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class OpenSupportTicket
{
    public function __invoke(WithdrawalRequestSubmitted $event): void
    {
        $request = $event->getRequest();
        // $request->getOrderNumber(), ->getEmail(), ->getScope(), ->getChannel(), …
    }
}
```

The request is already flushed. Everything runs synchronously inside the HTTP request — if your
listener does anything slow, dispatch a Messenger message with the request **id** instead (the
handler runs in another process, with another entity manager).

### Adding a notifier

Implement `WithdrawalRequestNotifierInterface` — autoconfiguration tags it, no config needed. This
applies equally whether your class lives inside the plugin or in your own application: as long as
it's autowired/autoconfigured (Symfony's default for anything under `src/`), it joins the chain
automatically, with no manual `tags:` entry required.

```php
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierInterface;

final class SlackWithdrawalRequestNotifier implements WithdrawalRequestNotifierInterface
{
    public function notify(WithdrawalRequest $request): void
    {
        // ...
    }
}
```

> The chain catches and logs each notifier's exceptions individually, so one failing channel
> no longer breaks the others or the customer's submission response. Still, avoid slow
> synchronous I/O in your notifier — the chain runs inside the customer's request.

To *replace* the shipped email notifier, alias its service id or decorate
`ChainWithdrawalRequestNotifier`.

### Reacting to a transition

Listen to winzou's transition event — no service configuration, and nothing to keep in sync with
the graph definition:

```php
namespace App\Withdrawal;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\EventListener\WithdrawalRequestListener;
use SM\Event\SMEvents;
use SM\Event\TransitionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SMEvents::POST_TRANSITION)]
final class NotifyOnRejection
{
    public function __invoke(TransitionEvent $event): void
    {
        $stateMachine = $event->getStateMachine();

        // The event fires for every graph in the application — guard, or you react to orders too.
        if (WithdrawalRequestListener::GRAPH !== $stateMachine->getGraph()) {
            return;
        }

        if (!$stateMachine->getObject() instanceof WithdrawalRequest) {
            return;
        }

        if ('reject' !== $event->getTransition()) {
            return;
        }

        // …
    }
}
```

`POST_TRANSITION` fires after the new state is set but **before** the controller flushes. If your
side effect must only happen once the state is committed, queue it here and act on the
`emagma_sylius_order_withdrawal.withdrawal_request.post_confirm` resource event — which is exactly
how the shipped confirmation email works.

winzou also dispatches `SMEvents::PRE_TRANSITION` and `SMEvents::TEST_TRANSITION`; calling
`$event->setRejected()` on either makes a transition conditional.

### The state machine

The shipped graph is `emagma_sylius_order_withdrawal_request`, defined in
`config/app/state_machine.yaml`: four states (`new`, `in_progress`, `confirmed`, `rejected`)
and three transitions (`start`, `confirm`, `reject`). Redefine it in your application to replace
that definition — the example below adds an `awaiting_return` state and two transitions:

```yaml
# config/packages/winzou_state_machine.yaml
winzou_state_machine:
    emagma_sylius_order_withdrawal_request:
        class: '%emagma_sylius_order_withdrawal.model.withdrawal_request.class%'
        property_path: state
        graph: emagma_sylius_order_withdrawal_request
        state_machine_class: '%sylius.state_machine.class%'
        states:
            new: ~
            in_progress: ~
            awaiting_return: ~
            confirmed: ~
            rejected: ~
        transitions:
            start:        { from: [new],                                to: in_progress }
            await_return: { from: [in_progress],                        to: awaiting_return }
            confirm:      { from: [new, in_progress, awaiting_return],  to: confirmed }
            reject:       { from: [new, in_progress],                   to: rejected }
            reopen:       { from: [rejected],                           to: in_progress }
```

> Safe to redeclare wholesale: the plugin hangs its own behaviour off the transition **event**,
> not off graph `callbacks`, so redefining the graph cannot drop the `updatedAt` stamp or the
> confirmation email.

Each new transition also needs a route, mirroring the shipped ones in `config/routes/admin.yaml`:

```yaml
# config/routes/emagma_sylius_order_withdrawal_extra.yaml
app_withdrawal_request_await_return:
    path: /admin/withdrawal-requests/{id}/await-return
    methods: [PUT]
    defaults:
        _controller: emagma_sylius_order_withdrawal.controller.withdrawal_request::applyStateMachineTransitionAction
        _sylius:
            event: await_return
            section: admin
            permission: true
            state_machine:
                graph: emagma_sylius_order_withdrawal_request
                transition: await_return
            redirect:
                route: emagma_sylius_order_withdrawal_admin_withdrawal_request_show
                parameters: { id: $id }
            flash: emagma_sylius_order_withdrawal.withdrawal_request.transition_applied
```

The admin detail template renders buttons for a hard-coded list of transitions (`start`, `confirm`,
`reject`), so a new one also needs [the template overridden](#overriding-templates), translation
keys under `emagma_sylius_order_withdrawal.state.*` / `.transition.*`, and a badge colour in the
grid's state template.

### Adding validation

Map the same class in your application. The `groups: [sylius]` part is mandatory — the form
validates that group only.

```yaml
# config/validator/withdrawal.yaml
Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest:
    properties:
        message:
            - Length:
                max: 2000
                groups: [sylius]
```

#### Validating the order number

By default the order number is free text and is never resolved to a real order. To require that it
matches an existing one, write a constraint and map it the same way:

```php
#[\Attribute]
final class ExistingOrderNumber extends Constraint
{
    public string $message = 'app.withdrawal.unknown_order';
}

final class ExistingOrderNumberValidator extends ConstraintValidator
{
    public function __construct(private OrderRepositoryInterface $orders)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value || null !== $this->orders->findOneByNumber($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
```

Think it through before you do: a customer who mistypes their order number is still exercising a
statutory right, and a hard rejection blocks them from notifying you at all. Consider flagging the
mismatch for the administrator instead of refusing the submission.

### Extending the form

```php
use Emagma\SyliusOrderWithdrawalPlugin\Form\Type\WithdrawalRequestType;
use Symfony\Component\Form\AbstractTypeExtension;

final class WithdrawalRequestTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phoneNumber', TelType::class, [
            'label' => 'app.withdrawal.phone_number',
            'required' => false,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [WithdrawalRequestType::class];
    }
}
```

A new field only persists if the entity has a matching property — see
[extending the entity](#extending-the-entity).

The form carries no anti-bot field of its own. If you need one — a honeypot, a CAPTCHA widget, a
signed timestamp — add it here through a form type extension, together with the constraint or
listener that rejects a filled decoy. See [the public endpoint](../SECURITY.md#public-endpoint)
for why this matters on a shop that is open to the internet.

### Overriding templates

Drop a file at the same relative path under
`templates/bundles/EmagmaSyliusOrderWithdrawalPlugin/`:

| Override | Changes |
| --- | --- |
| `shop/withdrawal_request/request.html.twig` | the storefront form page |
| `shop/footer/_withdrawal_request.html.twig` | the footer column and its link |
| `shop/form/theme.html.twig` | the storefront form theme |
| `admin/withdrawal_request/show.html.twig` | the admin detail page and buttons |
| `admin/withdrawal_request/grid/field/state.html.twig` | the status label |
| `admin/withdrawal_request/grid/field/scope.html.twig` | the scope column |
| `email/{acknowledgement,notification,confirmation}.html.twig` | the emails |

The storefront form template has a `sector_conditions` block you can fill without replacing the
whole template — extend with `@!EmagmaSyliusOrderWithdrawalPlugin/...` (the `@!` prefix targets
the bundle's own version). The override file **must** live at the overriding path, otherwise you
get a template-not-found or an infinite extend loop:

```twig
{# templates/bundles/EmagmaSyliusOrderWithdrawalPlugin/shop/withdrawal_request/request.html.twig #}
{% extends '@!EmagmaSyliusOrderWithdrawalPlugin/shop/withdrawal_request/request.html.twig' %}

{% block sector_conditions %}
    <div class="alert alert-info">
        {{ 'app.withdrawal.sector_notice'|trans }}
    </div>
{% endblock %}
```

### Moving or removing the footer link

The link is registered in `config/app/sylius_ui.yaml` as a block on the
`sylius.shop.layout.footer.grid` template event, at priority `15` — just after the "Customer care"
column. Redeclare the same block in your application to move or drop it:

```yaml
# config/packages/sylius_ui.yaml — move it elsewhere
sylius_ui:
    events:
        # remove it from the footer by pointing the block at an empty template
        sylius.shop.layout.footer.grid:
            blocks:
                emagma_withdrawal_request:
                    template: '@App/shop/_empty.html.twig'

        # and render it somewhere else
        sylius.shop.account.dashboard:
            blocks:
                withdrawal_request:
                    template: '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request.html.twig'
                    priority: 0
```

### Emails and recipients

The three emails go through `sylius.email_sender`, so they inherit your Sylius mailer
configuration. Each template extends `@SyliusShop/Email/layout.html.twig` and defines a `subject`
and a `content` block, with these variables available:

| Variable | |
| --- | --- |
| `withdrawalRequest` | the `WithdrawalRequest` entity |
| `channel` | the channel it was submitted through (may be `null`) |
| `localeCode` | the locale to render in — pass it to every `trans` call |

Where the shop-side notification goes is decided by `RecipientResolverInterface`, implemented by
`DefaultRecipientResolver` in `src/Resolver/`: the channel's contact email when
`prefer_channel_contact_email` is on and the channel has one, otherwise the `notification_email`
setting — and no recipient at all when neither is set, in which case `EmailWithdrawalRequestNotifier`
sends nothing. For anything more elaborate — a distribution list, per-country routing, a CRM lookup —
implement the interface yourself:

```php
namespace App\Withdrawal;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Resolver\RecipientResolverInterface;

final class TeamRecipientResolver implements RecipientResolverInterface
{
    /** @return string[] */
    public function getRecipients(WithdrawalRequest $request): array
    {
        return 'partial' === $request->getScope()->value
            ? ['returns@example.com', 'warehouse@example.com']
            : ['returns@example.com'];
    }
}
```

```yaml
# config/services.yaml
services:
    Emagma\SyliusOrderWithdrawalPlugin\Resolver\RecipientResolverInterface:
        alias: App\Withdrawal\TeamRecipientResolver
```

Returning an empty array suppresses the notification.

### Extending the entity

`WithdrawalRequest` is mapped as a Doctrine `<mapped-superclass>`: Sylius turns it into an entity
only while it is the configured resource model. Point the model at your own subclass and the
subclass owns the table instead:

```yaml
sylius_resource:
    resources:
        emagma_sylius_order_withdrawal.withdrawal_request:
            classes:
                model: App\Entity\WithdrawalRequest
```

```php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest as BaseWithdrawalRequest;

#[ORM\Entity]
#[ORM\Table(name: 'emagma_order_withdrawal_request')]
class WithdrawalRequest extends BaseWithdrawalRequest
{
    #[ORM\Column(name: 'internal_note', type: Types::TEXT, nullable: true)]
    protected ?string $internalNote = null;
}
```

- **Declare the table name.** Doctrine does not inherit it from a mapped superclass (the indexes
  are inherited).
- **Ship your own migration** for the columns you add, ordered after the plugin's.
- The grid, the form, the routes and the state machine all go through the resource parameter or
  an `instanceof` check, so they pick the subclass up with no further change.

If you only need extra input to reach a human, add an unmapped form field and append it to the
notification email.

## Database migrations

The plugin supports the same platforms as Sylius core — MySQL, MariaDB and PostgreSQL — and follows
core's convention for it: **every schema change ships as two migrations**, one per platform, with
adjacent timestamps and the same `getDescription()`.

| Migration | Base class | Runs on |
| --- | --- | --- |
| `Version20260720160024` | `Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration` | MySQL, MariaDB |
| `Version20260720160025` | `Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration` | PostgreSQL |

Both base classes implement `MigrationSkipInterface` and guard themselves in `preUp()`/`preDown()`,
so the one that does not match the connection is *skipped* rather than executed — and core's
`MigrationSkipListener` records the skipped version as complete, so it is not retried on every
subsequent `migrate`. Extending Doctrine's own `AbstractMigration` instead would make a MySQL
migration abort the whole run on PostgreSQL.

Adding a column therefore means writing both halves. Generate each against a real connection rather
than hand-translating the SQL:

```bash
# MySQL/MariaDB — the default dev stack connection
vendor/bin/console doctrine:migrations:diff

# PostgreSQL — point DATABASE_URL at a Postgres server, then diff again
DATABASE_URL='postgresql://user:pass@postgres:5432/sylius?serverVersion=16&charset=utf8' \
    vendor/bin/console doctrine:migrations:diff
```

Then change each generated class to extend the matching Sylius base class. The types differ in ways
that are easy to get wrong by hand: `TINYINT(1)` vs `BOOLEAN`, `LONGTEXT` vs `TEXT`, `DATETIME` vs
`TIMESTAMP(0) WITHOUT TIME ZONE`, `AUTO_INCREMENT` vs a separate `CREATE SEQUENCE`, inline `INDEX`
and `COMMENT` clauses vs separate `CREATE INDEX` and `COMMENT ON COLUMN` statements, and
`DROP FOREIGN KEY` vs `DROP CONSTRAINT`.

Verify with `doctrine:schema:update --dump-sql` on both platforms after migrating: it should emit
nothing for `emagma_order_withdrawal_request`.

## Front-end assets

The plugin ships **no JavaScript and no compiled assets**, so a consumer needs no front-end build
to use it as-is: `assets/admin/entrypoint.js` and `assets/shop/entrypoint.js` are empty
placeholders, and no shipped template calls `encore_entry_script_tags()`.

They are shipped rather than removed so that your customisations have somewhere to live. Composer
installs the PHP code but **does not build JavaScript** — your application's own Webpack Encore
build has to compile these entrypoints, and any npm packages they import must be installed in
**your** application (Composer manages PHP, not npm; there is no automatic propagation).

To wire them up in your Sylius application:

1. **Install the npm dependencies your customisations introduce** — none are required by default:

   ```bash
   yarn add swiper
   ```

2. **Register the entrypoints in your `webpack.config.js`**, pointing at the installed package
   under `vendor/`:

   ```js
   Encore
       // ... your existing admin config
       .addEntry('emagma-sylius-order-withdrawal-admin', './vendor/emagma/sylius-order-withdrawal-plugin/assets/admin/entrypoint.js');

   Encore
       // ... your existing shop config
       .addEntry('emagma-sylius-order-withdrawal-shop', './vendor/emagma/sylius-order-withdrawal-plugin/assets/shop/entrypoint.js');
   ```

3. **Include the built entries** in your admin/shop layout, or through a `sylius_ui` event:

   ```twig
   {{ encore_entry_script_tags('emagma-sylius-order-withdrawal-admin') }}
   ```

4. Rebuild: `yarn install && yarn encore dev` (or `production`).

> For the plugin's **own** development and CI build, dependencies are declared in
> `tests/TestApplication/package.json` instead — the bundled `sylius/test-application` merges and
> builds them automatically. That file never reaches consumers, so keep this section in sync with
> it if you add a dependency there.

## Translating

The plugin ships English and French across three translation domains, all under the
`emagma_sylius_order_withdrawal` root:

| Domain | Contains |
| --- | --- |
| `messages` | form labels, shop copy, admin labels, state/transition names, email bodies |
| `validators` | validation messages |
| `flashes` | flash messages |

**Override a key** — declare the same key in your application's `translations/`. Symfony merges by
key and the application wins, so do not edit the plugin's files in `vendor/`:

```yaml
# translations/messages.en.yaml
emagma_sylius_order_withdrawal:
    form:
        legal_notice: 'I confirm that I am withdrawing from this purchase under our returns policy.'
```

The keys most shops reword: `form.legal_notice` (the withdrawal statement itself — **review this
one**), `shop.intro`, `shop.title`, `shop.cta`, `shop.footer_link`, and the three `email.*.subject`
lines.

**Add a locale** — copy the three English files, translate the values, clear the cache:

```bash
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/messages.en.yaml    translations/messages.de.yaml
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/validators.en.yaml  translations/validators.de.yaml
cp vendor/emagma/sylius-order-withdrawal-plugin/translations/flashes.en.yaml     translations/flashes.de.yaml
bin/console cache:clear
```

Make sure the locale is enabled in Sylius (**Configuration → Locales**) and assigned to the
channel. Three keys carry placeholders, which must be kept as they are: `email.greeting`
(`%name%`), `email.notification.subject` (`%order%`), `email.confirmation.body` (`%order%`).

Emails are sent in the locale the request was submitted in (`localeCode`), not the locale of
whoever triggers the send.

Check your work with `bin/console debug:translation <locale> EmagmaSyliusOrderWithdrawalPlugin`,
which lists missing and unused keys per domain.

New locales are very welcome upstream, and they are the easiest possible first contribution: write
the three files for `translations/` and attach them to an issue; a maintainer adds them to the
plugin. Please translate all three domains, keep the key order identical to the English files so
diffs stay readable, and say in the issue whether you are a native speaker.

## Testing

The plugin uses the shared [`sylius/test-application`](https://github.com/Sylius/TestApplication)
as its test environment; there is no test application bundled in this repository. `composer
install` is the only setup step, and any PHP matching the declared `php: ^8.1` works — 8.1, 8.2 and
8.3 are all covered by CI.

Four commands, all must pass before a PR is merged:

```bash
vendor/bin/ecs check        # coding standard (Sylius Labs + an enforced file header)
vendor/bin/phpstan analyse  # static analysis, level max on src/
vendor/bin/phpunit          # unit tests
vendor/bin/behat            # acceptance tests
```

`vendor/bin/ecs check --fix` fixes most style findings, including the license header every PHP file
must carry. `vendor/bin/rector process --dry-run` is available too, but is a migration aid rather
than part of the gate.

### Unit tests

Fast, no database, no browser — this is where most of a contribution's coverage should live. They
cover the entity, both enums, the configuration tree, the recipient resolver, the emailer and both
notifiers.

```bash
vendor/bin/phpunit                                # everything
vendor/bin/phpunit --filter WithdrawalRequestTest # one class
```

### Behat suites

| Suite | Covers |
| --- | --- |
| `emagma_order_withdrawal_requesting` | form submission, validation, emails |
| `emagma_order_withdrawal_accessing` | reaching the form from the footer |
| `emagma_order_withdrawal_browsing` | admin grid: listing, filtering |
| `emagma_order_withdrawal_processing` | admin transitions, confirmation email |

Run with `--suite=` to avoid picking up Sylius's own core suites, which `behat.yml.dist` imports:

```bash
vendor/bin/behat --suite=emagma_order_withdrawal_requesting
```

All four suites pass. CI runs the three non-`@javascript` ones on every push and pull request, one
step per suite so that a failure in one does not hide the verdict of the next; `processing` is
`@javascript` and runs locally.

Behat needs a MySQL/MariaDB database at `DATABASE_URL` (see `tests/TestApplication/.env`) and a
headless Chrome at `http://127.0.0.1:9222` for the `@javascript` scenarios. Copy `behat.yml.dist`
to `behat.yml` (gitignored, so your local URL never lands in a commit) and set
`Behat\MinkExtension.base_url` to whatever serves your test environment:

```bash
cp behat.yml.dist behat.yml
```

### Testing your own extension

- **A notifier or a listener** → a unit test. Both shipped notifiers have one you can copy from
  (`tests/Unit/Notifier/`).
- **A new transition** → a scenario in `features/admin/processing_withdrawal_request.feature`, and
  the matching step in `ManagingWithdrawalRequestsContext`.
- **A new validation rule** → a scenario in `features/shop/requesting_order_withdrawal.feature`.
  The existing rejection scenarios all follow one shape; match it.

Write the feature file in the language of the customer's or administrator's intent, not the
implementation — that is the house style in `features/`, and it is what makes those files readable
as a specification of what the plugin promises.

### Running the suites in Docker

The demo image ships the full `vendor/` toolchain, so the suites can run inside it against its
baked SQLite database without installing anything locally:

```bash
docker build -f docker/demo/Dockerfile -t emagma/owp-demo .
docker run --rm emagma/owp-demo vendor/bin/phpunit
```

See [docker/demo/README.md](../docker/demo/README.md).
