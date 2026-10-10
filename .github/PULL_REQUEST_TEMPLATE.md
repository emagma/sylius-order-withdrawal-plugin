<!--
Thanks for contributing. Please read CONTRIBUTING.md if you haven't — in particular the
"Branching" section: open every pull request against `main`, which covers the whole Sylius 2 line.
`1.0.x-dev` (the plugin's 1.x line, for Sylius 1.14 LTS) is a maintenance branch a maintainer
cherry-picks into — don't open a second PR against it, just mention in the description that the fix
also applies there.
-->

## What and why

<!-- What this changes, and the problem it solves. The diff already shows the "what" in detail;
     use this space for the "why". Link the issue if there is one: "Fixes #123". -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation
- [ ] Translation
- [ ] Internal / tooling

## Checklist

- [ ] Targets the right branch (see [Branching](../CONTRIBUTING.md#branching))
- [ ] `vendor/bin/ecs check` passes
- [ ] `vendor/bin/phpstan analyse` passes
- [ ] `vendor/bin/phpunit` passes
- [ ] `vendor/bin/behat --suite=…` passes, or I've explained below why I couldn't run it
- [ ] Tests added for the behaviour I changed
- [ ] Docs updated, if this changes anything a user can observe
- [ ] `CHANGELOG.md` entry added under `## [Unreleased]`
- [ ] No user-visible strings hard-coded — translation keys added to both `en` and `fr`

## Breaking changes

<!-- If you ticked "Breaking change": what breaks, and what consumers have to do about it.
     This text should be usable as-is in UPGRADE.md. Otherwise write "None". -->

None.

## Notes for the reviewer

<!-- Anything you're unsure about, approaches you rejected, or parts you'd like a closer look at.
     Optional, but it usually makes the review faster. -->
