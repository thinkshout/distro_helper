<?php

namespace Drupal\distro_helper;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\CachedStorage;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;

/**
 * Provides a service to help with configuration management in distros.
 */
class DistroHelperUpdates {

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Drupal\Core\Config\StorageInterface definition.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorageSync;

  /**
   * Drupal\Core\Config\CachedStorage definition.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;

  /**
   * Constructs a new DistroHelperUpdates object.
   */
  public function __construct(ConfigManagerInterface $config_manager, StorageInterface $config_storage_sync, CachedStorage $config_storage) {
    $this->configManager = $config_manager;
    $this->configStorageSync = $config_storage_sync;
    $this->configStorage = $config_storage;
  }

  /**
   * Helper function for managing new or changed configuration files.
   *
   * Use it in update hooks to install configuration from newly created or
   * updated config files. Note that it is preferred to use the config factory
   * for targeted updates rather than this ham-handed version: this is only the
   * preferred method for including new configuration in updates.
   *
   * To do more targeted updates, use updateConfig.
   *
   * To use this function instead, indicate the module that has the new config,
   * the name of the config, and config directory (if not "install"). In order
   * to perform updates, you must indicate it as a 4th argument ("TRUE").
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
  public function installConfig(string $configName, string $module, string $directory = 'install', bool $update = FALSE) {
    $updated = [];
    $created = [];

    $config_manager = $this->configManager;
    $config = DistroHelperUpdates::loadConfigFromModule($configName, $module, $directory);
    $value = $config['value'];

    $type = $config_manager->getEntityTypeIdByName(basename($config['file']));
    /** @var \Drupal\Core\Entity\EntityTypeManager $entity_manager */
    $entity_manager = $config_manager->getEntityTypeManager();
    $definition = $entity_manager->getDefinition($type);
    $id_key = $definition->getKey('id');
    $id = $value[$id_key];

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $entity_storage */
    $entity_storage = $entity_manager->getStorage($type);
    $entity = $entity_storage->load($id);
    if ($entity) {
      if ($update) {
        $entity = $entity_storage->updateFromStorageRecord($entity, $value);
        $entity->save();
        $updated[] = $id;
      }
    }
    else {
      $value['_core']['default_config_hash'] = Crypt::hashBase64(serialize($value));
      $entity = $entity_storage->createFromStorageRecord($value);
      // If new config exists in sync, match up the uuids.
      $sync_config = $this->configStorageSync->read($configName);

      if (!empty($sync_config['uuid'])) {
        $entity->set('uuid', $sync_config['uuid']);
      }
      $entity->save();
      $created[] = $id;
    }
    // If possible, immediately export the updated files.
    $this->exportConfig($configName);
    return [
      'updated' => $updated,
      'created' => $created,
    ];
  }

  /**
   * Exports a single config file to the sync directory.
   *
   * @param string $config_name
   *   The config to read.
   */
  public function exportConfig($config_name) {
    // Get our sync directory.
    $config_dir = Settings::get('config_sync_directory');
    $directory = realpath($config_dir);
    if (!is_writable($directory)) {
      return;
    }

    // Get our storage settings.
    $sync_storage = $this->configStorageSync;
    $active_storage = $this->configStorage;

    // Export configuration collections.
    foreach ($active_storage->getAllCollectionNames() as $collection) {
      $active_collection = $active_storage->createCollection($collection);
      $sync_collection = $sync_storage->createCollection($collection);
      $sync_collection->write($config_name, $active_collection->read($config_name));
    }
  }

  /**
   * Helper function to do targeted updates of configuration.
   *
   * @param string $configName
   *   The name of the configuration, like node.type.page, with no ".yml".
   * @param array $elementKeys
   *   An array of paths to the configuration elements within the config that
   *   you want updated, using # as a separator. To set the UUID, you would just
   *   pass ["UUID"]. To set a Block label, you would pass ["settings#label"].
   * @param string $module
   *   Module machine name that has the config file with the new value.
   * @param string $directory
   *   Usually "install" but sometimes "optional".
   *
   * @return mixed
   *   FALSE if the update failed, otherwise the updated configuration object.
   */
  public function updateConfig(string $configName, array $elementKeys, string $module, string $directory = 'install') {
    $ymlConfig = DistroHelperUpdates::loadConfigFromModule($configName, $module, $directory)['value'];

    $config = \Drupal::service('config.factory')->getEditable($configName);
    if ($config->isNew()) {
      // Can't update nonexistent config.
      return FALSE;
    }
    $config_data = $config->getRawData();
    foreach ($elementKeys as $elementKey) {
      $newValue = $ymlConfig;
      $target = &$config_data;
      $elementPath = explode('#', $elementKey);
      foreach ($elementPath as $step) {
        if (isset($newValue[$step])) {
          if (!isset($target[$step])) {
            // This key doesn't exist in the old config -- add it:
            $target[$step] = [];
          }
          $target = &$target[$step];
          $newValue = $newValue[$step];
        }
        else {
          return FALSE;
        }
      }
      $target = $newValue;
    }
    $config->setData($config_data)->save();

    // If possible, immediately export the updated files.
    $this->exportConfig($configName);
    return $config;
  }

  /**
   * Helper function to load up a yml config file from a module.
   *
   * @param string $configName
   *   The name of the configuration, like node.type.page, with no ".yml".
   * @param string $module
   *   Module machine name that has the config file with the new value.
   * @param string $directory
   *   Usually "install" but sometimes "optional".
   *
   * @return array
   *   An array representation of a yml file.
   */
  private static function loadConfigFromModule(string $configName, string $module, string $directory = 'install') {
    $file = drupal_get_path('module', $module) . '/config/' . $directory . '/' . $configName . '.yml';
    $raw = file_get_contents($file);
    if (empty($raw)) {
      throw new \RuntimeException(sprintf('Config file not found at %s', $file));
    }
    $value = Yaml::decode($raw);
    if (!is_array($value)) {
      throw new \RuntimeException(sprintf('Invalid YAML file %s', $file));
    }
    return ['value' => $value, 'file' => $file];
  }

}
