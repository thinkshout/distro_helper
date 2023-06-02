<?php

namespace Drupal\Tests\ckeditor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests for the 'CKEditor' text editor plugin.
 *
 * @group ckeditor
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

  function testErrors() {
    // Failure 1: config does not exist in active config.
//    \Drupal::service('distro_helper.updates')->updateConfig('distro_helper_test.test', [
//      'some_stuff',
//    ], 'distro_helper_test');

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
