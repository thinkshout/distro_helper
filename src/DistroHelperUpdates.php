<?php

namespace Drupal\distro_helper;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\CachedStorage;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Crypt;

/**
 * Class DistroHelperUpdates.
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
   * updated config files. Note that it is preferred to use the config factory for
   * targeted updates rather than this ham-handed version: this is only the
   * preferred method for including new configuration in updates.
   *
   * The config factory for targeted updates, for example:
   *
   * $config = \Drupal::service('config.factory')->getEditable('node.type.page');
   * if (!$config->isNew()) {
   *   $config_data = $config->getRawData();
   *   $config_data['name'] = 'Fantastic Page';
   *   $config->setData($config_data)->save();
   * }
   *
   * To use this function instead, indicate the module that has the new config,
   * the name of the config, and config directory (if not "install"). In order
   * to perform updates, you must indicate it as a 4th argument ("TRUE").
   *
   * @param string $config_name
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
  public function installConfig(string $config_name, string $module, string $directory = 'install', bool $update = FALSE) {
    $updated = [];
    $created = [];

    /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
    $config_manager = $this->configManager;
    $file = drupal_get_path('module', $module) . '/config/' . $directory . '/' . $config_name . '.yml';
    $raw = file_get_contents($file);
    if (empty($raw)) {
      throw new \RuntimeException(sprintf('Config file not found at %s', $file));
    }
    $value = Yaml::decode($raw);
    if (!is_array($value)) {
      throw new \RuntimeException(sprintf('Invalid YAML file %s', $file));
    }

    $type = $config_manager->getEntityTypeIdByName(basename($file));
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
      $sync_config = $this->configStorageSync->read($config_name);

      if (!empty($sync_config['uuid'])) {
        $entity->set('uuid', $sync_config['uuid']);
      }
      $entity->save();
      $created[] = $id;
    }
    // If possible, immediately export the updated files.
    $this->exportConfig($config_name);
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
    $config_dir = \Drupal\Core\Site\Settings::get('config_sync_directory');
    $directory = realpath($config_dir);
    if (!is_writable($directory)) {
      return;
    }

    // Get our storage settings.
    $sync_storage = $this->configStorageSync;
    $active_storage = $this->configStorage;

    // Find out which config was saved.
    $sync_storage->write($config_name, $active_storage->read($config_name));

    // Export configuration collections.
    foreach ($active_storage->getAllCollectionNames() as $collection) {
      $active_collection = $active_storage->createCollection($collection);
      $sync_collection = $sync_storage->createCollection($collection);
      $sync_collection->write($config_name, $active_collection->read($config_name));
    }
  }

}
