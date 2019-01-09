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
namespace app\home\controller;
use app\home\logic\UsersLogic;
use think\Controller;
use think\Db;
use think\Session;

class Base extends Controller {
    public $session_id;
    public $cateTrre = array();
    /*
     * 初始化操作
     */
    public function _initialize() {
        Session::start();
        if($_GET['first_leader']){
            $first_leader = (int)$_GET['first_leader'];//分销上级
            setcookie('first_leader',$first_leader);
        }
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.icngo.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
    	$this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用

        // 判断当前用户是否手机
        if(isMobile())
            cookie('is_mobile','1',3600);
        else
            cookie('is_mobile','0',3600);
        //微信浏览器
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            $user_temp = session('user');
            if (isset($user_temp['user_id']) && $user_temp['user_id']) {
                $user = M('users')->where("user_id", $user_temp['user_id'])->find();
                if (!$user) {
                    $_SESSION['openid'] = 0;
                    session('user', null);
                }
            }
            if (empty($_SESSION['openid'])) {
                $this->weixin_config = M('wx_user')->find(); //获取微信配置
                $this->assign('wechat_config', $this->weixin_config);
                if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                    $wxuser = $this->GetOpenid(); //授权获取openid以及微信用户信息
                    session('subscribe', $wxuser['subscribe']);// 当前这个用户是否关注了微信公众号
                    setcookie('subscribe',$wxuser['subscribe']);
                    //微信自动登录
                    $logic = new UsersLogic();
                    $data = $logic->thirdLogin($wxuser);

                    if($data['status'] == 1){

                        session('user',$data['result']);
                        setcookie('user_id',$data['result']['user_id'],null,'/');
                        setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
                        setcookie('uname',$data['result']['nickname'],null,'/');
                        // 登录后将购物车的商品的 user_id 改为当前登录的id
                        M('cart')->where("session_id", $this->session_id)->save(array('user_id'=>$data['result']['user_id']));
                        $cartLogic = new \app\home\logic\CartLogic();
                        $cartLogic->setUserId($data['result']['user_id']);
                        $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
                    }
                }
            }
        }
        $this->public_assign();
    }
    /**
     * 保存公告变量到 smarty中 比如 导航
     */
    public function public_assign()
    {

       $tpshop_config = array();
       $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();
       foreach($tp_config as $k => $v)
       {
       	  if($v['name'] == 'hot_keywords'){
       	  	 $tpshop_config['hot_keywords'] = explode('|', $v['value']);
       	  }
          $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
       }

       $goods_category_tree = get_goods_category_tree();
       $this->cateTrre = $goods_category_tree;
       $this->assign('goods_category_tree', $goods_category_tree);
       $brand_list = M('brand')->cache(true)->field('id,name,parent_cat_id,logo,is_hot')->where("parent_cat_id>0")->select();
       $this->assign('brand_list', $brand_list);
       $this->assign('tpshop_config', $tpshop_config);
    }

    /*
     *
     */
    public function ajaxReturn($data)
    {
        exit(json_encode($data));
    }
}