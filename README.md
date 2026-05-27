# Media Insight

Media Insight is an admin-only WordPress media audit plugin for finding repeated image usage in featured images and ACF image/gallery fields.

## Scope

The scanner is intentionally read-only. It does not delete, replace, rewrite or optimize media files. It reports usage only.

Default scan scope:

- Pages: featured image.
- Pages: ACF image and gallery fields.
- Posts: featured image.

## Architecture

The plugin uses a modular procedural WordPress architecture rather than OOP. The runtime is split by concern:

- `media-insight.php` bootstraps constants, hooks, assets and deactivation cleanup.
- `includes/rest.php` owns privileged REST routes for starting, polling, processing and cancelling scans.
- `includes/workers.php` owns browser-driven and WP-Cron background batch execution.
- `includes/cache.php` owns scan status, transients, chunked results, runtime-key registry and lock handling.
- `includes/scanner/` owns scan arguments, cursor state, featured-image scanning, ACF scanning and finalization.
- `includes/admin/export.php` owns CSV export and CSV-injection protection.
- `build/admin-app.js` owns the WordPress admin UI.

## Scan lifecycle

1. The admin app starts a scan through `POST /media-insight/v2/scans`.
2. The REST callback creates cursor-based scan state and stores it in a user-scoped transient.
3. A single-event WP-Cron job is queued as a background fallback.
4. The admin app also calls `POST /scans/{scan_id}/process` in small browser-driven cycles.
5. Each process request acquires an option-based scan lock with a short TTL.
6. Batches are processed with an ID cursor, avoiding a full list of post IDs in memory.
7. Batch-local image results are merged into chunked transient buckets.
8. On completion, chunks are finalized into a report and temporary state/chunks are deleted.
9. The admin app fetches the report and exposes media links and CSV export.

## Lock and timeout behavior

Locks are stored as non-autoloaded options with a TTL. If a request dies because of a server timeout, the next process request can clear the stale lock once the TTL has expired. The admin app also remembers the active scan ID in browser storage so a page refresh can resume polling the same scan instead of losing the visible status.

Terminal statuses are treated as immutable: `complete`, `failed` and `cancelled` should not be overwritten by stale workers.

## Security boundaries

- Admin page access requires `manage_options`.
- REST routes use `permission_callback` and `current_user_can( 'manage_options' )`.
- CSV export uses an admin-post action with nonce and capability checks.
- Scan IDs are sanitized and validated.
- Scan limits are normalized and capped.
- Public REST payload fields are sanitized before returning.
- CSV cells are protected against spreadsheet formula injection.

## Performance notes

The scanner is intended for staging or admin-side audit workflows, not frontend requests. Scripts and styles are conditionally enqueued only on the Media Insight admin screen. Scan results and progress use transients/object cache rather than custom database tables.

For large sites, validate with at least 10,000 combined posts/media items before client rollout. See `docs/testing.md`.
