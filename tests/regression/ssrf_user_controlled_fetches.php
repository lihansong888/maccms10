<?php
/**
 * 回归：采集规则、远程图片下载、友链检测等用户可控 URL 必须经过 SSRF 安全客户端。
 * 跑法：php tests/regression/ssrf_user_controlled_fetches.php
 */
require __DIR__ . '/lib/bootstrap.php';

$checks = [
    'collection rule fetch' => [APP_PATH . 'common/util/Collection.php', 'get_html'],
    'remote image download' => [APP_PATH . 'common/model/Image.php', 'down_exec'],
    'friend-link URL check' => [APP_PATH . 'common.php', 'mac_check_back_link'],
];
foreach ($checks as $label => $item) {
    $functions = rt_functions($item[0]);
    rt_check(isset($functions[$item[1]]), $label . ' entry point exists');
    $body = isset($functions[$item[1]]) ? $functions[$item[1]]['body'] : '';
    rt_check(strpos($body, 'HttpClient::safeGet(') !== false, $label . ' uses SSRF-safe HTTP client');
    rt_same(false, strpos($body, 'mac_curl_get(') !== false, $label . ' does not use redirect-following cURL');
}

rt_done('user-controlled SSRF fetches');
