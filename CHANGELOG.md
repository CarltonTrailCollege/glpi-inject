# Changelog

## [0.1.0-beta.10] - 2026-10-22

### Added

- Record and display the last successful CSV upload time for each import model.
- Show existing CSV uploads as timestamp unavailable when their upload time predates timestamp tracking.

## [0.1.0-beta.9] - 2026-10-22

### Added

- Add horizontal scrolling and adjustable, session-persisted column widths to the CSV preview.

## [0.1.0-beta.8] - 2026-10-21

### Fixed

- Correct 32 GLPI coding-standard indentation violations in the importer and mapping provider using the locked PHPCBF tool; no import behavior changes.
- Verify PHP coding standards, Twig and inline JavaScript lint, GLPI-backed static analysis, and both regression suites locally.

## [0.1.0-beta.7] - 2026-10-21

### Changed

- Always try full-name matching when a User username lookup has no match; remove the Advanced checkbox and ignore legacy setting values.
- Preserve username precedence, unique/readable full-name requirements, and the prohibition on automatic user creation.
- Stop adding the obsolete setting on fresh installs while leaving existing database columns intact for safe upgrades.

## [0.1.0-beta.6] - 2026-10-21

### Fixed

- Resolve tree references by full hierarchy path first and use bounded unique lookups for related items instead of leaking GLPI SQL exceptions on duplicate matches.
- Report ambiguous references consistently in preview and import without choosing arbitrary records or creating duplicates.
- Test duplicate Location names, unique/duplicate full paths, and failures outside the preview row limit; document sampled preview validation.

## [0.1.0-beta.5] - 2026-10-21

### Added

- Add a Preview Columns menu with individual checkboxes and Show all/Hide all controls, keeping Line and Validation visible.
- Remember column visibility per model in browser-tab storage, reset for changed headers, and leave mappings and imports unchanged.

## [0.1.0-beta.4] - 2026-10-21

### Added

- Add an opt-in Advanced model setting for exact first-name/last-name User matching when no username matches, shared by preview and import.
- Preserve username precedence, support compound names, require unique readable non-deleted full-name matches, and never create user accounts.
- Migrate existing models with fallback disabled and add resolver regressions for ambiguity, permissions, missing/deleted users, and preview behavior.

## [0.1.0-beta.3] - 2026-10-21

### Added

- Expose Budget and Supplier as financial and administrative reference mappings, storing resolved IDs on Infocom for asset creation and updates.
- Preserve existing-value reuse, optional missing-value creation, read-only preview, entity restrictions, and GLPI permission checks for both financial references.
- Add provider and importer regressions for discovery, creation, reuse, disabled creation, updates, and access restrictions.

## [0.1.0-beta.2] - 2026-10-21

### Added

- Add a session-persisted Result Size selector beside Refresh preview with 1, 10, 50, 100, 500, 1000, and 10000 rows; default to 10 and refresh preview on selection without changing import batches.
- Validate supported sizes and add regressions for all limits, header exclusion, and invalid values.

## [0.1.0-beta.1] - 2026-10-21

### Changed

- Promote the prerelease maturity from alpha to beta while documenting remaining integration-test and import-reliability work.

## [0.1.0-alpha.40] - 2026-10-20

### Fixed

- Avoid a broken-pipe failure when the release workflow verifies archive contents.

## [0.1.0-alpha.39] - 2026-10-20

### Added

- Add GPL-2.0-or-later license text, alpha catalog metadata, plugin artwork, and product screenshots.
- Add reproducible release packaging and tag-triggered GitHub release publishing.

## [0.1.0-alpha.38] - 2026-10-20

### Security

- Enforce entity access on direct model/run endpoints and restore active-entity history filtering.
- Require record-level asset write permissions and entity-scoped, readable related-item matches with input-level dropdown creation checks.
- Serialize same-session batches, reject stale offsets and unknown actions, and prevent unauthorized/rejected requests from changing run failure state.
- Sanitize actual exported CSV cells, including error messages, and check model deletion succeeds before removing its CSV.
- Add security regressions and document review scope, controls, integration-test gaps, and remaining concurrency/transaction risks in SECURITY.md.

## [0.1.0-alpha.37] - 2026-10-20

### Fixed

- Scroll to the top of Preview after successfully saving column mappings instead of leaving the page at the top.

## [0.1.0-alpha.36] - 2026-10-20

### Changed

- Open a model's editor when its radio button is selected, removing the redundant Edit toolbar button.
- Keep Use model as the explicit action to open the import screen; disable selection actions without a selected model and show only Create when no models exist.

## [0.1.0-alpha.35] - 2026-10-20

### Fixed

- Parse uploaded CSVs and verify saved model file references before showing the loaded state directly; preserve the previous CSV until replacement succeeds.
- Report PHP upload size, interrupted upload, temporary-directory, and empty POST failures rather than silently leaving Continue disabled.
- Submit uploads to an explicit model URL and remove the import confirmation from the file-loading form.

## [0.1.0-alpha.34] - 2026-10-20

### Changed

- Rename the CSV header checkbox to First Row is Header in upload and model settings.

## [0.1.0-alpha.33] - 2026-10-20

### Changed

- Load a model on selection and move Continue beside Load this file, linking to column mapping without resubmitting the upload.
- Show CSV load success below the upload buttons and default new model delimiters to comma while preserving saved settings.

### Fixed

- Share Automatic encoding detection between preview and batch imports, detect UTF-16 before CSV parsing, and check the full CSV rather than assuming UTF-8 from its first 64 KB.
- Add encoding regressions for BOM and BOM-less UTF-8/UTF-16, batch cursor continuation, Windows-1252 punctuation, and late non-ASCII values.

## [0.1.0-alpha.32] - 2026-10-20

### Changed

- Default duplicate matching to Name for new models and existing models with an empty matching field.
- Remove the Always create new records option while preserving explicit alternative matching fields and asset creation/update settings.

## [0.1.0-alpha.30] - 2026-10-20

### Changed

- Replace per-row model actions with a shared Create, Edit, Use model, and Delete toolbar above available models.
- Select one model at a time using radio buttons; selection-dependent actions show an error when no model is selected, and deletion still requires confirmation.

## [0.1.0-alpha.29] - 2026-10-20

### Added

- Add an Edit button beside Continue in the import model picker to open the currently selected model's edit page.

## [0.1.0-alpha.28] - 2026-10-20

### Fixed

- Normalize common CSV date/datetime formats, keeping the model's selected ordering as the preference for ambiguous dates and rejecting invalid calendar dates.
- Honor dropdown creation for tree dropdowns such as Locations, reusing existing values and continuing to prohibit entity creation.
- Keep preview read-only and avoid applying asset update permission requirements to related dropdown values that only need lookup or creation.
- Add regressions for reported date values, Location creation/reuse, entity exclusion, preview safety, and independent asset creation/update flags.

## [0.1.0-alpha.27] - 2026-10-20

### Fixed

- Open saved import results with a GET request after the final batch instead of reloading the Launch POST with a consumed CSRF token.

## [0.1.0-alpha.26] - 2026-10-20

### Fixed

- Rotate GLPI's one-use CSRF token between import batches and serialize cancellation after an active batch to prevent subsequent batch requests from being rejected with HTTP 403.

## [0.1.0-alpha.25] - 2026-10-20

### Fixed

- Register the import AJAX endpoint with GLPI's central-access firewall strategy so legitimate import requests are not rejected with HTTP 403.

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