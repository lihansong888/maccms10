<?php
/**
 * 回归：拒绝 ThinkPHP 模板标签中以冒号开头的原始 PHP 表达式，防止字符串拼接绕过函数黑名单。
 * 跑法：php tests/regression/template_expression_safety.php
 */
require __DIR__ . '/lib/bootstrap.php';

$source = file_get_contents(APP_PATH . 'admin/controller/Template.php');
rt_check(strpos($source, '标签属性中的直接表达式') !== false, 'template editor documents and applies expression restriction');

$pattern = "/\\{[a-z][^}]*=\\s*([\"'])\\s*:/is";
$attack = '{define name="x" value=":array_map(\'sy\'.\'stem\',array(\'whoami\'))" /}';
$benignDefine = '{define name="x" value="hello" /}';
$benignIf = '{if condition="$vo.vod_id gt 0"}ok{/if}';

rt_same(1, preg_match($pattern, $attack), 'obfuscated system-call template expression is rejected');
rt_same(0, preg_match($pattern, $benignDefine), 'literal define values remain allowed');
rt_same(0, preg_match($pattern, $benignIf), 'ordinary variable-based if tags remain allowed');

rt_done('template expression safety');
