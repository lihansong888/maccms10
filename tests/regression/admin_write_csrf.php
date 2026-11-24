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
    'Admin'         => ['info', 'del', 'field'],
    'Card'          => ['del', 'info'],
    'Collect'       => ['test', 'info', 'del', 'healthtoggle'],
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
    'Cj'            => ['info', 'del', 'import', 'program'],
    'Actor'          => ['info', 'del', 'field', 'contentLangTranslate'],
    'Annex'          => ['info', 'del'],
    'Art'            => ['info', 'contentLangTranslate', 'aiSeoGenerate', 'restore', 'del', 'field', 'updateToday'],
    'BatchPlayer'    => ['batchStatus', 'batchSort', 'batchParse', 'batchDel', 'replaceFrom'],
    'Chatroom'       => ['del', 'field'],
    'Danmaku'        => ['del', 'field'],
    'Dynamics'       => ['del'],
    'Follow'         => ['del', 'field'],
    'Gbook'          => ['info', 'del', 'field'],
    'Link'           => ['info', 'del', 'batch', 'contentLangTranslate'],
    'MallGoods'      => ['info', 'del', 'field'],
    'Manga'          => ['info', 'contentLangTranslate', 'aiSeoGenerate', 'restore', 'del', 'field', 'updateToday', 'batchGenerateTag'],
    'Meilisearch'    => ['save', 'setup', 'sync'],
    'ResourceHub'    => ['syncTypes', 'autoBind', 'autoPlayer', 'addToCollect', 'addTimming', 'quickCollect', 'addCustomSite', 'delCustomSite', 'setRecommend', 'cleanDuplicateTypes'],
    'Role'           => ['info', 'del', 'field'],
    'ShareLog'       => ['del'],
    'SignMilestone'  => ['info', 'del', 'field'],
    'Topic'          => ['info', 'del', 'field', 'contentLangTranslate'],
    'Ulog'           => ['del'],
    'User'           => ['info', 'del', 'field', 'generateInviteCode'],
    'Visit'          => ['del'],
    'Website'        => ['info', 'contentLangTranslate', 'del', 'field', 'updateToday'],
    'ContentQuality' => ['markRecommend'],
    'AiAnnotation'   => ['generate', 'adopt', 'reject'],
    'Domain'         => ['del', 'import'],

    'Vod'           => ['info', 'restore', 'del', 'field', 'audit', 'contentLangTranslate', 'aiSeoGenerate', 'aiCoverGenerate', 'aiCoverRevert', 'iplot', 'updateToday'],
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

// 模板编辑器默认关闭；即使管理员显式开启，也只能读写 template 根目录内的真实文件。
$templateInfo = rt_functions(APP_PATH . 'admin/controller/Template.php');
rt_check(isset($templateInfo['info']), '[template_editor] Template::info missing');
if (isset($templateInfo['info'])) {
    $body = $templateInfo['info']['body'];
    rt_check(strpos($body, "realpath(ROOT_PATH . 'template')") !== false
        && strpos($body, "realpath(ROOT_PATH . substr(\$fullname, 2))") !== false
        && strpos($body, 'strpos($target, $templatePrefix)') !== false,
        '[template_editor] Template::info must confine file access to the real template root');
    rt_check(strpos($body, "Loader::validate('Token')") !== false,
        '[template_editor] Template::info writes must retain CSRF token validation');
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

// 被强制改为 POST 的按钮也必须显式选择 POST，避免后台 JS 默认 GET 导致功能失效。
$cashView = file_get_contents(APP_PATH . 'admin/view_new/cash/index.html');
rt_check(strpos($cashView, "data-method=\"post\"") !== false
    && substr_count($cashView, "data-method=\"post\"") >= 3,
    '[forced_csrf_ui] cash delete / clear / audit controls must use POST');
$userView = file_get_contents(APP_PATH . 'admin/view_new/user/index.html');
rt_check(strpos($userView, "class=\"layui-badge-rim j-tr-del\" data-method=\"post\"") !== false,
    '[forced_csrf_ui] user row delete must use POST');
$adminView = file_get_contents(APP_PATH . 'admin/view_new/admin/index.html');
rt_check(strpos($adminView, "class=\"layui-badge-rim j-tr-del\" data-method=\"post\"") !== false,
    '[forced_csrf_ui] administrator row delete must use POST');
$cardView = file_get_contents(APP_PATH . 'admin/view_new/card/index.html');
rt_check(strpos($cardView, "class=\"layui-badge-rim j-tr-del\" data-method=\"post\"") !== false
    && strpos($cardView, "data-method=\"post\"") !== false,
    '[forced_csrf_ui] card deletion and bulk clear must use POST');
$collectView = file_get_contents(APP_PATH . 'admin/view_new/collect/index.html');
rt_check(strpos($collectView, "class=\"layui-badge-rim j-tr-del\" data-method=\"post\"") !== false,
    '[forced_csrf_ui] collection deletion must use POST');

// 这些列表页的行删除/清空按钮必须走 POST，避免后端加固后前端仍发 GET。
foreach ([
    'actor/index.html',
    'chatroom/index.html',
    'danmaku/index.html',
    'dynamics/index.html',
    'follow/index.html',
    'gbook/index.html',
    'link/index.html',
    'mall_goods/index.html',
    'role/index.html',
    'share_log/index.html',
    'sign_milestone/index.html',
    'topic/index.html',
    'ulog/index.html',
    'visit/index.html',
    'website/index.html',
    'domain/index.html',
    'art/index.html',
    'manga/index.html',
] as $view) {
    $html = (string)@file_get_contents(APP_PATH . 'admin/view_new/' . $view);
    rt_check(strpos($html, 'j-tr-del') === false || strpos($html, 'data-method="post"') !== false,
        "[forced_csrf_ui] {$view}: row delete must submit POST");
}
$meiliView = (string)@file_get_contents(APP_PATH . 'admin/view_new/meilisearch/index.html');
rt_check(strpos($meiliView, "input[name=\\"__token__\\"]") !== false,
    '[forced_csrf_ui] Meilisearch setup must submit the page CSRF token');
$meiliSyncView = (string)@file_get_contents(APP_PATH . 'admin/view_new/meilisearch/sync.html');
rt_check(strpos($meiliSyncView, 'mac_admin_csrf_token()') !== false,
    '[forced_csrf_ui] Meilisearch full sync must submit the page CSRF token');
$collectInfoView = (string)@file_get_contents(APP_PATH . 'admin/view_new/collect/info.html');
rt_check(strpos($collectInfoView, "__token__=") !== false,
    '[forced_csrf_ui] collection source test must submit the page CSRF token');

rt_done('admin write endpoints forced CSRF');
