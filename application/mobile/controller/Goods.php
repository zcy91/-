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
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\mobile\controller;
use app\admin\logic\GoodsPromFactory;
use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
class Goods extends MobileBase {
    public function index(){
       // $this->show('<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} body{ background: #fff; font-family: "微软雅黑"; color: #333;font-size:24px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.8em; font-size: 36px } a,a:hover,{color:blue;}</style><div style="padding: 24px 48px;"> <h1>:)</h1><p>欢迎使用 <b>ThinkPHP</b>！</p><br/>版本 V{$Think.version}</div><script type="text/javascript" src="http://ad.topthink.com/public/static/client.js"></script><thinkad id="ad_55e75dfae343f5a1"></thinkad><script type="text/javascript" src="http://tajs.qq.com/stats?sId=9347272" charset="UTF-8"></script>','utf-8');
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 商品列表页
     */
    public function goodsList(){
    	$filter_param = array(); // 帅选数组
    	$id = I('id/d',1); // 当前分类id
    	$brand_id = I('brand_id/d',0);
    	$spec = I('spec',0); // 规格
    	$attr = I('attr',''); // 属性
    	$sort = I('sort','goods_id'); // 排序
    	$sort_asc = I('sort_asc','asc'); // 排序
    	$price = I('price',''); // 价钱
    	$start_price = trim(I('start_price','0')); // 输入框价钱
    	$end_price = trim(I('end_price','0')); // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
    	$filter_param['id'] = $id; //加入帅选条件中
    	$brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
    	$spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
    	$attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
    	$price  && ($filter_param['price'] = $price); //加入帅选条件中

    	$goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
    	// 分类菜单显示
    	$goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
    	//($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
    	$cateArr = $goodsLogic->get_goods_cate($goodsCate);

    	// 帅选 品牌 规格 属性 价格
    	$cat_id_arr = getCatGrandson ($id);

    	//$filter_goods_id = M('goods')->where(['is_on_sale'=>1,'cat_id'=>['in',implode(',', $cat_id_arr)]])->whereOr(['is_on_sale'=>1,'extend_cat_id'=>['in',implode(',', $cat_id_arr)]])->cache(false)->getField("goods_id",true);
        $filter_goods_id = M('goods')->where(['is_on_sale'=>1])->where('cat_id|extend_cat_id','in',implode(',', $cat_id_arr))->cache(false)->getField("goods_id",true);
    	//var_dump(M('goods')->getLastSql());die;
    	// 过滤帅选的结果集里面找商品
    	if($brand_id || $price)// 品牌或者价格
    	{
    		$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
    	}
    	if($spec)// 规格
    	{
    		$goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
    	}
    	if($attr)// 属性
    	{
    		$goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
    	}

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
    	$filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
    	$filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性

    	$count = count($filter_goods_id);
    	$page = new Page($count,C('PAGESIZE'));
    	if($count > 0)
    	{
    		$goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
    		$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    		if($filter_goods_id2)
    			$goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    	}
    	$goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $point_rate = tpCache('shopping.point_rate');
        $this -> assign('point_rate',$point_rate);
    	$this->assign('goods_list',$goods_list);
    	$this->assign('goods_category',$goods_category);
    	$this->assign('goods_images',$goods_images);  // 相册图片
    	$this->assign('filter_menu',$filter_menu);  // 帅选菜单
    	$this->assign('filter_spec',$filter_spec);  // 帅选规格
    	$this->assign('filter_attr',$filter_attr);  // 帅选属性
    	$this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
    	$this->assign('filter_price',$filter_price);// 帅选的价格期间
    	$this->assign('goodsCate',$goodsCate);
    	$this->assign('cateArr',$cateArr);
    	$this->assign('filter_param',$filter_param); // 帅选条件
    	$this->assign('cat_id',$id);
    	$this->assign('page',$page);// 赋值分页输出
    	$this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
    	C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList() {
        $where ='';

        $cat_id  = I("id/d",0); // 所选择的商品分类id
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " WHERE is_on_sale=1 and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count,10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit ".$page->firstRow.','.$page->listRows;
        $list = DB::query("select *  from __PREFIX__goods $where $order $limit");
        $point_rate = tpCache('shopping.point_rate');
        $this->assign('point_rate',$point_rate);
        $this->assign('lists',$list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
       exit($html);
    }

    /**
     * 商品详情页
     */
    public function goodsInfo(){
        //分享成为分销商
        if($_GET['first_leader']){
            $first_leader = (int)$_GET['first_leader'];//分销上级
            setcookie('first_leader',$first_leader);
        }

        C('TOKEN_ON',true);
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goodsModel = new \app\home\model\Goods();
        $goods_id = I("get.id/d");
        $goods = $goodsModel::get($goods_id);
        $goods['discount'] = $goods->discount;
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            $goodsPromLogic = $goodsPromFactory->makeModule($goods['prom_type'],$goods['prom_id']);//这里会自动更新商品活动状态，所以商品需要重新查询
            $goods = M('Goods')->where("goods_id", $goods_id)->find();
            //商品活动类型为秒杀
            if($goods['prom_type'] == 1 && $goodsPromLogic->checkActivityIsAble()){
                $goods['flash_sale'] = $goodsPromLogic->getPromModel();
                $goods['discount'] = round($goods['flash_sale']['price']/$goods['shop_price'],2)*10;
            }
        }
        if(empty($goods) || ($goods['is_on_sale'] == 0)){
            $this->error('此服务不存在或者已下架');
        }
        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)-> order("img_id asc") -> select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
		$filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count"); // 规格 对应 价格 库存表
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
      	$goods['sale_num'] = M('order_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
        //当前用户收藏
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数
        $this->assign('collect',$collect);
        $this->assign('commentStatistics',$commentStatistics);//评论概览
        $this->assign('goods_attribute',$goods_attribute);//属性值
        $this->assign('goods_attr_list',$goods_attr_list);//属性列表
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('goods',$goods);
        $this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
        $point_rate = tpCache('shopping.point_rate');
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }

    /**
     * 商品详情页
     */
    public function detail(){
        //  form表单提交
        C('TOKEN_ON',true);
        $goods_id = I("get.id/d");
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        $this->assign('goods',$goods);
        return $this->fetch();
    }

    /*
     * 商品评论
     */
    public function comment(){
        $goods_id = I("goods_id/d",0);
        $this->assign('goods_id',$goods_id);
        return $this->fetch();
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }
        $count = M('Comment')->where($where)->count();
        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
            $image = $list[$k]['img'];
            if($image){

                foreach ($image as $key=>$vo){
                   // $vo = iconv('gbk', 'utf-8', 'http://'.$_SERVER['HTTP_HOST'].$vo);
                    $img = '.'.$vo;
                    //$img = iconv('gbk', 'utf-8', $img);
                    $list[$k]['size'][$key] = getimagesize($img);
                }
            }
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
        }
        //var_dump($list);die;
        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('commentlist', $list);// 商品评论
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('p', I('p'));//页数
        return $this->fetch();
    }

    /*
     * 获取商品规格
     */
    public function goodsAttr(){
        $goods_id = I("get.goods_id/d",0);
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);
        return $this->fetch();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $rank= I('get.rank');
        //以兑换量（购买量）排序
        if($rank == 'num'){
            $ranktype = 'sales_sum';
            $order = 'desc';
        }
        //以需要积分排序
        if($rank == 'integral'){
            $ranktype = 'exchange_integral';
            $order = 'desc';
        }
        $point_rate = tpCache('shopping.point_rate');
        $goods_where = array(
            'is_on_sale' => 1,  //是否上架
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));

        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandson($cat_id));
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($rank == 'exchange' && !empty($user_id)) {
            //获取用户积分
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }
        $goods_where['exchange_integral'] =  $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->order($ranktype ,$order)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('totalPages',$page->totalPages);//总页数
        if(IS_AJAX){
            return $this->fetch('ajaxIntegralMall'); //获取更多
        }
        return $this->fetch();
    }

     /**
     * 商品搜索列表页
     */
    public function search(){
    	$filter_param = array(); // 帅选数组
    	$id = I('get.id/d',0); // 当前分类id
    	$brand_id = I('brand_id/d',0);
    	$sort = I('sort','goods_id'); // 排序
    	$sort_asc = I('sort_asc','asc'); // 排序
    	$price = I('price',''); // 价钱
    	$start_price = trim(I('start_price','0')); // 输入框价钱
    	$end_price = trim(I('end_price','0')); // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
    	$filter_param['id'] = $id; //加入帅选条件中
    	$brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
    	$price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        if($qtype){
        	$filter_param['qtype'] = $qtype;
        	$where[$qtype] = 1;
        }
        if($q) $where['goods_name'] = array('like','%'.$q.'%');

    	$goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
    	$filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id",true);

    	// 过滤帅选的结果集里面找商品
    	if($brand_id || $price)// 品牌或者价格
    	{
    		$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
    	}

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search'); // 获取指定分类下的帅选品牌
//var_dump($filter_goods_id);die;
    	$count = count($filter_goods_id);
    	$page = new Page($count,12);
    	if($count > 0)
    	{
    		$goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
    		$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    		if($filter_goods_id2)
    			$goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    	}
        $point_rate = tpCache('shopping.point_rate');
        $this -> assign('point_rate',$point_rate);
    	$goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    	$this->assign('goods_list',$goods_list);
    	$this->assign('goods_category',$goods_category);
    	$this->assign('goods_images',$goods_images);  // 相册图片
    	$this->assign('filter_menu',$filter_menu);  // 帅选菜单
    	$this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
    	$this->assign('filter_price',$filter_price);// 帅选的价格期间
    	$this->assign('filter_param',$filter_param); // 帅选条件
    	$this->assign('page',$page);// 赋值分页输出
    	$this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
    	$this->assign('q',$q);
    	C('TOKEN_ON',false);
        if(input('is_ajax')){
            return $this->fetch('ajaxGoodsList');
        }
        else{
            return $this->fetch();
        }

    }

    /**
     * 商品搜索列表页
     */
    public function ajaxSearch()
    {
        $point_rate = tpCache('shopping.point_rate');
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }

    /**
     * 品牌街
     */
    public function brandstreet()
    {
        $getnum = 9;   //取出数量
        $goods=M('goods')->where(array('is_recommend'=>1,'is_on_sale'=>1))->page(1,$getnum)->cache(true,TPSHOP_CACHE_TIME)->select(); //推荐商品
        for($i=0;$i<($getnum/3);$i++){
            //3条记录为一组
            $recommend_goods[] = array_slice($goods,$i*3,3);
        }
        $where = array(
            'is_hot' => 1,  //1为推荐品牌
        );
        $count = M('brand')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count,20);
        $brand_list = M('brand')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        $this->assign('recommend_goods',$recommend_goods);  //品牌列表
        $this->assign('brand_list',$brand_list);            //推荐商品
        $this->assign('listRows',$Page->listRows);
        if(I('is_ajax')){
            return $this->fetch('ajaxBrandstreet');
        }
        return $this->fetch();
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id){
        $goods_id = I('goods_id/d');
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'),$goods_id);
        exit(json_encode($result));
    }

    public function addService(){
        $goods_id = I('goods_id');
        $this->assign('goods_id',$goods_id);
        $data = M("admin")->where("role_id",4)->select();
        $this->assign('data',$data);
        return $this->fetch();
    }
    public function service(){
        $tpshop_config = array();
        $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();
        foreach($tp_config as $k => $v)
        {
            if($v['name'] == 'hot_keywords'){
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }
        $province = $tpshop_config['shop_info_province'];
        $city = $tpshop_config['shop_info_city'];
        $district = $tpshop_config['shop_info_district'];
        $add = $this->getAddressName($province,$city,$district);
        $this->assign('address',$add);
        $goods_id = I('goods_id');
        $admin_id = I('admin_id');
        $good = M('goods')->where("goods_id",$goods_id)->find();
        $admin = M('admin')->where("admin_id",$admin_id)->find();
        $comment = M("comment")->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->select();
        $this->assign('comment',$comment);
        //var_dump($comment);die;
        $comment_num = count($comment);
        //計算好評率
        $data = M('comment')->where(['is_show'=>1,'admin_id'=>$admin_id,'goods_id'=>$goods_id])->select();
        $arr = [];
        if($data){
            foreach ($data as $k=>$v){
                $c1 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (4,5)')->count();
                $c2 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (3)')->count();
                $c3 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (0,1,2)')->count();
                $c0 = $c1 + $c2 + $c3; // 所有评论
                if($c0 <= 0){
                    $arr[$k]['rate1'] = 100;
                }else{
                    $arr[$k]['rate1'] = ceil($c1 / $c0 * 100); // 好评率
                }
            }
            $rate1 = 0;
            $num = count($arr);
            foreach ($arr as $key=>$value){
                $rate1 += $value['rate1'];
            }
            $rate1 = $rate1/$num;
        }else{
            $rate1 = 100;
        }
        $order_num = $this->getOrderNum($admin_id,$goods_id);
        $this->assign('order_num',$order_num);
        $this->assign('rate',$rate1);
        $this->assign('comment_num',$comment_num);
        $this->assign('good',$good);
        $this->assign('admin',$admin);
        return $this->fetch();
    }
    public function getAddressName($p=0,$c=0,$d=0){
        $p = M('region')->where(array('id'=>$p))->field('name')->find();
        $c = M('region')->where(array('id'=>$c))->field('name')->find();
        $d = M('region')->where(array('id'=>$d))->field('name')->find();
        return $p['name'].'-'.$c['name'].'-'.$d['name'];
    }
    public function allcomment(){
        $p=I('p',1);
        $goods_id = I('goods_id');
        $admin_id = I('admin_id');
        $comment = M("comment")->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->select();
        $this->assign('comment',$comment);
        return $this->fetch();
    }
    public function serviceing(){
        $goods_id = I('goods_id');
        $admin_id = I('admin_id');
        if(IS_POST){
            $data = I('post.');
            $data['user_id'] = session('user')['user_id'];
            $data['nickname'] = session('user')['nickname'];
            $data['addtime'] = time();
            $goods_name = M('goods')->where('goods_id',$goods_id)->getField('goods_name');
            $data['goods_name'] = $goods_name;
            $r = M('consult')->add($data);
            if($r){
                $arr = ['status'=>'y','info'=>'您的需要我们收到，我们会尽快回复您'];
            }else{
                $arr = ['status'=>'n','info'=>'对不起，网络故障，请联系客服'];
            }
            $this->ajaxReturn($arr);
        }else{
            $admin = M('admin')->where('admin_id',$admin_id)->find();
            $this->assign('admin',$admin);
            $this->assign('admin_id',$admin_id);
            $this->assign('goods_id',$goods_id);
            return $this->fetch();
        }

    }
    public function getRate($admin_id,$goods_id){
        $data = M('comment')->where(['is_show'=>1,'admin_id'=>$admin_id,'goods_id'=>$goods_id])->select();
        $arr = [];
        if($data){
            foreach ($data as $k=>$v){
                $c1 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (4,5)')->count();
                $c2 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (3)')->count();
                $c3 = M('comment')->where(['admin_id'=>$admin_id,'goods_id'=>$goods_id])->where('(service_rank) in (0,1,2)')->count();
                $c0 = $c1 + $c2 + $c3; // 所有评论
                if($c0 <= 0){
                    $arr[$k]['rate1'] = 100;
                }else{
                    $arr[$k]['rate1'] = ceil($c1 / $c0 * 100); // 好评率
                }
            }
            $rate1 = 0;
            $num = count($arr);
            foreach ($arr as $key=>$value){
                $rate1 += $value['rate1'];
            }
            $rate1 = $rate1/$num;
        }else{
            $rate1 = 100;
        }
        return $rate1;
    }

    public function getOrderNum($admin_id,$goods_id){
//        $map = [];
//        $map['stock'] = array('gt',0);
       $data = M("order")->alias('o')->join("__ORDER_GOODS__ g",'o.order_id = g.order_id')->where(['o.admin_id'=>$admin_id,'g.goods_id'=>$goods_id,'o.pay_status'=>1])->select();
        $num = count($data);
        return $num;
    }
}