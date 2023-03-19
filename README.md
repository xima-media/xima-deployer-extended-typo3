# Deployer configuration for TYPO3 projects

This package extends [deployer-extended-typo3](https://github.com/sourcebroker/deployer-extended-typo3) for some common configurations.

## Highlights

* **Default values** for typical server environment
* **Feature-Branch Deployment** with new `base_branch` option
* **Non-git** deployment

## Installation

```
composer require xima/xima-deployer-extended-typo3
```

## Configuration

### deploy.php

```php
<?php

namespace Deployer;

require_once(__DIR__ . '/vendor/xima/xima-deployer-extended-typo3/autoload.php');


set('repository', 'git@github.com:your-repo-name.git');

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
defineTestHost('master', 'live');
```

## Feature-Branch deployment

There is a new command ```db:init``` which runs right before ```db:truncate```. This command checks for the *base_branch* option:

```
vendor/bin/dep deploy-fast example-ticket-001 --options=base_branch:master
```

If this option is set, the command checks if the current feature host has been initialized before. In case of an empty database, the `db:copy` command is triggert to fetch a database copy from the given **base_branch**.

## Default values

Configuration covers typical permission, shared and writtable directory settings. See [set.php](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/set.php) for default values.

To extend a default values array, use the following `set` command:

```php
set('shared_dirs', [
	...get('shared_dirs'),
	'newDir',
]);
```

## New useful commands

* dep log:app
* dep log:php

## Non-git deployment

If the source host has no access to the git repository, you can replace `deploy:update_code` with the new `deploy:upload_code` task to transfer all needed files.

```
task('deploy:update_code')->disable();
after('deploy:update_code', 'deploy:upload_code');
```

The files to upload become configured via [`upload_paths`](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/set.php#L61).