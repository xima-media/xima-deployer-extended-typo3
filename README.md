# Deployer configuration for TYPO3 projects

This package uses different `sourcebroker/deployer-*` packages to create deployment configurations for TYPO3 projects. It provides a set of default values and commands to simplify the deployment process.

## Highlights

* **Default values** for typical server environment
* **Feature-Branch Deployment** with new `base_branch` option and hosts only for existing branches
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
$branches = \Xima\XimaDeployerExtendedTypo3\Utility\BranchUtility::environmentBranches('example-', [
    'TICKET-\d+' => 'feature',
]);
foreach ($branches as $branchName => $stage) {
    defineTestHost($branchName, $stage);
}

// main host
defineTestHost('main', 'live');
```

## Server requirements

PHP-FPM must resolve the `current` symlink itself, so requests get the new release right after `deploy:symlink`. With Apache and PHP-FPM, set this in the FPM `php.ini` (see [Deployer: Avoid php-fpm reloading](https://deployer.org/docs/8.x/avoid-php-fpm-reloading)):

```ini
opcache.revalidate_path=1
```

Without it, OPcache keeps serving the entry scripts of the previous release under the `current/` path until it is reset. To check, print `__FILE__` in `public/index.php`: it must point to `releases/<n>/`, not `current/`. `$_SERVER['SCRIPT_FILENAME']` always contains `current` under Apache.

`buffer:start` holds incoming requests during the deployment, and `buffer:stop` releases them right after `typo3:cache:flush:pages`. `cache:clear_php_http` runs afterwards and only removes OPcache entries of previous releases. A failed request only prints a warning. Settings:

| Setting | Default | Description |
|---|---|---|
| `cache:clear_php_http:timeout` | `60` | Request timeout in seconds |
| `cache:clear_php_http:fail_on_error` | `false` | Fail the deployment if the request fails |

## Hosts for feature branches

`BranchUtility::environmentBranches()` returns only the branches that need a host, instead of defining a host for every possible ticket number. It takes the host name prefix and a list of branch name patterns (regex without delimiters, case-insensitive) with the stage of their hosts:

```php
BranchUtility::environmentBranches('example-', [
    'TICKET-\d+' => 'feature',
    'renovate-[0-9a-f]+' => 'test',
]);
// ['TICKET-12' => 'feature', 'renovate-1a2b3c4d' => 'test', ...]
```

Everything after the first `_` of a branch name is ignored, so `TICKET-12_some-feature` deploys to `TICKET-12`. The branches are collected without any network access, so neither a missing VPN nor a CI runner without git history blocks Deployer:

* `CI_COMMIT_BRANCH`
* host names on the command line, including nested calls like `db:export example-ticket-12` or `--options=target:example-ticket-12` that run on a remote instance
* `INSTANCE` of the current instance
* local and remote-tracking git branches, only on the local instance outside of CI (run `git fetch` to see new remote branches)

Hosts that must always exist, like `main`, are still defined explicitly.

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