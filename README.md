# GLPI Inject

GLPI Inject is a GLPI 11 plugin for reusable CSV import models. It is intended to preserve the model, upload, field-mapping, validation, import, and result-report workflow of the Data Injection plugin while supporting GLPI custom asset types.

**Current release:** `0.1.0-alpha.34` (alpha; expect incomplete parity and breaking changes).

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

Selecting a model loads its upload settings automatically. After loading a CSV, a success message appears below the upload buttons and Continue takes you to column mapping. New models default to comma-separated CSV. Automatic encoding detection supports UTF-8, UTF-16 little/big endian, and Windows-1252 fallback, using the same detection for preview and batch imports.

Duplicate matching defaults to Name, including for older models with no saved matching field. Another standard matching field can be selected; the always-create mode is no longer available. Creation and update remain controlled by the model's respective behavior options.

The current development slice follows the original plugin's model-picker-first import flow and separate model-management screen. It implements reusable model create/edit/delete with General/CSV/Advanced tabs, CSV upload (10 MB limit), encoding/delimiter/header selection, a four-part destination type/field/link-field/mandatory mapper, a 12-row preview, column-count and value validation, linked dropdown lookup, optional safe dropdown creation, date/number/boolean normalization, duplicate matching by a standard field, create/update imports, confirmation prompts, per-row results, failed-row CSV export, persistent run history, and ten-row AJAX batch progress with staged input rows. Standard GLPI assets and active custom asset definitions are discovered dynamically. Custom fields are passed to GLPI using their `custom_<system_name>` names, and GLPI item methods perform persistence.

The upstream Data Injection plugin still uses a fixed injectable-type registry, so its itemtype-specific injection code cannot handle GLPI custom asset definitions directly. Its reusable UI/workflow conventions are being used as the reference while the dynamic target layer uses GLPI runtime asset definitions and item APIs.

Remaining parity work includes broader GLPI datatype and dropdown coverage, model sharing/profile rights, and complex relation-object imports. Linked mappings resolve existing records by the displayed field, with full-path fallback for tree dropdowns. When enabled, missing dropdown values (including Locations) are created through GLPI with existing-value checks; entities are never created. Preview validates without creating dropdowns. Asset creation and update options apply independently of dropdown creation.

Date and datetime fields normalize common year-first, month-first, and day-first values, with optional times, into GLPI storage format. The selected date format takes precedence for ambiguous dates; choose month-day-year for CSV values such as `5/8/2026` meaning May 8.

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

Release tags use Semantic Versioning. Prereleases use SemVer identifiers such as `0.1.0-alpha.34`; the matching GitHub tag is `v0.1.0-alpha.34`.