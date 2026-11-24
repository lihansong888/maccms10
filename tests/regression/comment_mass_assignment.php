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

$indexComment = rt_functions(APP_PATH . 'index/controller/Comment.php');
$commentBody = isset($indexComment['saveData']) ? $indexComment['saveData']['body'] : '';
rt_check(strpos($commentBody, 'model(\'User\')->checkLogin()') !== false, 'site comment identity comes from verified login');
rt_check(strpos($commentBody, '$authUser[\'user_id\']') !== false, 'site comment uses verified user id');
rt_same(false, strpos($commentBody, 'intval(cookie(\'user_id\'))') !== false, 'site comment never trusts raw user id cookie');

$guestbook = rt_functions(APP_PATH . 'index/controller/Gbook.php');
$guestbookBody = isset($guestbook['saveData']) ? $guestbook['saveData']['body'] : '';
rt_check(strpos($guestbookBody, 'array_flip([\'gbook_content\', \'gbook_name\', \'verify\'])') !== false, 'site guestbook uses an explicit input allowlist');
rt_check(strpos($guestbookBody, 'model(\'User\')->checkLogin()') !== false, 'site guestbook identity comes from verified login');
rt_check(strpos($guestbookBody, '$authUser[\'user_id\']') !== false, 'site guestbook uses verified user id');
rt_check(strpos($guestbookBody, "'gbook_status'] = ($GLOBALS['config']['gbook']['audit'] == 1) ? 0 : 1") !== false, 'guestbook status is always server-controlled');

$apiComment = rt_functions(APP_PATH . 'api/controller/Comment.php');
$apiCommentBody = isset($apiComment['submit']) ? $apiComment['submit']['body'] : '';
rt_check(strpos($apiCommentBody, '$request->isPost()') !== false, 'comment API submit requires POST');
$apiGuestbook = rt_functions(APP_PATH . 'api/controller/Gbook.php');
$apiGuestbookBody = isset($apiGuestbook['submit']) ? $apiGuestbook['submit']['body'] : '';
rt_check(strpos($apiGuestbookBody, '$request->isPost()') !== false, 'guestbook API submit requires POST');
rt_check(strpos($apiGuestbookBody, '$authUser[\'user_id\']') !== false, 'guestbook API uses verified user id');

rt_done('public comment mass assignment protection');
