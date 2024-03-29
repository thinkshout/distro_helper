<?php

namespace Drupal\distro_helper;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Logger errors as an array that can be printed out.
   *
   * Using the drupal $logger factory in syncActiveConfigFromSavedConfigByKeys
   * would force us to change our test to a slower Kernel test.
   *
   * @var array
   */
  protected $loggerErrors;

  /**
   * Constructs a new DistroHelperUpdates object.
   */
  public function __construct(ConfigManagerInterface $config_manager, StorageInterface $config_storage_sync, CachedStorage $config_storage, LoggerChannelFactoryInterface $logger) {
    $this->configManager = $config_manager;
    $this->configStorageSync = $config_storage_sync;
    $this->configStorage = $config_storage;
    $this->logger = $logger->get('distro_helper');
    $this->loggerErrors = [];
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
    if ($this->configManager->getConfigFactory()->get('distro_helper')->get('disable_write')) {
      return;
    }
    // Get our sync directory.
    $config_dir = Settings::get('config_sync_directory');
    $directory = realpath($config_dir);
    if (!is_writable($directory)) {
      return;
    }

    // Get our storage settings.
    $sync_storage = $this->configStorageSync;
    $active_storage = $this->configStorage;

    if ($active_storage->read($config_name)) {
      // Find out which config was saved.
      $sync_storage->write($config_name, $active_storage->read($config_name));
    }
    else {
      // Log: Could not read $config_name from the config sync directory.
      // Is it new?
      $this->logger->warning(
        'Could not read the @directory file. Is the configuration new?',
        ['@directory' => $sync_storage->getFilePath($config_name)]);
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
    $new_config = DistroHelperUpdates::loadConfigFromModule($configName, $module, $directory)['value'];

    $active_config = $this->configManager->getConfigFactory()->getEditable($configName);
    if ($active_config->isNew()) {
      // Can't update nonexistent config.
      $this->logger->error(
        'No active config found for @configName in updateConfig(). Use installConfig() to import config that does not already exist in your database.',
        ['@config' => $configName]);
      return FALSE;
    }
    $raw_active_config = $active_config->getRawData();
    $raw_active_config = $this->syncActiveConfigFromSavedConfigByKeys($raw_active_config, $new_config, $elementKeys);
    foreach ($this->loggerErrors as $error) {
      $this->logger->error($error->render());
    }
    $active_config->setData($raw_active_config)->save();

    // If possible, immediately export the updated files.
    $this->exportConfig($configName);
    return $active_config;
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
    $file = \Drupal::service('extension.list.module')->getPath($module) . '/config/' . $directory . '/' . $configName . '.yml';
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

  /**
   * Syncs nested values in the 1st array with the same values from the 2nd.
   *
   * @param array $config_data
   *   The first array, the active config.
   * @param array $new_config
   *   The second array, the proposed config.
   * @param array $elementKeys
   *   A flattened array representing the nested field to update.
   *
   * @return array
   *   The updated array.
   */
  public function syncActiveConfigFromSavedConfigByKeys(array $config_data, array $new_config, array $elementKeys) {
    foreach ($elementKeys as $elementKey) {
      $newValue = $new_config;
      $target = &$config_data;
      $elementPath = explode('#', $elementKey);
      $depth = 0;
      foreach ($elementPath as $step) {
        if (isset($newValue[$step])) {
          if (!isset($target[$step])) {
            // This key doesn't exist in the old config -- add it:
            $target[$step] = [];
          }
          $target = &$target[$step];
          $newValue = $newValue[$step];
          $depth++;
        }
        else {
          if (isset($target[$step])) {
            $newValue = NULL;
            $depth++;
          }
        }
      }
      if ($depth < count($elementPath)) {
        // We didn't find the full path given in our new config. Throw message.
        $this->loggerErrors[] = new TranslatableMarkup('Could not find a value nested at @config', ['@config' => implode('.', $elementPath)]);
      }
      elseif ($newValue === NULL) {
        unset($target[$step]);
      }
      else {
        $target = $newValue;
      }
    }

    return $config_data;
  }

  /**
   * Returns the logger errors for unit tests.
   *
   * @return array
   *   The array of all errors found.
   */
  public function getLoggerErrors(): array {
    return $this->loggerErrors;
  }

}
