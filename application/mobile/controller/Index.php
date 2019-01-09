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
 * $Author: 当燃 2016-01-09
 */
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use Think\Db;
class Index extends MobileBase {

    public function index(){
        /*
            //获取微信配置
            $wechat_list = M('wx_user')->select();
            $wechat_config = $wechat_list[0];
            $this->weixin_config = $wechat_config;
            // 微信Jssdk 操作类 用分享朋友圈 JS
            $jssdk = new \Mobile\Logic\Jssdk($this->weixin_config['appid'], $this->weixin_config['appsecret']);
            $signPackage = $jssdk->GetSignPackage();
            print_r($signPackage);
        */
        $model = M("Navigationmobile");
        $navigationList = $model->where("is_show = 1")->order("sort asc")->select();
        $this->assign('navigationList',$navigationList);
        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
        $this->assign('thems',$thems);
        $this->assign('hot_goods',$hot_goods);
        $favourite_goods = M('goods')->where("is_new=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('new_goods',$favourite_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('favourite_goods',$favourite_goods);
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品

        //秒杀商品
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,f.price')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(3)->select();
        $point_rate = tpCache('shopping.point_rate');
        $this->assign('flash_sale_list',$flash_sale_list);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('favourite_goods',$favourite_goods);
        $this -> assign('point_rate',$point_rate);
		$this->assign('user)id',cookie('user_id'));
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";
        }
    }

    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    //打乱二维数组
    public function shuffle_assoc($list) {
        if (!is_array($list)) return $list;
        $keys = array_keys($list);
        shuffle($keys);
        $random = array();
        foreach ($keys as $key)
            $random[$key] = $this->shuffle_assoc($list[$key]);
        return $random;
    }
    //所有推荐：商品，资讯，视频，活动
    public function ajaxGetMore_allhot(){
        $p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $favourite_articles = M('article')->where("is_open = 1 and is_recommend = 1")->order("article_id DESC")->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐资讯
        $favourite_videos = M("video")->where("is_open = 1 and is_recommend = 1")->order("article_id DESC")->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐视频
        $favourite_activity = M("activity_group_buy")->where("is_end = 0 and recommended = 1")->order("id DESC")->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐活动
        $data = array_merge($favourite_goods,$favourite_articles);
        $data = array_merge($data,$favourite_videos);
        $data = array_merge($data,$favourite_activity);
        $data = $this->shuffle_assoc($data);
        //var_dump($data);die;
        $this->assign('favourite_goods',$data);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }
    //推荐视频
    public function ajaxGetMore_allhot_video(){
        $p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
        $data = M("video")->where("is_open = 1 and is_recommend = 1")->order("article_id DESC")->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐视频
        //var_dump($data);die;
        $this->assign('hot_videos',$data);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }
//    推荐咨询
    public function ajaxGetMore_allhot_article(){
        $p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
        $data = M("article")->where("is_open = 1 and is_recommend = 1")->order("article_id DESC")->page($p,15)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐视频
        //var_dump($data);die;
        $this->assign('hot_articles',$data);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }
    public function ajaxGetMore(){
    	$p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
    	$favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
    	$this->assign('favourite_goods',$favourite_goods);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }

    public function ajaxGetMore_new(){
        $p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
        $favourite_goods = M('goods')->where("is_new=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('new_goods',$favourite_goods);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }

    public function ajaxGetMore_hot(){
        $p = I('p/d',1);
        $point_rate = tpCache('shopping.point_rate');
        $favourite_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->page($p,C('PAGESIZE'))->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
        $this->assign('hot_goods',$favourite_goods);
        $this -> assign('point_rate',$point_rate);
        return $this->fetch();
    }

    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig(){
    	$askUrl = I('askUrl');//分享URL
    	$weixin_config = M('wx_user')->find(); //获取微信配置
    	$jssdk = new \app\mobile\logic\Jssdk($weixin_config['appid'], $weixin_config['appsecret']);
    	$signPackage = $jssdk->GetSignPackage(urldecode($askUrl));
    	if($signPackage){
    		$this->ajaxReturn($signPackage,'JSON');
    	}else{
    		return false;
    	}
    }

}