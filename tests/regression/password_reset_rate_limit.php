<?php
/**
 * 回归：密码找回验证码错误尝试按收件地址限速，成功后清除计数。
 * 跑法：php tests/regression/password_reset_rate_limit.php
 */
require __DIR__ . '/lib/bootstrap.php';

$functions = rt_functions(APP_PATH . 'common/model/User.php');
rt_check(isset($functions['findpass_reset']), 'User::findpass_reset exists');
$body = isset($functions['findpass_reset']) ? $functions['findpass_reset']['body'] : '';

rt_check(strpos($body, 'Cache::get($rateKey, 0)') !== false, 'failed-attempt counter is checked before verification');
rt_check(strpos($body, '$failedAttempts >= 20') !== false, 'password recovery is capped at twenty failed attempts');
rt_check(strpos($body, 'Cache::inc($rateKey)') !== false, 'failed code checks increment the counter');
rt_check(strpos($body, 'Cache::set($rateKey, 1, 900)') !== false, 'counter receives a fifteen-minute expiry');
rt_check(strpos($body, 'intval($res[\'code\']) === 9002') !== false, 'only invalid or unavailable verification codes increment the counter');
rt_check(strpos($body, 'Cache::rm($rateKey)') !== false, 'successful password reset clears the counter');

rt_done('password reset rate limit');
