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
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class Goods extends Base {

    /**
     *  商品分类列表
     */
    public function categoryList(){
        $GoodsLogic = new GoodsLogic();
        $cat_list = $GoodsLogic->goods_cat_list();
        $this->assign('cat_list',$cat_list);
        return $this->fetch();
    }

    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'),
     * select * from tp_goods_category where id = 393
        select * from tp_goods_category where parent_id = 393
        update tp_goods_category  set parent_id_path = concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
        insert into `tp_goods_category` (`parent_id`,`name`) values
        ('393','时尚饰品'),
     */
    public function addEditCategory(){

            $GoodsLogic = new GoodsLogic();
            if(IS_GET)
            {
                $goods_category_info = D('GoodsCategory')->where('id='.I('GET.id',0))->find();
                $level_cat = $GoodsLogic->find_parent_cat($goods_category_info['id']); // 获取分类默认选中的下拉框

                $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
                $this->assign('level_cat',$level_cat);
                $this->assign('cat_list',$cat_list);
                $this->assign('goods_category_info',$goods_category_info);
                return $this->fetch('_category');
                exit;
            }

            $GoodsCategory = D('GoodsCategory'); //

            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            //ajax提交验证
            if(I('is_ajax') == 1)
            {
                // 数据验证
                $validate = \think\Loader::validate('GoodsCategory');
                if(!$validate->batch()->check(input('post.')))
                {
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $return_arr = array(
                        'status' => -1,
                        'msg' => $error_msg[0],
                        'data' => $error,
                    );
                    $this->ajaxReturn($return_arr);
                } else {

                    $GoodsCategory->data(input('post.'),true); // 收集数据
                    $GoodsCategory->parent_id = I('parent_id_1');
                    input('parent_id_2') && ($GoodsCategory->parent_id = input('parent_id_2'));
                    //编辑判断
                    if($type == 2){
                        $children_where = array(
                            'parent_id_path'=>array('like','%_'.I('id')."_%")
                        );
                        $id = I('id');
                        $sql = "SELECT MAX(level) AS tp_max FROM `tp_goods_category` WHERE  `parent_id_path` LIKE '%!_$id!_%' ESCAPE '!' LIMIT 1 ";
                        $children1 = M('goods_category')->query($sql);
                        $children = $children1[0]['tp_max'];
                        if (I('parent_id_1')) {
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_1')))->getField('level', false);

                            if (($parent_level + $children) > 4) {
                                $return_arr = array(
                                    'status' => -1,
                                    'msg'   => $parent_level.'商品分类最多为三级'.$children,
                                    'data'  => '',
                                );
                                $this->ajaxReturn($return_arr);
                            }
                        }
                        if (I('parent_id_2')) {
                            $parent_level = M('goods_category')->where(array('id' => I('parent_id_2')))->getField('level', false);
                            if (($parent_level + $children) > 4) {
                                $return_arr = array(
                                    'status' => -1,
                                    'msg'   => '商品分类最多为三级',
                                    'data'  => '',
                                );
                                $this->ajaxReturn($return_arr);
                            }
                        }
                    }

                    if($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id)
                    {
                        //  编辑
                        $return_arr = array(
                            'status' => -1,
                            'msg'   => '上级分类不能为自己',
                            'data'  => '',
                        );
                        $this->ajaxReturn($return_arr);
                    }
//                    if($GoodsCategory->commission_rate > 100)
//                    {
//                        //  编辑
//                        $return_arr = array(
//                            'status' => -1,
//                            'msg'   => '分佣比例不得超过100%',
//                            'data'  => '',
//                        );
//                        $this->ajaxReturn($return_arr);
//                    }

                    if ($type == 2)
                    {
                        $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                        $GoodsLogic->refresh_cat(I('id'));
                    }
                    else
                    {
                        $GoodsCategory->save(); // 写入数据到数据库
                        $insert_id = $GoodsCategory->getLastInsID();
                        $GoodsLogic->refresh_cat($insert_id);
                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',
                        'data'  => array('url'=>U('Admin/Goods/categoryList')),
                    );
                    $this->ajaxReturn($return_arr);

                }
            }

    }

    /**
     * 获取商品分类 的帅选规格 复选框
     */
    public function ajaxGetSpecList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_spec = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_spec');
        $filter_spec_arr = explode(',',$filter_spec);
        $str = $GoodsLogic->GetSpecCheckboxList($_REQUEST['type_id'],$filter_spec_arr);
        $str = $str ? $str : '没有可帅选的商品规格';
        exit($str);
    }

    /**
     * 获取商品分类 的帅选属性 复选框
     */
    public function ajaxGetAttrList(){
        $GoodsLogic = new GoodsLogic();
        $_REQUEST['category_id'] = $_REQUEST['category_id'] ? $_REQUEST['category_id'] : 0;
        $filter_attr = M('GoodsCategory')->where("id = ".$_REQUEST['category_id'])->getField('filter_attr');
        $filter_attr_arr = explode(',',$filter_attr);
        $str = $GoodsLogic->GetAttrCheckboxList($_REQUEST['type_id'],$filter_attr_arr);
        $str = $str ? $str : '没有可帅选的商品属性';
        exit($str);
    }

    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        $id = $this->request->param('id');
        // 判断子分类
        $GoodsCategory = M("goods_category");
        $count = $GoodsCategory->where("parent_id = {$id}")->count("id");
        $count > 0 && $this->error('该分类下还有分类不得删除!',U('Admin/Goods/categoryList'));
        // 判断是否存在商品
        $goods_count = M('Goods')->where("cat_id = {$id}")->count('1');
        $goods_count > 0 && $this->error('该分类下有商品不得删除!',U('Admin/Goods/categoryList'));
        // 删除分类
        DB::name('goods_category')->where('id',$id)->delete();
        $this->success("操作成功!!!",U('Admin/Goods/categoryList'));
    }


    /**
     *  商品列表
     */
    public function goodsList(){
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        $page = I('p');
        //var_dump($page);die;
        $this->assign('p',$page);// 赋值分页输出
        return $this->fetch();
    }

    /**
     *  商品列表
     */
    public function ajaxGoodsList(){
        $where = ' 1 = 1 '; // 搜索条件
        I('intro')    && $where = "$where and ".I('intro')." = 1" ;
        I('brand_id') && $where = "$where and brand_id = ".I('brand_id') ;
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = ".I('is_on_sale') ;
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)
        {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')" ;
        }

        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $count = M('Goods')->where($where)->count();
        $Page  = new AjaxPage($count,10);
        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('p',$page);// 赋值分页输出
        return $this->fetch();
    }


    public function stock_list(){
    	$model = M('stock_log');
    	$map = array();
    	$mtype = I('mtype');
    	if($mtype == 1){
    		$map['stock'] = array('gt',0);
    	}
    	if($mtype == -1){
    		$map['stock'] = array('lt',0);
    	}
    	$goods_name = I('goods_name');
    	if($goods_name){
    		$map['goods_name'] = array('like',"%$goods_name%");
    	}
    	$ctime = I('ctime');
    	if($ctime){
            $ctime = str_replace('+',' ',$ctime);
    		$gap = explode(' - ', $ctime);
    		$this->assign('start_time',$gap[0]);
    		$this->assign('end_time',$gap[1]);
    		$this->assign('ctime',$gap[0].' - '.$gap[1]);
    		$map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
    	}
    	$count = $model->where($map)->count();
    	$Page  = new Page($count,20);
    	$show = $Page->show();
    	$this->assign('pager',$Page);
    	$this->assign('page',$show);// 赋值分页输出
    	$stock_list = $model->where($map)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    	$this->assign('stock_list',$stock_list);
    	return $this->fetch();
    }



    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {
        $GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\Goods();
        $goods_id = I('goods_id');
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if ((I('is_ajax') == 1) && IS_POST) {
            // 数据验证
            $is_distribut = input('is_distribut');
            $return_url = $is_distribut > 0 ? U('admin/Distribut/goods_list') : "/index.php?m=admin&c=Goods&a=goodsList&p=$page";
            $data = input('post.');
            $validate = \think\Loader::validate('Goods');
            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }
            $Goods->data($data, true); // 收集数据
//            $first_rate = $Goods -> first_rate;
//            $second_rate = $Goods -> second_rate;
//            $third_rate = $Goods -> third_rate;
//            $rate = (int)$first_rate + (int)$second_rate + (int)$third_rate;
//            if($rate>100){
//                $return_arr = array(
//                    'status' => -1,
//                    'msg' => '三个分销商比例总和不得超过100%',
//                    'data' =>array('url' => $return_url),
//                );
//                $this->ajaxReturn($return_arr);
//            }

            $Goods->on_time = time(); // 上架时间
            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));
            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',', I('shipping_area_ids/a', []));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            $Goods->spec_type = $Goods->goods_type;
            $price_ladder = array();
            if ($Goods->ladder_amount[0] > 0) {
                foreach ($Goods->ladder_amount as $key => $value) {
                    $price_ladder[$key]['amount'] = intval($Goods->ladder_amount[$key]);
                    $price_ladder[$key]['price'] = floatval($Goods->ladder_price[$key]);
                }
                $price_ladder = array_values(array_sort($price_ladder, 'amount', 'asc'));
                $price_ladder_max = count($price_ladder);
                if ($price_ladder[$price_ladder_max - 1]['price'] >= $Goods->shop_price) {
                    $return_arr = array(
                        'msg' => '价格阶梯最大金额不能大于商品原价！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                if ($price_ladder[0]['amount'] <= 0 || $price_ladder[0]['price'] <= 0) {
                    $return_arr = array(
                        'msg' => '您没有输入有效的价格阶梯！',
                        'status' => -0,
                        'data' => array('url' => $return_url)
                    );
                    $this->ajaxReturn($return_arr);
                }
                $Goods->price_ladder = serialize($price_ladder);
            } else {
                $Goods->price_ladder = '';
            }
            if ($type == 2) {
                $res1 = M('spec_goods_price') -> where("goods_id",$goods_id) -> select();

                if($res1){
                    $Goods->isUpdate(true)->save(); // 写入数据到数据库
                    $Goods->afterSave($goods_id);
                    $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
                    $res2 = M('spec_goods_price') -> where("goods_id",$goods_id) -> select();
                    $arr = array();
                    $arr =  array_merge($res1 , $res2);
                    $goods_name = M('goods') -> where('goods_id',$goods_id) -> getField('goods_name');
                    foreach($arr as $k=>$v) {
                        $result[$v["key"]] = $v; // key 根据你想要的
                    }
                    foreach ($res1 as $ke=>$va){
                        $key = $va['key'];
                        $array['stock'] = $result["$key"]['store_count'] - $va['store_count'];
                        if($array['stock'] == 0){
                            continue;
                        }
                        $array['ctime'] = time();
                        $array['muid'] = session('admin_id');
                        $array['goods_id'] =$goods_id;
                        $array['goods_name'] = $goods_name;
                        $array['goods_spec'] = $result["$key"]['key_name'];
                        M('stock_log') -> add($array);
                    }
                    //新增
                    $key_arr = array_keys($result);
                    foreach($res1 as $k1=>$v1) {
                        $res_arr1[$v1["key"]] = $v1; // key 根据你想要的
                    }
                    $res1_key = array_keys($res_arr1);
                        foreach ($key_arr as $kk1=>$vk1){
                            if(!in_array($vk1,$res1_key)){
                                $no['muid'] = session('admin_id');
                                $no['goods_name'] = $goods_name;
                                $no['stock'] = $result["$vk1"]['store_count'];
                                $no['goods_spec'] = $result["$vk1"]['key_name'];
                                $no['ctime'] = time();
                                M('stock_log') -> add($no);
                            }
                        }
                        //删除
                    if($res2){
                        foreach($res2 as $k2=>$v2) {
                            $res_arr2[$v2["key"]] = $v2; // key 根据你想要的
                        }
                        $res2_key = array_keys($res_arr2);
                        foreach ($key_arr as $kk2=>$vk2){
                            if(!in_array($vk2,$res2_key)){
                                $yes['muid'] = session('admin_id');
                                $yes['goods_name'] = $goods_name;
                                $yes['stock'] = 0 - $result["$vk2"]['store_count'];
                                $yes['goods_spec'] = $result["$vk2"]['key_name'];
                                $yes['ctime'] = time();
                                M('stock_log') -> add($yes);
                            }
                        }
                    } else{
                        $goods_name = M('goods') -> where('goods_id',$goods_id) -> getField('goods_name');
                            foreach ($res1 as $al){
                                $yes1['muid'] = session('admin_id');
                                $yes1['goods_name'] = $goods_name;
                                $yes1['stock'] = 0 - $al['store_count'];
                                $yes1['goods_spec'] = $al['key_name'];
                                $yes1['ctime'] = time();
                                M('stock_log') -> add($yes1);
                            }
                    }

                }else{
                    $oldStore = admin_old_stock($goods_id);
                    $Goods->isUpdate(true)->save(); // 写入数据到数据库
                    $Goods->afterSave($goods_id);
                    $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
                    $res2 = M('spec_goods_price') -> where("goods_id",$goods_id) -> select();
                    if($res2){
                        foreach ($res2 as $v2){
                            admin_stock_log($goods_id,$v2['key']);
                        }
                    } else{
                        admin_stock_log($goods_id,'',$oldStore);
                    }
                }
                //$Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                    'market_price' => I('market_price'), //专柜价
                    'goods_price' => I('shop_price'), // 本店价
                    'member_goods_price' => I('shop_price'), // 会员折扣价
                ));
            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();

            }
            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id, I('goods_type')); // 处理商品 属性
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
                'page' => $page,
            );
            //库存日志
            if($type!=2){
                $res = M('spec_goods_price') -> where("goods_id",$goods_id) -> select();
                if($res){
                    foreach ($res as $val){
                        $key = $val['key'];
                        admin_stock_log($goods_id,$key);
                    }
                }else{
                    admin_stock_log($goods_id);
                }
            }

            $this->ajaxReturn($return_arr);
        }

        $goodsInfo = M('Goods')->where('goods_id=' . I('GET.id', 0))->find();
        if ($goodsInfo['price_ladder']) {
            $goodsInfo['price_ladder'] = unserialize($goodsInfo['price_ladder']);
        }
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $brandList = $GoodsLogic->getSortBrands();
        $goodsType = M("GoodsType")->select();
        $suppliersList = M("suppliers")->select();
        $plugin_shipping = M('plugin')->where(array('type' => array('eq', 'shipping')))->select();//插件物流
        $shipping_area = D('Shipping_area')->getShippingArea();//配送区域
        $goods_shipping_area_ids = explode(',', $goodsInfo['shipping_area_ids']);
        $this->assign('goods_shipping_area_ids', $goods_shipping_area_ids);
        $this->assign('shipping_area', $shipping_area);
        $this->assign('plugin_shipping', $plugin_shipping);
        $this->assign('suppliersList', $suppliersList);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('brandList', $brandList);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))-> order("img_id asc") -> select();
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $goodsVideo = M("GoodsVideos")->where('goods_id =' . I('GET.id', 0)) -> select();
        $this->assign('goodsVideo',$goodsVideo);
        $this->initEditor(); // 编辑器
        return $this->fetch('_goods');
    }

    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList(){
        $model = M("GoodsType");
        $count = $model->count();
        $Page = $pager = new Page($count,14);
        $show  = $Page->show();
        $goodsTypeList = $model->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch('goodsTypeList');
    }


    /**
     * 添加修改编辑  商品属性类型
     */
    public function addEditGoodsType()
    {
        $id = $this->request->param('id', 0);
        $model = M("GoodsType");
        if (IS_POST) {
            $data = $this->request->post();
            if ($id)
                DB::name('GoodsType')->update($data);
            else
                DB::name('GoodsType')->insert($data);

            $this->success("操作成功!!!", U('Admin/Goods/goodsTypeList'));
            exit;
        }
        $goodsType = $model->find($id);
        $this->assign('goodsType', $goodsType);
        return $this->fetch('_goodsType');
    }

    /**
     * 商品属性列表
     */
    public function goodsAttributeList(){
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }

    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList(){
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;
        // 关键词搜索
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
        $attr_input_type = array(0=>'手工录入',1=>' 从列表中选择',2=>' 多行文本框');
        $this->assign('attr_input_type',$attr_input_type);
        $this->assign('goodsTypeList',$goodsTypeList);
        $this->assign('goodsAttributeList',$goodsAttributeList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 添加修改编辑  商品属性
     */
    public  function addEditGoodsAttribute(){

            $model = D("GoodsAttribute");
            $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            $attr_values = str_replace('_', '', I('attr_values')); // 替换特殊字符
            $attr_values = str_replace('@', '', $attr_values); // 替换特殊字符
            $attr_values = trim($attr_values);

            $post_data = input('post.');
            $post_data['attr_values'] = $attr_values;

            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {
                    // 数据验证
                    $validate = \think\Loader::validate('GoodsAttribute');
                    if(!$validate->batch()->check($post_data))
                    {
                        $error = $validate->getError();
                        $error_msg = array_values($error);
                        $return_arr = array(
                            'status' => -1,
                            'msg' => $error_msg[0],
                            'data' => $error,
                        );
                        $this->ajaxReturn($return_arr);
                    } else {
                             $model->data($post_data,true); // 收集数据

                             if ($type == 2)
                             {
                                 $model->isUpdate(true)->save(); // 写入数据到数据库
                             }
                             else
                             {
                                 $model->save(); // 写入数据到数据库
                                 $insert_id = $model->getLastInsID();
                             }
                             $return_arr = array(
                                 'status' => 1,
                                 'msg'   => '操作成功',
                                 'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
                             );
                             $this->ajaxReturn($return_arr);
                }
            }
           // 点击过来编辑时
           $attr_id = I('attr_id/d',0);
           $goodsTypeList = M("GoodsType")->select();
           $goodsAttribute = $model->find($attr_id);
           $this->assign('goodsTypeList',$goodsTypeList);
           $this->assign('goodsAttribute',$goodsAttribute);
           return $this->fetch('_goodsAttribute');
    }

    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',
                'goods_attribute' => 'attr_id',
        		'ad' =>'ad_id',
        );
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];
        $model->save();
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',
            'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }
    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }

    /**
     * 删除商品
     */
    public function delGoods()
    {
        $goods_id = input('id');
        $error = '';

        // 判断此商品是否有订单
        $c1 = M('OrderGoods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有订单,不得删除! <br/>';


         // 商品团购
        $c1 = M('group_buy')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有团购,不得删除! <br/>';

         // 商品退货记录
        $c1 = M('return_goods')->where("goods_id = $goods_id")->count('1');
        $c1 && $error .= '此商品有退货记录,不得删除! <br/>';

        if($error)
        {
            $return_arr = array('status' => -1,'msg' =>$error,'data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
            $this->ajaxReturn($return_arr);
        }
        //库存日志更新
        $arr = M('spec_goods_price') ->where('goods_id ='.$goods_id) -> select();
        if($arr){
            foreach ($arr as $val){
                admin_stock_log($goods_id,$val['key'],2*$val['store_count']);
            }

        } else{
            $store_count = M('goods') -> where('goods_id',$goods_id) -> getField('store_count');
            $data['order_sn'] ='N/A';
            $data['muid'] = session('admin_id');
            $data['ctime'] = time();
            $goods_name = M('goods') -> where('goods_id',$goods_id) -> getField('goods_name');
            $data['goods_name'] = $goods_name;
            $data['stock'] = 0 - $store_count;
            M('stock_log') -> add($data);
        }
        // 删除此商品
        M("Goods")->where('goods_id ='.$goods_id)->delete();  //商品表
        M("cart")->where('goods_id ='.$goods_id)->delete();  // 购物车
        M("comment")->where('goods_id ='.$goods_id)->delete();  //商品评论
        M("goods_consult")->where('goods_id ='.$goods_id)->delete();  //商品咨询
        M("goods_images")->where('goods_id ='.$goods_id)->delete();  //商品相册
        M("spec_goods_price")->where('goods_id ='.$goods_id)->delete();  //商品规格
        M("spec_image")->where('goods_id ='.$goods_id)->delete();  //商品规格图片
        M("goods_attr")->where('goods_id ='.$goods_id)->delete();  //商品属性
        M("goods_collect")->where('goods_id ='.$goods_id)->delete();  //商品收藏

        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 删除商品类型
     */
    public function delGoodsType()
    {
        // 判断 商品规格
        $id = $this->request->param('id');
        $count = M("Spec")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品规格不得删除!',U('Admin/Goods/goodsTypeList'));
        // 判断 商品属性
        $count = M("GoodsAttribute")->where("type_id = {$id}")->count("1");
        $count > 0 && $this->error('该类型下有商品属性不得删除!',U('Admin/Goods/goodsTypeList'));
        // 删除分类
        M('GoodsType')->where("id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/goodsTypeList'));
    }

    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $id = input('id');
        // 判断 有无商品使用该属性
        $count = M("GoodsAttr")->where("attr_id = {$id}")->count("1");
        $count > 0 && $this->error('有商品使用该属性,不得删除!',U('Admin/Goods/goodsAttributeList'));
        // 删除 属性
        M('GoodsAttribute')->where("attr_id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/goodsAttributeList'));
    }

    /**
     * 删除商品规格
     */
    public function delGoodsSpec()
    {
        $id = input('id');
        // 判断 商品规格项
        $count = M("SpecItem")->where("spec_id = {$id}")->count("1");
        $count > 0 && $this->error('清空规格项后才可以删除!',U('Admin/Goods/specList'));
        // 删除分类
        M('Spec')->where("id = {$id}")->delete();
        $this->success("操作成功!!!",U('Admin/Goods/specList'));
    }

    /**
     * 品牌列表
     */
    public function brandList(){
        $model = M("Brand");
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";
        $count = $model->where($where)->count();
        $Page = $pager = new Page($count,10);
        $brandList = $model->where($where)->order("`sort` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $show  = $Page->show();
        $cat_list = M('goods_category')->where("parent_id = 0")->getField('id,name'); // 已经改成联动菜单
        $this->assign('cat_list',$cat_list);
        $this->assign('pager',$pager);
        $this->assign('show',$show);
        $this->assign('brandList',$brandList);
        return $this->fetch('brandList');
    }

    /**
     * 添加修改编辑  商品品牌
     */
    public  function addEditBrand(){
            $id = I('id');
            if(IS_POST)
            {
               	$data = I('post.');
                $brandVilidate = Loader::validate('Brand');
                if(!$brandVilidate->batch()->check($data)){
                    $return = ['status'=>0,'msg'=>'操作失败','result'=>$brandVilidate->getError()];
                    $this->ajaxReturn($return);
                }
                if($id){
                	M("Brand")->update($data);
                }else{
                	M("Brand")->insert($data);
                }
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
            }
           $cat_list = M('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
           $this->assign('cat_list',$cat_list);
           $brand = M("Brand")->find($id);
           $this->assign('brand',$brand);
           return $this->fetch('_brand');
    }

    /**
     * 删除品牌
     */
    public function delBrand()
    {
        // 判断此品牌是否有商品在使用
        $goods_count = M('Goods')->where("brand_id = {$_GET['id']}")->count('1');
        if($goods_count)
        {
            $return_arr = array('status' => -1,'msg' => '此品牌有商品在用不得删除!','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
            $this->ajaxReturn($return_arr);
        }

        $model = M("Brand");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 初始化编辑器链接
     * 本编辑器参考 地址 http://fex.baidu.com/ueditor/
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('admin/Ueditor/imageUp',array('savepath'=>'goods'))); // 图片上传目录
        $this->assign("URL_imageUp", U('admin/Ueditor/imageUp',array('savepath'=>'article'))); //  不知道啥图片
        $this->assign("URL_fileUp", U('admin/Ueditor/fileUp',array('savepath'=>'article'))); // 文件上传s
        $this->assign("URL_scrawlUp", U('admin/Ueditor/scrawlUp',array('savepath'=>'article')));  //  图片流
        $this->assign("URL_getRemoteImage", U('admin/Ueditor/getRemoteImage',array('savepath'=>'article'))); // 远程图片管理
        $this->assign("URL_imageManager", U('admin/Ueditor/imageManager',array('savepath'=>'article'))); // 图片管理
        $this->assign("URL_getMovie", U('admin/Ueditor/getMovie',array('savepath'=>'article'))); // 视频上传
        $this->assign("URL_Home", "");
    }



    /**
     * 商品规格列表
     */
    public function specList(){
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }


    /**
     *  商品规格列表
     */
    public function ajaxSpecList(){
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;
        // 关键词搜索
        $model = D('spec');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $specList = $model->where($where)->order('`type_id` desc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $GoodsLogic = new GoodsLogic();
        foreach($specList as $k => $v)
        {       // 获取规格项
                $arr = $GoodsLogic->getSpecItem($v['id']);
                $specList[$k]['spec_item'] = implode(' , ', $arr);
        }

        $this->assign('specList',$specList);
        $this->assign('page',$show);// 赋值分页输出
        $goodsTypeList = M("GoodsType")->select(); // 规格分类
        $goodsTypeList = convert_arr_key($goodsTypeList, 'id');
        $this->assign('goodsTypeList',$goodsTypeList);
        return $this->fetch();
    }
    /**
     * 添加修改编辑  商品规格
     */
    public  function addEditSpec(){

            $model = D("spec");
            $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
            if((I('is_ajax') == 1) && IS_POST)//ajax提交验证
            {
                // 数据验证
                $validate = \think\Loader::validate('Spec');
                $post_data = input('post.');
                if ($type == 2) {
                    //更新数据
                    $check = $validate->scene('edit')->batch()->check($post_data);
                } else {
                    //插入数据
                    $check = $validate->batch()->check($post_data);
                }
                if (!$check) {
                    $error = $validate->getError();
                    $error_msg = array_values($error);
                    $return_arr = array(
                        'status' => -1,
                        'msg' => $error_msg[0],
                        'data' => $error,
                    );
                    $this->ajaxReturn($return_arr);
                }
                $model->data($post_data, true); // 收集数据
                if ($type == 2) {
                    $model->isUpdate(true)->save(); // 写入数据到数据库
                    $model->afterSave(I('id'));
                } else {
                    $model->save(); // 写入数据到数据库
                    $insert_id = $model->getLastInsID();
                    $model->afterSave($insert_id);
                }
                $return_arr = array(
                    'status' => 1,
                    'msg' => '操作成功',
                    'data' => array('url' => U('Admin/Goods/specList')),
                );
                $this->ajaxReturn($return_arr);
            }
           // 点击过来编辑时
           $id = I('id/d',0);
           $spec = $model->find($id);
           $GoodsLogic = new GoodsLogic();
           $items = $GoodsLogic->getSpecItem($id);
           $spec[items] = implode(PHP_EOL, $items);
           $this->assign('spec',$spec);

           $goodsTypeList = M("GoodsType")->select();
           $this->assign('goodsTypeList',$goodsTypeList);
           return $this->fetch('_spec');
    }


    /**
     * 动态获取商品规格选择框 根据不同的数据返回不同的选择框
     */
    public function ajaxGetSpecSelect(){
        $goods_id = I('get.goods_id/d') ? I('get.goods_id/d') : 0;
        $GoodsLogic = new GoodsLogic();
        //$_GET['spec_type'] =  13;
        $specList = M('Spec')->where("type_id = ".I('get.spec_type/d'))->order('`order` desc')->select();
        foreach($specList as $k => $v)
            $specList[$k]['spec_item'] = M('SpecItem')->where("spec_id = ".$v['id'])->order('id')->getField('id,item'); // 获取规格项

        $items_id = M('SpecGoodsPrice')->where('goods_id = '.$goods_id)->getField("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
        $items_ids = explode('_', $items_id);

        // 获取商品规格图片
        if($goods_id)
        {
           $specImageList = M('SpecImage')->where("goods_id = $goods_id")->getField('spec_image_id,src');
        }
        $this->assign('specImageList',$specImageList);

        $this->assign('items_ids',$items_ids);
        $this->assign('specList',$specList);
        return $this->fetch('ajax_spec_select');
    }

    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */
    public function ajaxGetSpecInput(){
         $GoodsLogic = new GoodsLogic();
         $goods_id = I('goods_id/d') ? I('goods_id/d') : 0;
         $str = $GoodsLogic->getSpecInput($goods_id ,I('post.spec_arr/a',[[]]));
         exit($str);
    }

    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename', '');
        M('goods_images')->where("image_url = '$path'")->delete();
    }
        public function del_goods_video()
    {
        $path = I('filename','');
        M('goods_videos')->where("video_url = '$path'")->delete();
    }

    /**
     * 初始化商品关键词搜索
     */
    public function initGoodsSearchWord(){
        $searchWordLogic = new SearchWordLogic();
        $searchWordLogic->initGoodsSearchWord();
    }
}