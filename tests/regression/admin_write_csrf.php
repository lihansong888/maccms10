<?php
/**
 * 回归：后台敏感写接口强制 CSRF，不依赖全局开关 security_csrf_admin（默认 '0'）。
 * 来源：review P1「新后台写接口 CSRF 默认未生效」——跨站 POST 可启停采集接口 / 批量启停内容。
 *
 * 跑法：php tests/regression/admin_write_csrf.php
 */
require __DIR__ . '/lib/bootstrap.php';

// ─── 列 forced_csrf：以下写接口必须在入口调用 $this->adminCsrfOk() ───
$forced = [
    'Collect'       => ['healthtoggle'],
    'ResourceCheck' => ['enable', 'disable'],
];
foreach ($forced as $cls => $fns) {
    $funcs = rt_functions(APP_PATH . "admin/controller/{$cls}.php");
    foreach ($fns as $fn) {
        rt_check(isset($funcs[$fn]), "[forced_csrf] {$cls}::{$fn} not found");
        if (isset($funcs[$fn])) {
            rt_check(strpos($funcs[$fn]['body'], '$this->adminCsrfOk()') !== false,
                "[forced_csrf] {$cls}::{$fn}: must call \$this->adminCsrfOk() (global security_csrf_admin is off by default)");
        }
    }
}

// Base::adminCsrfOk 必须走与 CsrfGuard 相同的判定，且不读全局开关
$base = rt_functions(APP_PATH . 'admin/controller/Base.php');
rt_check(isset($base['adminCsrfOk']), '[forced_csrf] Base::adminCsrfOk missing');
if (isset($base['adminCsrfOk'])) {
    $b = $base['adminCsrfOk']['body'];
    rt_check(strpos($b, 'CsrfGuard::tokenAccepted(') !== false, '[forced_csrf] adminCsrfOk must delegate to CsrfGuard::tokenAccepted');
    rt_check(strpos($b, 'security_csrf_admin') === false, '[forced_csrf] adminCsrfOk must not depend on security_csrf_admin');
}

// tokenAccepted 判定本身
$g = '\\app\\common\\behavior\\CsrfGuard';
rt_same(false, $g::tokenAccepted('', 'abc', ''), 'empty submitted token rejected');
rt_same(false, $g::tokenAccepted('x', '', ''), 'no session token → rejected');
rt_same(false, $g::tokenAccepted('x', 'abc', 'def'), 'wrong token rejected');
rt_same(true, $g::tokenAccepted('abc', 'abc', ''), 'stable token accepted');
rt_same(true, $g::tokenAccepted('def', 'abc', 'def'), 'legacy token accepted');

// 已披露的 GET-CSRF 写接口：必须同时拒绝 GET 并校验会话 CSRF 令牌。
$csrfActions = [
    'Cash' => ['del', 'audit'],
    'User' => ['del', 'field'],
];
foreach ($csrfActions as $cls => $actions) {
    $funcs = rt_functions(APP_PATH . "admin/controller/{$cls}.php");
    foreach ($actions as $action) {
        rt_check(isset($funcs[$action]), "[csrf] {$cls}::{$action} exists");
        if (isset($funcs[$action])) {
            $body = $funcs[$action]['body'];
            rt_check(strpos($body, 'Request()->isPost()') !== false, "[csrf] {$cls}::{$action} rejects GET");
            rt_check(strpos($body, '$this->adminCsrfOk()') !== false, "[csrf] {$cls}::{$action} validates token");
        }
    }
}

$cashView = file_get_contents(APP_PATH . 'admin/view_new/cash/index.html');
rt_check(strpos($cashView, 'j-tr-del" data-method="post"') !== false, '[csrf] cash row delete sends POST');
rt_check(strpos($cashView, 'j-ajax" data-method="post"') !== false, '[csrf] cash audit/clear actions send POST');
$userView = file_get_contents(APP_PATH . 'admin/view_new/user/index.html');
rt_check(strpos($userView, 'data-method="post"') !== false, '[csrf] user status and row delete send POST');

foreach (['static/js/admin_common.js', 'static_new/js/admin_common.js'] as $jsPath) {
    $js = file_get_contents(ROOT_PATH . $jsPath);
    rt_check(strpos($js, 'function macAdminActionRequest') !== false, "[csrf] {$jsPath} supports method-aware actions");
    rt_check(strpos($js, "that.attr('data-method')") !== false, "[csrf] {$jsPath} reads POST method from protected controls");
}

rt_done('admin write endpoints forced CSRF');
