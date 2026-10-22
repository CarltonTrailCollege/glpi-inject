# Security

GLPI Inject handles CSV files that may contain personal or inventory data and performs writes to GLPI assets. Restrict access to trusted administrators and treat uploaded files, import history, and exported error reports as sensitive.

## Review status

The last recorded security-focused source review was performed on **2026-10-20** against alpha.38. This document update does not constitute a new security audit, penetration test, or certification. The project is still beta, and changes made after that review have not received a full security reassessment here.

Compatibility claims are limited to GLPI 11.0.8 and later in the GLPI 11 series. See the [README](README.md) for installation and usage guidance.

## Controls in the implementation

- Plugin pages require GLPI configuration rights. Model, import-run, batch, and error-export access is checked against the logged-in user and accessible entity.
- Import destinations are selected from server-generated options for the active asset type. Saved mappings are normalized against those allowed options before use; posted values do not directly select arbitrary PHP classes or fields.
- Asset and related-record operations use GLPI item APIs and permission checks rather than direct SQL writes. Related items are entity-scoped where applicable and must be readable. Optional dropdown creation is permission-checked; Entities are excluded.
- CSV uploads are limited to 10 MB, require a `.csv` extension, use randomized server-side names, and are moved with PHP's `move_uploaded_file`. Stored filenames are checked before reads and cleanup.
- Mutating forms include GLPI CSRF tokens. Batch endpoints require POST, validate the run owner/entity and active model, reject stale offsets and unknown actions, and return refreshed CSRF tokens.
- Failed-row exports sanitize the actual CSV cells against spreadsheet formula prefixes, including error messages.
- Templates use escaped output, and browser-rendered error messages use text content rather than HTML insertion.

These are implementation controls, not a claim that every GLPI deployment, permission configuration, or request path has been independently tested.

## Known limitations

### Import atomicity and concurrency

An import is not transactional. If a later write fails, earlier asset, related-item, Infocom, or log writes may already have persisted. Requests in one PHP session are serialized by the session lock, but there is no database-level lock or uniqueness guarantee across separate sessions. Concurrent imports may race on batches or missing dropdown creation. Avoid running the same model simultaneously.

### Active model changes

Each batch reloads the model and reads from its stored CSV. The run does not hold an immutable snapshot of the model settings or uploaded file. Do not edit, replace, or delete a model's CSV while an import is running; changes can affect subsequent batches.

### Integration coverage

The project has provider and importer regression suites, but these use test doubles for much of GLPI behavior. End-to-end tests against real GLPI installations and databases are still needed for entity inheritance, custom-asset permissions, CSRF enforcement, migrations, deployment, concurrent sessions, and failure recovery. Passing the local regression suite is not a substitute for those tests.

These limitations are tracked in [issue #3](https://github.com/CarltonTrailCollege/glpi-inject/issues/3) (real-GLPI integration tests) and [issue #5](https://github.com/CarltonTrailCollege/glpi-inject/issues/5) (concurrency and partial-write reliability).

## Operator guidance

- Grant plugin access only to users who should configure imports and write the selected asset types.
- Keep GLPI and PHP supported and patched; use HTTPS and secure the host and GLPI document directory.
- Restrict access to backups, uploaded CSV files, run history, logs, and error exports. These can contain personal information and inventory details.
- Define retention and deletion procedures for source CSVs and import records. Uninstalling the plugin removes its model, run, and log tables and its uploaded CSV directory.
- Verify mappings and preview rows before importing. Preview validates only the selected sample and reflects database state at preview time.
- Avoid concurrent imports and edits to a model or its source file while a run is active.

## Reporting a vulnerability

Report suspected vulnerabilities through [GitHub private vulnerability reporting](https://github.com/CarltonTrailCollege/glpi-inject/security/advisories/new). Do not post exploit details, personal data, credentials, or sensitive inventory information in a public issue.

Use [GitHub Issues](https://github.com/CarltonTrailCollege/glpi-inject/issues) for ordinary bugs and feature requests. The Carlton Trail College GLPI Inject maintainer group owns triage, fixes, advisories, and release coordination; repository owners are responsible for keeping that ownership staffed. No response-time SLA is promised.
