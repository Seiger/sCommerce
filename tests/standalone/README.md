# Isolated sCommerce regression checks

Run from the package root with PHP 8.4, SQLite and the dependencies of a compatible
Evolution CMS installation. The CMS is not bootstrapped; database checks use in-memory SQLite.

```sh
export SCOMMERCE_TEST_AUTOLOAD=/path/to/site/core/vendor/autoload.php
php tests/standalone/scommerce-bulk-status.php
php tests/standalone/scommerce-bulk-menu.php
php tests/standalone/scommerce-bulk-export.php
php tests/standalone/scommerce-new-order.php
php tests/standalone/scommerce-review.php
php tests/standalone/scommerce-review-migration.php
php tests/standalone/scommerce-repeat.php
php tests/standalone/scommerce-order-events.php
php tests/standalone/scommerce-credentials-column.php
php tests/standalone/scommerce-payment-ledger.php
php tests/standalone/scommerce-webhook-dispatch.php
php tests/standalone/scommerce-payment-actions.php
php tests/standalone/scommerce-payment-schedule.php
php tests/standalone/scommerce-offline-settlement.php
php tests/standalone/scommerce-line-identity.php
php tests/standalone/scommerce-fulfilment.php
php tests/standalone/scommerce-checkout-context.php
php tests/standalone/scommerce-order-taxes.php
php tests/standalone/scommerce-vat-display.php
php tests/standalone/scommerce-container-deposit.php
node tests/standalone/scommerce-bulk-status.mjs
node tests/standalone/scommerce-bulk-export.mjs
node tests/standalone/scommerce-review.mjs
node tests/standalone/scommerce-repeat.mjs
node tests/standalone/scommerce-payment-actions.mjs
```

`scommerce-payment-ledger.php` also runs against a disposable MySQL/MariaDB or PostgreSQL database when
`SCOMMERCE_TEST_DB_DRIVER` (`mysql` or `pgsql`) and `SCOMMERCE_TEST_DB_HOST`, `_PORT`, `_DATABASE`,
`_USERNAME`, `_PASSWORD` are set; it drops and recreates its tables there.

Package classes, Blade templates and translations are loaded from this checkout,
not the installed consumer copy. These checks do not replace browser QA.
The new-order checks cover draft initialization and rendering, not order creation.
