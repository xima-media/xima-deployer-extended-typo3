<?php

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;

// Override of sourcebroker/deployer-extended task to throw on non-2xx HTTP response codes.
// Read more on https://github.com/sourcebroker/deployer-extended#cache-clear-php-http
task('cache:clear_php_http', function () {
    $random = md5(time() . mt_rand());
    // Try to find fileName from previous release to prevent real_cache problems when "current" folder still points to
    // old release directory and Apache is giving 404 error because clear_cache_* file does not exist in old release dir.
    $releasesList = get('releases_list');
    $webPath = get('web_path', '');
    $previousClearCacheFiles = null;
    if (isset($releasesList[1]) && test('[ -e {{deploy_path}}/releases/' . $releasesList[1] . ' ]')) {
        $previousClearCacheFiles = preg_split(
            '/\R/',
            run("find {{deploy_path}}/releases/$releasesList[1]/$webPath -name 'cache_clear_random_*'")
        );
    }
    if (!empty($previousClearCacheFiles) && !empty($previousClearCacheFiles[0])) {
        $fileName = pathinfo($previousClearCacheFiles[0], PATHINFO_BASENAME);
    } else {
        $fileName = "cache_clear_random_" . $random . '.php';
    }
    if (test('[ -L {{deploy_path}}/current ]')) {
        run('cd {{deploy_path}}/current/' . $webPath . ' && echo ' . escapeshellarg(
                get(
                    'cache:clear_php_http:phpcontent',
                    "<?php\n"
                    . "clearstatcache(true);\n"
                    . "if(function_exists('opcache_reset')) {opcache_reset();}\n"
                )
            ) . ' > ' . $fileName);
    }

    if (empty(get('public_urls', []))) {
        throw new GracefulShutdownException('You need at least one "public_url" to call task cache:clear_php_http');
    }
    $clearCacheUrl = rtrim(get('public_urls')[0], '/') . '/' . $fileName;

    switch (get('fetch_method', 'wget')) {
        case 'curl':
            $result = runLocally(
                '{{local/bin/curl}} ' . get('fetch_method_curl_options',
                    '--insecure --silent --location --output /dev/null --write-out "%{http_code}"') . ' ' . escapeshellarg($clearCacheUrl) . ' 2>/dev/null',
                ['timeout' => get('cache:clear_php_http:timeout', 15)]
            );
            break;

        case 'file_get_contents':
            $result = runLocally(
                '{{local/bin/php}} -r \'file_get_contents("' . escapeshellarg($clearCacheUrl) . '");\'',
                ['timeout' => get('cache:clear_php_http:timeout', 15)]
            );
            break;

        case 'wget':
        default:
            $result = runLocally(
                '{{local/bin/wget}} ' . get('fetch_method_wget_options',
                    '--no-check-certificate -q -O /dev/null') . ' ' . escapeshellarg($clearCacheUrl),
                ['timeout' => get('cache:clear_php_http:timeout', 15)]
            );
            break;
    }

    // Validate the HTTP response code for curl (which outputs the status code)
    if (get('fetch_method', 'wget') === 'curl') {
        $httpCode = trim($result);
        if (!preg_match('/^2\d{2}$/', $httpCode)) {
            throw new GracefulShutdownException(
                sprintf(
                    'cache:clear_php_http failed: HTTP status code %s returned for URL %s',
                    $httpCode,
                    $clearCacheUrl
                )
            );
        }
    }
})->desc('Clear php caches for current release')->hidden();
