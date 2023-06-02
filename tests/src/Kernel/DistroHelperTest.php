<?php

namespace Drupal\Tests\ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;

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
    'distro_helper_tests',
  ];

  /**
   * Tests the update helper for UpdateExceptions.
   */
  public function testErrors() {
    // Failure 1: config does not exist in active config. TODO.
    // Failure 2: config does not exist in the module.
    \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.test_missing', [
      'some_stuff',
    ], 'distro_helper_test');

    // Failure 3: Invalid yml file.
    \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.empty', [
      'some_stuff',
    ], 'distro_helper_test');

    // Failure 4: key does not exist in config.
    \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.test', [
      'some_more_stuff#stuff0',
    ], 'distro_helper_test');
  }

}
