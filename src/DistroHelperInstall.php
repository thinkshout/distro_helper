<?php

namespace Drupal\distro_helper;

// @codingStandardsIgnoreFile

/**
 * Provides a service to help with config management in distros on install.
 *
 * @deprecated in distro_helper:1.0.5 and is removed from distro_helper:2.0.0.
 * @see https://github.com/thinkshout/distro_helper/issues/21
 * Use \Drupal\distro_helper\DistroHelperUpdates::syncUuids instead.
 * Example usage: \Drupal::service('distro_helper.updates')->syncUUIDs($configs);
 */
class DistroHelperInstall {

  /**
   * Drupal\distro_helper\DistroHelperUpdates definition.
   *
   * @var \Drupal\distro_helper\DistroHelperUpdates
   */
  protected $distroHelperUpdates;

  /**
   * Constructs a new DistroHelperUpdates object.
   */
  public function __construct(DistroHelperUpdates $distro_helper_updates) {
    $this->distroHelperUpdates = $distro_helper_updates;
  }

  /**
   * Update the uuids from the just-installed site to match the config folder.
   *
   * @param array $configs
   *   The a set of configs as an array (leave off the .yml).
   */
  public function syncUuids(array $configs) {
    $this->distroHelperUpdates->syncUUIDs(\Drupal::service('config.storage.export')->listAll());
  }

}
