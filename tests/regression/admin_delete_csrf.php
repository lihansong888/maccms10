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

$fieldControllers = [
    'Task','Live','Role','Type','User','Actor','Group','Gbook','Topic','Admin','Follow','Notify',
    'UserPm','Coupon','Website','Comment','Danmaku','Seckill','Timming','Chatroom','MallGoods',
    'Voddowner','Vodplayer','Vodserver','VodAuditRule','SignMilestone','Art','Manga','Vod',
];
foreach ($fieldControllers as $name) {
    $functions = rt_functions(APP_PATH . "admin/controller/{$name}.php");
    rt_check(isset($functions['field']), "[field_csrf] {$name}::field exists");
    if (isset($functions['field'])) {
        $body = $functions['field']['body'];
        rt_check(strpos($body, 'Request()->isPost()') !== false, "[field_csrf] {$name}::field rejects GET");
        rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[field_csrf] {$name}::field validates CSRF");
    }
}

foreach (['Cash', 'Vod'] as $name) {
    $functions = rt_functions(APP_PATH . "admin/controller/{$name}.php");
    rt_check(isset($functions['audit']), "[audit_csrf] {$name}::audit exists");
    if (isset($functions['audit'])) {
        $body = $functions['audit']['body'];
        rt_check(strpos($body, 'Request()->isPost()') !== false, "[audit_csrf] {$name}::audit rejects GET");
        rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[audit_csrf] {$name}::audit validates CSRF");
    }
}

$monitor = rt_functions(APP_PATH . 'admin/controller/Monitor.php');
foreach (['rule_save','rule_del','rule_field','rule_enable_recommended','rule_test'] as $action) {
    rt_check(isset($monitor[$action]), "[monitor_csrf] Monitor::{$action} exists");
    if (isset($monitor[$action])) {
        $body = $monitor[$action]['body'];
        rt_check(strpos($body, 'Request()->isPost()') !== false, "[monitor_csrf] {$action} rejects GET");
        rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[monitor_csrf] {$action} validates CSRF");
    }
}
$monitorView = file_get_contents(APP_PATH . 'admin/view_new/monitor/rule.html');
rt_check(strpos($monitorView, "X-CSRF-Token") !== false, '[monitor_csrf] fetch actions send token header');
$monitorInfoView = file_get_contents(APP_PATH . 'admin/view_new/monitor/rule_info.html');
rt_check(strpos($monitorInfoView, "X-CSRF-Token") !== false, '[monitor_csrf] rule save sends token header');
rt_check(strpos($monitorView, "fetch(DEL_URL, {method: 'POST'") !== false, '[monitor_csrf] rule deletion uses POST');

$notify = rt_functions(APP_PATH . 'admin/controller/Notify.php');
rt_check(isset($notify['broadcast']), '[notify_csrf] Notify::broadcast exists');
if (isset($notify['broadcast'])) {
    rt_check(strpos($notify['broadcast']['body'], 'Request()->isPost()') !== false, '[notify_csrf] broadcast rejects GET');
    rt_check(strpos($notify['broadcast']['body'], '$this->adminCsrfOk()') !== false, '[notify_csrf] broadcast validates CSRF');
}

$addon = rt_functions(APP_PATH . 'admin/controller/Addon.php');
foreach (['state','uninstall'] as $action) {
    rt_check(isset($addon[$action]), "[addon_csrf] Addon::{$action} exists");
    if (isset($addon[$action])) {
        $body = $addon[$action]['body'];
        rt_check(strpos($body, 'isPost()') !== false, "[addon_csrf] {$action} requires POST");
        rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[addon_csrf] {$action} validates stable admin CSRF token");
    }
}
$addonView = file_get_contents(APP_PATH . 'admin/view_new/box/index.html');
rt_check(substr_count($addonView, "type: 'post'") >= 2, '[addon_csrf] addon state and uninstall use POST');

$extraActions = [
    'Index' => ['clear','check_back_link'],
    'Database' => ['uploadStorage','import','optimize','repair','export','sql','rep'],
    'Task' => ['log_del'],
    'User' => ['generateInviteCode'],
    'Cj' => ['content_del','content_into'],
];
foreach ($extraActions as $controller => $actions) {
    $functions = rt_functions(APP_PATH . "admin/controller/{$controller}.php");
    foreach ($actions as $action) {
        rt_check(isset($functions[$action]), "[write_csrf] {$controller}::{$action} exists");
        if (isset($functions[$action])) {
            $body = $functions[$action]['body'];
            rt_check(strpos($body, 'isPost()') !== false, "[write_csrf] {$controller}::{$action} rejects GET");
            rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[write_csrf] {$controller}::{$action} validates CSRF");
        }
    }
}

$cj = rt_functions(APP_PATH . 'admin/controller/Cj.php');
foreach (['info','program','col_all','col_url','col_content','content_del','content_into','import'] as $action) {
    rt_check(isset($cj[$action]), "[cj_csrf] Cj::{$action} exists");
    if (isset($cj[$action])) {
        $body = $cj[$action]['body'];
        rt_check(strpos($body, 'adminCsrfOk()') !== false, "[cj_csrf] {$action} validates CSRF");
        if ($action !== 'info') {
            rt_check(strpos($body, 'Request()->isPost()') !== false, "[cj_csrf] {$action} requires POST");
        }
    }
}
$cjView = file_get_contents(APP_PATH . 'admin/view_new/cj/index.html');
rt_check(strpos($cjView, 'j-iframe" data-method="post" data-href="{:url(\'col_url\')') !== false, '[cj_csrf] URL collection iframe uses POST');
rt_check(strpos($cjView, 'j-iframe" data-method="post" data-href="{:url(\'col_content\')') !== false, '[cj_csrf] content collection iframe uses POST');
rt_check(strpos($cjView, "data: {__token__: window.__MAC_ADMIN_CSRF__") !== false, '[cj_csrf] rule import upload sends CSRF token');

$uploadActions = [
    'Upload' => ['upload'],
    'ThemeDesign' => ['upload'],
    'Domain' => ['import'],
];
foreach ($uploadActions as $controller => $actions) {
    $functions = rt_functions(APP_PATH . "admin/controller/{$controller}.php");
    foreach ($actions as $action) {
        rt_check(isset($functions[$action]), "[upload_csrf] {$controller}::{$action} exists");
        if (isset($functions[$action])) {
            $body = $functions[$action]['body'];
            rt_check(strpos($body, 'isPost()') !== false, "[upload_csrf] {$controller}::{$action} requires POST");
            rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[upload_csrf] {$controller}::{$action} validates CSRF");
        }
    }
}
$uploadViews = [
    'annex/info.html','user/info.html','role/info.html','type/info.html','topic/info.html',
    'manga/info.html','actor/info.html','vod/info.html','art/info.html','website/info.html',
    'system/config.html','tplconfig/theme.html','addon/config.html','domain/index.html',
    'themedesign/design.html','cj/index.html',
];
foreach ($uploadViews as $view) {
    $source = file_get_contents(APP_PATH . 'admin/view_new/' . $view);
    rt_check(strpos($source, '__token__') !== false, "[upload_csrf] {$view} sends upload token");
}
$addonLocalView = file_get_contents(APP_PATH . 'admin/view_new/addon/add.html');
rt_check(strpos($addonLocalView, "__token__: function") !== false, '[upload_csrf] addon local installer sends legacy token');
$playerImportView = file_get_contents(APP_PATH . 'admin/view_new/vodplayer/import.html');
rt_check(strpos($playerImportView, '__token__') !== false, '[upload_csrf] player import sends legacy token');

foreach (['static/js/admin_common.js', 'static_new/js/admin_common.js'] as $path) {
    $js = file_get_contents(ROOT_PATH . $path);
    rt_check(strpos($js, "String(method || 'post')") !== false, "[delete_csrf] {$path} defaults admin AJAX actions to POST");
    rt_check(strpos($js, "postForm.submit()") !== false, "[iframe_csrf] {$path} supports POST iframe forms");
    rt_check(strpos($js, "window.__MAC_ADMIN_CSRF__") !== false, "[iframe_csrf] {$path} sends the stable admin token");
}

rt_done('admin delete CSRF coverage');
