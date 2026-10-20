# Changelog

## [0.1.0-alpha.24] - 2026-10-20

### Fixed

- Cancel active imports without waiting for a full CSV scan or page reload, and prevent a late batch response from restarting progress.

## [0.1.0-alpha.23] - 2026-10-20

### Fixed

- Capture AJAX permission/session errors as JSON when the endpoint executes, and show HTTP status plus a response excerpt when GLPI returns HTML.

## [0.1.0-alpha.22] - 2026-10-20

### Fixed

- Clear GLPI's compiled/template cache after Debian plugin deployment so updated progress and result templates appear immediately.

## [0.1.0-alpha.21] - 2026-10-20

### Changed

- Render import progress directly in the Launch response instead of redirecting to a separate run URL.

## [0.1.0-alpha.20] - 2026-10-20

### Fixed

- Correct plugin version metadata; alpha.19 had accidentally continued reporting alpha.18.

## [0.1.0-alpha.18] - 2026-10-20

### Changed

- Scroll completed and failed import runs to their results and report zero-created runs as having no imported assets.

## [0.1.0-alpha.17] - 2026-10-20

### Fixed

- Persist first-batch server exceptions in import run logs and display failed runs as failures instead of successful imports.
- Verify the cursor-batched importer creates an asset when Name is the only mapped field.

## [0.1.0-alpha.16] - 2026-10-20

### Changed

- Scroll to the active import progress panel after Launch so progress and any batch error are visible immediately.

## [0.1.0-alpha.15] - 2026-10-20

### Changed

- Start imports without synchronously scanning and staging the complete CSV; stream rows through cancellable progress batches.
- Show indeterminate progress until the CSV end establishes the final total, and report cancelled runs distinctly.

## [0.1.0-alpha.14] - 2026-10-20

### Changed

- Keep the viewport at the preview results after refreshing the preview.

## [0.1.0-alpha.13] - 2026-10-20

### Fixed

- Submit the Automatic detection encoding choice as the value expected by the upload handler.
- Verify automatic decoding with a Windows-1252 CSV regression.

## [0.1.0-alpha.12] - 2026-10-20

### Changed

- Save column mappings in place and show an inline success confirmation beside the save button.

## [0.1.0-alpha.11] - 2026-10-20

### Fixed

- Improve contrast and spacing for the loaded CSV status badges.

## [0.1.0-alpha.10] - 2026-10-20

### Added

- Map financial and administrative information fields on assets and create or update the attached Infocom child record during import.

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