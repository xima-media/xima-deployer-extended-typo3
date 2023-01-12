# Deployer configuration for TYPO3 projects

This package extends [deployer-extended-typo3](https://github.com/sourcebroker/deployer-extended-typo3) for some common configurations

## Highlights

* **Feature-Branch Deployment** with new `base_branch` option

## New useful commands

* dep log:app
* dep log:php

## Install

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

// main host
defineTestHost('master', 'live');

// feature branch hosts
for ($i = 0; $i <= 999; $i++) {
    $ticketNr = str_pad($i, 3, '0', STR_PAD_LEFT);
    $branchName = 'TICKET-' . $ticketNr;
    defineTestHost($branchName, 'feature');
}
```

## Feature-Branch deployment

There is a new command ```db:init``` which runs right before ```db:truncate```. This command checks for the *base_branch* option:

```
vendor/bin/dep deploy-fast example-ticket-001 --options=base_branch:master
```

If this option is set, the command checks if the current feature host has been initialized before. In case of an empty database, the `db:copy` command is triggert to fetch a database copy from the given **base_branch**.
