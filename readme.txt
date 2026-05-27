=== Media Insight ===
Contributors: webactueel
Tags: media, featured image, acf, image audit
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.3.10
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find repeated featured images on pages/posts and ACF image/gallery fields on pages.

== Description ==

Media Insight is a focused admin-only audit tool. It is read-only and does not delete, replace, or update site content.

The scan runs in small batches through the WordPress REST API and stores progress in temporary cache data. Results include image previews, usage locations, edit links and CSV export.

The fixed scan scope is:

* Pages: featured image.
* Pages: ACF image and gallery fields.
* Posts: featured image.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP in WordPress admin.

Important: the release ZIP uses the fixed WordPress plugin folder `media-insight/`. Uploading a newer ZIP with this same folder replaces earlier 4.2.4+ releases through WordPress admin. If an old rootless 4.2.3 package was installed into a different folder, deactivate and remove that old copy once, then install this ZIP.
2. Activate the plugin.
3. Open **Media Insight** in wp-admin.
4. Start the scan.

== Frequently Asked Questions ==

= Does this delete repeated images? =

No. The plugin is read-only and only reports repeated usage.

= What does it scan exactly? =

Featured images on pages and posts, plus ACF image/gallery fields on pages.

= How does the scan run? =

The admin app starts a REST scan, processes small batches, stores progress in cache/transients, and queues WP Cron so large scans can continue outside a single request.

== Changelog ==

= 4.3.10 =
* Refined the admin layout with a more compact scan setup, smaller ready status, cleaner empty results state and tighter premium dashboard spacing.
* Kept the nginx-safe CSV export route from the previous hotfix.

= 4.3.6 =
* Added admin-app resume support for interrupted or refreshed scans by remembering the active scan ID locally and refreshing its REST status on reload.
* Added developer and staging validation documentation for large-library tests, debug-log monitoring, low-privilege endpoint checks and release gates.

= 4.3.0 =
* Upgraded the admin experience with a more native Gutenberg-style product screen, scan presets, clearer empty states, thumbnail result cards and direct media actions.
* Added thumbnail and media-edit URLs to the REST report payload for richer admin reporting.
* Set the default scan preset to a safer 500-item standard scan while keeping full scans available.
* Added CSV fields for thumbnail URL, media edit URL and alt text.
* Added a POT template for translation handoff and an Update URI header for private-plugin update safety.
* Removed a duplicate worker return and refreshed runtime metadata.

= 4.2.15 =
* Returned failed/cancelled scan statuses from the process REST route instead of leaving the admin app with a stale running state.
* Refreshed scan status after transient process errors so the UI recovers from expired-state and permission-loss edge cases.
* Converted unrecoverable process/refresh errors into a visible failed state instead of leaving the scan controls stuck in running mode.

= 4.2.12 =
* Added a client-side start guard to prevent fast double-click duplicate scan requests.
* Normalized terminal progress metadata so complete scans cannot show a misleading processed/total mismatch.
* Cleaned empty runtime registry groups after unregistering runtime keys.

= 4.2.11 =
* Hardened the admin app against missing individual wp-components fallbacks.
* Prevented accidental duplicate scan starts while a scan is still running after a transient process error.
* Normalized negative client-side scan limits before REST submission.
* Added noopener to external image result links.

= 4.2.10 =
* Redesigned the admin app toward native WordPress/Gutenberg component styling.
* Added a clearer two-column scan workflow with status, guidance, fixed scope and cleaner result cards.
* Improved responsive admin layout, status badges, progress details and empty-result states.

= 4.2.8 =
* Kept the fixed plugin directory slug `media-insight` for WordPress upload replacement.
* Bumped the package version so WordPress recognizes this ZIP as newer than previous releases.
* Added explicit upgrade guidance for installations created from older rootless release packages.

= 4.2.7 =
* Prevented cancel requests from deleting completed or failed scan reports.
* Made terminal scan statuses immutable against stale worker overwrites.
* Forced completed scans to report 100% progress even when content changes during a scan.
* Cleaned runtime state when scans fail because the owner loses scan permissions.

= 4.2.6 =
* Hardened cancellation so a cancelled scan cannot be overwritten by a stale worker status.
* Strengthened CSV formula-injection protection for values with leading whitespace.
* Aligned REST scan-limit validation with backend capping behavior.

= 4.2.5 =
* Fixed cancellation handling so active workers stop before writing complete or failed statuses.
* Fixed scan progress display for completed scans with zero matching items.
* Tightened scan limit validation so negative REST values cannot be converted into positive limits.

= 4.2.3 =
* Improved cron cleanup during uninstall.
* Restored the previous current user after background scan processing.
* Updated release metadata.

= 4.1.8 =
* Reduced repeated runtime registry writes.
* Registered scheduled scan events explicitly for deactivation cleanup.
* Added full GPLv2 license text.

= 4.1.7 =
* Cleaned the release package and kept only runtime assets.
* Added guarded build metadata.
* Aligned release metadata and license identifiers.

= 4.1.6 =
* Removed manual text-domain loading.
* Replaced broad uninstall cleanup with registered runtime-key cleanup.

= 4.1.5 =
* Tightened export input handling.
* Improved admin script settings output.

= 4.1.4 =
* Standardized Media Insight prefixes.
* Improved REST scan ID validation.

= 4.1.3 =
* Improved CSV output.
* Improved stored usage sanitization.

= 4.1.2 =
* Cleaned release metadata and CSS.

= 4.1.1 =
* Improved admin state handling and export headers.

= 4.1.0 =
* Added REST-driven scan processing and chunked scan results.
* Simplified the WordPress admin screen.
