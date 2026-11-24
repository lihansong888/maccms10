<?php
/**
 * 回归：所有远端采集请求必须经过 SSRF 安全客户端，拒绝非 HTTP(S) 与内网目标。
 * 跑法：php tests/regression/ssrf_collection.php
 */
require __DIR__ . '/lib/bootstrap.php';
require_once APP_PATH . 'common/util/HttpClient.php';

$collectFile = APP_PATH . 'common/model/Collect.php';
$collect = rt_functions($collectFile);
rt_check(isset($collect['checkCjUrl']), 'Collect::checkCjUrl exists');
$check = isset($collect['checkCjUrl']) ? $collect['checkCjUrl']['body'] : '';
rt_check(strpos($check, "'http', 'https'") !== false, 'collection URL scheme is restricted to HTTP(S)');
rt_check(strpos($check, "isset(" . '$result' . "['user'])") !== false, 'URL userinfo is rejected');
rt_check(strpos($check, "isset(" . '$result' . "['pass'])") !== false, 'URL password is rejected');

$source = file_get_contents($collectFile);
$unsafeCalls = substr_count($source, 'mac_curl_get(');
$safeCalls = substr_count($source, 'HttpClient::safeGet(');
rt_same(0, $unsafeCalls, 'no legacy insecure mac_curl_get collection calls remain');
rt_same(9, $safeCalls, 'all nine remote collection request paths use safeGet');

$http = rt_functions(APP_PATH . 'common/util/HttpClient.php');
rt_check(isset($http['safeGet']), 'HttpClient::safeGet exists');
$safe = isset($http['safeGet']) ? $http['safeGet']['body'] : '';
rt_check(strpos($safe, 'resolvePublicIp') !== false, 'safeGet validates resolved destination IP');
rt_check(strpos($safe, 'CURLOPT_RESOLVE') !== false, 'safeGet pins validated destination IP');
rt_check(strpos($safe, 'CURLOPT_FOLLOWLOCATION, 0') !== false, 'safeGet validates redirects manually');

$ip = '\\app\\common\\util\\HttpClient';
rt_same(true, $ip::isPublicIpv4('8.8.8.8'), 'public IPv4 remains allowed');
foreach (['10.0.0.1', '172.16.0.1', '192.168.1.1', '127.0.0.1', '169.254.169.254', '100.64.0.1', '0.1.2.3', '240.0.0.1'] as $blocked) {
    rt_same(false, $ip::isPublicIpv4($blocked), 'non-public IPv4 rejected: ' . $blocked);
}

rt_done('collection SSRF protection');
