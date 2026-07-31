# Security policy

## Reporting a vulnerability

**Do not open a public GitHub issue.**

Email <security@emagma.fr> with:

- what the vulnerability is and what it lets an attacker do;
- the affected version and Sylius line;
- steps to reproduce, or a proof of concept.

You will get an acknowledgement, and we will work with you on a fix and a disclosure timeline
before anything is made public. Please give us a reasonable window to release before disclosing.

Emagma open-sources this plugin as is and does not offer a bug bounty. Reports are still very much
appreciated, and reporters are credited in the changelog unless they prefer otherwise.

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
vulnerability. Rate-limiting and spam protection on the public form are your application's
responsibility.
