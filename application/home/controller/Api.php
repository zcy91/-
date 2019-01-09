<?php

namespace app\home\controller;
use app\home\logic\UsersLogic;
use think\Db;
use think\Session;
use think\Controller;
use think\Verify;

class Api extends Controller {
    public  $send_scene;

    public function _initialize() {
        Session::start();
    }
    /*
     * 获取地区
     */
    public function getRegion(){
        $parent_id = I('get.parent_id/d');
        $selected = I('get.selected',0);
        $data = M('region')->where("parent_id",$parent_id)->select();
        $html = '';
        if($data){
            foreach($data as $h){
            	if($h['id'] == $selected){
            		$html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
            	}
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }


    public function getTwon(){
    	$parent_id = I('get.parent_id/d');
    	$data = M('region')->where("parent_id",$parent_id)->select();
    	$html = '';
    	if($data){
    		foreach($data as $h){
    			$html .= "<option value='{$h['id']}'>{$h['name']}</option>";
    		}
    	}
    	if(empty($html)){
    		echo '0';
    	}else{
    		echo $html;
    	}
    }

    /**
     * 获取省
     */
    public function getProvince()
    {
        $province = Db::name('region')->field('id,name')->where(array('level' => 1))->cache(true)->select();
        $res = array('status' => 1, 'msg' => '获取成功', 'result' => $province);
        exit(json_encode($res));
    }

    /**
     * 获取市或者区
     */
    public function getRegionByParentId()
    {
        $parent_id = input('parent_id');
        $res = array('status' => 0, 'msg' => '获取失败，参数错误', 'result' => '');
        if($parent_id){
            $region_list = Db::name('region')->field('id,name')->where(['parent_id'=>$parent_id])->select();
            $res = array('status' => 1, 'msg' => '获取成功', 'result' => $region_list);
        }
        exit(json_encode($res));
    }

    /*
     * 获取地区
     */
    public function get_category(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
            $list = M('goods_category')->where("parent_id", $parent_id)->select();

        foreach($list as $k => $v)
            $html .= "<option value='{$v['id']}'>{$v['name']}</option>";
        exit($html);
    }


    /**
     * 前端发送短信方法: APP/WAP/PC 共用发送方法
     */
    public function send_validate_code(){

        $this->send_scene = C('SEND_SCENE');

        $type = I('type');
        $scene = I('scene');    //发送短信验证码使用场景
        $mobile = I('mobile');
        $sender = I('send');
        $verify_code = I('verify_code');
        $mobile = !empty($mobile) ?  $mobile : $sender ;
        $session_id = I('unique_id' , session_id());
        session("scene" , $scene);
        //注册
        if($scene == 1 && !empty($verify_code)){
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_reg')) {
                ajaxReturn(array('status'=>-1,'msg'=>'图像验证码错误'));
            }
        }
        if($type == 'email'){
            //发送邮件验证码
            $logic = new UsersLogic();
            $res = $logic->send_email_code($sender);
            ajaxReturn($res);
        }else{
            //发送短信验证码
            $res = checkEnableSendSms($scene);
            if($res['status'] != 1){
                ajaxReturn($res);
            }
            //判断是否存在验证码
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$session_id, 'status'=>1))->order('id DESC')->find();
            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 60;
            $sms_time_out = 60;
            //120秒以内不可重复发送
            if($data && (time() - $data['add_time']) < $sms_time_out){
                $return_arr = array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');
                ajaxReturn($return_arr);
            }
            //随机一个验证码
            $code = rand(1000, 9999);

            $user = session('user');
            if ($scene == 6){

                if(!$user['user_id']){
                    //登录超时
                    ajaxReturn(array('status'=>-1,'msg'=>'登录超时'));
                }
                $params = array('code'=>$code);

                if($user['nickname']){
                    $params['user_name'] = $user['nickname'];
                }
            }
            $params['code'] =$code;

            //发送短信
            $resp = sendSms($scene , $mobile , $params, $session_id);

            if($resp['status'] == 1){
                //发送成功, 修改发送状态位成功
                M('sms_log')->where(array('mobile'=>$mobile,'code'=>$code,'session_id'=>$session_id , 'status' => 0))->save(array('status' => 1));
                $return_arr = array('status'=>1,'msg'=>'发送成功,请注意查收');
            }else{
                $return_arr = array('status'=>-1,'msg'=>'发送失败'.$resp['msg']);
            }
            ajaxReturn($return_arr);
        }
    }

    /**
     * 验证短信验证码: APP/WAP/PC 共用发送方法
     */
    public function check_validate_code(){

        $code = I('post.code');
        $mobile = I('mobile');
        $send = I('send');
        $sender = empty($mobile) ? $send : $mobile;
        $type = I('type');
        $session_id = I('unique_id', session_id());
        $scene = I('scene', -1);

        $logic = new UsersLogic();
        $res = $logic->check_validate_code($code, $sender, $type ,$session_id, $scene);
        ajaxReturn($res);
    }

    /**
     * 检测手机号是否已经存在
     */
    public function issetMobile()
    {
      $mobile = I("mobile",'0');
      $users = M('users')->where('mobile',$mobile)->find();
      if($users)
          exit ('1');
      else
          exit ('0');
    }

    public function issetMobileOrEmail()
    {
        $mobile = I("mobile",'0');
        $users = M('users')->where("email",$mobile)->whereOr('mobile',$mobile)->find();
        if($users)
            exit ('1');
        else
            exit ('0');
    }

    public function issetEmail()
    {
        $mobile = I("mobile",'0');
        $users = M('users')->where("email",$mobile)->find();
        if($users)
            exit ('1');
        else
            exit ('0');
    }
    /**
     * 查询物流
     */
    public function queryExpress()
    {
        $shipping_code = input('shipping_code');
        $invoice_no = input('invoice_no');
        if(empty($shipping_code) || empty($invoice_no)){
            return json(['status'=>0,'message'=>'参数有误','result'=>'']);
        }
        return json(queryExpress($shipping_code,$invoice_no));
    }

    public function test(){
        $scene = session("scene");
        echo ' scene : '.$scene;
    }

    /**
     * 检查订单状态
     */
    public function check_order_pay_status()
    {
        $order_id = I('order_id/d');
        if(empty($order_id)){
            $res = ['message'=>'参数错误','status'=>-1,'result'=>''];
            $this->AjaxReturn($res);
        }
        $order = M('order')->field('pay_status')->where(['order_id'=>$order_id])->find();
        if($order['pay_status'] != 0){
            $res = ['message'=>'已支付','status'=>1,'result'=>$order];
        }else{
            $res = ['message'=>'未支付','status'=>0,'result'=>$order];
        }
        $this->AjaxReturn($res);
    }

    /**
     * 广告位js
     */
    public function ad_show()
    {
        $pid = I('pid/d',1);
        $where = array(
            'pid'=>$pid,
            'enabled'=>1,
            'start_time'=>array('lt',strtotime(date('Y-m-d H:00:00'))),
            'end_time'=>array('gt',strtotime(date('Y-m-d H:00:00'))),
        );
        $ad = D("ad")->where($where)->order("orderby desc")->cache(true,TPSHOP_CACHE_TIME)->find();
        $this->assign('ad',$ad);
        return $this->fetch();
    }
    /**
     *  搜索关键字
     * @return array
     */
    public function searchKey(){
        $searchKey = input('key');
        $searchKeyList = Db::name('search_word')
            ->where('keywords','like',$searchKey.'%')
            ->whereOr('pinyin_full','like',$searchKey.'%')
            ->whereOr('pinyin_simple','like',$searchKey.'%')
            ->limit(10)
            ->select();
        if($searchKeyList){
            return ['status'=>1,'msg'=>'搜索成功','result'=>$searchKeyList];
        }else{
            return ['status'=>0,'msg'=>'没记录','result'=>$searchKeyList];
        }
    }

    /**
     *  注册api
     * @return array
     */
    public function reg(){
        $username = I('mobile');
        $password = I('password');
        if(check_mobile($username)){
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['nickname'] = $map['mobile'] = $username; //手机注册
        }
        if($is_validated != 1)
            exit(json_encode(array('status'=>-1,'msg'=>'请用手机号','result'=>null)));

        if(!$username || !$password)
            exit(json_encode(array('status'=>-1,'msg'=>'请输入用户名或密码','result'=>null)));
        if(get_user_info($username,1)||get_user_info($username,2))
            exit(json_encode(array('status'=>-1,'msg'=>'账号已存在','result'=>null)));
        $map['password'] = encrypt($password);
        $map['reg_time'] = time();
        $map['first_leader'] = 0;
        $distribut_condition = tpCache('distribut.condition');
        if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销
            $map['is_distribut']  = 1;
        $map['push_id'] = 0;
        $user_id = M('users')->add($map);
        if($user_id === false )
            exit(json_encode(array('status'=>-1,'msg'=>'注册失败','result'=>null)));
        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分
        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        $user = M('users')->where("user_id", $user_id)->find();
        exit(json_encode(array('status'=>1,'msg'=>'注册成功','result'=>$user)));
    }
    /**
     *  修改密码api
     * @return array
     */

    public function resetpwd(){
        $mobile = I('mobile');
        $password = I('password');
        if(strlen($password) < 6){
            exit(json_encode(array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'')));
        }
        $row = M('users')->where("mobile ='{$mobile}'")->update(array('password'=>$password));
        if(!$row){
            exit(json_encode(array('status'=>-1,'msg'=>'密码修改失败','result'=>'')));
        }
        exit(json_encode(array('status'=>1,'msg'=>'密码修改成功','result'=>'')));

    }
    /**
     *  账户变化api
     * @return array
     */

    public function account(){
        $params = I('get.');
        $get_sign = $params['sign'];
        if(!$get_sign){
            exit(json_encode(array('status'=>-2,'msg'=>'签名缺少','result'=>'')));
        }
        unset($params['sign']);
        ksort($params);
        $paramsStrExceptSign = '';
        foreach ($params as $val){
            $paramsStrExceptSign .= urldecode($val);
        }
        $paramsStrExceptSign .= 'apikey';
        $sign = md5($paramsStrExceptSign);
        if($sign != $get_sign){
            exit(json_encode(array('status'=>-2,'msg'=>'签名错误','result'=>'')));
        }
        $mobile = I('mobile');
        if(!$mobile){
            exit(json_encode(array('status'=>-3,'msg'=>'缺少参数','result'=>'')));
        }
        $m_op_type = I('money_act_type');
        $user_money = I('user_money');
        $user_money =  $m_op_type ? $user_money : 0-$user_money;
        $point_type = I('point_type'); //1: 增加，0： 减少
        $pay_points = I('pay_points');
        $pay_points =  $point_type ? $pay_points : 0 - $pay_points;
        $desc = I('desc');
        $user_id = M('users') -> where("mobile = $mobile") -> getField('user_id');
        $order_id = I('order_id');
        $order_sn = I('order_sn');
        $res = accountApiLog($user_id,$user_money,$pay_points,$desc,0,$order_id,$order_sn);
        if($res){
            exit(json_encode(array('status'=>1,'msg'=>'修改成功','result'=>'')));
        } else{
            exit(json_encode(array('status'=>-1,'msg'=>'修改失败','result'=>'')));
        }
    }

}