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
    'Admin'         => ['del', 'field'],
    'Card'          => ['del', 'info'],
    'Collect'       => ['del', 'healthtoggle'],
    'Coupon'        => ['save', 'del', 'field'],
    'DataReplace'   => ['doReplace'],
    'Database'      => ['export', 'uploadStorage', 'import', 'optimize', 'repair', 'del'],
    'Group'         => ['info', 'del', 'field'],
    'Images'        => ['del', 'sync'],
    'Live'          => ['info', 'del', 'field'],
    'Monitor'       => ['setting_save', 'cron_token_reset', 'rule_save', 'rule_del', 'rule_field', 'rule_enable_recommended', 'rule_test', 'event_ack', 'access_ban', 'access_unban'],
    'Notify'        => ['broadcast', 'del', 'field'],
    'Order'         => ['del'],
    'Plog'          => ['del'],
    'ResourceCheck' => ['enable', 'disable'],
    'Seckill'       => ['save', 'del', 'field'],
    'Task'          => ['info', 'del', 'field', 'log_del'],
    'ThemeDesign'   => ['save_step', 'preview', 'init_page', 'publish', 'create_from_preset', 'activate_site_theme', 'rollback', 'upload', 'generate', 'detect', 'generate_apply', 'gen_image'],
    'ThemeGen'      => ['index'],
    'Template'      => ['del'],
    'Timming'       => ['del', 'field'],
    'Type'          => ['contentLangTranslate', 'del', 'field', 'batch', 'move'],
    'UserPm'        => ['del', 'field'],
    'Cj'            => ['info', 'del', 'import'],
    'Vod'           => ['info', 'restore', 'del', 'field', 'audit'],
    'VodAuditRule'  => ['info', 'del', 'field'],
    'Vodplayer'     => ['del', 'field'],
    'Vodserver'     => ['del', 'field'],
];
foreach ($forced as $cls => $fns) {
    $funcs = rt_functions(APP_PATH . "admin/controller/{$cls}.php");
    foreach ($fns as $fn) {
        rt_check(isset($funcs[$fn]), "[forced_csrf] {$cls}::{$fn} not found");
        if (isset($funcs[$fn])) {
            rt_check(strpos($funcs[$fn]['body'], '$this->adminCsrfOk()') !== false,
                "[forced_csrf] {$cls}::{$fn}: must call \$this->adminCsrfOk() (global security_csrf_admin is off by default)");
            $postGuard = strpos($funcs[$fn]['body'], 'Request()->isPost()') !== false
                || strpos($funcs[$fn]['body'], 'request()->isPost()') !== false
                || strpos($funcs[$fn]['body'], '$this->request->isPost()') !== false;
            // ResourceCheck::enable/disable share a private targetIds() helper that rejects non-POST requests.
            if (!$postGuard && strpos($funcs[$fn]['body'], 'targetIds()') !== false && isset($funcs['targetIds'])) {
                $postGuard = strpos($funcs['targetIds']['body'], 'Request()->isPost()') !== false;
            }
            rt_check($postGuard,
                "[forced_csrf] {$cls}::{$fn}: must reject non-POST requests directly or through its request-validation helper");
        }
    }
}

// 在线升级分步流程保留 GET 重定向，但每一步必须校验同一会话 CSRF 令牌。
$update = rt_functions(APP_PATH . 'admin/controller/Update.php');
foreach (['step1', 'step2', 'step3'] as $fn) {
    rt_check(isset($update[$fn]), "[updater_csrf] Update::{$fn} not found");
    if (isset($update[$fn])) {
        rt_check(strpos($update[$fn]['body'], '$this->updateCsrfOk()') !== false,
            "[updater_csrf] Update::{$fn} must validate session token");
    }
}
rt_check(isset($update['updateCsrfOk'])
    && strpos($update['updateCsrfOk']['body'], 'CsrfGuard::tokenAccepted(') !== false,
    '[updater_csrf] Update::updateCsrfOk must delegate to CsrfGuard');

// 文件删除接口必须先 realpath，再确认目标仍在允许目录内。
$images = rt_functions(APP_PATH . 'admin/controller/Images.php');
if (isset($images['del'])) {
    rt_check(strpos($images['del']['body'], "realpath(ROOT_PATH . 'upload')") !== false,
        '[upload_delete] Images::del must resolve upload root');
    rt_check(strpos($images['del']['body'], 'strpos($target, $prefix)') !== false,
        '[upload_delete] Images::del must enforce upload directory boundary');
}
$template = rt_functions(APP_PATH . 'admin/controller/Template.php');
if (isset($template['del'])) {
    rt_check(strpos($template['del']['body'], 'realpath(ROOT_PATH . substr($a, 2))') !== false,
        '[template_delete] Template::del must resolve target path');
    rt_check(strpos($template['del']['body'], 'strpos($target, $prefix)') !== false,
        '[template_delete] Template::del must enforce template directory boundary');
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

rt_done('admin write endpoints forced CSRF');
