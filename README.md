# GLPI Inject

GLPI Inject is a GLPI 11 plugin for reusable CSV import models. It is intended to preserve the model, upload, field-mapping, validation, import, and result-report workflow of the Data Injection plugin while supporting GLPI custom asset types.

**Current release:** `0.1.0-beta.4` (beta; some integration coverage and import-reliability hardening remain planned).

See [SECURITY.md](SECURITY.md) for the source-review scope, security controls, validation, and remaining production hardening work.

The plugin is licensed under [GPL-2.0-or-later](LICENSE). The GLPI catalog metadata is in [glpiinject.xml](glpiinject.xml).

## Compatibility

- GLPI 11.0.8 and later in the GLPI 11 series
- PHP version required by the installed GLPI release

## Design

The importer discovers active custom asset definitions through GLPI's `AssetDefinitionManager`, resolves each definition to its runtime asset itemtype, and obtains fields through GLPI's search-option APIs. Imports will create and update items through the item's `CommonDBTM` methods so GLPI permissions, validation, history, and lifecycle hooks remain in effect.

This is an in-process GLPI plugin. It does not require an external REST API token or bypass GLPI's item APIs with direct database writes. “Modern API” here refers to GLPI's current plugin and itemtype APIs; the standalone REST API is a separate integration option and should not be used for internal plugin writes.

## Import workflow

1. Create a reusable model and select an asset type, including a custom asset.
2. Upload a CSV and configure its delimiter, encoding, and header handling.
3. Map CSV columns to fields exposed by the selected GLPI itemtype.
4. Preview and validate rows, including required fields and duplicate matching.
5. Run the import and review per-row success and error results.

Preview Result Size supports 1, 10, 50, 100, 500, 1000, or 10000 rows (default 10), remembered for the current session. Changing the selection refreshes preview only; imports still process the full CSV. Large previews may take longer to validate and render.

Financial and administrative mappings include Budget and Supplier by name. Their IDs are saved on the asset's Infocom record, independently of any asset-level supplier mapping. Existing accessible values are reused; missing Budget and Supplier records may be created when Allow creation of dropdowns is enabled and GLPI permissions allow it. Preview never creates these records.

For User mappings against the username field, Advanced options includes Match users by full name when no username matches (off by default). The importer tries the username first, then an exact first-name plus last-name match, including compound names. Repeated whitespace in the CSV full name is normalized. Only a unique readable, non-deleted user is accepted; ambiguous matches fail and require a username. The same resolution is used by preview and import, and missing user accounts are never created.

Selecting a model loads its upload settings automatically. After loading a CSV, a success message appears below the upload buttons and Continue takes you to column mapping. New models default to comma-separated CSV. Automatic encoding detection supports UTF-8, UTF-16 little/big endian, and Windows-1252 fallback, using the same detection for preview and batch imports.

Duplicate matching defaults to Name, including for older models with no saved matching field. Another standard matching field can be selected; the always-create mode is no longer available. Creation and update remain controlled by the model's respective behavior options.

The current development slice follows the original plugin's model-picker-first import flow and separate model-management screen. It implements reusable model create/edit/delete with General/CSV/Advanced tabs, CSV upload (10 MB limit), encoding/delimiter/header selection, a four-part destination type/field/link-field/mandatory mapper, a configurable preview, column-count and value validation, linked dropdown lookup, optional safe dropdown creation, date/number/boolean normalization, duplicate matching by a standard field, create/update imports, confirmation prompts, per-row results, failed-row CSV export, persistent run history, and ten-row AJAX batch progress with staged input rows. Standard GLPI assets and active custom asset definitions are discovered dynamically. Custom fields are passed to GLPI using their `custom_<system_name>` names, and GLPI item methods perform persistence.

The upstream Data Injection plugin still uses a fixed injectable-type registry, so its itemtype-specific injection code cannot handle GLPI custom asset definitions directly. Its reusable UI/workflow conventions are being used as the reference while the dynamic target layer uses GLPI runtime asset definitions and item APIs.

Remaining parity work includes broader GLPI datatype and dropdown coverage, model sharing/profile rights, and complex relation-object imports. Linked mappings resolve existing records by the displayed field, with full-path fallback for tree dropdowns. When enabled, missing dropdown values (including Locations) are created through GLPI with existing-value checks; entities are never created. Preview validates without creating dropdowns. Asset creation and update options apply independently of dropdown creation.

Date and datetime fields normalize common year-first, month-first, and day-first values, with optional times, into GLPI storage format. The selected date format takes precedence for ambiguous dates; choose month-day-year for CSV values such as `5/8/2026` meaning May 8.

## Release packaging

Build the installable archive from the repository root with PHP CLI:

```bash
php scripts/package-release.php
```

The script writes `dist/glpiinject-<version>.tar.gz`. The archive contains a top-level `glpiinject/` directory and only plugin runtime files, metadata, and documentation; it excludes tests, deployment scripts, and repository history. Verify the archive structure with:

```bash
tar -tzf dist/glpiinject-<version>.tar.gz
```

Pushing a `v<version>` tag that matches `PLUGIN_GLPIINJECT_VERSION` in `setup.php` runs the release workflow, validates the package root, and attaches the archive to a GitHub Release. The existing alpha.38 tag predates this workflow; alpha.40 established the corrected workflow. Update the version, catalog version entry, and download URL together for each release.

The beta catalog manifest, artwork, screenshots, and release archive are publicly available. A catalog listing must be accepted before Marketplace review can be requested; these are separate approvals tracked in [issue #6](https://github.com/CarltonTrailCollege/glpi-inject/issues/6). Real-GLPI integration coverage and cross-session import reliability are planned as post-release work; see [SECURITY.md](SECURITY.md) for the known limitations and reporting process.

## Quality checks

Install the pinned development tools and run the same checks as CI:

```bash
composer install
npm ci
composer check
```

The checks run GLPI's PHP coding standard on production PHP files, TwigCS on templates, ESLint on inline template JavaScript, PHPStan level 1 against a sibling GLPI 11 checkout at `../glpi-core`, and the regression scripts with PHP assertions enabled. GLPI's documented `glpi-project/coding-standard` package is archived upstream but remains the official published ruleset; it is pinned in the lockfile so checks are reproducible. CI checks out GLPI's `11.0/bugfixes` branch automatically. To run PHPStan locally, clone that branch beside this repository:

```bash
git clone --depth 1 --branch 11.0/bugfixes https://github.com/glpi-project/glpi.git ../glpi-core
```

## Installation for development

GLPI Inject is intended to be installed on the Debian host where GLPI is running. Clone this repository on that host, then run the installer with the GLPI root and web-server account:

```bash
git clone https://github.com/CarltonTrailCollege/glpi-inject.git
cd glpi-inject
sudo bash scripts/install-debian.sh --glpi-dir /var/www/glpi --web-user www-data
```

The installer copies the plugin into `GLPI/plugins/glpiinject/`, sets it readable by the GLPI web-server group, then runs `plugin:install --force` and `plugin:activate` using GLPI's console as `www-data`. `--force` also reruns the plugin's install/migration hook when deploying updates. Replace `/var/www/glpi` if your GLPI root is elsewhere. The GLPI console and PHP CLI must be installed on the Debian host.

To cleanly uninstall it:

```bash
sudo bash scripts/uninstall-debian.sh --glpi-dir /var/www/glpi --web-user www-data
```

The uninstaller asks you to type `yes`, invokes `plugin:uninstall` so the plugin hook drops its model/run tables and removes its uploaded CSV directory, and only then removes `plugins/glpiinject`. For automation, append `--yes` to skip the confirmation. This permanently removes saved import models, run history/logs, and uploaded CSV files.

You can also install and activate through **Setup > Plugins** after copying the repository contents into `GLPI/plugins/glpiinject/`. The scripts are intended for a Debian GLPI host; do not run them from the Windows development checkout unless that path is actually mounted on the Debian server.

Release tags use Semantic Versioning. Prereleases use SemVer identifiers such as `0.1.0-beta.4`; the matching GitHub tag is `v0.1.0-beta.4`.