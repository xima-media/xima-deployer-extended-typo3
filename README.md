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

PHP-FPM must resolve the `current` symlink itself. Settings for the PHP-FPM `php.ini` (see [Deployer: Avoid php-fpm reloading](https://deployer.org/docs/8.x/avoid-php-fpm-reloading)):

```ini
; recommended: OPcache resolves the "current" symlink and caches files under their release path
opcache.revalidate_path=1

; recommended: no open_basedir, any value disables the realpath cache and slows down every request
open_basedir=

; recommended: a short realpath cache lifetime limits how long a worker resolves "current" to a previous release
realpath_cache_ttl=30
realpath_cache_size=16M

; recommended: room for the files of two releases until cache:clear_php_http removes the previous one
opcache.max_accelerated_files=65407
```

Without `opcache.revalidate_path=1`, OPcache keeps the entry points of the previous release under the `current/` path until `cache:clear_php_http` runs. Until then, every request takes the detour to the new release described below.

PHP rounds `opcache.max_accelerated_files` up to the next of its prime numbers (…, 16229, 32531, 65407, 130987, …), so values between 32532 and 65407 all result in 65407.

## Request buffer and release switch

`buffer:start` holds incoming requests during the deployment. It also replaces code injected by earlier versions of this package in all retained releases. `buffer:stop` releases the requests right after `typo3:cache:flush:pages` and marks all releases except the one `current` points to. It runs after `dep rollback` as well.

A PHP-FPM worker can still resolve `current` to a previous release until its realpath cache entry expires (`realpath_cache_ttl`). Such a request runs only the entry point of that release: it clears the realpath cache of the worker and includes the entry point of the release `current` points to in the same request. The client gets no redirect, and request method and body are kept. An entry point that no longer exists in the current release (e.g. `typo3/index.php` after an upgrade to TYPO3 v14) keeps running in the previous release.

`cache:clear_php_http` runs after `buffer:stop` and calls `opcache_reset()`. This empties the whole OPcache of the PHP-FPM instance, including the new release and other sites served by it. Any failure of the task (missing `public_urls`, unwritable web directory, failed request) only prints a warning.

| Setting | Default | Description |
|---|---|---|
| `cache:clear_php_http:timeout` | `60` | Request timeout in seconds |
| `cache:clear_php_http:fail_on_error` | `false` | Fail the deployment if the request fails |
| `oldrelease_redirect_sleep` in `buffer_config` | `0` | Microseconds to wait before a previous release includes the current entry point |

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

The migrations run without a timeout, as upgrade wizards can take longer than Deployer's default of 300 seconds. To cap every migration step, set a limit in seconds:

```php
set('migration_timeout', 1800);
```

or for a single run: `vendor/bin/dep deploy -o migration_timeout=1800 <host>`.

## Feature-Branch deployment

There is a new command ```db:init``` which runs right before ```db:truncate```. This command checks for the *txBaseBranch* option:

```
vendor/bin/dep deploy-fast example-ticket-001 --options=txBaseBranch:main
```

If this option is set, the command checks if the current feature instance has been initialized before. In case of an empty database, the `db:copy` command is triggert to fetch a database copy from the given base branch.

### Timeout of database tasks

Copying a large database takes longer than Deployer's default timeout of 300 seconds. The database tasks (`db:copy`, `db:import`, ...) call each other as nested Deployer processes, locally and on the remote host. Every `db:*` task, including those inside `deploy` (`db:backup`, `db:init`, `db:truncate`), therefore runs with `db_timeout` (default: 1800 seconds) instead of `default_timeout`. `0` disables the limit:

```php
set('db_timeout', 3600);
```

Set it in `deploy.php`: the nested processes load `deploy.php` again, but they do not receive a `-o db_timeout=…` from the command line. Other tasks keep `default_timeout`. `db:*` tasks defined in your `deploy.php` are not covered.


## Non-git deployment

All files become uploaded via rsync and can be configured via [`upload_paths`](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/set.php#L61).