<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.icngo.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用TP5助手函数可实现单字母函数M D U等,也可db::name方式,可双向兼容
 * ============================================================================
 * $Author: 当燃   2016-05-10
 */
namespace app\mobile\controller;
use think\Db;
use think\Page;

class Activity extends MobileBase {
    public function index(){
        return $this->fetch();
    }
   /**
    * 商品详情页
    */
    public function group(){
        //form表单提交
        C('TOKEN_ON',true);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d",66);

        $group_buy_info = M('GroupBuy')->where(['goods_id'=>$goods_id,'start_time'=>['<=',time()],'end_time'=>['>=',time()]])->find(); // 找出这个商品
        if(empty($group_buy_info))
        {
            $this->error("此商品没有团购活动",U('Mobile/Activity/group_list'));
        }

        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册

        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表

        // 商品规格 价钱 库存表 找出 所有 规格项id
        $keys = M('SpecGoodsPrice')->where("goods_id", $goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') ");
        if($keys) {
            $filter_spec = $goodsLogic->get_spec($goods_id);
        }
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        M('Goods')->where("goods_id", $goods_id)->save(array('click_count'=>$goods['click_count']+1 )); // 统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数
        $this->assign('group_buy_info',$group_buy_info);
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
        $this->assign('commentStatistics',$commentStatistics);
        $this->assign('goods_attribute',$goods_attribute);
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('filter_spec',$filter_spec);
        $this->assign('goods_images_list',$goods_images_list);
        $this->assign('goods',$goods);
        $this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
        return $this->fetch();
    }


    /**
     * 团购活动列表
     */
    public function group_list()
    {
        $istype =I('get.type');
        //以最新新品排序
        if($istype == 'new'){
            $orderby = 'start_time desc';
        }
    	$count =  M('GroupBuy')->where(time()." >= start_time and ".time()." <= end_time ")->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
    	$Page = new Page($count,$pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
    	$show = $Page->show();  // 分页显示输出
    	$this->assign('page',$show);    // 赋值分页输出
        $list = M('GroupBuy')->where(time()." >= start_time and ".time()." <= end_time ")->order($orderby)->limit($Page->firstRow.','.$Page->listRows)->select();   // 找出这个商品
        $this->assign('list', $list);
        if(I('is_ajax')) {
            return $this->fetch('ajax_group_list');      //输出分页
        }
        return $this->fetch();
    }

    /**
     * 活动商品列表
     */
    public function discount_list(){
        $prom_id =I('id/d');    //活动ID
        $where = array(     //条件
            'prom_type'=>3,
            'prom_id'=>$prom_id,
        );
        $pagesize = C('PAGESIZE');  //每页显示数
    	$count =  M('goods')->where($where)->count(); // 查询满足要求的总记录数
    	$Page = new Page($count,$pagesize); //分页类
        $prom_list = M('goods')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select(); //活动对应的商品
    	$this->assign('prom_list', $prom_list);
        if(I('is_ajax')){
            return $this->fetch('ajax_discount_list');
        }
    	return $this->fetch();
    }

    /**
     * 商品活动页面
     * @author lxl
     * @time2017-1
     */
    public function promote_goods(){
        $now_time = time();
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $count = M('prom_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
        $this->assign('promote',$promote);
        if(I('is_ajax')){
            return $this->fetch('ajax_promote_goods');
        }
        return $this->fetch();
    }


    /**
     * 抢购活动列表页
     */
    public function flash_sale_list()
    {
        $time_space = flash_sale_time_space();
        $this->assign('time_space', $time_space);
        return $this->fetch();
    }

    /**
     * 抢购活动列表ajax
     */
    public function ajax_flash_sale()
    {
        $p = I('p',1);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $where = array(
            'f.start_time'=>array('egt',$start_time),
            'f.end_time'=>array('elt',$end_time)
        );
        $flash_sale_goods = M('flash_sale')
            ->field('f.end_time,f.goods_name,f.price,f.goods_id,f.price,g.shop_price,100*(FORMAT(f.buy_num/f.goods_num,2)) as percent')
            ->alias('f')
            ->join('__GOODS__ g','g.goods_id = f.goods_id')
            ->where($where)
            ->page($p,10)
            ->select();
        $this->assign('flash_sale_goods',$flash_sale_goods);
        return $this->fetch();
    }

    public function coupon_list()
    {
        $atype = I('atype', 1);
        $where = array('type' => 2,'send_start_time'=>['elt',time()],'send_end_time'=>['egt',time()]);
        $order = array('id' => 'desc');
        if ($atype == 2) {
            //即将过期
            $order = ['spacing_time' => 'asc'];
            $where['send_end_time-UNIX_TIMESTAMP()'] = ['egt', 0];
        }
        if ($atype == 3) {
            //面值最大
            $order = ['money' => 'desc'];
        }
        $count = M('coupon')->where($where)->count();
        $Page = new Page($count, 15);
        $coupon_list = M('coupon')->alias('c')->field(C('database.prefix') . 'coupon.*,send_end_time-UNIX_TIMESTAMP() as spacing_time')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order($order)->select();
        if (is_array($coupon_list) && count($coupon_list) > 0) {
            $user = session('user');
            if ($user) {
                $user_coupon = M('coupon_list')->where(array('uid' => $user['user_id'], 'type' => 2))->getField('cid');
            }
            if (!empty($user_coupon)) {
                foreach ($coupon_list as $k => $val) {
                    if (!empty($user_coupon[$val['id']])) {
                        $coupon_list[$k]['isget'] = 1;
                    }
                }
            }
        }
        $this->assign('atype', $atype);
        $this->assign('coupon_list', $coupon_list);
        $this->assign('listRows', $Page->listRows);
        if (request()->isAjax()) {
            return $this->fetch('ajax_coupon_list');
        }
        return $this->fetch();
    }

    /**
     * 领券
     */
    public function getCoupon()
    {
        $id = I('coupon_id/d');
        if (empty($id)){
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        if (session('?user')) {
            $user = session('user');
            $coupon_info = M('coupon')->where(array('id' => $id))->find();
            if (empty($coupon_info)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '活动已结束或不存在，看下其他活动吧~']);
            } elseif ($coupon_info['send_end_time'] < time()) {
                //来晚了，过了领取时间
                $this->ajaxReturn(['status' => 0, 'msg' => '抱歉，已经过了领取时间']);
            } elseif ($coupon_info['send_num'] >= $coupon_info['createnum']) {
                //来晚了，优惠券被抢完了
                $this->ajaxReturn(['status' => 0, 'msg' => '来晚了，优惠券被抢完了']);
            } else {
                $userCouponCount = M('coupon_list')->where(array('cid' => $id, 'uid' => $user['user_id']))->count();
                if ($userCouponCount > 0) {
                    //已经领取过
                    $this->ajaxReturn(['status' => 2, 'msg' => '您已领取过该优惠券']);
                } else {
                    $data = array('uid' => $user['user_id'], 'cid' => $id, 'type' => 2, 'send_time' => time());
                    M('coupon_list')->add($data);
                    M('coupon')->where(array('id' => $id))->setInc('send_num');
                    $this->ajaxReturn(['status' => 1, 'msg' => '恭喜您，抢到' . $coupon_info['money'] . '元优惠券!']);
                }
            }
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '请先登录']);
        }
    }
}