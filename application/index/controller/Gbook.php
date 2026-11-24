<?php
namespace app\index\controller;

use app\common\util\CsrfGuard;

class Gbook extends Base
{
    use CsrfGuard;

    var $_config;
    public function __construct()
    {
        parent::__construct();
        //关闭中
        if($GLOBALS['config']['gbook']['status'] == 0){
            echo 'gbook is close';
            exit;
        }
    }

    public function index()
    {
        if (Request()->isPost()) {
            return $this->saveData();
        }
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/index');
    }

    public function ajax()
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/ajax',0,'json');
    }

    public function report()
    {
        $param = mac_param_url();
        $this->assign('param',$param);
        $this->assign('gbook',$GLOBALS['config']['gbook']);
        return $this->label_fetch('gbook/report');
    }
    
    public function saveData() {
        if (!Request()->isPost()) {
            return ['code' => 1001, 'msg' => lang('param_err')];
        }
        $csrfErr = $this->checkCsrf();
        if ($csrfErr !== null) {
            return $csrfErr;
        }

        $param = input();
        // 留言接口只接受正文、访客显示名和验证码；身份、回复、审核、计数等字段由服务器生成。
        $param = array_intersect_key($param, array_flip(['gbook_content', 'gbook_name', 'verify']));

        if($GLOBALS['config']['gbook']['verify'] == 1){
            if(!captcha_check($param['verify'])){
                return ['code'=>1002,'msg'=>lang('verify_err')];
            }
        }

        // 始终验证登录身份；不能直接信任可伪造的 user_id / user_name Cookie。
        $authUser = null;
        $authResult = model('User')->checkLogin();
        if (isset($authResult['code']) && intval($authResult['code']) === 1 && !empty($authResult['info'])) {
            $authUser = $authResult['info'];
        }
        if ($GLOBALS['config']['gbook']['login'] == 1 && empty($authUser)) {
            return ['code' => 1003, 'msg' => lang('index/require_login')];
        }

        if(empty($param['gbook_content'])){
            return ['code'=>1004,'msg'=>lang('index/require_content')];
        }

        $cookie = 'gbook_timespan';
        if(!empty(cookie($cookie))){
            return ['code'=>1005,'msg'=>lang('frequently')];
        }

        $param['gbook_content']= htmlentities(mac_filter_words($param['gbook_content']));
        // if(!preg_match('/[^\x00-\x80]/',$param['gbook_content'])){
        //     return ['code'=>1005,'msg'=>lang('index/require_cn')];
        // }

        $param['gbook_reply'] = '';

        if (empty($authUser)) {
            $param['user_id'] = 0;
            $param['gbook_name'] = !empty($param['gbook_name']) ? $param['gbook_name'] : lang('controller/visitor');
        } else {
            $param['user_id'] = intval($authUser['user_id']);
            $param['gbook_name'] = !empty($authUser['user_nick_name']) ? $authUser['user_nick_name'] : $authUser['user_name'];
        }
        $param['gbook_name'] = htmlentities(trim($param['gbook_name']));

        $param['gbook_status'] = ($GLOBALS['config']['gbook']['audit'] == 1) ? 0 : 1;

        $param['gbook_ip'] = mac_get_ip_long();

        $res = model('Gbook')->saveData($param);

        if($res['code']>1){
            return $res;
        }
        else{
            cookie($cookie, 't', $GLOBALS['config']['gbook']['timespan']);
            if($GLOBALS['config']['gbook']['audit'] ==1){
                $res['msg'] =  lang('index/thanks_msg_audit');
            }
            else{
                $res['msg'] = lang('index/thanks_msg');
            }
            return $res;
        }
    }

}
