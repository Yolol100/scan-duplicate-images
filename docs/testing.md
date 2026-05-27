# Media Insight staging and release test plan

Use this checklist before installing the plugin on production.

## 1. Sandbox install

Test first in LocalWP, WordPress Playground or a comparable staging clone.

In `wp-config.php`, enable debug logging:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Run at least one small scan and one full scan, then inspect:

```text
wp-content/debug.log
```

Acceptance target: no PHP Notice, Warning, Deprecated or Fatal messages caused by Media Insight.

## 2. Large-library stress test

Use a staging database with at least 10,000 combined posts/pages/media references. Prefer a clone with ACF image and gallery data if the production site uses ACF.

Test under constrained server settings:

- `max_execution_time`: 30 seconds.
- `WP_MEMORY_LIMIT`: 64M.
- Object cache enabled and disabled if both are possible.

Watch for:

- scan progress keeps moving in small increments;
- no permanently stuck `running` state;
- stale locks recover after the lock TTL;
- full scan eventually reaches `complete` or a visible `failed` status;
- debug log remains clean.

## 3. Crash and interruption tests

Run these manually on staging:

1. Start a scan and refresh the admin page mid-scan.
2. Start a scan and close the browser tab for 60 seconds, then reopen the plugin screen.
3. Start a scan and temporarily interrupt the network connection.
4. Start a scan and click Cancel while it is running.

Expected behavior:

- the UI should resume or show a clear terminal state;
- a lock should not remain stuck forever;
- a cancelled scan should not later become complete because of a stale worker;
- starting a new scan should remain possible after cancellation or failure.

## 4. Permission tests

Log in as a role without `manage_options`, such as Editor.

Test:

- direct admin page access;
- REST start endpoint;
- REST process endpoint;
- REST cancel endpoint;
- admin-post CSV export URL.

Expected behavior: 401/403 response or WordPress capability failure. No scan data or CSV output should be exposed.

## 5. Quality gates

Run these checks where available:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check build/admin-app.js
wp plugin check media-insight --format=json
phpcs --standard=WordPress --extensions=php .
```

Treat Plugin Check and PHPCS as signals. Triage false positives against the actual runtime behavior before changing code.

## 6. Release acceptance

Release only when all of these are true:

- PHP syntax is clean.
- JavaScript syntax is clean.
- Plugin Check has no untriaged critical findings.
- WordPress debug log remains clean after multiple scans.
- Large-library scan completes or fails gracefully.
- Low-privilege role tests cannot access scan data or exports.
- Uninstall removes runtime keys, transient data and scheduled scan registry data.
