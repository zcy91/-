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
 * Date: 2016-03-19
 */

namespace app\common\logic;


/**
 * Class orderLogic
 * @package Common\Logic
 */
class OrderLogic
{
	public function addReturnGoods($rec_id,$order)
	{
		$data = I('post.');
		$confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
		$confirm_time = $confirm_time_config * 24 * 60 * 60;
		if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
			return ['result'=>-1,'msg'=>'已经超过' . $confirm_time_config . "天内退货时间"];
		}

		if ($_FILES['return_imgs']['tmp_name'][0]) {
			$files = request()->file("return_imgs");
			$validate = ['size'=>1024 * 1024 * 3,'ext'=>'jpg,png,gif,jpeg'];
			$dir = 'public/upload/return_goods/';
			if (!($_exists = file_exists($dir))){
				$isMk = mkdir($dir);
			}
			$parentDir = date('Ymd');
			foreach($files as $key => $file){
				$info = $file->rule($parentDir)->validate($validate)->move($dir, true);
				if($info){
					$filename = $info->getFilename();
					$new_name = '/'.$dir.$parentDir.'/'.$filename;
					$return_imgs[]= $new_name;
				}else{
					$this->error($info->getError());//上传错误提示错误信息
				}
			}
			if (!empty($return_imgs)) {
				$data['imgs'] = implode(',', $return_imgs);// 上传的图片文件
			}
		}

		$data['addtime'] = time();
		$data['user_id'] = $order['user_id'];
		$order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();
		if($data['type'] < 2){
			//退款申请，若该商品有赠送积分或优惠券，在平台操作退款时需要追回
			$rate = round($order_goods['member_goods_price']*$data['goods_num']/$order['goods_price'],2);
			if($order['order_amount']>0){
				$data['refund_money'] = $rate*$order['order_amount'];//退款金额
				$data['refund_deposit'] = $rate*$order['user_money'];//该退余额支付部分
				$data['refund_integral'] = floor($rate*$order['integral']);//该退积分支付
			}else{
				$data['refund_deposit'] = $rate*$order['user_money'];//该退余额支付部分
				$data['refund_integral'] = floor($rate*$order['integral']);//该退积分支付
			}
		}

		if(!empty($data['id'])){
			$result = M('return_goods')->where(array('id'=>$data['id']))->save($data);
		}else{
			$result = M('return_goods')->add($data);
		}

		if($result){
			return ['result'=>1,'msg'=>'申请成功'];
		}
		return ['result'=>-1,'msg'=>'申请失败'];
	}

    /**
     * 获取可申请退换货订单商品
     * @param $sale_t
     * @param $keywords
     * @param $user_id
     * @return array
     */
    public function getReturnGoodsIndex($sale_t, $keywords, $user_id)
    {
        if($keywords){
            $condition['order_sn'] = $keywords;
        }
        if($sale_t == 1){
            //三个月内
            $condition['add_time'] = array('gt' , 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        }else if($sale_t == 2){
            //三个月前
            $condition['add_time'] = array('lt' , 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
        }
    	$condition['user_id'] = $user_id;
    	$condition['pay_status'] = 1;
    	$condition['shipping_status'] = 1;
    	$condition['deleted'] = 0;
    	$count = M('order')->where($condition)->count();
    	$Page  = new \think\Page($count,10);
    	$show = $Page->show();
    	$order_list = M('order')->where($condition)->order('order_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();

    	foreach ($order_list as $k=>$v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $data = M('order_goods')->where(['order_id'=>$v['order_id'],'is_send'=>['lt',2]])->select();
            if(!empty($data)){
                $order_list[$k]['goods_list'] = $data;
            }else{
                unset($order_list[$k]);  //除去没有可申请的订单
            }

    	}

        return [
            'order_list' => $order_list,
            'page' => $show
        ];
    }

    /**
     * 获取退货列表
     * @param type $keywords
     * @param type $addtime
     * @param type $status
     * @return type
     */
    public function getReturnGoodsList($keywords, $addtime, $status)
	{
		if($keywords){
            $where['order_sn|goods_name'] = array('like',"%$keywords%");
    	}
    	if($status === '0' || !empty($status)){
            $where['status'] = $status;
    	}
    	if($addtime == 1){
            $where['addtime'] = array('gt',(time()-90*24*3600));
    	}
    	if($addtime == 2){
            $where['addtime'] = array('lt',(time()-90*24*3600));
    	}
    	$query = M('return_goods')->alias('r')->field('r.*,g.goods_name')
                ->join('__ORDER__ o', 'r.order_id = o.order_id AND o.deleted = 0')
                ->join('__GOODS__ g', 'r.goods_id = g.goods_id', 'LEFT')
                ->where($where);
        $query2 = clone $query;
        $count = $query->count();
    	$page = new \think\Page($count,10);
    	$list = $query2->order("id desc")->limit($page->firstRow, $page->listRows)->select();
    	$goods_id_arr = get_arr_column($list, 'goods_id');
    	if(!empty($goods_id_arr)) {
            $goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
        }

        return [
            'goodsList' => $goodsList,
            'return_list' => $list,
            'page' => $page->show()
        ];
	}

    /**
     * 删除订单
     * @param type $order_id
     * @return type
     */
    public function delOrder($order_id)
    {
        $validate = validate('order');
        if (!$validate->scene('del')->check(['order_id' => $order_id])) {
            return ['status' => 0, 'msg' => $validate->getError()];
        }

        $row = M('order')->where('order_id' ,$order_id)->update(['deleted'=>1]);
        if (!$row) {
            return ['status'=>-1,'msg'=>'删除失败'];
        }

        return ['status'=>1,'msg'=>'删除成功'];
    }

    /**
     * 记录取消订单
     */
    public function recordRefundOrder($user_id, $order_id, $user_note, $consignee, $mobile)
    {
    	$order = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
    	if (!$order) {
    		return ['status' => -1, 'msg' => '订单不存在'];
    	}
    	$order_return_num = M('return_goods')->where(['order_id' => $order_id, 'user_id' => $user_id,'status'=>['neq',5]])->count();
    	if($order_return_num > 0){
    		return ['status' => -1, 'msg' => '该订单中有商品正在申请售后'];
    	}
    	$order_status = 3;//已取消
    	$order_info = [
    	'user_note' => $user_note,
    	'consignee' => $consignee,
    	'mobile'    => $mobile,
    	'order_status'=> $order_status,
    	];

    	$result = M('order')->where(['order_id' => $order_id])->update($order_info);
    	if (!$result) {
    		return ['status' => 0, 'msg' => '操作失败'];
    	}

    	$data['order_id'] = $order_id;
    	$data['action_user'] = $user_id;
    	$data['action_note'] = $user_note;
    	$data['order_status'] = $order_status;
    	$data['pay_status'] = $order['pay_status'];
    	$data['shipping_status'] = $order['shipping_status'];
    	$data['log_time'] = time();
    	$data['status_desc'] = '用户取消已付款订单';
    	M('order_action')->add($data);//订单操作记录
    	return ['status' => 1, 'msg' => '提交成功'];
    }
}