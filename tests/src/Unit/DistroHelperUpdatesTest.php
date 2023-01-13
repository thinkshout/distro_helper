<?php

namespace Drupal\Tests\distro_helper\Unit;

use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\distro_helper\DistroHelperUpdates;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Simple test to ensure that asserts pass.
 *
 * @coversDefaultClass \Drupal\distro_helper\DistroHelperUpdates
 * @group distro_helper
 */
class DistroHelperUpdatesTest extends UnitTestCase {

  /**
   * The Update service.
   *
   * @var \Drupal\distro_helper\DistroHelperUpdates
   */
  protected $distroHelperUpdates;

  /**
   * The original nursery rhyme.
   *
   * @var array
   */
  protected array $ymlOld = [
    'this_little_piggy' => [
      'went to the market' => TRUE,
      'had roast beef' => TRUE,
    ],
    'that_little_piggy' => [
      'stayed home' => TRUE,
      'had none' => TRUE,
    ],
    'the_final_little_piggy' => [
      'went wee wee wee wee' => [
        'distance' => 'all the way home.',
      ],
    ],
  ];

  /**
   * The new nursery rhyme.
   *
   * @var array
   */
  protected array $ymlNew = [
    'this_little_piggy' => [
      'wore his mask to the market' => TRUE,
      'had impossible beef' => TRUE,
    ],
    'that_little_piggy' => [
      'stayed home' => TRUE,
      'got delivery' => TRUE,
      'gave a good tip' => TRUE,
    ],
    'the_final_little_piggy' => [
      'went wee wee wee wee' => [
        'and met their step goal',
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $config_manager = $this->prophesize(ConfigManagerInterface::class);
    $config_storage_sync = $this->prophesize(StorageInterface::class);
    $config_storage = $this->prophesize(CachedStorage::class);
    $logger = $this->prophesize(LoggerChannelFactory::class);
    $this->distroHelperUpdates = new DistroHelperUpdates($config_manager->reveal(), $config_storage_sync->reveal(), $config_storage->reveal(), $logger->reveal());
  }

  /**
   * @covers ::syncActiveConfigFromSavedConfigByKeys
   */
  public function testSyncActiveConfigFromSavedConfigByKeys() {
    // Test: Adding a new value.
    $adding_a_value = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, ['that_little_piggy#gave a good tip']);
    $this->assertEquals($adding_a_value, [
      'this_little_piggy' => [
        'went to the market' => TRUE,
        'had roast beef' => TRUE,
      ],
      'that_little_piggy' => [
        'stayed home' => TRUE,
        'had none' => TRUE,
        'gave a good tip' => TRUE,
      ],
      'the_final_little_piggy' => [
        'went wee wee wee wee' => [
          'distance' => 'all the way home.',
        ],
      ],
    ], 'Added a value to the old array.');

    // Test: Removing a value.
    $removing_a_value = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, ['the_final_little_piggy#went wee wee wee wee#distance']);
    $this->assertEquals($removing_a_value, [
      'this_little_piggy' => [
        'went to the market' => TRUE,
        'had roast beef' => TRUE,
      ],
      'that_little_piggy' => [
        'stayed home' => TRUE,
        'had none' => TRUE,
      ],
      'the_final_little_piggy' => [
        'went wee wee wee wee' => [],
      ],
    ], 'Removed a value from the old array.');

    // Test: Updating a value.
    $replacing_a_value = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, [
      'this_little_piggy#had roast beef',
      'this_little_piggy#had impossible beef',
    ]);
    $this->assertEquals($replacing_a_value, [
      'this_little_piggy' => [
        'went to the market' => TRUE,
        'had impossible beef' => TRUE,
      ],
      'that_little_piggy' => [
        'stayed home' => TRUE,
        'had none' => TRUE,
      ],
      'the_final_little_piggy' => [
        'went wee wee wee wee' => [
          'distance' => 'all the way home.',
        ],
      ],
    ], 'Replaced a value in the old array.');

    // Test: Trying to update a path that does not exist.
    $bad_update = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, ['the_final_little_piggy#went weeeeee all the way home#distance']);
    $this->assertEquals($bad_update, $this->ymlOld, 'Tried to update a non-existent path, old array unchanged.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unset($this->distroHelperUpdates);
  }

}
