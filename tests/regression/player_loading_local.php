<?php
/**
 * 回归：播放器预加载/缓冲页必须使用仓库内的中性页面，不依赖第三方远程页面。
 */
require __DIR__ . '/lib/bootstrap.php';

// Both shipped static trees contain local copies, so the selected player asset tree never needs a remote loading host.
$defaults = file_get_contents(APP_PATH . 'extra/maccms.php');
rt_check($defaults !== false, '[player_loading] unable to read player defaults');
rt_check(strpos($defaults, "'prestrain' => '/static_new/player/prestrain.html'") !== false,
    '[player_loading] default prestrain must use local static_new page');
rt_check(strpos($defaults, "'buffer' => '/static_new/player/loading.html'") !== false,
    '[player_loading] default buffer must use local static_new page');

foreach ([
    'static/js/playerconfig.js' => ['/static/player/prestrain.html', '/static/player/loading.html'],
    'static_new/js/playerconfig.js' => ['/static_new/player/prestrain.html', '/static_new/player/loading.html'],
] as $file => $paths) {
    $source = file_get_contents(ROOT_PATH . $file);
    rt_check($source !== false, "[player_loading] unable to read {$file}");
    $normalized = str_replace('\\/', '/', $source);
    foreach ($paths as $local) {
        rt_check(strpos($normalized, $local) !== false, "[player_loading] {$file} missing local URL {$local}");
    }
    rt_check(strpos($source, 'union.maccms.la') === false,
        "[player_loading] {$file} must not call the remote union host");
}
rt_check(is_file(ROOT_PATH . 'static/player/prestrain.html'), '[player_loading] static prestrain page missing');
rt_check(is_file(ROOT_PATH . 'static/player/loading.html'), '[player_loading] static loading page missing');
rt_check(is_file(ROOT_PATH . 'static_new/player/prestrain.html'), '[player_loading] static_new prestrain page missing');
rt_check(is_file(ROOT_PATH . 'static_new/player/loading.html'), '[player_loading] static_new loading page missing');

rt_done('local player loading pages');
