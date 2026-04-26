=== Duplicate Image Usage Scanner ===
Contributors: webactueel
Tags: images, media, admin, acf, duplicate images
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find images that are used across multiple WordPress posts, pages, custom post types, featured images, Gutenberg block attributes, srcset/background references, and ACF fields.

== Description ==

Duplicate Image Usage Scanner is an admin-only reporting tool. It helps site owners, editors, and agencies understand where the same image is reused across a WordPress site.

The plugin scans:

* post content image tags
* image srcset values
* inline background image URLs
* featured images
* Gutenberg block attributes
* ACF fields when ACF is active
* public post types selected in the admin screen

Version 3.1.0 includes a progressive AJAX batch scan with a progress bar. The old one-request scan remains available as a no-JavaScript fallback.

The plugin does not delete files and does not modify content. It only reports repeated image usage.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open **Image Usage Scanner** in wp-admin.
4. Select post types and run the batch scan.

== Frequently Asked Questions ==

= Does this delete duplicate media files? =

No. It reports images that are used in more than one scanned item. It does not delete, replace, or modify content.

= Does it support large sites? =

Yes. The primary scan flow processes content in AJAX batches with a progress bar. A fallback one-request scan is still available.

= Does it support ACF? =

Yes, when ACF is active. It recursively scans ACF values for image IDs, image arrays, and image URLs.

= Is repeated image usage always bad? =

No. Logos, badges, icons, and shared banners are often intentionally reused.

== Changelog ==

= 3.1.0 =
* Added duplicate-copy bootstrap protection to avoid fatal collisions when another version is active.
* Added user-scoped transient keys for scan state and export reports.
* Added uninstall cleanup for temporary scan transients.
* Added GPL license file, directory index guards, and production packaging ignore rules.
* Packaged with a stable plugin folder slug for cleaner release installs.

= 3.0.0 =
* Added progressive AJAX batch scanning with progress feedback.
* Added transient-based scan state and report reuse for CSV exports.
* Kept one-request fallback scanning for no-JavaScript environments.
* Added dedicated admin JavaScript for safer long-running scans.
* Improved production-readiness for larger WordPress sites.

= 2.0.0 =
* Added richer scan report with usage sources, post types, edit links, and counts.
* Added public post type selection.
* Added featured image scanning.
* Added Gutenberg block attribute scanning.
* Added ACF image ID, URL, and array support.
* Added srcset and background-image detection.
* Added attachment-ID based grouping when possible.
* Added CSV export.
* Improved admin UX and naming.

= 1.2.0 =
* Added nonce checks, escaping, direct access guards, and corrected admin CSS loading.
