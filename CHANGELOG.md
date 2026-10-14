# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

`main` covers the whole Sylius 2.x line; the `1.0.x-dev` branch (Sylius `^1.14.0`) is maintained
separately — see [Branching](CONTRIBUTING.md#branching).

## [2.1.0] - 2026-10-14

### Added

- Sylius 2.3 support, on Symfony 6.4, 7.4 and 8.0 (Symfony 8 needs PHP 8.4).

### Changed

- The service definitions moved from `config/services.xml` and `config/services/rate_limiter.xml`
  to `config/services.php` and `config/services/rate_limiter.php`, because Symfony 8 can no longer
  load XML service configuration. The extension loads them as before, and they define exactly the
  same services, so an existing install needs no change.

### Deprecated

- `config/services.xml` and `config/services/rate_limiter.xml` now only import their PHP
  counterparts, and will be removed in 3.0. Do not reference them.

## [2.0.2] - 2026-10-12

### Fixed

- `WithdrawalRequest` can be extended by overriding the resource model, as the developer guide
  documents. It is now mapped as a Doctrine mapped superclass; without an override it stays an
  entity on the same table, so no migration is needed.

## [2.0.1] - 2026-10-12

No change to the shipped code. A new test covers `notification_email` from the configuration to
the recipient resolver, including an unset or empty environment variable.

## [2.0.0] - 2026-10-10

First public release, targeting Sylius `~2.0.0 || ~2.1.0 || ~2.2.0`.
