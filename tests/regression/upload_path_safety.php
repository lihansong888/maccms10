<?php
/**
 * 回归：上传目录标识不能携带路径分隔符或目录跳转；头像目录只能使用当前登录用户 ID。
 *
 * 跑法：php tests/regression/upload_path_safety.php
 */
require __DIR__ . '/lib/bootstrap.php';

$src = (string) file_get_contents(APP_PATH . 'common/model/Upload.php');
rt_check(strpos($src, "preg_match('/\\\\A[a-zA-Z0-9_-]{1,32}\\\\z/D', \$param['flag'])") !== false,
    '[upload_path] upload flag must be restricted to one safe path segment');
rt_check(strpos($src, "\$param['user_id'] = \$currentUserId;") !== false,
    '[upload_path] avatar path must use authenticated user ID');

$valid = ['vod', 'actor', 'website', 'user', 'my_upload-1'];
foreach ($valid as $flag) {
    rt_check((bool) preg_match('/\\A[a-zA-Z0-9_-]{1,32}\\z/D', $flag), "[upload_path] valid flag rejected: {$flag}");
}
$invalid = ['../application', '..\\\\application', 'vod/../../config', '/tmp', 'a.b', 'two words', ''];
foreach ($invalid as $flag) {
    rt_check(!(bool) preg_match('/\\A[a-zA-Z0-9_-]{1,32}\\z/D', $flag), '[upload_path] unsafe flag accepted: ' . var_export($flag, true));
}

rt_done('upload path safety');
