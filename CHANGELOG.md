# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog.
This project follows Semantic Versioning (SemVer).

---

## [1.0.3] - 2026-06-21

### Added

- Added official support for Laravel 13.
- Added compatibility constraints for:
  - Laravel 10
  - Laravel 11
  - Laravel 12
  - Laravel 13
- Added support for PHP 8.2 and PHP 8.3.
- Added Orchestra Testbench 11 support for Laravel 13 testing.

### Changed

- Updated all Illuminate package constraints:

  - illuminate/support
  - illuminate/database
  - illuminate/http
  - illuminate/contracts
  - illuminate/filesystem

  from:

  ```text
  ^10.0|^11.0|^12.0
