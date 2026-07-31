# Admin guide

Finding, filtering and processing the requests customers submit.

Everything lives under **Customers → Withdrawal requests** in the admin menu.

## The grid

<!-- TODO(media): screenshot of the admin grid. -->
![The withdrawal requests grid](../media/admin-grid.png)

Newest first, sorted on the submission date.

| Column | |
| --- | --- |
| Request date and time | when the customer submitted |
| Last name / First name | who |
| Order number | as typed by the customer |
| Channel | which shop the request came from |
| My withdrawal concerns | *the totality* or *a part* of the order |
| Status | a coloured badge — see below |

Four filters are available: **last name**, **order number**, **status** and **channel**, plus a
date filter on the submission date.

The grid is read-only. Requests are created by customers, never by administrators, and there is no
edit or delete action — a withdrawal notification is a record of something a customer told you, so
it is kept as sent.

## The detail page

<!-- TODO(media): screenshot of a request detail page with its transition buttons. -->
![A single request with its available transitions](../media/admin-show.png)

Shows everything the customer submitted, including the fields the grid leaves out: their email
address, the reception date (or *Order not received yet*), and the free-text detail on a partial
withdrawal.

Below the details sit the actions available **in the request's current state** — a button only
appears when the transition is legal.

## The lifecycle

A request moves through a Symfony state machine named
`emagma_sylius_order_withdrawal_request`.

```
                ┌────────────► confirmed
                │
  new ──start──► in_progress
   │            │
   │            └────────────► rejected
   │
   └──── confirm / reject ───► (directly)
```

| State | Meaning |
| --- | --- |
| **New** | Just submitted. Nobody has looked at it. |
| **In progress** | Someone has taken it in charge. |
| **Confirmed** | The withdrawal is accepted. |
| **Rejected** | The withdrawal is refused. |

| Action | From | To | Side effect |
| --- | --- | --- | --- |
| Take in charge | `new` | `in_progress` | — |
| Confirm withdrawal | `new`, `in_progress` | `confirmed` | **sends a confirmation email to the customer** |
| Reject | `new`, `in_progress` | `rejected` | — |

Two things worth knowing:

- **You can confirm or reject straight from `new`.** Taking a request in charge is a convenience
  for teams that want to show it's being handled, not a mandatory step.
- **The states are terminal.** There is no transition out of `confirmed` or `rejected`. If you need
  one, see [Extending → the state machine](../dev/extending.md#the-state-machine).

Every transition stamps the request's `updatedAt`.

## Rejecting a request

Rejecting sends **no email**. The plugin deliberately does not tell a customer "your withdrawal was
refused" on its own, because refusing a statutory right usually needs a reasoned, human reply
rather than a template.

If you want an automatic rejection email, the hook is
[a workflow listener](../dev/extending.md#reacting-to-a-transition) — the shipped confirmation
email is implemented exactly that way and makes a good model.

## Permissions

The admin routes are declared with `permission: true`, so they go through Sylius's standard
authorisation layer. If you run an ACL/RBAC plugin, the withdrawal routes are governed by it like
any other admin resource.

## Next

- [Emails](emails.md) — what the confirmation email says, and how to change it
- [Configuration reference](configuration.md) — where notifications are routed
- [Extending](../dev/extending.md) — new states, new transitions, new side effects
