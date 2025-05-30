<?php

namespace Drupal\Tests\distro_helper\Unit;

use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\distro_helper\DistroHelperUpdates;
use Drupal\Tests\UnitTestCase;

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
    $extension_path_resolver = $this->prophesize(ExtensionPathResolver::class);
    $this->distroHelperUpdates = new DistroHelperUpdates($config_manager->reveal(), $config_storage_sync->reveal(), $config_storage->reveal(), $extension_path_resolver->reveal());
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
    // Proves that bad requests get logged.
    $this->assertEquals($this->distroHelperUpdates->getLoggerErrors()[0],
      new TranslatableMarkup('Could not find a value nested at @config for either the new or old config. Is your path correct?', ['@config' => 'the_final_little_piggy.went weeeeee all the way home.distance'])
    );

    // Test: Trying to update a path that does not exist AND real paths.
    $partially_bad_update = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, [
      'the_final_little_piggy#went weeeeee all the way home#distance',
      'this_little_piggy#had roast beef',
      'this_little_piggy#had impossible beef',
    ]);
    $this->assertEquals($partially_bad_update, [
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
    ], 'Part of a bad update succeeded.');

    // Proves that bad requests get logged.
    $this->assertEquals($this->distroHelperUpdates->getLoggerErrors()[1],
       new TranslatableMarkup('Could not find a value nested at @config for either the new or old config. Is your path correct?', ['@config' => 'the_final_little_piggy.went weeeeee all the way home.distance'])
    );

    // Test: Trying to update a path that doesn't exist either place, but only
    // at the deepest, final level. Equivalent of trying to unset a thing that's
    // already unset, which is fine.
    $malformed_but_harmless_update = $this->distroHelperUpdates->syncActiveConfigFromSavedConfigByKeys($this->ymlOld, $this->ymlNew, [
      'the_final_little_piggy#went wee wee wee wee#but where',
      'this_little_piggy#had roast beef',
      'this_little_piggy#had impossible beef',
    ]);
    $this->assertEquals($malformed_but_harmless_update, [
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
    ], 'Part of a bad, but harmless, update succeeded.');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unset($this->distroHelperUpdates);
  }

}
