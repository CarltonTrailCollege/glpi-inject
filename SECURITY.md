# Security Review

Source-level review performed on 2026-10-20 for alpha.38. This is not a penetration test or a guarantee that the plugin is vulnerability-free.

## Scope

- PHP controllers, import services, target discovery, installation hooks, and plugin setup.
- Twig output contexts, browser actions, and Debian deployment scripts.
- Upload validation, ownership, entity access, write permissions, batch requests, and CSV exports.

## Hardening Applied

- Check entity access as well as ownership for direct model, batch, and error-export requests.
- Keep recent history scoped to the active entity; remove the query that overwrote this filter.
- Check GLPI record/input-level create and update permissions before asset writes.
- Scope entity-assigned related-item lookups using GLPI entity restrictions and require read permission on matched references.
- Require input-level creation permission for missing dropdowns; entity creation remains excluded.
- Reject unknown actions, non-POST batches, and stale batch offsets. Retain the PHP session lock during a batch to serialize requests from the same session.
- Only mark a run failed after authorized processing starts; rejected requests must not mutate another run.
- Sanitize the actual exported spreadsheet cells, including error messages, against formula prefixes. Preserve original source-row JSON without modifying its nested values.
- Check model deletion succeeds before deleting its uploaded file.

## Existing Controls Reviewed

- User-controlled SQL values use GLPI database criteria and item APIs rather than concatenated SQL.
- Mapping destinations are resolved against server-generated mapping options, not arbitrary posted class/field names.
- Templates retain escaping; browser error messages use textContent rather than innerHTML.
- Mutating forms send GLPI CSRF tokens; batch responses rotate tokens for subsequent requests.
- Uploads use randomized CSV names, an extension allowlist, a 10 MB limit, and move_uploaded_file. CSV reads and cleanup check filename basenames.
- Debian scripts quote paths, validate the GLPI console, use restrictive deployed permissions, and require confirmation for uninstall.

## Validation And Remaining Work

PHP lint and provider/importer regression suites run locally. Added behavioral tests cover denied asset writes, foreign-entity references, related-item read/create denials, and spreadsheet formula prefixes.

Live GLPI 11.0.8 integration tests are still required for entity inheritance, custom-asset permissions, CSRF rejection, concurrent requests, and deployment. Bash/ShellCheck and a live Debian deployment were not executed in the Windows development environment.

The importer is not fully transactional: an asset may persist if a later Infocom or log write fails. Different authenticated sessions can still race on the same run or create the same missing dropdown; same-session serialization is not a database lock or uniqueness guarantee. Do not launch simultaneous runs against the same model until database-level coordination is implemented.

Model mappings are decoded permissively from stored JSON. Unexpected server exceptions can surface implementation details to authorized users. Model editing/uploading during an active run is not locked or snapshotted. These remain hardening opportunities and should be considered before production use.

CSV uploads and error logs can contain personal information. Restrict plugin access, protect backups and the GLPI document directory, define retention policies, and deploy only supported GLPI/PHP versions over HTTPS.