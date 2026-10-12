# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This is the `1.0.x-dev` branch, covering Sylius `^1.14.0`; `main` covers the whole Sylius 2.x
line — see [Branching](CONTRIBUTING.md#branching).

## [Unreleased]

## [1.0.1] - 2026-10-12

No change to the shipped code. A new test covers `notification_email` from the configuration to
the recipient resolver, including an unset or empty environment variable.

## [1.0.0] - 2026-10-10

First public release of the plugin's 1.x line, targeting Sylius `^1.14.0`. Feature-equivalent to
`2.0.0`, with the version-bound pieces adapted: winzou state machine instead of Symfony Workflow,
`sylius_ui` template events instead of Twig Hooks, and Semantic UI templates.
