<?php
/**
 * 回归：卡密兑换必须原子占用卡密、增加积分并写入积分日志。
 * 同时确认新安装与旧库升级都使用 InnoDB，保证事务真实生效。
 */
require __DIR__ . '/lib/bootstrap.php';

$cardFns = rt_functions(APP_PATH . 'common/model/Card.php');
rt_check(isset($cardFns['useData']), '[card_tx] Card::useData missing');
$body = isset($cardFns['useData']) ? $cardFns['useData']['body'] : '';

foreach ([
    'Db::startTrans();' => 'transaction must start',
    "'card_use_status' => ['eq', 0]" => 'card must be claimed with unused status condition',
    'if ($affected !== 1)' => 'only one concurrent redeemer may win',
    "model('User')->where('user_id'" => 'points must be credited to the selected user',
    "model('Plog')->saveData(" => 'points log must be written in the same transaction',
    'Db::commit();' => 'successful redemption must commit',
    'catch (\\Throwable $e)' => 'all throwable failures must roll back',
    'Db::rollback();' => 'failed redemption must roll back',
] as $needle => $message) {
    rt_check(strpos($body, $needle) !== false, '[card_tx] ' . $message);
}

$install = file_get_contents(ROOT_PATH . 'application/install/sql/install.sql');
rt_check($install !== false, '[card_tx] unable to read install SQL');
foreach (['user', 'card', 'msg', 'plog'] as $table) {
    rt_check((bool)preg_match('/CREATE TABLE `mac_' . $table . '`[\\s\\S]*?\\) ENGINE=InnoDB\\b/', $install),
        "[card_tx] new install must create mac_{$table} as InnoDB");
}

$upgrade = file_get_contents(APP_PATH . 'data/update/database.php');
rt_check($upgrade !== false, '[card_tx] unable to read database upgrade script');
foreach (['card', 'msg'] as $table) {
    rt_check(strpos($upgrade, "\$pre . '{$table}'") !== false,
        "[card_tx] legacy database upgrade must migrate mac_{$table} to InnoDB");
}
rt_check(strpos($upgrade, '$engine_list[$innodb_table]') !== false,
    '[card_tx] upgrade must use exact table engine metadata');

rt_done('card redemption transaction');
