## What this module does
Provides a service "distro_helper.updates" with the following methods. Each of
these methods writes configuration to the database and then writes the config
out to the file system, if using file-based-config.

You can disable the "writing the config out to the file system" behavior in
settings.php by adding:
```php
$config['distro_helper']['disable_write'] = TRUE;
```

For example, if using Pantheon, you might add it inside this conditional:
```php
if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
  $config['distro_helper']['disable_write'] = TRUE;
}
```
Note that this is likely unnecessary, since this module checks to see if the
file-based-config directory is writable before attempting a write, anyway.

### installConfig (distro_helper.updates)

If you add new or updated configuration to a module's config folder, you need to provide an update hook to the site to let it know to pull in that new configuration.

This module's service 100% overwrites the config file in active config with the one in the install directory for the module, so it's most suited for new config.

The first time an update hook is run, the uuid for the config gets created and set in Active config. If this is exported into the Sync config directory, the UUID will be saved there as well.

If you run an update hook multiple times for testing purposes or on multiple environments, this helper function will keep the uuids in the sync and active config identical, despite the typical deployment workflow of update hook followed by config import.

Usage for this services is:

```php
\Drupal::service('distro_helper.updates')->installConfig($config_id, $modulename, $dirname);
```

### updateConfig (distro_helper.updates)

If you update configuration, but don't want to completely overwrite existing configuration on sites, use this method to do a targeted update of configuration.

It works much like installConfig, but you need to pass in a "$targets" variable as well, which is an array of paths to the part of the configuration that you want to install, using '#' as a separator.

Usage for this services is:

```php
\Drupal::service('distro_helper.updates')->updateConfig($config_id, $targets, $modulename, $dirname);
```

### syncUuids (distro_helper.install)

Pushes uuids from your file based configuration into your site configuration. This is intended for fresh site installs where you want to see how your installed config differs from the config you get when you export a persistent database. The primary usage is to do this for ALL site config, but it is built to allow for a subset of configurations to be "synced" as well.

Usage is:

```
\Drupal::service('distro_helper.install')->syncUUIDs($configs);
```

The standard way to call it, passing in ALL the file-based configs, is easy with the 'config.storage.export' service:

```
\Drupal::service('distro_helper.install')->syncUUIDs(\Drupal::service('config.storage.export')->listAll());
```
