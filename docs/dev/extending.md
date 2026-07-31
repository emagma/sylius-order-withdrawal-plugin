# Extending

Every extension point the plugin offers, cheapest first.

Read [architecture.md](architecture.md) if you want the flow these hook into.

| I want to… | Reach for |
| --- | --- |
| React when a request is submitted | [the domain event](#reacting-to-a-submission) |
| Notify something other than email | [a notifier](#adding-a-notifier) |
| React when a request changes state | [a workflow listener](#reacting-to-a-transition) |
| Add a state or a transition | [the workflow](#the-state-machine) |
| Add or change a validation rule | [the validation mapping](#adding-validation) |
| Change the form's fields | [a form extension](#extending-the-form) |
| Change any template | [template overriding](#overriding-templates) |
| Move the storefront link | [the Twig hook](#moving-or-removing-the-footer-link) |
| Store extra fields | [extending the entity](#extending-the-entity) — the expensive one |

## Reacting to a submission

`WithdrawalRequestSubmitted` is dispatched after the request is persisted and the emails are sent.
It is the intended place for your own logic.

```php
namespace App\Withdrawal;

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

The request is already flushed, so it has an id.

## Adding a notifier

Use this instead of the event when the thing you are adding is genuinely *"tell someone about this
request"* — it joins the same chain as the shipped email notifier and is covered by the same tests
and configuration.

```php
namespace App\Withdrawal;

use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Emagma\SyliusOrderWithdrawalPlugin\Notifier\WithdrawalRequestNotifierInterface;

final readonly class SlackWithdrawalRequestNotifier implements WithdrawalRequestNotifierInterface
{
    public function __construct(private ChatterInterface $chatter)
    {
    }

    public function notify(WithdrawalRequest $request): void
    {
        $this->chatter->send(new ChatMessage(sprintf(
            'Withdrawal request for order %s', $request->getOrderNumber(),
        )));
    }
}
```

No configuration needed — implementing the interface is enough, autoconfiguration applies the
`emagma_sylius_order_withdrawal.notifier` tag.

> **Guard your notifier.** The chain does not catch exceptions, so anything that throws will break
> the customer's submission response *after* the request was saved. Wrap network calls in a
> `try`/`catch`, or move them off the request — see [below](#doing-work-asynchronously).

To *replace* the shipped email notifier rather than add to it, alias its service id to your own
class, or decorate `ChainWithdrawalRequestNotifier`.

## Reacting to a transition

The plugin's own confirmation email is a listener on Symfony's workflow event. Yours looks the
same:

```php
use Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.emagma_sylius_order_withdrawal_request.completed')]
final class NotifyOnRejection
{
    public function __invoke(CompletedEvent $event): void
    {
        if (!$event->getSubject() instanceof WithdrawalRequest) {
            return;
        }

        if ('reject' !== $event->getTransition()?->getName()) {
            return;
        }

        // …
    }
}
```

Symfony also dispatches `.guard`, `.leave`, `.transition`, `.enter`, `.entered` and `.announce` for
the same graph — use `.guard` to make a transition conditional.

## The state machine

The workflow is plain Symfony configuration under the name
`emagma_sylius_order_withdrawal_request`. Redefining it in your application replaces the plugin's
definition:

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        emagma_sylius_order_withdrawal_request:
            type: state_machine
            marking_store:
                type: method
                property: state
            supports:
                - Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest
            initial_marking: new
            places: [new, in_progress, awaiting_return, confirmed, rejected]
            transitions:
                start:            { from: new,                          to: in_progress }
                await_return:     { from: in_progress,                  to: awaiting_return }
                confirm:          { from: [new, in_progress, awaiting_return], to: confirmed }
                reject:           { from: [new, in_progress],           to: rejected }
                reopen:           { from: rejected,                     to: in_progress }
```

Then add, for each new transition, a route mirroring the shipped ones:

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

The admin detail template renders buttons for a hard-coded list of transitions
(`start`, `confirm`, `reject`), so a new one also needs the template overridden — see
[overriding templates](#overriding-templates). Add translation keys under
`emagma_sylius_order_withdrawal.state.*` and `.transition.*`, and a badge colour in the grid's
state template.

## Adding validation

The plugin's rules live in `config/validation/WithdrawalRequest.xml`, in the `sylius` validation
group. Add your own by mapping the same class in your application:

```yaml
# config/validator/withdrawal.yaml
Emagma\SyliusOrderWithdrawalPlugin\Entity\WithdrawalRequest:
    properties:
        message:
            - Length:
                max: 2000
                groups: [sylius]
```

The `groups: [sylius]` part is not optional — the form validates that group only.

### Validating the order number

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
mismatch for the admin instead of refusing the submission.

## Extending the form

`WithdrawalRequestType` is `final`, so extend it the Symfony way:

```php
use Emagma\SyliusOrderWithdrawalPlugin\Form\Type\WithdrawalRequestType;
use Symfony\Component\Form\AbstractTypeExtension;

final class WithdrawalRequestTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->remove('notYetReceived');
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

## Overriding templates

Standard Symfony bundle template overriding. Drop a file at the same relative path under
`templates/bundles/EmagmaSyliusOrderWithdrawalPlugin/`:

| Override this | To change |
| --- | --- |
| `shop/withdrawal_request/request.html.twig` | the storefront form page |
| `shop/footer/_withdrawal_request.html.twig` | the footer link markup |
| `admin/withdrawal_request/show/content.html.twig` | the admin detail page and its buttons |
| `admin/withdrawal_request/grid/field/state.html.twig` | the status badge |
| `admin/withdrawal_request/grid/field/scope.html.twig` | the scope column |
| `email/{acknowledgement,notification,confirmation}.html.twig` | the emails |

### The `sector_conditions` block

If all you need is extra legal copy above the storefront form, you don't have to replace the whole
template — override it, extend the original, and fill the block it leaves empty for you.

The file **must** live at the overriding path, because `@!` means "the bundle's own version of the
template I am currently overriding". Put it anywhere else and you get a template-not-found, or an
infinite extend loop:

```twig
{# templates/bundles/EmagmaSyliusOrderWithdrawalPlugin/shop/withdrawal_request/request.html.twig #}
{% extends '@!EmagmaSyliusOrderWithdrawalPlugin/shop/withdrawal_request/request.html.twig' %}

{% block sector_conditions %}
    <div class="alert alert-info">
        {{ 'app.withdrawal.sector_notice'|trans }}
    </div>
{% endblock %}
```

## Moving or removing the footer link

The link is a Twig hookable at `sylius_shop.base.footer.content`, priority 150. Reconfigure it in
your application:

```yaml
# config/packages/sylius_twig_hooks.yaml
sylius_twig_hooks:
    hooks:
        # remove it from the footer
        'sylius_shop.base.footer.content':
            emagma_withdrawal_request:
                enabled: false

        # and put it somewhere else
        'sylius_shop.account.dashboard.content':
            withdrawal_request:
                template: '@EmagmaSyliusOrderWithdrawalPlugin/shop/footer/_withdrawal_request.html.twig'
                priority: 0
```

## Doing work asynchronously

Everything in the submission flow runs inside the HTTP request. To move your work off it, dispatch
a message from the event listener instead of doing the work there:

```php
#[AsEventListener]
final class QueueWithdrawalSync
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(WithdrawalRequestSubmitted $event): void
    {
        $this->bus->dispatch(new SyncWithdrawalRequest($event->getRequest()->getId()));
    }
}
```

Pass the **id**, not the entity — the handler runs in a different process with a different entity
manager.

## Extending the entity

This is the one genuinely awkward extension point, so here is the honest picture.

`WithdrawalRequest` is non-`final` with `protected` properties, so it is written for subclassing,
and the resource class is a container parameter you can swap:

```yaml
# config/packages/emagma_sylius_order_withdrawal.yaml
sylius_resource:
    resources:
        emagma_sylius_order_withdrawal.withdrawal_request:
            classes:
                model: App\Entity\WithdrawalRequest
```

But the plugin maps its **concrete class** as a Doctrine `<entity>` (in
`config/doctrine/WithdrawalRequest.orm.xml`), not as a mapped superclass, and ships no interface.
So a subclass mapped to the same table gives Doctrine two entities for one table. Making this work
means also neutralising or replacing the plugin's mapping in your application, which is a bigger
commitment than it looks.

**Prefer one of these instead:**

- **A side table.** Create your own entity with a `many-to-one` to `WithdrawalRequest`, populated
  from a `WithdrawalRequestSubmitted` listener. Your data, your migration, no conflict, and it
  survives plugin upgrades untouched.
- **Nothing at all.** If you only need the extra input to reach a human, add it to the form as an
  unmapped field and append it to the notification email.

If you genuinely need columns on the main table, open an issue describing the case — an interface
plus a mapped superclass would be the right fix, and it belongs in the plugin rather than in every
consumer.

## Next

- [Translating](translating.md)
- [Testing](testing.md) — how to cover what you added
- [Contributing](../../CONTRIBUTING.md) — if your extension belongs upstream
