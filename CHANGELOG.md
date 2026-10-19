# Changelog

## [0.1.0-alpha.1] - 2026-10-19

First alpha release of GLPI Inject.

### Added

- GLPI 11 plugin scaffold and Debian install/uninstall scripts.
- Reusable import models and dynamic discovery of built-in and custom asset itemtypes.
- CSV upload, mapping, preview, validation, and GLPI-native create/update operations.
- Persistent import runs, per-row logs, batched progress, and failed-row CSV export.

### Known limitations

- Complex relation-object imports, broader datatype/dropdown coverage, and model sharing/profile rights are not yet at feature parity with Data Injection.
- Requires integration testing on a live GLPI 11.0.8 instance.