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
 * 专题管理
 * Date: 2016-03-09
 */

namespace app\admin\controller;

use app\admin\model\GoodsActivity;
use think\AjaxPage;
use think\Page;
use app\admin\logic\GoodsLogic;
use think\Loader;
use think\Db;

class Promotion extends Base
{

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 商品活动列表
     */
    public function prom_goods_list()
    {
        $parse_type = array('0' => '直接打折', '1' => '减价优惠', '2' => '固定金额出售', '3' => '买就赠优惠券');
        $level = M('user_level')->select();
        if ($level) {
            foreach ($level as $v) {
                $lv[$v['level_id']] = $v['level_name'];
            }
        }
        $this->assign("parse_type", $parse_type);

        $count = M('prom_goods')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $res = M('prom_goods')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if ($res) {
            foreach ($res as $val) {
                if (!empty($val['group']) && !empty($lv)) {
                    $val['group'] = explode(',', $val['group']);
                    foreach ($val['group'] as $v) {
                        $val['group_name'] .= $lv[$v] . ',';
                    }
                }
                $prom_list[] = $val;
            }
        }
        $this->assign('pager',$Page);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('prom_list', $prom_list);
        return $this->fetch();
    }

    public function prom_goods_info()
    {
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info['start_time'] = date('Y-m-d');
        $info['end_time'] = date('Y-m-d', time() + 3600 * 60 * 24);
        if ($prom_id > 0) {
            $info = M('prom_goods')->where("id=$prom_id")->find();
            $info['start_time'] = date('Y-m-d', $info['start_time']);
            $info['end_time'] = date('Y-m-d', $info['end_time']);
            $prom_goods = M('goods')->where("prom_id=$prom_id and prom_type=3")->select();
            $this->assign('prom_goods', $prom_goods);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        $this->initEditor();
        return $this->fetch();
    }

    public function prom_goods_save()
    {
        $prom_id = I('id');
        $data = I('post.');
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if($data['start_time']>=$data['end_time']){
            $this->error('开始时间不能大于结束时间', U('Promotion/prom_goods_list'));
        }
        $data['group'] = $data['group'] ? implode(',', $data['group']) : '';
        $data['goods_ids'] = $data['goods_id'] ? implode(',', $data['goods_id']) : '';
        if ($prom_id) {
            M('prom_goods')->where("id", $prom_id)->save($data);
            $last_id = $prom_id;
            adminLog("管理员修改了商品促销 " . I('name'));
        } else {
            $last_id = M('prom_goods')->add($data);
            adminLog("管理员添加了商品促销 " . I('name'));
        }

        if (is_array($data['goods_id'])) {
            $goods_id = implode(',', $data['goods_id']);
            if ($prom_id > 0) {
                M("goods")->where("prom_id=$prom_id and prom_type=3")->save(array('prom_id' => 0, 'prom_type' => 0));
            }
            M("goods")->where("goods_id in($goods_id)")->save(array('prom_id' => $last_id, 'prom_type' => 3));
        }
        $this->success('编辑促销活动成功', U('Promotion/prom_goods_list'));
    }

    public function prom_goods_del()
    {
        $prom_id = I('id');
        $order_goods = M('order_goods')->where("prom_type = 3 and prom_id = $prom_id")->find();
        if (!empty($order_goods)) {
            $this->error("该活动有订单参与不能删除!");
        }
        M("goods")->where("prom_id=$prom_id and prom_type=3")->save(array('prom_id' => 0, 'prom_type' => 0));
        M('prom_goods')->where("id=$prom_id")->delete();
        $this->success('删除活动成功', U('Promotion/prom_goods_list'));
    }


    /**
     * 活动列表
     */
    public function prom_order_list()
    {
        $parse_type = array('0' => '满额打折', '1' => '满额优惠金额', '2' => '满额送积分', '3' => '满额送优惠券');
        $level = M('user_level')->select();
        if ($level) {
            foreach ($level as $v) {
                $lv[$v['level_id']] = $v['level_name'];
            }
        }
        $count = M('prom_order')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $res = M('prom_order')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if ($res) {
            foreach ($res as $val) {
                if (!empty($val['group']) && !empty($lv)) {
                    $val['group'] = explode(',', $val['group']);
                    foreach ($val['group'] as $v) {
                        $val['group_name'] .= $lv[$v] . ',';
                    }
                }
                $prom_list[] = $val;
            }
        }
        $this->assign('pager', $Page);// 赋值分页输出
        $this->assign('page', $show);// 赋值分页输出
        $this->assign("parse_type", $parse_type);
        $this->assign('prom_list', $prom_list);
        return $this->fetch();
    }

    public function prom_order_info()
    {
        $this->assign('min_date', date('Y-m-d'));
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info['start_time'] = date('Y-m-d');
        $info['end_time'] = date('Y-m-d', time() + 3600 * 24 * 60);
        if ($prom_id > 0) {
            $info = M('prom_order')->where("id=$prom_id")->find();
            $info['start_time'] = date('Y-m-d', $info['start_time']);
            $info['end_time'] = date('Y-m-d', $info['end_time']);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        $this->initEditor();
        return $this->fetch();
    }

    public function prom_order_save()
    {
        $prom_id = I('id');
        $data = I('post.');
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $data['group'] = $data['group'] ? implode(',', $data['group']) : '';
        if ($prom_id) {
            M('prom_order')->where("id=$prom_id")->save($data);
            adminLog("管理员修改了商品促销 " . I('name'));
        } else {
            M('prom_order')->add($data);
            adminLog("管理员添加了商品促销 " . I('name'));
        }
        $this->success('编辑促销活动成功', U('Promotion/prom_order_list'));
    }

    public function prom_order_del()
    {
        $prom_id = I('id');
        $order = M('order')->where("order_prom_id = $prom_id")->find();
        if (!empty($order)) {
            $this->error("该活动有订单参与不能删除!");
        }

        M('prom_order')->where("id=$prom_id")->delete();
        $this->success('删除活动成功', U('Promotion/prom_order_list'));
    }

    public function group_buy_list()
    {
        $Ad = M('group_buy');
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $res = $Ad->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        if ($res) {
            foreach ($res as $val) {
                $val['start_time'] = date('Y-m-d H:i', $val['start_time']);
                $val['end_time'] = date('Y-m-d H:i', $val['end_time']);
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $show = $Page->show();
        $this->assign('page', $show);
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    public function group_buy()
    {
        $act = I('GET.act', 'add');
        $groupbuy_id = I('get.id');
        $group_info = array();
        $group_info['start_time'] = date('Y-m-d');
        $group_info['end_time'] = date('Y-m-d', time() + 3600 * 365);
        if ($groupbuy_id) {
            $group_info = D('group_buy')->where('id=' . $groupbuy_id)->find();
            $group_info['start_time'] = date('Y-m-d H:i', $group_info['start_time']);
            $group_info['end_time'] = date('Y-m-d H:i', $group_info['end_time']);
            $act = 'edit';
        }
        $this->assign('min_date', date('Y-m-d'));
        $this->assign('info', $group_info);
        $this->assign('act', $act);
        $this->initEditor(); // 编辑器
        return $this->fetch();
    }

    public function groupbuyHandle()
    {
        $data = I('post.');
        $data['groupbuy_intro'] = htmlspecialchars(stripslashes($this->request->param('groupbuy_intro')));
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($data['act'] == 'del') {
            $r = D('group_buy')->where('id=' . $data['id'])->delete();
            M('goods')->where("prom_type=2 and prom_id=" . $data['id'])->save(array('prom_id' => 0, 'prom_type' => 0));
            if ($r) exit(json_encode(1));
        }
        $groupBuyValidate = Loader::validate('GroupBuy');
        if(!$groupBuyValidate->batch()->check($data)){
            $return = ['status' => 0,'msg' =>'操作失败','result' => $groupBuyValidate->getError() ];
            $this->ajaxReturn($return);
        }
        if ($data['price'] >= $data['goods_price']) {
            $this->ajaxReturn(['status' => 0,'msg' =>'操作失败','result' => ['price'=>'团购价格不能大于或等于原商品价格']]);
        } else {
            $data['rebate'] = round(($data['price']/$data['goods_price'])*10,1);   //计算折扣
        }
        if ($data['act'] == 'add') {
            $r = D('group_buy')->add($data);
            M('goods')->where("goods_id=" . $data['goods_id'])->save(array('prom_id' => $r, 'prom_type' => 2));
        }
        if ($data['act'] == 'edit') {
            $r = D('group_buy')->where('id=' . $data['id'])->save($data);
            M('goods')->where("prom_type=2 and prom_id=" . $data['id'])->save(array('prom_id' => 0, 'prom_type' => 0));
            M('goods')->where("goods_id=" . $data['goods_id'])->save(array('prom_id' => $data['id'], 'prom_type' => 2));
        }
        if ($r) {
            $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0,'msg' =>'操作失败','result' =>'']);
        }
    }

    public function get_goods()
    {
        $prom_id = I('id');
        $promGoods = Db::name('prom_goods')->where(['id'=>$prom_id])->find();
        $prom_goods = M('goods')->where("prom_id=$prom_id and prom_type=3")-> getField('goods_id',true);
        $goodsList = Db::name('goods')->where('goods_id','in',$prom_goods)->select();
        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }

    public function search_goods()
    {
        $GoodsLogic = new GoodsLogic;
        $brandList = $GoodsLogic->getSortBrands();
        $this->assign('brandList', $brandList);
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);

        $goods_id = I('goods_id');
        $where = ' is_on_sale = 1 and prom_type=0 and store_count>0 ';//搜索条件
        if (!empty($goods_id)) {
            $where .= " and goods_id not in ($goods_id) ";
        }
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        if (I('cat_id')) {
            $this->assign('cat_id', I('cat_id'));
            $grandson_ids = getCatGrandson(I('cat_id'));
            $where = " $where  and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        if (I('brand_id')) {
            $this->assign('brand_id', I('brand_id'));
            $where = "$where and brand_id = " . I('brand_id');
        }
        if (!empty($_REQUEST['keywords'])) {
            $this->assign('keywords', I('keywords'));
            $where = "$where and (goods_name like '%" . I('keywords') . "%' or keywords like '%" . I('keywords') . "%')";
        }
        $count = M('goods')->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = M('goods')->where($where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();//分页显示输出
        $this->assign('page', $show);//赋值分页输出
        $this->assign('goodsList', $goodsList);
        $this->assign('pager', $Page);//赋值分页输出
        $tpl = I('get.tpl', 'search_goods');
        return $this->fetch($tpl);
    }

    //限时抢购
    public function flash_sale()
    {
        $condition = array();
        $model = M('flash_sale');
        $count = $model->where($condition)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $prom_list = $model->where($condition)->order("id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('prom_list', $prom_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    public function flash_sale_info()
    {
        $id = I('id');
        if (IS_POST) {
            $data = I('post.');
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $flashSaleValidate = Loader::validate('FlashSale');
            if (!$flashSaleValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $flashSaleValidate->getError()];
                $this->ajaxReturn($return);
            }
            if (empty($data['id'])) {
                $insert_id = Db::name('flash_sale')->insertGetId($data);
                $r = Db::name('goods')->where('goods_id', $data['goods_id'])->update(['prom_id' => $insert_id, 'prom_type' => 1]);
                adminLog("管理员添加抢购活动 " . $data['name']);
            } else {
                $r = M('flash_sale')->where("id=" . $data['id'])->save($data);
                M('goods')->where("prom_type=1 and prom_id=" . $data['id'])->save(array('prom_id' => 0, 'prom_type' => 0));
                M('goods')->where("goods_id=" . $data['goods_id'])->save(array('prom_id' => $data['id'], 'prom_type' => 1));
            }
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '编辑抢购活动成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '编辑抢购活动失败', 'result' => ''];
            }
            $this->ajaxReturn($return);
        }
        $now_time = date('H');
        if ($now_time % 2 == 0) {
            $flash_now_time = $now_time;
        } else {
            $flash_now_time = $now_time - 1;
        }
        $flash_sale_time = strtotime(date('Y-m-d') . " " . $flash_now_time . ":00:00");
        $info['start_time'] = date("Y-m-d H:i:s", $flash_sale_time);
        $info['end_time'] = date("Y-m-d H:i:s", $flash_sale_time + 7200);
        if ($id > 0) {
            $info = M('flash_sale')->where("id=$id")->find();
            $info['start_time'] = date('Y-m-d H:i', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i', $info['end_time']);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        return $this->fetch();
    }

    public function flash_sale_del()
    {
        $id = I('del_id');
        if ($id) {
            M('flash_sale')->where("id=$id")->delete();
            M('goods')->where("prom_type=1 and prom_id=$id")->save(array('prom_id' => 0, 'prom_type' => 0));
            exit(json_encode(1));
        } else {
            exit(json_encode(0));
        }
    }

    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp', array('savepath' => 'promotion')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp', array('savepath' => 'promotion')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage', array('savepath' => 'promotion')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager', array('savepath' => 'promotion')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie', array('savepath' => 'promotion')));
        $this->assign("URL_Home", "");
    }

    /**
     * 商品预售列表
     *
     */
    public function pre_sell_list()
    {
        $condition = array('act_type' => 1);
        I('keywords') && $condition['goods_name'] = I('keywords');
        $model = D('goods_activity');
        $count = $model->where($condition)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $pre_sell_list = $model->where($condition)->order("act_id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($pre_sell_list as $key => $val) {
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key]->toArray(), unserialize($pre_sell_list[$key]['ext_info']));
            $pre_sell_list[$key]['act_status'] = $model->getPreStatusAttr($pre_sell_list[$key]);
            $pre_count_info = $model->getPreCountInfo($pre_sell_list[$key]['act_id'], $pre_sell_list[$key]['goods_id']);
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key], $pre_count_info);
            $pre_sell_list[$key]['price'] = $model->getPrePrice($pre_sell_list[$key]['total_goods'], $pre_sell_list[$key]['price_ladder']);
        }
        $this->assign('pre_sell_list', $pre_sell_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 预售商品商品详情页
     */
    public function pre_sell_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $goods_logic = new GoodsLogic();
            $save = $goods_logic->savePreSell($data);
            if ($save['status']) {
                $this->success($save['msg'], U('Promotion/pre_sell_list'));
                exit();
            } else {
                $this->error($save['msg']);
            }
        }
        $id = I('id');
        $default_time['start_time'] = date('Y-m-d H:i:s');
        $default_time['end_time'] = date('Y-m-d 23:59:59', time() + 3600 * 24 * 7);
        if ($id > 0) {
            $goods_activity_model = new GoodsActivity();
            $info = M('goods_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
            $pre_count_info = $goods_activity_model->getPreCountInfo($info['act_id'], $info['goods_id']);
            if (empty($info)) {
                $this->error('该预售商品活动已被删除或者不存在', U('Promotion/pre_sell_list'));
            }
            $info['start_time'] = date('Y-m-d H:i', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i', $info['end_time']);
            $info = array_merge($info, unserialize($info['ext_info']));
            if (!empty($info['retainage_start']) || !empty($info['retainage_start'])) {
                $info['retainage_start'] = date('Y-m-d H:i', $info['retainage_start']);
                $info['retainage_end'] = date('Y-m-d H:i', $info['retainage_end']);
            }
            $this->assign('pre_count_info', $pre_count_info);//预售商品的订购数量和订单数量
            $this->assign('info', $info);
        }
        $this->assign('default_time', $default_time);
        $this->assign('min_date', date('Y-m-d'));
        return $this->fetch();
    }

    /**
     * 预售商品删除处理
     */
    public function pre_sell_del()
    {
        $id = I('del_id');
        if ($id) {
            $goods_activity = M('goods_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
            if (empty($goods_activity)) {
                exit(json_encode(array('status' => 0, 'msg' => '删除的商品不存在')));
            }
            $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
            if ($goods_activity['is_finished'] == 0) {
                if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
                    exit(json_encode(array('status' => 0, 'msg' => '该预售商品正在预售中不能删除，请先结束活动，并编辑活动失败')));
                }
                if ($goods_activity['end_time'] < time()) {
                    exit(json_encode(array('status' => 0, 'msg' => '该预售商品结束未处理，请先编辑活动失败')));
                }
            }
            $pre_sell_order_count_where = array(
                'order_prom_type' => 4,
                'order_prom_id' => $id,
//					'order_status' => array('neq', 5)
            );
            $pre_sell_order_count = M('order')->where($pre_sell_order_count_where)->count();
            if ($pre_sell_order_count > 0) {
                exit(json_encode(array('status' => 0, 'msg' => '该预售商品已有' . $pre_sell_order_count . '个订单,不能删除')));
            } else {
                M('goods_activity')->where("act_id=$id")->delete();
                M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_is']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
            }
            exit(json_encode(array('status' => 1, 'msg' => '删除成功,并下架了该商品')));
        } else {
            exit(json_encode(array('status' => 0, 'msg' => '非法操作')));
        }
    }

    /**
     * 预售活动成功
     */
    public function pre_sell_success()
    {
        $act_id = I('id');
        if (empty($act_id)) {
            $this->error('非法操作');
        }
        $goods_activity = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
        if (empty($goods_activity)) {
            $this->error('该预售商品不存在');
        }
        if ($goods_activity['is_finished'] != 0) {
            $this->error('该预售商品已经结束');
        }
        if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
            $this->error('该预售商品正在预售中，请先结束活动');
        }
        //获取预售商品最后的价格
        $pre_count_info = D('goods_activity')->getPreCountInfo($goods_activity['act_id'], $goods_activity['goods_id']);
        $pre_sell_final_price = D('goods_activity')->getPrePrice($pre_count_info['total_goods'], $goods_activity['price_ladder']);
        //获取购买预售商品的订单id数组
        $pre_sell_order_id_where = array(
            'order_prom_type' => 4,
            'order_prom_id' => $goods_activity['act_id'],
            'order_status' => 0
        );
        $pre_sell_order_id_list = M('order')->where($pre_sell_order_id_where)->getField('order_id', true);
        if (count($pre_sell_order_id_list) > 0) {
            //更新所有预售商品的订单的订单商品的金额
            M('order_goods')->where(array('order_id' => array('IN', $pre_sell_order_id_list)))->save(array('member_goods_price' => $pre_sell_final_price));
            //获取所有更新后的订单商品的商品总价
            $pre_sell_order_goods = M('order_goods')
                ->field('order_id,SUM(goods_num*member_goods_price) as goods_amount')
                ->where(array('order_id' => array('IN', $pre_sell_order_id_list)))
                ->group('order_id')
                ->select();
            //更新订单的价格
            foreach ($pre_sell_order_goods as $key => $val) {
                $able_message = false;//是否需要通知用户
                $message = '';
                $pre_sell_order = M('order')->field('order_sn,user_id,order_id,user_id,paid_money,pay_status,order_amount')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->find();
                //如果订单未支付的将其作废
                if ($pre_sell_order['pay_status'] == 0) {
                    M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save(array('order_status' => 5));
                }
                //如果是支付定金的
                if ($pre_sell_order['paid_money'] > 0 && $pre_sell_order['pay_status'] == 2) {
                    $save_data = array(
                        'goods_price' => $pre_sell_order_goods[$key]['goods_amount'],
                        'total_amount' => $pre_sell_order_goods[$key]['goods_amount'],
                        'order_amount' => $pre_sell_order_goods[$key]['goods_amount'] - $pre_sell_order['paid_money']//需要支付的尾款
                    );
                    M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save($save_data);
                    $able_message = true;
                    $message = '您的预售订单需要支付尾款，订单号为' . $pre_sell_order['order_sn'];
                }
                //如果是支付全款的
                if ($pre_sell_order['paid_money'] == 0 && $pre_sell_order['pay_status'] == 1) {
                    //如果需要退还差价的
                    if ($pre_sell_order['order_amount'] > $pre_sell_order_goods[$key]['goods_amount']) {
                        $save_data2 = array(
                            'goods_price' => $pre_sell_order_goods[$key]['goods_amount'],
                            'total_amount' => $pre_sell_order_goods[$key]['goods_amount'],
                            'order_amount' => $pre_sell_order_goods[$key]['goods_amount']
                        );
                        M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save($save_data2);
                        $cha_amount = $pre_sell_order['order_amount'] - $pre_sell_order_goods[$key]['goods_amount'];
                        accountLog($pre_sell_order['user_id'], $cha_amount, 0, '退还预售商品' . $goods_activity['act_name'] . '的差价，订单ID为' . $pre_sell_order['order_id']);
                    }
                }
                //通知用户订单处理
                if ($able_message == true) {
                    $user_info = M('users')->where('user_id = ' . $pre_sell_order['user_id'])->find();
                    if (!empty($user_info)) {
                        if (!empty($user_info['email'])) {
                            //send_email($user_info['email'], '预售订单处理', $message);
                        }
                    }
                }
            }
        }

        M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->save(array('is_finished' => 1));
        M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_id']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
        $this->success('该预售商品成功结束,并下架了该商品', U('Admin/Promotion/pre_sell_list'));
    }

    /**
     * 预售活动失败
     */
    public function pre_sell_fail()
    {
        $act_id = I('id');
        if (empty($act_id)) {
            $this->error('非法操作');
        }
        $goods_activity = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
        if (empty($goods_activity)) {
            $this->error('该预售商品不存在');
        }
        if ($goods_activity['is_finished'] != 0) {
            $this->error('该预售商品已经结束');
        }
        if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
            $this->error('该预售商品正在预售中，请先结束活动');
        }
        //获取购买预售商品的并且已经支付的订单id
        $pre_sell_order_where = array(
            'order_prom_type' => 4,
            'order_prom_id' => $goods_activity['act_id'],
            'order_status' => 0,
            'pay_status' => array(array('eq', 1), array('eq', 2), 'or')
        );
        $pre_sell_order_list = M('order')->field('user_id,order_id,pay_status,goods_price,total_amount,order_amount,paid_money')->where($pre_sell_order_where)->select();
        foreach ($pre_sell_order_list as $key => $val) {
            //如果是支付定金的
            if ($pre_sell_order_list[$key]['paid_money'] > 0 && $pre_sell_order_list[$key]['pay_status'] == 2) {
                //退还订金
                accountLog($pre_sell_order_list[$key]['user_id'], $pre_sell_order_list[$key]['paid_money'], 0, '退还预售商品' . $goods_activity['act_name'] . '的定金，订单ID为：' . $pre_sell_order_list[$key]['order_id']);
            }
            //如果是支付全款的
            if ($pre_sell_order_list[$key]['paid_money'] == 0 && $pre_sell_order_list[$key]['pay_status'] == 1) {
                //退还全款
                accountLog($pre_sell_order_list[$key]['user_id'], $pre_sell_order_list[$key]['order_amount'], 0, '退还预售商品' . $goods_activity['act_name'] . '的全款，订单ID为：' . $pre_sell_order_list[$key]['order_id']);
            }
        }
        //最后把该预售商品的订单标记已作废
        $pre_sell_order_cancel_where = array(
            'order_prom_type' => 4,
            'order_prom_id' => $goods_activity['act_id'],
            'order_status' => 0,
        );
        M('order')->where($pre_sell_order_cancel_where)->save(array('order_status' => 5));
        M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->save(array('is_finished' => 2));
        M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_id']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
        $this->success('该预售商品失败结束,并下架了该商品', U('Admin/Promotion/pre_sell_list'));
    }

}