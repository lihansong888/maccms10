<?php
/**
 * 回归：TVBox 配置必须是合法 JSON，并指向 MacCMS 原生 Provider API。
 */
$root = dirname(__DIR__, 2);
$file = $root . '/tvbox.json';
if (!is_file($file)) {
    fwrite(STDERR, "FAIL: tvbox.json missing\n");
    exit(1);
}
$config = json_decode(file_get_contents($file), true);
if (!is_array($config) || empty($config['sites']) || !is_array($config['sites'])) {
    fwrite(STDERR, "FAIL: tvbox.json must contain a sites array\n");
    exit(1);
}
$site = $config['sites'][0];
$checks = [
    isset($site['key']) && $site['key'] === 'hansong_maccms',
    isset($site['type']) && (int)$site['type'] === 1,
    isset($site['api']) && $site['api'] === 'https://tv.lihansong.top/api.php/provide/vod/',
    isset($site['searchable']) && (int)$site['searchable'] === 1,
    isset($site['quickSearch']) && (int)$site['quickSearch'] === 1,
    isset($site['filterable']) && (int)$site['filterable'] === 1,
];
foreach ($checks as $index => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: TVBox site configuration assertion " . ($index + 1) . "\n");
        exit(1);
    }
}
echo "OK: TVBox CMS API configuration\n";
