<?php
/**
 * 回归：公共评论接口不允许客户端伪造 user_id、comment_id、审核状态或计数器。
 * 跑法：php tests/regression/comment_mass_assignment.php
 */
require __DIR__ . '/lib/bootstrap.php';

$functions = rt_functions(APP_PATH . 'index/controller/Comment.php');
rt_check(isset($functions['saveData']), 'public Comment::saveData exists');
$body = isset($functions['saveData']) ? $functions['saveData']['body'] : '';

rt_check(strpos($body, '$allowedFields = [') !== false, 'public comment input uses an explicit allowlist');
foreach (['user_id', 'comment_id', 'comment_status', 'comment_ip', 'comment_up', 'comment_down', 'comment_report', 'comment_name'] as $field) {
    rt_same(false, strpos($body, "'{$field}'") !== false && strpos($body, '$allowedFields') !== false && strpos(substr($body, strpos($body, '$allowedFields'), 350), "'{$field}'") !== false,
        "untrusted {$field} is not allowlisted");
}
rt_check(strpos($body, "\$param['user_id'] = 0;") !== false, 'guest identity is forced to user id zero');
rt_check(strpos($body, "\$param['user_id'] = intval(cookie('user_id'));") !== false, 'logged-in identity comes from authenticated cookie');
rt_check(strpos($body, "unset(\$param['comment_id'])") === false, 'comment edit id is excluded by allowlist');

rt_done('public comment mass assignment protection');
