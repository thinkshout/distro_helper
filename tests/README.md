## How to run these tests.

### Setup
Make sure you have a database user "drupal" with the password "drupal", or edit the file at `drupal/phpunit.xml`
and set `SIMPLETEST_DB` to a username and password that will work on your local machine.

### Running tests
Run tests from within the `drupal` directory:

```
cd drupal
composer install
./vendor/bin/phpunit --group=distro_helper --group=distro_helper
```

You can troubleshoot individual tests by type in this way:
```
./vendor/bin/phpunit web/modules/contrib/distro_helper/tests/src/Kernel
./vendor/bin/phpunit web/modules/contrib/distro_helper/tests/src/Unit
```
