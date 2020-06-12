Amazon Pay Tests
================

## Unit Tests

### Getting started

1. Make sure you have [`PHPUnit`](http://phpunit.de/) installed
2. Install WordPress and WP Unit Test library.

   ```
   $ tests/bin/install-wp-tests.sh wp_test root root
   ```

   **Notes**:
   - `wp_test` is a database name. It will be created if it doesn't exist and all data will be removed during the testing.
   - If running the default docker set up, use `127.0.0.1:5678` as the database host when running the `install-wp-tests.sh` script.

### Running the tests

Change to the plugin root directory and type:

```
$ phpunit
```

Example output:

```
Installing...
Running as single site... To run multisite, use -c tests/phpunit/multisite.xml
Not running ms-files tests. To execute these, use --group ms-files.
Not running external-http tests. To execute these, use --group external-http.
PHPUnit 5.7.23 by Sebastian Bergmann and contributors.

Runtime:       PHP 7.1.6-2~ubuntu14.04.1+deb.sury.org+1
Configuration: /srv/www/wordpress-default/wp-content/plugins/woocommerce-gateway-amazon-payments-advanced/phpunit.xml

...............................................                   47 / 47 (100%)

Time: 4.68 seconds, Memory: 12.00MB

OK (47 tests, 119 assertions)
```

## End-to-end Tests

### Getting started

1. Make sure you have [`node`](https://docs.npmjs.com/getting-started/installing-node),
   `npm`
2. Execute `npm install` to install all dependencies.
3. Create your e2e local config, for example:

   ```
   $ touch tests/e2e/config/local-development.json
   ```

4. Update the config that matches your test site.

### Running the tests

Change to the plugin root directory and type:

```
$ npm test
```

### Adding new test

* Name your test file with `tests/e2e/test-*.js` format.
* See existing test file to see how it should be written.
