<?php
/**
 * 回归：模板编辑与删除只能访问真实 template 目录内的文件。
 * 跑法：php tests/regression/template_path_safety.php
 */
require __DIR__ . '/lib/bootstrap.php';
require_once APP_PATH . 'admin/controller/Template.php';

$class = '\\app\\admin\\controller\\Template';
$functions = rt_functions(APP_PATH . 'admin/controller/Template.php');
rt_check(isset($functions['resolveTemplateFilePath']), 'template path resolver exists');
$body = isset($functions['resolveTemplateFilePath']) ? $functions['resolveTemplateFilePath']['body'] : '';
rt_check(strpos($body, 'realpath(') !== false, 'path resolution uses canonical filesystem paths');
rt_check(strpos($body, "strpos(" . '$path' . ", './template/') !== 0") !== false, 'path must begin inside template root');
rt_check(strpos($body, "=== '..'") !== false, 'parent traversal segments are rejected');
rt_check(strpos($body, 'is_file($real)') !== false, 'existing targets must be regular files');
rt_check(strpos($body, 'strpos($real, $prefix) !== 0') !== false, 'resolved symlink targets must remain under template root');

$template = rt_bare($class);
rt_same(false, rt_invoke($template, 'resolveTemplateFilePath', ['./template/../application/config.php', false]), 'single parent traversal rejected');
rt_same(false, rt_invoke($template, 'resolveTemplateFilePath', ['./template/../../application/config.php', false]), 'multi-level parent traversal rejected');
rt_same(false, rt_invoke($template, 'resolveTemplateFilePath', ['/etc/passwd', false]), 'absolute paths rejected');

$delete = isset($functions['del']) ? $functions['del']['body'] : '';
rt_check(strpos($delete, 'Request()->isPost()') !== false, 'template deletion rejects GET');
rt_check(strpos($delete, '$this->adminCsrfOk()') !== false, 'template deletion validates CSRF token');
$view = file_get_contents(APP_PATH . 'admin/view_new/template/index.html');
rt_check(strpos($view, 'j-tr-del" data-method="post"') !== false, 'template row deletion sends POST');
rt_check(strpos($view, '$.post("{:url(\'template/del\')}"') !== false, 'template bulk deletion sends POST');

rt_done('template path safety');
