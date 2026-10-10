# Security policy

## Reporting a vulnerability

**Do not open a public GitHub issue.**

Email <security@emagma.fr> with:

- what the vulnerability is and what it lets an attacker do;
- the affected version and Sylius line;
- steps to reproduce, or a proof of concept.

You will get an acknowledgement, and we will work with you on a fix and a disclosure timeline
before anything is made public. Please give us a reasonable window to release before disclosing.

We don't run a bug bounty, but reports are genuinely appreciated and we take them seriously.
Reporters are credited in the changelog unless they prefer otherwise.

## Supported versions

Security fixes land on the branch of each currently supported Sylius line — see
[Branching](CONTRIBUTING.md#branching). Older branches are not patched.

## Scope

In scope: anything in this repository — the plugin's PHP code, templates, configuration and
migration.

Out of scope: vulnerabilities in Sylius, Symfony or other dependencies (report those upstream), and
the plugin's deliberate design choices. In particular, note that **the withdrawal form is public
and unauthenticated by design**, and **the order number is free text that is never matched against
a real order** — see [what the plugin does not do](README.md#what-it-does-not-do). Neither is a
vulnerability.

## Public endpoint

`/withdrawal-request` is anonymous by design — anyone can submit it without authentication — and
every accepted submission sends **two** emails: a notification to the shop, and (unless you set
`send_acknowledgement: false`) an acknowledgement to the address the visitor typed in. That address
is never verified.

**Out of the box the plugin ships no protection against automated submission.** There is no
CAPTCHA, no honeypot, no proof of work, and no rate limiter — the plugin defines none, it only
offers `rate_limiter_service` to plug one in, and that is **unset by default**. An unprotected
endpoint lets an attacker script it to make your shop deliver attacker-chosen mail to
attacker-chosen recipients, at your mail domain's expense: bounce rates, spam complaints and
blocklisting, on top of a flooded inbox and a table full of junk requests.

Protecting the endpoint is the operator's responsibility. At minimum:

- Declare a limiter under `framework.rate_limiter` and name it in `rate_limiter_service` — the
  plugin's listener then caps submissions per IP address and returns HTTP 429 past the limit. Two
  things make the difference between a real control and a decorative one: its storage must be
  shared between your application servers (a filesystem pool gives each node its own buckets and
  multiplies the effective limit), and `framework.trusted_proxies` must be configured behind any
  reverse proxy, load balancer or CDN — otherwise every visitor shares one IP and one bucket,
  turning the limit into an outage for real customers instead of a control on abuse.
- Consider `send_acknowledgement: false`, which removes the attacker-controlled recipient entirely
  and leaves only the mail to your own address. Weigh it against the legal expectation of
  acknowledging a withdrawal on a durable medium.
- Add whatever your threat model calls for on top — CAPTCHA, WAF rules, bot management at the CDN.
  The form is a standard Symfony form type and can be extended; see
  [Extending the form](docs/developer-guide.md#extending-the-form).

See [Configuration](docs/user-guide.md#configuration) for the settings.
