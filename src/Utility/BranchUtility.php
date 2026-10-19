<?php

namespace Xima\XimaDeployerExtendedTypo3\Utility;

use SourceBroker\DeployerInstance\Env;

class BranchUtility
{
    /**
     * Collects the branches that need a host, instead of defining one host per possible ticket number.
     *
     * Everything after the first "_" of a branch name is ignored (TICKET-12_some-feature -> TICKET-12),
     * matching the branch id the CI templates deploy to.
     *
     * Sources, all without network access so a missing VPN or a CI runner without git history cannot
     * block or slow down Deployer:
     * - CI_COMMIT_BRANCH
     * - host names on the command line, including nested calls like "db:export <host>" or
     *   "--options=target:<host>" that run on a remote instance without a git checkout
     * - INSTANCE of the current instance
     * - local and remote-tracking git branches, only on the local instance outside of CI
     *
     * @param string $hostPrefix Host name in front of the lowercased branch name, e.g. "dev-"
     * @param array<string, string> $patterns Branch name regex (without delimiters, case-insensitive) => stage
     * @return array<string, string> Branch name => stage
     */
    public static function environmentBranches(string $hostPrefix, array $patterns): array
    {
        $env = new Env();
        $env->load();
        $instance = (string)$env->get('INSTANCE');

        $candidates = [getenv('CI_COMMIT_BRANCH') ?: ''];

        if (getenv('CI') !== 'true' && $instance === 'local') {
            exec('git for-each-ref --format="%(refname)" refs/heads refs/remotes 2>/dev/null', $refs);
            foreach ($refs as $ref) {
                $candidates[] = preg_replace('#^refs/(heads|remotes/[^/]+)/#', '', $ref);
            }
        }

        $hostNames = [$instance, ...($_SERVER['argv'] ?? [])];

        $branches = [];
        foreach ($patterns as $pattern => $stage) {
            foreach ($candidates as $candidate) {
                if (preg_match('#^(' . $pattern . ')(?:_|$)#i', $candidate, $matches)) {
                    $branches[strtolower($matches[1])] ??= [$matches[1], $stage];
                }
            }
            foreach ($hostNames as $hostName) {
                if (preg_match_all('#' . preg_quote($hostPrefix, '#') . '(' . $pattern . ')\b#i', $hostName, $matches)) {
                    foreach ($matches[1] as $branch) {
                        $branches[strtolower($branch)] ??= [$branch, $stage];
                    }
                }
            }
        }

        return array_column($branches, 1, 0);
    }
}
