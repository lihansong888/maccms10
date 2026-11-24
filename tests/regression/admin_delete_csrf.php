<?php
/**
 * 回归：后台所有控制器的删除入口统一要求 POST + CSRF。
 * 跑法：php tests/regression/admin_delete_csrf.php
 */
require __DIR__ . '/lib/bootstrap.php';

$controllers = [
    'Task','Cash','Card','Link','Plog','Ulog','Live','Role','Type','User','Order','Actor',
    'Group','Gbook','Annex','Visit','Topic','Admin','Follow','Notify','UserPm','Domain',
    'Coupon','Images','Website','Comment','Danmaku','Seckill','Timming','Database',
    'Dynamics','Chatroom','Template','ShareLog','MallGoods','Voddowner','Vodplayer',
    'Vodserver','Vodplayfail','VodAuditRule','SignMilestone','UserAccessLog','Collect',
    'Cj','Art','Manga',
];
foreach ($controllers as $name) {
    $functions = rt_functions(APP_PATH . "admin/controller/{$name}.php");
    rt_check(isset($functions['del']), "[delete_csrf] {$name}::del exists");
    if (isset($functions['del'])) {
        $body = $functions['del']['body'];
        rt_check(strpos($body, 'Request()->isPost()') !== false, "[delete_csrf] {$name}::del rejects GET");
        rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[delete_csrf] {$name}::del validates CSRF");
    }
}

foreach (['static/js/admin_common.js', 'static_new/js/admin_common.js'] as $path) {
    $js = file_get_contents(ROOT_PATH . $path);
    rt_check(strpos($js, "String(method || 'post')") !== false, "[delete_csrf] {$path} defaults admin AJAX actions to POST");
}

rt_done('admin delete CSRF coverage');
