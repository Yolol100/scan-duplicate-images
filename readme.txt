=== Image Usage & Duplicate Media Scanner ===
Contributors: webactueel
Tags: images, media, admin, acf, elementor, woocommerce, duplicate images
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit repeated image usage, possible duplicate media files, Elementor image references, WooCommerce galleries, Gutenberg block attributes, featured images, srcset/background references, and ACF fields.

== Description ==

Image Usage & Duplicate Media Scanner is an admin-only reporting tool. It helps site owners, editors, and agencies understand where images are reused, which media files may be duplicate uploads, and which media items were not found in the selected scan scope.

The plugin scans:

* post content image tags
* image srcset values
* inline background image URLs
* featured images
* Gutenberg block attributes
* Elementor JSON post meta
* WooCommerce product gallery images
* ACF fields when ACF is active
* public post types selected in the admin screen
* media-library image attachments for possible duplicate files

The plugin does not delete files and does not modify content. It only reports findings for manual review.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open **Image Usage Scanner** in wp-admin.
4. Select post types and sources, then run the audit.

== Frequently Asked Questions ==

= Does this delete duplicate media files? =

No. It reports repeated image usage and possible duplicate media files. It does not delete, replace, or modify content.

= Is repeated image usage always bad? =

No. Logos, badges, icons, and shared banners are often intentionally reused.

= What is the strongest cleanup signal? =

A possible duplicate media group with an exact file hash match is the strongest signal. Still review manually and make a backup before cleanup.

= Are images marked as not found safe to delete? =

No. They were not found in the selected scan scope. They may still be used by theme options, CSS, widgets, menus, forms, builders, or external templates.

== Changelog ==

= 3.2.1 =
* Confirmed fallback scan trigger markup and tightened AJAX error handling for non-JSON server failures.
* Prevented external image URLs from being loaded as previews inside wp-admin; non-local URL results now use a placeholder and remain linked from the result title.
* Updated tested-up-to metadata to WordPress 7.0.

= 3.2.0 =
* Rebranded the admin screen to Image Usage & Duplicate Media Scanner.
* Redesigned the admin UI with a Gutenberg/@wordpress-components inspired card, badge, panel, and progress style.
* Added media-library duplicate detection using file size, dimensions, and file hash when readable.
* Added a manual-review list for media not found in the selected scan scope.
* Added Elementor JSON meta scanning.
* Added WooCommerce product gallery scanning.
* Improved ACF scanning with field-type awareness to reduce false positives.
* Fixed progressbar ARIA updates and added an aria-live progress message.
* Added a client-ready action CSV with report type, source, confidence, file size, dimensions, and edit links.
* Fixed duplicate CSV header naming from the previous export format.

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
