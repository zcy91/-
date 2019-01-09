<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.icngo.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * 订单以及售后中心
 * @author soubao 当燃
 *  @Date: 2016-12-20
 */
namespace app\home\controller;
use app\common\logic\MessageLogic;
use app\common\logic\OrderLogic;
use app\common\logic\OrderGoodsLogic;
use app\common\logic\CommentLogic;
use think\Db;
use think\Page;

class Order extends Base {

	public $user_id = 0;
	public $user = array();

    public function _initialize() {
        parent::_initialize();
        if(session('?user'))
        {
        	$user = session('user');
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        	$this->assign('user_id',$this->user_id);
            //获取用户信息的数量
            $messageLogic = new MessageLogic();
            $user_message_count = $messageLogic->getUserMessageCount();
            $this->assign('user_message_count', $user_message_count);
        }else{
        	header("location:".U('Home/User/login'));
        	exit;
        }
        //用户中心面包屑导航
        $navigate_user = navigate_user();
        $this->assign('navigate_user',$navigate_user);
    }

    /*
     * 订单列表
     */
    public function order_list(){
        $where = ' user_id=:user_id';
        $bind['user_id'] = $this->user_id;
        //条件搜索
       if(I('get.type')){
           $where .= C(strtoupper(I('get.type')));
       }
       // 搜索订单 根据商品名称 或者 订单编号
       $search_key = trim(I('search_key'));
       if($search_key)
       {
          $where .= " and (order_sn like :search_key1 or order_id in (select order_id from `".C('database.prefix')."order_goods` where goods_name like :search_key2) ) ";
           $bind['search_key1'] = "%$search_key%";
           $bind['search_key2'] = "%$search_key%";
       }

        $count = M('order')->where($where)->bind($bind)->count();
        $Page = new Page($count,10);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->bind($bind)->limit($Page->firstRow.','.$Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach($order_list as $k=>$v)
        {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
            if($order_list[$k]['order_prom_type'] == 4){
                $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_list[$k]['order_prom_id']))->find();
                $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
                $order_list[$k]['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
                $order_list[$k]['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
                $order_list[$k]['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            }else{
                $order_list[$k]['pre_sell_is_finished'] = -1;//没有参与预售的订单
            }
        }
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('page',$show);
        $this->assign('lists',$order_list);
        $this->assign('active','order_list');
        $this->assign('active_status',I('get.type'));
        return $this->fetch();
    }

    /*
     * 订单详情
     */
    public function order_detail(){
        $id = I('get.id/d');
        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        if(!$order_info){
            $this->error('没有获取到订单信息');
            exit;
        }
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        if($order_info['order_prom_type'] == 4){
            $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_info['order_prom_id']))->find();
            $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
            $order_info['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
            $order_info['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
            $order_info['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            $order_info['pre_sell_deliver_goods'] = $pre_sell_item['deliver_goods'];
        }else{
            $order_info['pre_sell_is_finished'] = -1;//没有参与预售的订单
        }
        //获取订单进度条
        $sql = "SELECT action_id,log_time,status_desc,order_status FROM ((SELECT * FROM __PREFIX__order_action WHERE order_id = :id AND status_desc <>'' ORDER BY action_id) AS a) GROUP BY status_desc ORDER BY action_id";
        $bind['id'] = $id;
        $items = DB::query($sql,$bind);
        $items_count = count($items);

        $ids = $order_info['province'].','.$order_info['city'].','.$order_info['district'];
        $region_list = M('region')->where("id in (".$ids.")")->getField("id,name");
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no',true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id'=>$id))->select();
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('region_list',$region_list);
        $this->assign('order_info',$order_info);
        $this->assign('order_action',$order_action);
        $this->assign('active','order_list');
        return $this->fetch();
    }

    public function del_order()
    {
        $order_id = I('order_id/d',0);
        $order_type = I('type/s');

        $orderLogic = new \app\common\logic\OrderLogic;
        $return = $orderLogic->delOrder($order_id);
        $this->ajaxReturn($return);
        //$this->success('删除成功',U('Home/Order/order_list', array('type'=>$order_type)));
    }

    public function cancel_order_info(){
    	$order_id = I('order_id/d',0);
    	$order = M('order')->where(array('order_id'=>$order_id,'order_status'=>3,'pay_status'=>1))->find();
    	$this->assign('order', $order);
    	return $this->fetch();
    }
    //取消订单弹窗
    public function refund_order()
    {
    	$order_id = I('get.order_id/d');

    	$order = M('order')
                ->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount,total_amount')
                ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
                ->find();

        if (!$order) {
            return $this->error('订单不存在');
        }

        $this->assign('user',  $this->user);
        $this->assign('order', $order);
    	return $this->fetch();
    }

    //申请取消订单
    public function record_refund_order()
    {
        $order_id   = input('post.order_id', 0);
        $user_note  = input('post.user_note', '');
        $consignee  = input('post.consignee', '');
        $mobile     = input('post.mobile', '');

        $logic = new \app\common\logic\OrderLogic;
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);

        $this->ajaxReturn($return);
    }

    public function virtual_order(){
        $Order = new \app\common\model\Order();
    	$order_id = I('get.order_id/d');
    	$map['order_id'] = $order_id;
    	$map['user_id'] = $this->user_id;
    	$orderobj = $Order->where($map)->find();
        if(!$orderobj) $this->error('没有获取到订单信息');
        // 添加属性  包括按钮显示属性 和 订单状态显示属性
        $order_info = $orderobj->append(['order_status_detail','order_button','order_goods','store'])->toArray();
    	//获取订单操作记录
    	$order_action = M('order_action')->where(array('order_id'=>$order_id))->select();
    	$this->assign('order_status',C('ORDER_STATUS'));
    	$this->assign('pay_status',C('PAY_STATUS'));
    	$this->assign('order_info',$order_info);
    	$this->assign('order_action',$order_action);

    	if($order_info['pay_status'] == 1 && $order_info['order_status']!=3){
    		$vrorder = M('vr_order_code')->where(array('order_id'=>$order_id))->select();
    		$this->assign('vrorder',$vrorder);
    	}
    	return $this->fetch();
    }


    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status', -1);
        $logic = new CommentLogic;
        $data = $logic->getComment($user_id, $status); //获取评论列表
        $store_id_list = get_arr_column($data['result'], 'store_id');
        if (!empty($store_id_list)) {
            $store_list = M('store')->cache(true)->where("store_id", "in", implode(',', $store_id_list))->getField('store_id,store_name,store_qq');
        }
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('comment_page', $data['page']);
        $this->assign('store_list', $store_list);
        $this->assign('comment_list', $data['result']);
        $this->assign('active', 'comment');
        return $this->fetch();
    }

    /**
     * 删除评价
     */
    public function delComment()
    {
        $comment_id = I('comment_id');
        if (empty($comment_id)) {
            $this->error('参数错误');
        }
        $comment = Db::name('comment')->where('comment_id', $comment_id)->find();
        if ($this->user_id != $comment['user_id']) {
            $this->error('不能删除别人的评论');
        }
        Db::name('reply')->where('comment_id', $comment_id)->delete();
        Db::name('comment')->where('comment_id', $comment_id)->delete();
        $this->success('删除评论成功');
    }


    /**
     * @time 2016/8/5
     * @author dyr
     * 订单评价列表
     */
    public function comment_list()
    {
        $order_id = I('get.order_id/d');
        $store_id = I('get.store_id/d');
        $part_finish = I('get.part_finish', 0);
        if (empty($order_id) || empty($store_id)) {
            $this->error("参数错误");
        } else {
            //查找店铺信息
            $store_where['store_id'] = $store_id;
            $store_info = M('store')->field('store_id,store_name,store_phone,store_address,store_logo')->where($store_where)->find();
            if (empty($store_info)) {
                $this->error("该商家不存在");
            }
            //查找订单是否已经被用户评价
            $order_comment_where['order_id'] = $order_id;
            $order_comment_where['deleted'] = 0;
            $order_info = M('order')->field('order_id,order_sn,is_comment,add_time')->where($order_comment_where)->find();
            //查找订单下的所有未评价的商品
            $order_goods_logic = new OrderGoodsLogic();
            $no_comment_goods_list = $order_goods_logic->get_no_comment_goods_list($order_id);
            $goods_id_list = array();
            foreach ($no_comment_goods_list as $key => $value) {
                array_push($goods_id_list, $value['goods_id']);
            }
            $this->assign('goods_id_list', $goods_id_list);
            $this->assign('store_info', $store_info);
            $this->assign('order_info', $order_info);
            $this->assign('no_comment_goods_list', $no_comment_goods_list);
            $this->assign('no_comment_goods_list_count', count($no_comment_goods_list));
            $this->assign('part_finish', $part_finish);
            return $this->fetch();
        }
    }

    /**
     * @time 2016/8/5
     * @author dyr
     *  添加评论
     */
    public function addComment()
    {
        $remark = I("post.remark/a");
        $hide_username = I('post.hide_username');
        $store_score['describe_score'] = I('post.store_packge_hidden');
        $store_score['seller_score'] = I('post.store_speed_hidden');
        $store_score['logistics_score'] = I('post.store_sever_hidden');
        $order_id = $store_score['order_id'] = $store_score_where['order_id'] = I('post.order_id/d');
        $store_score['user_id'] = $store_score_where['user_id'] = $this->user_id;
        $store_score_where['deleted'] = 0;
        $store_id = M('order')->where(array('order_id' => $store_score_where['order_id']))->getField('store_id');
        $store_score['store_id'] = $store_id;
        //处理订单评价
        if (!empty($store_score['describe_score']) && !empty($store_score['seller_score']) && !empty($store_score['logistics_score'])) {
            $order_comment = M('order_comment')->where($store_score_where)->find();
            if ($order_comment) {
                M('order_comment')->where($store_score_where)->save($store_score);
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            } else {
                M('order_comment')->add($store_score);//订单打分
                M('order')->where(array('order_id' => $order_id))->save(array('is_comment' => 1));
            }
            //订单打分后更新店铺评分
            $store_logic = new StoreLogic();
            $store_logic->updateStoreScore($store_id);
        }
        //处理商品评价
        if (is_array($remark)) {
            foreach ($remark as $key => $value) {
                if (!empty($value['rank']) && !empty($value['content'])) {
                    $comment['goods_id'] = $key;
                    $comment['order_id'] = $store_score['order_id'];
                    $comment['store_id'] = $store_id;
                    $comment['user_id'] = $this->user_id;
                    $comment['content'] = $value['content'];
                    $comment['ip_address'] = getIP();
                    $comment['spec_key_name'] = $value['spec_key_name'];
                    $comment['goods_rank'] = $value['rank'];
                    $comment['img'] = (empty($value['commment_img'][0])) ? '' : serialize($value['commment_img']);
                    $comment['impression'] = (empty($value['tag'][0])) ? '' : implode(',', $value['tag']);
                    $comment['is_anonymous'] = empty($hide_username) ? 1 : 0;
                    $comment['add_time'] = time();
                    M('comment')->add($comment);//想评论表插入数据
                    M('order_goods')->where(array('order_id' => $store_score['order_id'], 'goods_id' => $key))->save(array('is_comment' => 1));
                    M('goods')->where(array('goods_id' => $key))->setInc('comment_count', 1);
                    unset($comment);
                }
            }
        }
        //查找订单下是否有没有评价的商品
        $order_goods_logic = new OrderGoodsLogic();
        $no_comment_goods_list = $order_goods_logic->get_no_comment_goods_list($order_id);
        $no_comment_goods_count = count($no_comment_goods_list);
        if ($no_comment_goods_count > 0) {
            $this->redirect(U('Order/comment_list', array('part_finish' => 1, 'order_id' => $order_id, 'store_id' => $store_id)));
        } else {
            M('order')->where(['order_id' => $order_id])->save(array('order_status' => 4));
            $this->redirect(U('Order/comment_list', array('order_id' => $order_id, 'store_id' => $store_id)));
        }
    }

    /**
     *  点赞
     * @author dyr
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $result['success'] = 1;
        }
        exit(json_encode($result));
    }

    /**
     * 添加回复
     * @author dyr
     */
    public function reply_add()
    {
        $comment_id = I('post.comment_id/d');
        $reply_id = I('post.reply_id/d', 0);
        $content = I('post.content');
        $to_name = I('post.to_name', '');
        $goods_id = I('post.goods_id/d');
        $reply_data = array(
            'comment_id' => $comment_id,
            'parent_id' => $reply_id,
            'content' => $content,
            'user_name' => $this->user['nickname'],
            'to_name' => $to_name,
            'reply_time' => time()
        );
        $where = array('o.user_id' => $this->user_id, 'og.goods_id' => $goods_id, 'o.pay_status' => 1);
        $user_goods_count = Db::name('order')
            ->alias('o')
            ->join('__ORDER_GOODS__ og','o.order_id = og.order_id', 'LEFT')
            ->where($where)
            ->count();
        if ($user_goods_count > 0) {
            M('reply')->add($reply_data);
            M('comment')->where(array('comment_id' => $comment_id))->setInc('reply_num');
            $json['success'] = true;
        } else {
            $json['success'] = false;
            $json['msg'] = '只有购买过该商品才能进行评价';
        }
        $this->ajaxReturn($json);
    }

    public function order_confirm()
    {
    	$id = I('post.order_id/d', 0);
    	$data = confirm_order($id, $this->user_id);
        $this->ajaxReturn($data);
    }

    /**
     * 可申请退换货
     */
    public function return_goods_index(){
        $sale_t = I('sale_t/i',0);
        $keywords = I('keywords');
        $model = new OrderLogic();
        $data = $model->getReturnGoodsIndex($sale_t,$keywords,$this->user_id);
    	$this->assign('store_list',$data['store_list']);
    	$this->assign('order_list',$data['order_list']);
    	$this->assign('page',$data['show']);
    	return $this->fetch();
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $rec_id = I('rec_id',0);
        $return_goods = M('return_goods')->where(array('rec_id'=>$rec_id))->find();
        if(!empty($return_goods))
        {
            $this->error('已经提交过退货申请!',U('Order/return_goods_info',array('id'=>$return_goods['id'])));
        }
        $order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();
        $order = M('order')->where(array('order_id'=>$order_goods['order_id'],'user_id'=>$this->user_id))->find();
        if(empty($order))$this->error('非法操作');
        if(IS_POST)
        {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id,$order);  //申请售后
            if($res['result']==1)$this->success($res['msg'],U('Order/return_goods_list'));
            $this->error($res['msg']);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region')->where("id in (".implode(',', $region_id).")")->getField('id,name');
        $order_info = array_merge($order,$order_goods);  //合并数组
        $this->assign('return_address', $return_address);
        $this->assign('goods', $order_goods);
    	$this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
    	$order_sn = I('order_sn');
    	$addtime = I('addtime');
    	$end_time = I('end_time');
    	$where = array('user_id'=>$this->user_id);
    	if($order_sn){
    		$where['order_sn'] = $order_sn;
    	}
    	$status = I('status');
    	if($status === '0' || !empty($status)){
    		$where['status'] = $status;
    	}
    	if($addtime == 1){
    		$where['addtime'] = array('gt',(time()-90*24*3600));
    	}
    	if($addtime == 2){
    		$where['addtime'] = array('lt',(time()-90*24*3600));
    	}
    	$count = M('return_goods')->where($where)->count();
    	$page = new Page($count,10);
    	$list = M('return_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
    	$goods_id_arr = get_arr_column($list, 'goods_id');
    	if(!empty($goods_id_arr))
    		$goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
    	$this->assign('goodsList', $goodsList);
    	$state = C('REFUND_STATUS');
    	$this->assign('list', $list);
    	$this->assign('state',$state);
    	$this->assign('page', $page->show());// 赋值分页输出
    	return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
    	$id = I('id/d',0);
        $user_id = $this->user_id;
    	if(IS_POST){
    		$data = I('post.');
    		$return_goods = M('return_goods')->where(array('id'=>$data['id'],'user_id'=>$user_id))->find();
    		if(empty($return_goods)) $this->error('参数错误');
    		$data['delivery'] = serialize($data['delivery']);
    		$data['status'] = 2;
    		M('return_goods')->where(['id'=>$data['id'],'user_id'=>$user_id])->save($data);
    		$this->success('发货提交成功',U('Home/Order/return_goods_info',array('id'=>$data['id'])));
    	}
    	$return_goods = M('return_goods')->where(['id' => $id,'user_id'=>$user_id])->find();
    	if(empty($return_goods)) $this->error('参数错误');
    	if($return_goods['imgs']) $return_goods['imgs'] = explode(',', $return_goods['imgs']);
    	if($return_goods['seller_delivery']) {
    		$return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);
    	}
    	if($return_goods['delivery']) {
    		$return_goods['delivery'] = unserialize($return_goods['delivery']);
    	}
    	$goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
    	$this->assign('goods',$goods);
    	$this->assign('return_goods',$return_goods);
    	$store = M('store')->where(array('store_id'=>$return_goods['store_id']))->find();
    	if($store['district']){
    		$region = M('region')->where("id in({$store['province_id']},{$store['city_id']},{$store['district']})")->getField('id,name');
    		$store['store_address'] = $region[$store['province_id']].$region[$store['city_id']].$region[$store['district']].$store['store_address'];
    	}
    	$this->assign('store',$store);
    	return $this->fetch();
    }

    public function return_goods_refund()
    {
    	$order_sn = I('order_sn');
    	$where = array('user_id'=>$this->user_id);
    	if($order_sn){
    		$where['order_sn'] = $order_sn;
    	}
    	$where['status'] = 5;
    	$count = M('return_goods')->where($where)->count();
    	$page = new Page($count,10);
    	$list = M('return_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
    	$goods_id_arr = get_arr_column($list, 'goods_id');
    	if(!empty($goods_id_arr))
    		$goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
    	$this->assign('goodsList', $goodsList);
    	$state = C('REFUND_STATUS');
    	$this->assign('list', $list);
    	$this->assign('state',$state);
    	$this->assign('page', $page->show());// 赋值分页输出
    	return $this->fetch();
    }

    /**
     * 取消服务单
     */
    public function return_goods_cancel(){
    	$id = I('id/d',0);
    	if(empty($id))$this->ajaxReturn(['status'=>0,'msg'=>'参数错误']);
    	$res=M('return_goods')->where(['id'=>$id,'user_id'=>$this->user_id])->save(array('status'=>-2,'canceltime'=>time()));
        if($res){
            $this->ajaxReturn(['status'=>1,'msg'=>'成功取消服务单']);
        }
            $this->ajaxReturn(['status'=>0,'msg'=>'服务单不存在']);
    }
}