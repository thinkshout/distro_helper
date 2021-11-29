<?php

namespace Drupal\distro_helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\CachedStorage;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;

/**
 * Provides a service to help with configuration management in distros on install.
 */
class DistroHelperInstall {

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Config\StorageInterface definition.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorageSync;

  /**
   * Constructs a new DistroHelperUpdates object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StorageInterface $config_storage_sync) {
    $this->configFactory = $config_factory;
    $this->configStorageSync = $config_storage_sync;
  }

  /**
   * Helper function to update the uuids from the just-installed site to match the config folder.
   *
   * @param string $configName
   *   The name of the config (its file name with the '.yml' part).
   * @param string $module
   *   Module machine name that has the config files.
   * @param string $directory
   *   Usually "install" but sometimes "optional".
   * @param bool $update
   *   Whether or not to update the config if it already exists.
   *
   * @return array[]
   *   Two arrays of 'updated' and 'created' configurations.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function syncUUIDs($configs) {
    // Foreach config in the system.
    foreach($configs as $configName) {
      // If new config exists in sync, match up the uuids.
      $sync_config = $this->configStorageSync->read($configName);
      $entity = $this->configFactory->getEditable($configName);

      if (!empty($sync_config['uuid'])) {
        $entity->set('uuid', $sync_config['uuid']);
      }
      if (isset($sync_config['_core']['default_config_hash'])) {
        $entity->set('_core', $sync_config['_core']);
      }
      $entity->save();
    }
  }
}
