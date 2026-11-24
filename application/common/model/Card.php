<?php
namespace app\common\model;
use think\Db;

class Card extends Base {
    // 设置数据表（不含前缀）
    protected $name = 'card';

    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = '';

    // 自动完成
    protected $auto       = [];
    protected $insert     = [];
    protected $update     = [];

    public function getCardUseStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('not_used'),1=>lang('used')];
        return $arr[$data['card_use_status']];
    }

    public function getCardSaleStatusTextAttr($val,$data)
    {
        $arr = [0=>lang('not_sale'),1=>lang('sold')];
        return $arr[$data['card_sale_status']];
    }

    public function listData($where,$order,$page,$limit=20)
    {
        $page = $page > 0 ? (int)$page : 1;
        $limit = $limit ? (int)$limit : 20;
        $total = $this->where($where)->count();
        $list = Db::name('Card')->where($where)->order($order)->page($page)->limit($limit)->select();
        foreach($list as $k=>$v){
            if($v['user_id'] >0){
                $user = model('User')->infoData(['user_id'=>$v['user_id']]);
                $list[$k]['user'] = $user['info'];
            }
        }
        return ['code'=>1,'msg'=>lang('data_list'),'page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>1002,'msg'=>lang('obtain_err')];
        }
        $info = $info->toArray();

        return ['code'=>1,'msg'=>lang('obtain_ok'),'info'=>$info];
    }

    public function saveData($data)
    {
        $validate = \think\Loader::validate('Card');
        if(!$validate->check($data)){
            return ['code'=>1001,'msg'=>lang('param_err').'：'.$validate->getError() ];
        }

        if(!empty($data['card_id'])){
            $where=[];
            $where['card_id'] = ['eq',$data['card_id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['card_add_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }

    public function saveAllData($num,$money,$point,$role_no,$role_pwd)
    {
        $data=[];
        $t = time();
        for($i=1;$i<=$num;$i++){
            $card_no = mac_get_rndstr(16,$role_no);
            $card_pwd = mac_get_rndstr(8,$role_pwd);

            $data[$card_no] = ['card_no'=>$card_no,'card_pwd'=>$card_pwd,'card_money'=>$money,'card_points'=>$point,'card_add_time'=>$t];
        }
        $data = array_values($data);
        $res = $this->allowField(true)->insertAll($data);
        if(false === $res){
            return ['code'=>1002,'msg'=>lang('save_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('save_ok')];
    }


    public function delData($where)
    {
        $res = $this->where($where)->delete();
        if($res===false){
            return ['code'=>1001,'msg'=>lang('del_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('del_ok')];
    }

    public function fieldData($where,$col,$val)
    {
        if(!isset($col) || !isset($val)){
            return ['code'=>1001,'msg'=>lang('param_err')];
        }

        $data = [];
        $data[$col] = $val;
        $res = $this->allowField(true)->where($where)->update($data);
        if($res===false){
            return ['code'=>1001,'msg'=>lang('set_err').'：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>lang('set_ok')];
    }

    public function useData($card_no,$card_pwd,$user_info)
    {
        if (empty($card_no) || empty($card_pwd) || empty($user_info) || empty($user_info['user_id'])) {
            return ['code' => 1001, 'msg'=>lang('param_err')];
        }

        $where = [];
        $where['card_no'] = ['eq', $card_no];
        $where['card_pwd'] = ['eq', $card_pwd];
        $where['card_use_status'] = ['eq', 0];
        $user_id = intval($user_info['user_id']);

        try {
            return Db::transaction(function () use ($where, $user_id) {
                // 先确认会员存在，再以「未使用」条件原子占用卡密，避免并发重复兑换。
                $user = model('User')->where(['user_id' => $user_id])->find();
                if (empty($user)) {
                    return ['code' => 1003, 'msg' => lang('model/card/update_user_points_err')];
                }

                $info = $this->where($where)->find();
                if (empty($info)) {
                    return ['code' => 1002, 'msg' => lang('model/card/not_found')];
                }

                $update = [];
                $update['card_sale_status'] = 1;
                $update['card_use_status'] = 1;
                $update['card_use_time'] = time();
                $update['user_id'] = $user_id;
                // UPDATE 会取得行锁；并发请求中只有一个请求能把状态 0 更新为 1。
                $claimed = $this->where($where)->update($update);
                if ($claimed != 1) {
                    return ['code' => 1002, 'msg' => lang('model/card/not_found')];
                }

                if (intval($info['card_points']) > 0) {
                    $res = model('User')->where(['user_id' => $user_id])->setInc('user_points', $info['card_points']);
                    if ($res === false) {
                        throw new \RuntimeException('CARD_POINTS_UPDATE_FAILED');
                    }
                }

                // 积分日志必须与卡密状态、会员积分处于同一事务。
                $data = [];
                $data['user_id'] = $user_id;
                $data['plog_type'] = 1;
                $data['plog_points'] = $info['card_points'];
                $result = model('Plog')->saveData($data);
                if (!is_array($result) || intval($result['code']) !== 1) {
                    throw new \RuntimeException('CARD_POINT_LOG_FAILED');
                }

                return ['code' => 1, 'msg' => lang('model/card/used_card_ok', [$info['card_points']])];
            });
        } catch (\Exception $e) {
            return ['code' => 1004, 'msg' => lang('model/card/update_card_status_err')];
        }
    }
}