# The customer journey

What a customer sees, what the form asks, and what it refuses.

The whole journey is one page and one submission. There is no account, no login, and no order
lookup — deliberately, because a customer exercising their right of withdrawal may have checked out
as a guest, or lost their account.

<!-- TODO(media): screenshot of the storefront form. -->
![The withdrawal form on the storefront](../media/shop-form.png)

## Reaching the form

Two ways in:

- the **Withdrawal request** link in the shop footer, injected on every storefront page via the
  Twig hook `sylius_shop.base.footer.content`;
- the direct URL, `/{locale}/withdrawal-request`, which you can link from your terms, your order
  confirmation email, or anywhere else.

## The form, field by field

| Field | Required | Notes |
| --- | --- | --- |
| First name | yes | |
| Last name | yes | |
| Order number | yes | Free text. **Not** matched against a real order — see below. |
| Order date | yes | Cannot be in the future. |
| Order not received yet | no | Checkbox, for withdrawing before delivery. |
| Order reception date | no | Cannot be in the future. |
| Email address | yes | Must be a valid address; the acknowledgement goes here. |
| My withdrawal concerns | yes | Radio: *the totality of my order* / *a part of my order*. Defaults to totality. |
| Details | conditional | **Required when the withdrawal is partial.** Free text otherwise. |
| Legal notice | yes | Must be ticked. The statement the customer is making. |

### Order number is free text

The customer types it. The plugin stores it as a plain string and never resolves it to a Sylius
order — so a typo, a reference from a different system, or an order that does not exist will all be
accepted and land in the admin grid for a human to sort out.

That is a deliberate trade-off: refusing a request because the customer mistyped a reference would
block a statutory right. If you would rather validate it, see
[Extending → validating the order number](../dev/extending.md#validating-the-order-number).

### The legal notice

The checkbox label is the withdrawal statement itself — by default, in English:

> I hereby notify you that I withdraw from the contract for the order identified above, and I
> undertake to return the order at my own expense.

This is the piece most likely to need adjusting for your jurisdiction and your terms. It is a plain
translation key (`emagma_sylius_order_withdrawal.form.legal_notice`), so overriding it is a
one-liner — see [translating.md](../dev/translating.md).

The form also renders an empty Twig block, `sector_conditions`, immediately above the fields. It
exists so you can insert jurisdiction- or sector-specific notices (statutory exclusions, for
instance) without forking the template.

## What gets rejected

The form re-renders with an error, and **nothing is recorded**, when:

- a required field is blank;
- the email address is not a valid one;
- the order date or the reception date is in the future;
- the withdrawal is partial but no detail was given;
- the legal notice was not accepted.

Every message is a translation key, so you can reword all of them without touching code.

## After submitting

Three things happen, in this order:

1. The request is saved, stamped with the current time, the current channel and the current locale,
   in state **New**.
2. An **acknowledgement email** goes to the address the customer typed — timestamped, recapping the
   order number, the date, the scope and any detail. This is the "durable medium" the directive
   asks for.
3. A **notification email** goes to the shop, and the `WithdrawalRequestSubmitted` event is
   dispatched for anything else you have plugged in.

The customer sees a flash message and lands back on an empty form. There is no tracking page — from
their point of view the exchange continues by email.

## Next

- [Admin guide](admin-guide.md) — what happens to the request once it arrives
- [Emails](emails.md) — the exact contents and how to change them
- [Extending](../dev/extending.md) — adding fields, adding validation, reacting to a submission
