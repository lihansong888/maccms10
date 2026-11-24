<?php
/**
 * 回归：播放器预加载/缓冲提示默认不再请求第三方域名。
 * 跑法：php tests/regression/player_remote_defaults.php
 */
require __DIR__ . '/lib/bootstrap.php';

$config = file_get_contents(APP_PATH . 'extra/maccms.php');
rt_check(strpos($config, "'prestrain' => ''") !== false, 'preload prompt default is empty');
rt_check(strpos($config, "'buffer' => ''") !== false, 'buffer prompt default is empty');

foreach (['admin/view_new/system/configplay.html', 'admin/view_new/system/configupload.html'] as $path) {
    $view = file_get_contents(APP_PATH . $path);
    rt_check(strpos($view, "play[prestrain]").val('')") !== false, "{$path}: reset does not restore third-party preload");
    rt_check(strpos($view, "play[buffer]").val('')") !== false, "{$path}: reset does not restore third-party buffer");
}

rt_done('player remote defaults');
