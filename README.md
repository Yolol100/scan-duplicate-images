# Media Insight — WordPress Image Usage Audit

> **Portfolio project · WordPress/PHP · REST API · ACF · background processing · read-only audit**

Media Insight is an admin-only WordPress plugin for finding repeated image usage in featured images and ACF image/gallery fields. It is intentionally read-only: the plugin reports where media is used without deleting, replacing or rewriting content.

**Built by:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio](https://andrewbaeten.nl)

## What problem it solves

On larger WordPress sites, the same image can appear across featured images, posts and nested ACF fields. Manually tracing those relationships is slow and risky. Media Insight turns that investigation into a bounded background scan with resumable progress, locking and exportable results.

## Portfolio snapshot

| Area | What it demonstrates |
| --- | --- |
| WordPress | Admin tooling and media/content inspection |
| ACF | Image and gallery field scanning |
| REST API | Privileged start, process, poll and cancel endpoints |
| Background processing | Browser-driven batches with WP-Cron fallback |
| Reliability | Cursor state, stale-lock recovery and immutable terminal states |
| Security | Capability checks, nonces, validated scan IDs and CSV-injection protection |
| Performance | Chunked transient results and bounded batch processing |

## Scope

Default scan scope:

- Pages: featured image.
- Pages: ACF image and gallery fields.
- Posts: featured image.

The scanner does not delete, replace, rewrite or optimize media files.

## Architecture

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
4. The admin app also processes small browser-driven batches.
5. Each process request acquires an option-based scan lock with a short TTL.
6. Batches use an ID cursor instead of loading every post ID into memory.
7. Results are merged into chunked transient buckets.
8. On completion, temporary scan state is finalized and cleaned up.
9. The admin app exposes the report and CSV export.

## Lock and timeout behaviour

Locks are stored as non-autoloaded options with a TTL. If a request dies because of a server timeout, a later request can clear the stale lock after expiry. The admin app remembers the active scan ID so a page refresh can resume polling instead of losing the visible scan state.

Terminal statuses are treated as immutable: `complete`, `failed` and `cancelled` are not overwritten by stale workers.

## Security boundaries

- Admin page access requires `manage_options`.
- REST routes use explicit permission callbacks and capability checks.
- CSV export uses an admin-post action with nonce and capability checks.
- Scan IDs are sanitized and validated.
- Scan limits are normalized and capped.
- Public REST payload fields are sanitized before returning.
- CSV cells are protected against spreadsheet formula injection.

## Performance notes

The scanner is designed for staging or admin-side audit workflows, not frontend requests. Scripts and styles load only on the Media Insight admin screen. Progress and results use transients/object cache instead of custom database tables.

For large sites, validate with at least 10,000 combined posts/media items before client rollout. See `docs/testing.md`.

## About the developer

I am **Andrew Baeten**, a WordPress Developer & Web Designer with 10+ years of experience across **70+ WordPress projects**. My work combines WordPress, WooCommerce, Elementor, ACF, UX, performance, technical SEO and quality-focused delivery.

[Portfolio](https://andrewbaeten.nl) · [LinkedIn](https://www.linkedin.com/in/andrew-baeten-305a1478/) · [Email](mailto:info@andrewbaeten.nl)
