# Deployer configuration for TYPO3 projects

This package uses different `sourcebroker/deployer-*` packages to create deployment configurations for TYPO3 projects. It provides a set of default values and commands to simplify the deployment process.

## Highlights

* **Default values** for typical server environment
* **Feature-Branch Deployment** with new `base_branch` option
* **Non-git** deployment
* **New commands** `dep launch` & `dep sequelace`

## Installation

```
composer require xima/xima-deployer-extended-typo3
```

Create a `deploy.php` file in the root of your project and include the following code:

### deploy.php

```php
<?php

namespace Deployer;

require_once './vendor/autoload.php';
new \Xima\XimaDeployerExtendedTypo3\Loader();

function defineTestHost($branchName, $stage)
{
    host('example-' . strtolower($branchName))
        ->setHostname('192.168.0.1')
        ->setRemoteUser('username')
        ->set('labels', ['stage' => $stage])
        ->set('branch', $branchName)
        ->set('public_urls', ['https://' . strtolower($branchName) . '.example.com'])
        ->set('deploy_path', '/var/www/html/example_' . strtolower($branchName));
}

// feature branch hosts
for ($i = 1; $i <= 999; $i++) {
    $branchName = 'TICKET-' . $i;
    defineTestHost($branchName, 'feature');
}

// main host
defineTestHost('main', 'live');
```

## Default values

Configuration covers typical permission, shared and writable directory settings. See [config.php](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/config.php) for default values.

To extend a default values array, use the following `set` command:

```php
set('shared_dirs', [
	...get('shared_dirs'),
	'newDir',
]);
```

## Database migrations and upgrade wizards

The deployment runs configured SQL migrations and TYPO3 upgrade wizards immediately before and after `typo3:extension:setup`. Configure the tasks in your `deploy.php` file:

```php
set('migration_before_tasks', [
    '.migration/001-before.sql',
    'my_upgrade_wizard',
]);

set('migration_after_tasks', [
    '.migration/002-after.sql',
]);
```

Tasks run in the order listed. Entries ending in `.sql` are imported with TYPO3 Console's `database:import` command; all other entries are passed to `upgrade:run` as upgrade wizard identifiers. Omit either setting, or provide an empty list, if there are no tasks for that deployment phase.

## Feature-Branch deployment

There is a new command ```db:init``` which runs right before ```db:truncate```. This command checks for the *txBaseBranch* option:

```
vendor/bin/dep deploy-fast example-ticket-001 --options=txBaseBranch:main
```

If this option is set, the command checks if the current feature instance has been initialized before. In case of an empty database, the `db:copy` command is triggert to fetch a database copy from the given base branch.


## Non-git deployment

All files become uploaded via rsync and can be configured via [`upload_paths`](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/set.php#L61).