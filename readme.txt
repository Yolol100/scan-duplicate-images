=== Featured & ACF Image Usage Scanner ===
Contributors: webactueel
Tags: media, featured image, acf, image audit
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 3.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find repeated featured images on pages/posts and ACF image/gallery fields on pages.

== Description ==

Featured & ACF Image Usage Scanner is a focused admin-only audit tool.

It scans only:

* Pages: featured image.
* Pages: ACF image and gallery fields.
* Posts: featured image.

The plugin is read-only. It does not delete, replace, or update site content.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP in WordPress admin.
2. Activate the plugin.
3. Open **Featured & ACF Images** in wp-admin.
4. Start the focused scan.

== Frequently Asked Questions ==

= Does this delete repeated images? =

No. The plugin is read-only and only reports repeated usage.

= What does it scan exactly? =

Featured images on pages and posts, plus ACF image/gallery fields on pages.

== Changelog ==

= 3.4.0 =
* Made the admin layout full width for better use of WordPress admin space.
* Reduced the visual weight of the top hero section with a more compact heading and spacing.
* Cleaned up duplicate docblocks in admin files.

= 3.3.9 =
* Split oversized admin and scanner files into focused WordPress-friendly modules for clearer maintenance.
* Kept the public plugin hooks and scan behavior unchanged.

= 3.3.8 =
* Centralized admin POST normalization before scan and export sanitization.
* Added the missing scoped form CSS selector to keep admin layout intent explicit.

= 3.3.7 =
* Removed unused AJAX localization text and unused report payload fields.
* Kept the scan scope unchanged while reducing stored report noise.

= 3.3.6 =
* Removed duplicated ACF context labels for nested group/repeater/clone structures.
* Avoided storing an unused AJAX scan-state transient when no pages or posts match the scan.

= 3.3.5 =
* Preserved full parent context when scanning nested ACF group/repeater/clone fields.
* Reduced duplicate suppression risk when the same nested subfield label appears in multiple parent ACF structures.

= 3.3.4 =
* Removed unused backwards-compatibility wrappers and obsolete CSS left from earlier broad scanner versions.
* Removed an unused transient/media constant and simplified internal ACF fallback calls.
* Confirmed the release ZIP contains only runtime plugin files.

= 3.3.3 =
* Improved ACF flexible content/layout scanning for nested image and gallery fields.
* Prevented ACF usage counters from increasing when a candidate image was not actually added to the report.
* Tightened report wording to explicitly mention ACF image and gallery fields.

= 3.3.2 =
* Fixed ACF gallery fields that return attachment IDs.
* Restricted the ACF scan to image and gallery fields only.
* Excluded SVG/icon-like files from the image matcher to reduce icon noise.

= 3.3.0 =
* Refocused the plugin to only scan featured images and ACF image fields.
* Simplified the admin UI and report output.
* Removed broad scan flows from the active scanner.

