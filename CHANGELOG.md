# Changelog

## [0.1.0-alpha.9] - 2026-10-20

### Fixed

- Exclude core import targets whose displayed type name is exactly “General,” while retaining specifically named targets.

## [0.1.0-alpha.8] - 2026-10-20

### Fixed

- Disambiguate duplicate import-target labels by appending their GLPI itemtype names.

## [0.1.0-alpha.7] - 2026-10-20

### Fixed

- Read custom asset display names from GLPI's definition label and translation APIs to prevent blank generic asset targets.
- Use explicit GLPI search-option itemtypes when qualifying duplicate mapping field labels.

## [0.1.0-alpha.6] - 2026-10-20

### Fixed

- Remove blank mapping fields and distinguish duplicate labels by their source itemtype.
- Include active custom assets in the import target list even when the model-management profile lacks the asset-specific create right.

## [0.1.0-alpha.5] - 2026-10-19

### Fixed

- Add the GLPI Inject Tools entry after the cached menu is loaded, so existing sessions see it without affecting Data Injection.

## [0.1.0-alpha.4] - 2026-10-19

### Fixed

- Discover active custom asset definitions alongside the original plugin's broader built-in import types.
- Register a dedicated GLPI Inject Tools menu with distinct import and model entries.

## [0.1.0-alpha.3] - 2026-10-19

### Fixed

- Offer the broader set of GLPI primary itemtypes supported by Data Injection while filtering by runtime class validity and create rights.
- Include active custom asset definitions, including generated Vehicles, Furniture, and other asset types.
- Use distinct GLPI Inject, Import CSV, and Import models navigation labels.

## [0.1.0-alpha.2] - 2026-10-19

### Fixed

- Avoid calling the uninitialized generic GLPI Asset type label while building dynamic custom-asset mapping choices.

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