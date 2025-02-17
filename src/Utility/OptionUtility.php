<?php

namespace Xima\XimaDeployerExtendedTypo3\Utility;

class OptionUtility extends \SourceBroker\DeployerExtendedDatabase\Utility\OptionUtility
{
    public const AVAILABLE_OPTIONS = [
        'dumpcode',
        'tags',
        'target',
        'fromLocalStorage',
        'exportTaskAddIgnoreTablesToStructureDump',
        'importTaskDoNotDropAllTablesBeforeImport',
        'base_branch',
        'token'
    ];
}