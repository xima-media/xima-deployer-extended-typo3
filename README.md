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

* dep launch
* dep log:app
* dep log:phpfpm
	* dep log:phpfpm-slow
	* dep log:phpfpm-access
	* dep log:phpfpm-error
* dep sequelace

## Non-git deployment

If the source host has no access to the git repository, you can replace `deploy:update_code` with the new `deploy:upload_code` task to transfer all needed files.

```
task('deploy:update_code')->disable();
after('deploy:update_code', 'deploy:upload_code');
```

The files to upload become configured via [`upload_paths`](https://github.com/xima-media/xima-deployer-extended-typo3/blob/main/set.php#L61).

## Reset from Gitlab Artifact

If Host A needs current data from Host B without direct access, Gitlab artifacts may be used as an intermediary.

Prerequisites:
- Gitlab API token with download access
- Artifact download url in *deploy.php* of Host A:
```php
set('reset_gitlab_artifact_url', 'https://<domain>/api/v4/projects/<project-id>/jobs/artifacts/<branch>/download?job=export-job');
```

### 1. Host B: Exports database and media files, which will then be uploaded as artifact.

```yaml
export-job:
  ...
  script:
    - vendor/bin/dep db:export --options=dumpcode:myArtifact --no-interaction -vvv host-b
    - vendor/bin/dep db:process --options=dumpcode:myArtifact --no-interaction -vvv host-b
    - vendor/bin/dep db:compress --options=dumpcode:myArtifact --no-interaction -vvv host-b
    - vendor/bin/dep db:download --options=dumpcode:myArtifact --no-interaction -vvv host-b
    - vendor/bin/dep media:pull --no-interaction host-b
  artifacts:
    paths:
      - .dep/database
      - public/fileadmin
      - public/uploads
    expire_in: 1 day
```
### 2. Host A: Uses task **reset:from_gitlab_artifact** to download and import the artifact.

```yaml
...
import-job:
  ...
  script:
    - vendor/bin/dep reset:from_gitlab_artifact --options="token:$CI_VARIABLE_WITH_API_TOKEN,dumpcode:myArtifact" host-a
  when: manual
```

## Patch for media:push task

The flag `--keep-dirlinks (-K)` is broken in recent rsync versions - this causes media:push from https://github.com/sourcebroker/deployer-extended-media to fail when syncing into symlinked directories: https://github.com/sourcebroker/deployer-extended-media/issues/9

To patch this, the absolute path to the shared dir is used:
```php
# 37 $src = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
$src = get('deploy_path') . '/' . 'shared';
```

Warning: All synchronised directories in **media_custom** must be **shared_dirs**.
