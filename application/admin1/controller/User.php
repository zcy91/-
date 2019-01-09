<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.icngo.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * Author: 当燃
 * Date: 2015-09-09
 */

namespace app\admin\controller;
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use think\Loader;

class User extends Base {

    public function index(){
        return $this->fetch();
    }
    public function consult(){
        $admin_id = $_SESSION['admin_id'];
        $timegap = I('timegap');
        $nickname = I('nickname');
        $map = array();
        if($timegap){
            $gap = explode(' - ', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['addtime'] = array('between',array(strtotime($begin),strtotime($end)));
        }
        if($nickname){
            $map['nickname'] = array('like',"%$nickname%");
        }
        if($admin_id !=1){
            $map['admin_id'] = array('=',$admin_id);
        }

        $count = M('consult')->where($map)->count();
        $page = new Page($count);
        $lists  = M('consult')->where($map)->order("readtime asc")->order("end_time asc")->limit($page->firstRow.','.$page->listRows)->select();
        $this->assign('page',$page->show());
        $this->assign('pager',$page);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    public function consultedit(){
        if(IS_POST) {
            $data = I('post.');
            $data['return_admin_id'] = $_SESSION['admin_id'];
            $data['addtime'] = time();
            $data['end_time'] = strtotime($data['end_time']);
            M('consult')->where('id='.$data['cid'])->save(['end_time'=>$data['end_time']]);
            $r = M('consult_return')->add($data);
            if ($r) {
                $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
            $this->ajaxReturn($return);
        }else{
            $act = I('get.act','add');
            $this->assign('act',$act);
            $id = I('get.id');
            if($id){
                $data=[];
                $data['readtime'] = time();
                D('consult')->where('id='.$id)->save($data);
                $info = D('consult')->where('id='.$id)->find();
                $this->assign('info',$info);
                $lists = M("consult_return")->where('cid='.$id)->order("addtime desc")->select();
                $this->assign('lists',$lists);
            }
            return $this->fetch();
        }

    }
    /**
     * 会员列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by').' '.I('sort');

        $model = M('users');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,10);
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }

        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();

        $user_id_arr = get_arr_column($userList, 'user_id');
        if(!empty($user_id_arr))
        {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(".  implode(',', $user_id_arr).")  group by first_leader");
            $first_leader = convert_arr_key($first_leader,'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(".  implode(',', $user_id_arr).")  group by second_leader");
            $second_leader = convert_arr_key($second_leader,'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(".  implode(',', $user_id_arr).")  group by third_leader");
            $third_leader = convert_arr_key($third_leader,'third_leader');
        }
        $this->assign('first_leader',$first_leader);
        $this->assign('second_leader',$second_leader);
        $this->assign('third_leader',$third_leader);
        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /**
     * 会员详细信息查看
     */
    public function detail(){
        $uid = I('get.id');
        $user = D('users')->where(array('user_id'=>$uid))->find();
        if(!$user)
            exit($this->error('会员不存在'));
        if(IS_POST){
            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            if($password != '' && $password != $password2){
                exit($this->error('两次输入密码不同'));
            }
            if($password == '' && $password2 == ''){
                unset($_POST['password']);
            }else{
                $_POST['password'] = encrypt($_POST['password']);
            }

            if(!empty($_POST['email']))
            {   $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if(!empty($_POST['mobile']))
            {   $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            $row = M('users')->where(array('user_id'=>$uid))->save($_POST);
            if($row)
                exit($this->success('修改成功'));
            exit($this->error('未作内容修改或修改失败'));
        }

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();

        $this->assign('user',$user);
        return $this->fetch();
    }

    public function add_user(){
    	if(IS_POST){
    		$data = I('post.');
			$user_obj = new UsersLogic();
			$res = $user_obj->addUser($data);
			if($res['status'] == 1){
				$this->success('添加成功',U('User/index'));exit;
			}else{
				$this->error('添加失败,'.$res['msg'],U('User/index'));
			}
    	}
    	return $this->fetch();
    }

    public function export_user(){
    	$strTable ='<table width="500" border="1">';
    	$strTable .= '<tr>';
    	$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
    	$strTable .= '</tr>';
    	$count = M('users')->count();
    	$p = ceil($count/5000);
    	for($i=0;$i<$p;$i++){
    		$start = $i*5000;
    		$end = ($i+1)*5000;
    		$userList = M('users')->order('user_id')->limit($start.','.$end)->select();
    		if(is_array($userList)){
    			foreach($userList as $k=>$val){
    				$strTable .= '<tr>';
    				$strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['level'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['email'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['reg_time']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['last_login']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_money'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_points'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].' </td>';
    				$strTable .= '</tr>';
    			}
    			unset($userList);
    		}
    	}
    	$strTable .='</table>';
    	downloadExcel($strTable,'users_'.$i);
    	exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address(){
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id'=>$uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList',$regionList);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete(){
        $uid = I('get.id');
        $row = M('users')->where(array('user_id'=>$uid))->delete();
        if($row){
            $this->success('成功删除会员');
        }else{
            $this->error('操作失败');
        }
    }
    /**
     * 删除会员
     */
    public function ajax_delete(){
        $uid = I('id');
        if($uid){
            $row = M('users')->where(array('user_id'=>$uid))->delete();
            if($row !== false){
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log(){
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id'=>$user_id))->count();
        $page = new Page($count);
        $lists  = M('account_log')->where(array('user_id'=>$user_id))->order('change_time desc')->limit($page->firstRow.','.$page->listRows)->select();

        $this->assign('user_id',$user_id);
        $this->assign('page',$page->show());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit(){
        $order_info = I('get.');
        $user_id = $order_info['user_id'];
        if(!$user_id > 0)
            $this->error("参数有误");
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id',$user_id)->find();
        if(IS_POST){
            $return_info = I('post.');
            $return_id   = $return_info['return_id'];
            if(!$return_info['desc'])
                $this->error("请填写操作说明");
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money =  $m_op_type ? $user_money : 0-$user_money;
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points =  $p_op_type ? $pay_points : 0-$pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if( $revision_frozen_money != 0){    //有加减冻结资金的时候
                $frozen_money =  $f_op_type ? $revision_frozen_money : 0-$revision_frozen_money;
                $frozen_money = $user['frozen_money']+$frozen_money;    //计算用户被冻结的资金
                if($f_op_type==1 and $revision_frozen_money > $user['user_money']){ $this->error("用户剩余资金不足！！");}
                if($f_op_type==0 and $revision_frozen_money > $user['frozen_money']){$this->error("冻结的资金不足！！");}
                $user_money = $f_op_type ? 0-$revision_frozen_money : $revision_frozen_money ;    //计算用户剩余资金
                M('users')->where('user_id',$user_id)->update(['frozen_money' => $frozen_money]);
            }

            if(accountLog($user_id,$user_money,$pay_points,$return_info['desc'],0,$return_info['order_id'],$return_info['order_sn'])){
                if($return_id>0){  //有退货id,是订单退款，要更新退货单状态
                    $orderLogic = new OrderLogic();
                    $res = $orderLogic->alterReturnGoodsStatus($return_id,$return_info['order_id']);
                    $orderLogic->closeOrderByReturn($return_info['order_id']);
                    $this->success("操作成功", U("Admin/order/return_info", array('id' => $return_id)));
                }
                $this->success("操作成功",U("Admin/User/account_log",array('id'=>$user_id)));
            }else{
                $this->error("操作失败");
            }
            exit;
        }
        if($order_info['return_id']){  //有退货id,是订单退款
            $return_info = M('return_goods')->field('order_sn,order_id,goods_id,spec_key')->where('id',$order_info['return_id'])->find(); //查找退货商品信息
            $order = M('order') -> field('user_money,integral') -> where('order_id',$return_info['order_id']) -> find();
            $order_info=array_merge($return_info,$order_info);  //合并数值
            $order_goods= M('order_goods')->where(array_splice($return_info ,1))->find();  //去掉order_sn 后作为条件去查找
            $order_info['user_money']  =$order_goods['member_goods_price']*$order_goods['goods_num'];  //计算默认退款
        }
        $this -> assign('order',$order);
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        $this->assign('order_info',$order_info);
        return $this->fetch();
    }

    public function recharge(){
    	$timegap = I('timegap');
    	$nickname = I('nickname');
    	$map = array();
    	if($timegap){
    		$gap = explode(' - ', $timegap);
    		$begin = $gap[0];
    		$end = $gap[1];
    		$map['ctime'] = array('between',array(strtotime($begin),strtotime($end)));
    	}
    	if($nickname){
    		$map['nickname'] = array('like',"%$nickname%");
    	}
    	$count = M('recharge')->where($map)->count();
    	$page = new Page($count);
    	$lists  = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
    	$this->assign('page',$page->show());
        $this->assign('pager',$page);
    	$this->assign('lists',$lists);
    	return $this->fetch();
    }

    public function level(){
    	$act = I('get.act','add');
    	$this->assign('act',$act);
    	$level_id = I('get.level_id');
    	if($level_id){
    		$level_info = D('user_level')->where('level_id='.$level_id)->find();
    		$this->assign('info',$level_info);
    	}
    	return $this->fetch();
    }

    public function levelList(){
    	$Ad =  M('user_level');
        $p = $this->request->param('p');
    	$res = $Ad->order('level_id')->page($p.',10')->select();
    	if($res){
    		foreach ($res as $val){
    			$list[] = $val;
    		}
    	}
    	$this->assign('list',$list);
    	$count = $Ad->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$this->assign('page',$show);
    	return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if(strstr($search_key,'@'))
        {
            $list = M('users')->where(" email like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }
        }
        else
        {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['mobile']}</option>";
            }
        }
        exit;
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
          $list = M('users')->where("first_leader = 1")->select();
          return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users',$users);
        return $this->fetch();
    }

    /**
     * 发送系统消息
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $text= I('post.text');//内容
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => 0,
            'send_time' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email= I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if($email){
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $model = M("withdrawals");
        $_GET = array_merge($_GET,$_POST);
        unset($_GET['create_time']);

        $status = I('status');
        $user_id = I('user_id');
        $account_bank = I('account_bank');
        $account_name = I('account_name');
        $create_time = I('create_time');
        $create_time = $create_time  ? $create_time  : date('Y/m/d',strtotime('-1 year')).'-'.date('Y/m/d',strtotime('+1 day'));
        $create_time2 = explode('-',$create_time);
        $this->assign('start_time', $create_time2[0]);
        $this->assign('end_time', $create_time2[1]);
        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";

        if($status === '0' || $status > 0)
            $where .= " and status = $status ";
        $user_id && $where .= " and user_id = $user_id ";
        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
        $account_name && $where .= " and account_name like '%$account_name%' ";

        $count = $model->where($where)->count();
        $Page  = new Page($count,16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('create_time',$create_time);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('pager',$Page);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        return $this->fetch();
    }
    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $withdrawals = DB::name('withdrawals')->where('id',$id)->find();
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if (IS_POST) {
            $data = I('post.');
            // 如果是已经给用户转账 则生成转账流水记录
            if ($data['status'] == 1 && $withdrawals['status'] != 1) {
                if ($user['user_money'] < $withdrawals['money']) {
                    $this->error("用户余额不足{$withdrawals['money']}，不够提现");
                    exit;
                }
                accountLog($withdrawals['user_id'], ($withdrawals['money'] * -1), 0, "平台提现");
                $remittance = array(
                    'user_id' => $withdrawals['user_id'],
                    'bank_name' => $withdrawals['bank_name'],
                    'account_bank' => $withdrawals['account_bank'],
                    'account_name' => $withdrawals['account_name'],
                    'money' => $withdrawals['money'],
                    'status' => 1,
                    'create_time' => time(),
                    'admin_id' => session('admin_id'),
                    'withdrawals_id' => $withdrawals['id'],
                    'remark' => $data['remark'],
                );
                M('remittance')->add($remittance);
            }
            DB::name('withdrawals')->update($data);
            $this->success("操作成功!", U('Admin/User/remittance'), 3);
            exit;
        }

        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];
        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    public function withdrawals_update(){
        $id = I('id/a');
        $status = I('status');
        $withdrawals = M('withdrawals')->where('id','in', $id)->select();
        if($status == 1){
            $r = M('withdrawals')->where('id','in', $id)->save(array('status'=>$status,'check_time'=>time()));
        }else if($status == -1){
            $r = M('withdrawals')->where('id','in', $id)->save(array('status'=>$status,'refuse_time'=>time()));
        }else if($status == 2){
            foreach($withdrawals as $val){
                $user = M('users')->where(array('user_id'=>$val['user_id']))->find();
                if($user['user_money'] < $val['money'])
                {
                    $data['status'] = -2;
                    $data['remark'] = '账户余额不足';
                    M('withdrawals')->where(array('id'=>$val['id']))->save($data);
                }else{
                    if($val['bank_name'] == '支付宝 '){
                        //流水号1^收款方账号1^收款账号姓名1^付款金额1^备注说明1|流水号2^收款方账号2^收款账号姓名2^付款金额2^备注说明2
                        $alipay['batch_no'] = time();
                        $alipay['batch_fee'] += $val['money'];
                        $alipay['batch_num'] += 1;
                        $str = isset($alipay['detail_data']) ? '|' : '';
                        $alipay['detail_data'] .= $str.$val['pay_code'].'^'.$val['account_bank'].'^'.$val['realname'].'^'.$val['money'].'^'.$val['remark'];
                    }
                    if($val['bank_name'] == '微信'){
                        $wxpay = array(
                            'userid' => $val['user_id'],//用户ID做更新状态使用
                            'openid' => $val['account_bank'],//收钱的人微信 OPENID
                            'pay_code'=>$val['pay_code'],//提现申请ID
                            'money' => $val['money'],//金额
                            'desc' => '恭喜您提现申请成功!'
                        );
                        $res = $this->transfer('weixin',$wxpay);//微信在线付款转账
                        if($res['partner_trade_no']){
                            accountLog($val['user_id'], ($val['money'] * -1), 0,"平台处理用户提现申请");
                            $r = M('withdrawals')->where(array('id'=>$val['id']))->save(array('status'=>$status,'pay_time'=>time()));
                        }else{
                            $this->ajaxReturn(array('status'=>0,'msg'=>$res['msg']),'JSON');
                        }
                    }
                }
            }
            if(!empty($alipay)){
                $this->transfer('alipay',$alipay);
            }
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
        }else if($status == 3){
            $r = M('withdrawals')->where('id in ('.implode(',', $id).')')->delete();
        }else{
            accountLog($val['user_id'], ($val['money'] * -1), 0,"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
            $r = M('withdrawals')->where('id in ('.implode(',', $id).')')->save(array('status'=>2,'pay_time'=>time()));
        }
        if($r){
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
        }

    }

    public function transfer($atype,$data){
        if($atype == 'weixin'){
            include_once  PLUGIN_PATH."payment/weixin/weixin.class.php";
            $wxpay_obj = new \weixin();
            return $wxpay_obj->transfer($data);
        }else{
            //支付宝在线批量付款
            include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            return $alipay_obj->transfer($data);
        }
    }
    /**
     *  转账汇款记录
     */
    public function remittance(){
        $model = M("remittance");
        $_GET = array_merge($_GET,$_POST);
        unset($_GET['create_time']);

        $user_id = I('user_id');
        $account_bank = I('account_bank');
        $account_name = I('account_name');

        $create_time = I('create_time');
        $create_time = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time2 = explode(' - ',$create_time);
        $this->assign('start_time',$create_time2[0]);
        $this->assign('end_time',$create_time2[1]);
        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";
        $user_id && $where .= " and user_id = $user_id ";
        $account_bank && $where .= " and account_bank like '%$account_bank%' ";
        $account_name && $where .= " and account_name like '%$account_name%' ";

        $count = $model->where($where)->count();
        $Page  = new Page($count,16);
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('pager',$Page);
        $this->assign('create_time',$create_time);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        return $this->fetch();
    }
}