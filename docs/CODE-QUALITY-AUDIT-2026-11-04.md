# Repository 2/37 — Media Insight quality audit

Audit date: 2026-11-04. Status: **draft PR; no production release approval**.

- Repository: `Yolol100/scan-duplicate-images`
- Product: Media Insight 4.3.10 (WordPress 6.0+, PHP 7.4+)
- Audited baseline: `main` commit `2e175a9d3b2f55f8171995c1f25eba968badcd54`
- Change branch: `quality/audit-media-insight-20261104`, draft [PR #3](https://github.com/Yolol100/scan-duplicate-images/pull/3).
- Files reviewed: plugin bootstrap, REST endpoints, scanner modules, report finalization, ACF adapters, cache/locking, scheduled workers, CSV output, uninstall, compiled admin assets, PHP CI and docs.
- No customer site, production database, private data or live WordPress credentials were accessed or changed.

## Confirmed bug

**MI-001 — Phantom 'unique post' in scan results (medium).**

The existing `media_insight_count_unique_usage_posts()` appended `absint( $usage['post_id'] )` even when the result was `0`. For cached, imported or otherwise malformed usage rows, missing/invalid references therefore increased the unique-post count and could flag an image as repeatedly used when no second valid post existed.

- **Test-first proof:** `tests/scan-results-regression.php` covers valid, duplicate, empty, invalid and mixed IDs. The original implementation failed on both PHP 7.4 and 8.3: [Actions run #41842831750](https://github.com/Yolol100/scan-duplicate-images/actions/runs/41842831750), `zero or malformed post IDs are not posts: expected 1, got 2`.
- **Owner fix:** the same `includes/scanner/finalize.php` function now excludes zero IDs before deduplicating. No REST contracts, source selectors, hooks or media are changed.
- **Green proof:** [Actions run #41843094376](https://github.com/Yolol100/scan-duplicate-images/actions/runs/41843094376), PHP 7.4 and 8.3 checks both passed.

## Test coverage strengthened

The original GitHub Actions job ran only PHP syntax, which cannot catch semantic bugs. We extended that same repository-native workflow rather than adding an unrelated test system:

1. Execute deterministic standalone result-count regression on PHP 7.4 and 8.3.
2. Activate the plugin on a disposable WordPress/MySQL instance.
3. Prove anonymous and Editor users cannot start a privileged REST scan.
4. Create a disposable image and two published posts using it as the featured image; run the actual REST scan and confirm the correct duplicate report.
5. Prove Editor cannot read an administrator's scan report.
6. Exercise the declared minimum WP 6.0/PHP 7.4 combination and a modern WP 7.0/PHP 8.3 combination. Both must be green on the exact PR-head SHA.

The last runtime matrix result is only authoritative after this audit's final commit. Recheck GitHub Actions before merging.

## Comparable public sources and choices

- [10up/wp-scaffold](https://github.com/10up/wp-scaffold) runs repository-native workflows and maintains formal quality tooling; borrowed the idea of CI-backed behavioral tests, **not** a copied project architecture.
- [WordPress/plugin-check](https://github.com/WordPress/plugin-check) separates static and runtime checks; reproduced that principle with isolated real WordPress tests. Plugin Check itself and PHPCS are **not** yet run for this PR.
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) calls for route permission checks and validated input; kept existing capability callbacks and verified their behavior in CI.
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/) makes transient expiration conditional; the plugin's status/cache behavior is not assumed perfect merely because one scan passes.

## Other findings and open risks (no speculative deletions)

- The 31-file repository tree has no confirmed exact duplicate source blobs in the inspected inventory. Generated React build and translation files must remain because WordPress loads them.
- Existing scan scope is intentionally limited: pages/posts featured images and ACF image/gallery fields on pages. **A missing hit is not proof an image is unused across Elementor, CSS, blocks or custom integrations.**
- Cron-lock expiry, concurrent cancellation and object-cache races require deliberate multiworker stress. Current isolated CI proves a normal end-to-end scan, not lock safety under concurrency.
- Large libraries (10,000+ items), long-running jobs, PHP 64M constraints and ACF repeater/flexible-content nesting remain staging acceptance items.
- CSV spreadsheet formula hardening and exported-file re-import are not equivalent to general spreadsheet security or privacy review.
- Source PHP lint and functional tests are not a vulnerability-free guarantee; run official Plugin Check, WordPress Coding Standards and release ZIP parity when release gate is authorized.
- The plugin version and Stable tag remain 4.3.10. A release containing modified runtime PHP must receive an approved version/changelog and verified installable ZIP before distribution.
- Any extra cleanup of scanners, caching, cron, ACF or UI code is deferred unless a test proves equivalent behavior; do not delete solely from grep results.

## Reversal and ownership

Close PR #3 to discard these changes; protected `main` remains the published source. A green PR is **source/controlled-runtime evidence only**, not staging, site, plugin package or production GO.
