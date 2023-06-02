<?php

namespace Drupal\Tests\ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Utility\UpdateException;

/**
 * Tests for the 'Distro Helper' plugin.
 *
 * @group distro_helper
 */
class DistroHelperTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'config',
    'distro_helper',
    'distro_helper_test',
  ];

  /**
   * Tests the update helper for UpdateExceptions.
   */
  public function testErrors() {
    try {
      // Failure 1: config does not exist in active config.
      \Drupal::service('distro_helper.updates')
        ->updateConfig('distro_helper_test.test', [
          'some_stuff',
        ], 'distro_helper_test');
    }
    catch (UpdateException $exception) {
      self::assertEquals($exception->getMessage(), 'No active config found for distro_helper_test.test while running updateConfig(). Use installConfig() to import config that does not already exist in your database.');
    }

    try {
      // Failure 2: config does not exist in the module.
      \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.test_missing', [
        'some_stuff',
      ], 'distro_helper_test', 'install');
    }
    catch (UpdateException $exception) {
      self::assertEquals($exception->getMessage(), 'Config file not found at modules/contrib/distro_helper/tests/modules/distro_helper_test/config/install/distro_helper_test.test_missing.yml');
    }

    try {
      // Failure 3: Invalid yml file.
      \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.invalid', [
        'some_stuff',
      ], 'distro_helper_test', 'mock_install');
    }
    catch (UpdateException $exception) {
      self::assertEquals($exception->getMessage(), 'Invalid YAML file modules/contrib/distro_helper/tests/modules/distro_helper_test/config/mock_install/distro_helper_test.invalid.yml');
    }

    // Failure 4: key does not exist in config.
    $this->installConfig(['distro_helper_test']);
    try {
      \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.test', [
        'some_stuffing',
      ], 'distro_helper_test');
    }
    catch (UpdateException $exception) {
      self::assertEquals($exception->getMessage(), 'Could not find a value nested at some_stuffing');
    }
  }

}
