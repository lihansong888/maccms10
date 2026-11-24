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

$start = strpos($body, 'Db::startTrans();');
$log = strpos($body, "model('Plog')->saveData(");
$commit = strpos($body, 'Db::commit();');
rt_check($start !== false && $log !== false && $commit !== false && $start < $log && $log < $commit,
    '[card_tx] card claim, point credit and log write must remain inside one transaction');

$install = file_get_contents(ROOT_PATH . 'application/install/sql/install.sql');
rt_check($install !== false, '[card_tx] unable to read install SQL');
foreach (['user', 'card', 'msg', 'plog'] as $table) {
    $plogCreate = strpos($upgrade, 'CREATE TABLE `{$pre}plog`');
rt_check($plogCreate !== false
    && strpos(substr($upgrade, $plogCreate, 1600), 'ENGINE=InnoDB') !== false,
    '[card_tx] newly created mac_plog must use InnoDB');

rt_done('card redemption transaction');
