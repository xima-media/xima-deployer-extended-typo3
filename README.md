# Deployer configuration for TYPO3 projects

This package extends [deployer-extended-typo3](https://github.com/sourcebroker/deployer-extended-typo3) for some common configurations

## Highlights

* **Feature-Branch Deployment** with new `base_branch` option
* Disable database override feature by setting ```db_databases_merged``` to ```db_databases```

## New useful commands

* dep log:app
* dep log:php