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
 */

namespace app\common\logic;

use think\Model;


/**
 * 分类逻辑定义
 * Class CatsLogic
 * @package common\Logic
 */
class GoodsLogic extends Model
{
    /**
     * 获取促销商品数据
     * @return mixed
     */
    public function getPromotionGoods()
    {
        $goods_where = array('g.is_on_sale' => 1);
        $promotion_goods = M('goods')
            ->alias('g')
            ->field('g.goods_id,g.goods_name,f.price AS shop_price,f.end_time')
            ->join('__FLASH_SALE__ f','g.goods_id = f.goods_id')
            ->where($goods_where)
            ->limit(3)
            ->select();
        return $promotion_goods;
    }

    /**
     * 获取精品商品数据
     * @return mixed
     */
    public function getHighQualityGoods()
    {
        $goods_where = array('is_recommend' => 1, 'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $promotion_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $promotion_goods;
    }

    /**
     * 获取新品商品数据
     * @return mixed
     */
    public function getNewGoods()
    {
        $goods_where = array('is_new' => 1,  'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $new_goods;
    }

    /**
     * 获取热销商品数据
     * @return mixed
     */
    public function getHotGood()
    {
        $goods_where = array('is_hot' => 1,  'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $new_goods;
    }

    /**
     * 获取首页轮播图片
     * @return mixed
     */
    public function getHomeAdv()
    {
        $adv = M('ad')->where('pid = 2')->field(array('ad_link','ad_name','ad_code'))->cache(true,TPSHOP_CACHE_TIME)->select();
        //广告地址转换
        foreach($adv as $k=>$v){
            if(!strstr($v['ad_link'],'http')){
                $adv[$k]['ad_link'] = SITE_URL.$v['ad_link'];
            }
            $adv[$k]['ad_code'] = SITE_URL.$v['ad_code'];
        }
        return $adv;
    }

    /**
     * 获取秒杀商品
     * @return mixed
     */
    public function getFlashSaleGoods($count, $page = 1, $start_time=0, $end_time=0)
    {
        //$where['f.status'] = 1;
        $where['f.start_time'] = array('egt', $start_time ?: time());
        if ($end_time) {
            $where['f.end_time'] = array('elt',$end_time);
        }

        $flash_sale_goods = M('flash_sale')->alias('f')
            ->field('f.end_time,f.goods_name,f.price,f.goods_id,f.price,g.shop_price,100*(FORMAT(f.buy_num/f.goods_num,2)) as percent')
            ->join('__GOODS__ g','g.goods_id = f.goods_id')
            ->where($where)
            //->order('f.start_time', 'asc')
            ->page($page, $count)
            ->select();
        return $flash_sale_goods;
    }

     /**
     * 找相似
     */
    public function getSimilar($goods_id, $p, $count)
    {
        $goods = M('goods')->field('cat_id')->where('goods_id', $goods_id)->find();
        if (empty($goods)) {
            return [];
        }

        $where = ['goods_id' => ['<>', $goods_id], 'cat_id' => $goods['cat_id']];
    	$goods_list = M('goods')->field("goods_id,goods_name,shop_price")
                ->where($where)->page($p, $count)->select();

    	return $goods_list;
    }

    /**
     * 积分商城
     */
    public function integralMall($rank, $user_id, $p = 1)
    {
        $ranktype = '';
        if ($rank == 'num') {
            $ranktype = 'sales_sum';//以兑换量（购买量）排序
        } elseif ($rank == 'integral') {
            $ranktype = 'exchange_integral';//以需要积分排序
        }

        $point_rate = tpCache('shopping.point_rate');
        $goods_where['is_on_sale'] = 1;//是否上架

        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));
        //我能兑换
        if (!empty($user_id)) {
            //获取用户积分
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }
        $goods_where['exchange_integral'] =  $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总数
        $goods_list = M('goods')->field('goods_id,goods_name,shop_price,market_price,exchange_integral')
                ->where($goods_where)->order($ranktype ,'desc')->page($p, 15)->select();

        $result = [
            'goods_list' => $goods_list,
            'goods_list_count' => $goods_list_count,
            'point_rate' => $point_rate,
        ];

        return $result;
    }

    /**
     *  获取排好序的品牌列表
     */
    function getSortBrands()
    {
        $brandList =  M("Brand")->select();
        $brandIdArr =  M("Brand")->where("name in (select `name` from `".C('database.prefix')."brand` group by name having COUNT(id) > 1)")->getField('id,cat_id');
        $goodsCategoryArr = M('goodsCategory')->where("level = 1")->getField('id,name');
        $nameList = array();
        foreach($brandList as $k => $v)
        {

            $name = getFirstCharter($v['name']) .'  --   '. $v['name']; // 前面加上拼音首字母

            if(array_key_exists($v[id],$brandIdArr) && $v['cat_id']) { // 如果有双重品牌的 则加上分类名称
                $name .= ' ( '. $goodsCategoryArr[$v['cat_id']] . ' ) ';
            }
            $nameList[] = $v['name'] = $name;
            $brandList[$k] = $v;
        }
        array_multisort($nameList,SORT_STRING,SORT_ASC,$brandList);

        return $brandList;
    }

    /**
     *  获取排好序的分类列表
     */
    function getSortCategory()
    {
        $categoryList =  M("GoodsCategory")->getField('id,name,parent_id,level');
        $nameList = array();
        foreach($categoryList as $k => $v)
        {

            //$str_pad = str_pad('',($v[level] * 5),'-',STR_PAD_LEFT);
            $name = getFirstCharter($v['name']) .' '. $v['name']; // 前面加上拼音首字母
            //$name = getFirstCharter($v['name']) .' '. $v['name'].' '.$v['level']; // 前面加上拼音首字母
            /*
            // 找他老爸
            $parent_id = $v['parent_id'];
            if($parent_id)
                $name .= '--'.$categoryList[$parent_id]['name'];
            // 找他 爷爷
            $parent_id = $categoryList[$v['parent_id']]['parent_id'];
            if($parent_id)
                $name .= '--'.$categoryList[$parent_id]['name'];
            */
            $nameList[] = $v['name'] = $name;
            $categoryList[$k] = $v;
        }
        array_multisort($nameList,SORT_STRING,SORT_ASC,$categoryList);

        return $categoryList;
    }
}