<?php
/**
 * 回归：采集规则、远程图片下载、友链检测等用户可控 URL 必须经过 SSRF 安全客户端。
 * 跑法：php tests/regression/ssrf_user_controlled_fetches.php
 */
require __DIR__ . '/lib/bootstrap.php';

$checks = [
    'collection rule fetch' => [APP_PATH . 'common/util/Collection.php', 'HttpClient::safeGet($url, 30, false)'],
    'remote image download' => [APP_PATH . 'common/model/Image.php', 'HttpClient::safeGet($url, 30, false)'],
    'friend-link URL check' => [APP_PATH . 'common.php', 'HttpClient::safeGet($url, 15, false)'],
];
foreach ($checks as $label => $item) {
    $source = file_get_contents($item[0]);
    rt_check(strpos($source, $item[1]) !== false, $label . ' uses SSRF-safe HTTP client');
    rt_same(0, substr_count($source, 'mac_curl_get($url, [], \'\', false)'), $label . ' no longer uses redirect-following cURL');
}

rt_done('user-controlled SSRF fetches');
