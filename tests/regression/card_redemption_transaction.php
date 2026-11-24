<?php
/**
 * 回归：卡密兑换必须在单一数据库事务中原子消费，并同步更新积分与积分日志。
 * 跑法：php tests/regression/card_redemption_transaction.php
 */
require __DIR__ . '/lib/bootstrap.php';

$functions = rt_functions(APP_PATH . 'common/model/Card.php');
rt_check(isset($functions['useData']), 'Card::useData exists');
$body = isset($functions['useData']) ? $functions['useData']['body'] : '';

rt_check(strpos($body, 'Db::transaction(') !== false, 'card redemption runs in a database transaction');
rt_check(strpos($body, "'card_use_status' => ['eq', 0]") !== false, 'only unused cards can be redeemed');
rt_check(strpos($body, '$claimed != 1') !== false, 'conditional card claim rejects concurrent duplicate redemption');
rt_check(strpos($body, "setInc('user_points'") !== false, 'points are credited inside the transaction');
rt_check(strpos($body, "model('Plog')->saveData($data)") !== false, 'points log is written inside the transaction');
rt_check(strpos($body, 'CARD_POINT_LOG_FAILED') !== false, 'points log failure rolls back the transaction');
rt_check(strpos($body, 'catch (\\Exception $e)') !== false, 'transaction failures are handled');

rt_done('card redemption transaction');
