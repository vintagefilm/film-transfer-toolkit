# End-to-end tests (Playwright, local only)

- `tftt.js`, `tstorage.js`, `adm120.js`: run against the local test site at http://localhost:8088 (all modules on).
- `twizard.js`: fresh-site setup wizard walkthrough at http://localhost:8090. Run `wpfresh-reset.sh` first.
- `helpshots.js`: regenerates the bundled help illustrations (then convert to WebP into assets/img/help/).

PHP consistency tests: `wp eval-file wp-content/plugins/film-transfer-toolkit/tests/registry-tests.php`.
These files are not included in the installable zip.
