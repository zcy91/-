<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.icngo.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 *
 * TPshop 公共逻辑类  将放到Application\Common\Logic\   由于很多模块公用 将不在放到某个单独模下面
 */

namespace app\common\logic;

use think\Model;
//use think\Page;

/**
 * 分销逻辑层
 * Class CatsLogic
 * @package Home\Logic
 */
class DistributLogic //extends Model
{
    public function hello(){
        echo 'function hello(){';
    }

    /**
     * 生成分销记录
     */
    public function rebate_log($order)
    {
        $user = M('users')->where("user_id", $order['user_id'])->find();
        $pattern = tpCache('distribut.pattern'); // 分销模式
        //按照商品分成 每件商品的佣金拿出来
        if($pattern  == 0)
        {
            // 获取所有商品分类
            $cat_list =  M('goods_category')->getField('id,parent_id,commission_rate');
            $order_goods = M('order_goods')->master()->where("order_id", $order['order_id'])->select(); // 订单所有商品
            $pay_status = M('order') -> where('order_id',$order['order_id']) -> getField('pay_status');
            $data['status'] = $pay_status;
            $commission = 0;
            foreach($order_goods as $k => $v)
            {
                $tmp_commission = 0;
                $goods = M('goods')->where("goods_id", $v['goods_id'])->find(); // 单个商品的佣金
                $price = $order['goods_price']; //出售价格
                //单个商品的分销比例
                $first_rate = (float)$goods['first_rate'];
                $second_rate = (float)$goods['second_rate'];
                $third_rate = (float)$goods['third_rate'];
                $first_tmp_commission = $price / $v['goods_num'] * ($first_rate /100);
                $second_tmp_commission = $price / $v['goods_num'] * ($second_rate /100);
                $third_tmp_commission = $price / $v['goods_num'] * ($third_rate /100);
                $first_tmp_commission = $first_tmp_commission  * $v['goods_num']; // 单个商品的一级分佣乘以购买数量
                $first_money += $first_tmp_commission; // 所有商品的一级佣金
                $second_tmp_commission = $second_tmp_commission  * $v['goods_num']; // 单个商品的二级分佣乘以购买数量
                $second_money += $second_tmp_commission; // 所有商品的二级佣金
                $third_tmp_commission = $third_tmp_commission  * $v['goods_num']; // 单个商品的三级分佣乘以购买数量
                $thirdmoney += $third_tmp_commission; // 所有商品的三级佣金
            }
        }else{
            $order_rate = tpCache('distribut.order_rate'); // 订单分成比例
            $commission = $order['goods_price'] * ($order_rate / 100); // 订单的商品总额 乘以 订单分成比例
        }

        // 如果这笔订单没有分销金额
        if($first_money == 0 && $second_money == 0 && $thirdmoney == 0)
            return false;
        //  微信消息推送
        $wx_user = M('wx_user')->find();
        $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
        // 一级 分销商赚 的钱. 小于一分钱的 不存储
        if($user['first_leader'] > 0 && $first_money > 0.01)
        {
            $data = array(
                'user_id' =>$user['first_leader'],
                'buy_user_id'=>$user['user_id'],
                'nickname'=>$user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['goods_price'],
                'money' => $first_money,
                'level' => 1,
                'create_time' => time(),
            );
            $pay_status = M('order') -> where('order_id',$order['order_id']) -> getField('pay_status');
            $data['status'] = $pay_status;
            M('rebate_log')->add($data);
            // 微信推送消息
            $tmp_user = M('users')->where("user_id", $user['first_leader'])->find();
            if($tmp_user['oauth']== 'weixin')
            {
                $wx_content = "你的一级下线,刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥".$first_money."奖励 !";
                $jssdk->push_msg($tmp_user['openid'],$wx_content);
            }
        }
        // 二级 分销商赚 的钱.
        if($user['second_leader'] > 0 && $second_money > 0.01)
        {
            $data = array(
                'user_id' =>$user['second_leader'],
                'buy_user_id'=>$user['user_id'],
                'nickname'=>$user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['goods_price'],
                'money' => $second_money,
                'level' => 2,
                'create_time' => time(),
            );
            $pay_status = M('order') -> where('order_id',$order['order_id']) -> getField('pay_status');
            $data['status'] = $pay_status;
            M('rebate_log')->add($data);
            // 微信推送消息
            $tmp_user = M('users')->where("user_id", $user['second_leader'])->find();
            if($tmp_user['oauth']== 'weixin')
            {
                $wx_content = "你的二级下线,刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥".$second_money."奖励 !";
                $jssdk->push_msg($tmp_user['openid'],$wx_content);
            }
        }
        // 三级 分销商赚 的钱.
        if($user['third_leader'] > 0 && $thirdmoney > 0.01)
        {
            $data = array(
                'user_id' =>$user['third_leader'],
                'buy_user_id'=>$user['user_id'],
                'nickname'=>$user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['goods_price'],
                'money' =>$thirdmoney,
                'level' => 3,
                'create_time' => time(),
            );
            $pay_status = M('order') -> where('order_id',$order['order_id']) -> getField('pay_status');
            $data['status'] = $pay_status;
            M('rebate_log')->add($data);
            // 微信推送消息
            $tmp_user = M('users')->where("user_id", $user['third_leader'])->find();
            if($tmp_user['oauth']== 'weixin')
            {
                $wx_content = "你的三级下线,刚刚下了一笔订单:{$order['order_sn']} 如果交易成功你将获得 ￥".$thirdmoney."奖励 !";
                $jssdk->push_msg($tmp_user['openid'],$wx_content);
            }

        }
        M('order')->where("order_id", $order['order_id'])->save(array("is_distribut"=>1));  //修改订单为已经分成
    }

    /**
     * 自动分成 符合条件的 分成记录
     */
    function auto_confirm(){

        $switch = tpCache('distribut.switch');
        if($switch == 0)
            return false;

        $today_time = time();
        $distribut_date = tpCache('distribut.date');
        $distribut_time = $distribut_date * (60 * 60 * 24); // 计算天数 时间戳
        $rebate_log_arr = M('rebate_log')->where("status = 2 and ($today_time - confirm) >  $distribut_time")->select();
        foreach ($rebate_log_arr as $key => $val)
        {
            accountLog($val['user_id'], $val['money'], 0,"订单:{$val['order_sn']}分佣",$val['money']);
            $val['status'] = 3;
            $val['confirm_time'] = $today_time;
            $val['remark'] = $val['remark']."满{$distribut_date}天,程序自动分成.";
            M("rebate_log")->where("id", $val['id'])->save($val);
        }
    }
}