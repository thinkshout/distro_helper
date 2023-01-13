<?php

namespace Drupal\Tests\distro_helper\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\distro_helper\DistroHelperUpdates;

/**
 * Simple test to ensure that asserts pass.
 *
 * @group phpunit_example
 */
class DistroHelperUpdatesTest extends UnitTestCase {

  protected $distro_helper_update;

  /**
   * Before a test method is run, setUp() is invoked.
   * Create new unit object.
   */
  public function setUp() {
    $this->distro_helper_update = new DistroHelperUpdates();
  }

  public function testSyncActiveConfigFromSavedConfigByKeys() {
    // Do it.
  }

  /**
   * Once test method has finished running, whether it succeeded or failed, tearDown() will be invoked.
   * Unset the $unit object.
   */
  public function tearDown() {
    unset($this->distro_helper_update);
  }

}
