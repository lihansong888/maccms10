<?php
/**
 * 回归：演员与网址列表的 FIND_IN_SET 排序参数必须经数据库驱动引用。
 * 跑法：php tests/regression/sql_order_injection.php
 */
require __DIR__ . '/lib/bootstrap.php';

$checks = [
    'Website' => [APP_PATH . 'common/model/Website.php', 'find_in_set(website_name,'],
    'Actor' => [APP_PATH . 'common/model/Actor.php', 'find_in_set(actor_name,'],
];
foreach ($checks as $name => $item) {
    $source = file_get_contents($item[0]);
    rt_check(strpos($source, $item[1]) !== false, $name . ' FIND_IN_SET sort remains available');
    rt_check(strpos($source, 'Db::quote((string)$name)') !== false, $name . ' sort value is quoted by PDO driver');
    $rawConcat = preg_match('/find_in_set\\([^\\n]*\\.\\s*\\$name\\s*\\./', $source);
    rt_same(0, $rawConcat, $name . ' sort value is not concatenated as raw SQL');
}

rt_done('FIND_IN_SET SQL injection protection');
