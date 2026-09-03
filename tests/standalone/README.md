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
node tests/standalone/scommerce-bulk-status.mjs
node tests/standalone/scommerce-bulk-export.mjs
node tests/standalone/scommerce-review.mjs
```

Package classes, Blade templates and translations are loaded from this checkout,
not the installed consumer copy. These checks do not replace browser QA.
The new-order checks cover draft initialization and rendering, not order creation.
