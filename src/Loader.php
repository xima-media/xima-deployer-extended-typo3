<?php

namespace Xima\XimaDeployerExtendedTypo3;

class Loader
{
    public function __construct()
    {
        new \SourceBroker\DeployerLoader\Load([
            ['package' => 'sourcebroker/deployer-extended'],
            ['get' => 'sourcebroker/deployer-typo3-media'],
            ['get' => 'sourcebroker/deployer-typo3-database'],
            ['get' => 'sourcebroker/deployer-typo3-deploy-ci'],
            ['package' => 'xima/xima-deployer-extended-typo3'],
        ]);
    }
}