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
 * 微信交互类
 */
namespace app\home\controller;
use app\home\logic\UsersLogic;
use app\home\logic\CartLogic;
use think\Request;
class LoginApi extends Base {
    public $config;
    public $oauth;
    public $class_obj;

    public function __construct(){
        parent::__construct();
//        unset($_GET['oauth']);   // 删除掉 以免被进入签名
//        unset($_REQUEST['oauth']);// 删除掉 以免被进入签名

        $this->oauth = I('get.oauth');
        //获取配置
        $data = M('Plugin')->where("code",$this->oauth)->where("type","login")->find();
        $this->config = unserialize($data['config_value']); // 配置反序列化

    }

    /**
     * 支付宝授权登录
     */
    public function aliLogin(){
        //获取配置文件的ali参数
        //$ali_config = C("ALI_CONFIG");
        $ali_config['RSA_PRIVATE_KEY'] = '
MIIEpQIBAAKCAQEAvuDpscFCymPavtAXmtVt3sFaQOij2mOc2pwZDwAiylYYc/6l
V3o7rau+hJqAHm6egLk8ux6RDq8l5mnpvI5U5LAzjBDoR03ny952Y1X1M46jVz33
F0ii7ifmqTWeb675diosmRidfdwxSx6lFOHxN6KuLJI1Hw88Rp2voWRVJcWEDPQc
eVl5Hp2SN+llzbTcNUyPFGlWL4WjSxF4Zoa4d9LM1eb6hv2H0tHGeicwcovZ2b+S
NHM5yOWC6X4xPlAatDVUw/fbWc8Pox2qu6o0wkIcNS7o3ET8ZSJGAUvFiNk+x5ci
5OQkm4svUAVwva+1T4WfwnogQvl2k98WeIRpdQIDAQABAoIBAQCQ0pz83SEL5g9F
zyTZuS2PYSxVKy2GuSV9SApLM1MyKXiMKZzRblBxSGlYutCSRmPETschQePVPFaJ
J7rL8UG/8WBq2TkjQJyqNbOlUsajw4ly2/TpmZblEykTITeAjUWAvibwUZjMsZBE
6o9d9L/L8oYsExJy6mBVQ7bJwRJJgO7mNvBKRrQ2tgxslhdShKDwBLErf1b60rCQ
GxmcjCB6lbO3spg2TIXYn8rtVNAvGPqIA7qt7Eul7Z5sbhzH10QWUzMnwNAKr2d7
twgbf1bzZfENtBA3YUacWOIY8JGhb0U+Qo34FcRCXJd8NKqaR3DeyvyQYQS9hQIN
jwHrnWsBAoGBAOm0ajUooHHnDSoyiSfzzSR9aZln/0hAIAW1qUqv/Bq/MHsgoEYA
fo7ymdeR1wpC/o/P1tD5gcDW1Gbe8uQ70KdiJUlM5UHd+PN9HFMOCKPT4D5ad1kd
fIlq058IJ0bH0t+DFAASiYh7X8QzkExst5hF+Js0XvmxKCeqfAVk0I7RAoGBANEW
lpjTmcuclcPgNkWsdIT43y9Vtldcbo5u0O4ExJT/AxO2xIMtpq0Qix0IEeL9McM0
8Ddoqp9CSxQ0fZ7RgVhwB7eiN3FUInXMGdRMVC7e3uN1KfMSSF3m7mKCrItY1jse
Kvkq7b/6hR1tfjBJYVS2C1J22P99hhozAQCWqUFlAoGBAI1I6LxiZzz09dyA2LIy
jd51gxWe7ZH8Ul+hR0tDwSFaXDDTtJEdU2WP/Ll6DYrCnarLd462iF7QgW//cM/R
6X6HswrxVdfQHeT1yd3cKhUAzhxkDKrvTI626mDGSRhdTXqaf8jbbBH6pBa1JZNO
Vl/UTUtnCdPh6eixBHEuVXdBAoGAYr7fOqfcX1vBIzO1jewnT1FV3j4FknaVw/Cz
/WUFDjTtWBcd8bHSoLNKb9iK0f+vy7gpppFo2zPsz0sG9MWO55xpGKGku4H3kFhm
7mtp6oTZEOUZfbFpuedBOAbsxBadfmf99ZT/mYYP7djzGozdSat752M3klnOxnrG
A2gj4T0CgYEAo9npdrmZ8kLHkgB7K4p95RWDMRvlFJDqLu7ln3F2SUOuhIY++zy8
RuHuXkrfReT5qcrmxCoeW9DyqE9iNdv8xo4LM0h4YNCvBEDIKjJfvHAkQrFqDOV6
B7A4PuW68MEwnM2XgXgXgDEE/O0VsYC9g9Vmz0bdXohswbxkDNtAePg=';
        $ali_config['ALIPAY_RSA_PBULIC_KEY'] = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAl5PxxRNRyEk4G0aKbYEXXZ5zfAoO3X/UoxGOjYnQ7sK4Ns4dfto8UA2xlkPmIUmz/05mvqUlTYJp6gRgtgaVRQLBLzeZKj4nKOeb/NBZkkTTQsExHRD35oMNnCFEjIdPhDTKQquhvcinSIMMB+D7RcAKLwgVNUohbvbuIbrDmKdtJUH8eIP3fQ7b9Pac3Fw+2Kww9LV8a9ZWRv54AJBsm0+g36bmhJ+LGMwEWc+zH2iDOFp9rm4t3JjNi5ROeAavurLXWfkwtuusU0Xh+AVLvlzeRqVtoSByCBCeBT9jDBUoLG+bASwxJfvQN8IjCr8L1r5iIc9nDD3OJyu/WSEEQQIDAQAB';

        //应用的APPID
        $app_id = "2019011062815968";
        //【成功授权】后的回调地址
        $my_url = "http://".$_SERVER['HTTP_HOST']."/Home/LoginApi/aliLogin";

        //Step1：获取auth_code
        $auth_code = $_REQUEST["auth_code"];//存放auth_code

        if(empty($auth_code)){
            //state参数用于防止CSRF攻击，成功授权后回调时会原样带回
            $_SESSION['alipay_state'] = md5(uniqid(rand(), TRUE));
            //拼接请求授权的URL
            $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=".$app_id."&scope=auth_user&redirect_uri=".$my_url."&state="
                . $_SESSION['alipay_state'];

            echo("<script> top.location.href='" . $url . "'</script>");
        }
        //Step2: 使用auth_code换取apauth_token
        if($_REQUEST['state'] == $_SESSION['alipay_state'] || 1)
        {
            vendor("Alipay.AopClient"); //引入sdk
            $aop = new \AopClient();

            $aop->gatewayUrl           = "https://openapi.alipay.com/gateway.do";
            $aop->appId                = $app_id;
            $aop->rsaPrivateKey        = $ali_config['RSA_PRIVATE_KEY'];//应用私钥
            $aop->alipayrsaPublicKey   = $ali_config['ALIPAY_RSA_PBULIC_KEY'];//支付宝公钥
            $aop->apiVersion           = '1.0';
            $aop->signType             = 'RSA2';
            $aop->postCharset          = 'utf-8';
            $aop->format               = 'json';

            //根据返回的auth_code换取access_token
            vendor("Alipay.AlipaySystemOauthTokenRequest");//调用sdk里面的AlipaySystemOauthTokenRequest类
            $request = new \AlipaySystemOauthTokenRequest();

            $request->setGrantType("authorization_code");
            $request->setCode($auth_code);

            $result = $aop->execute($request);
            //var_dump($result);die;
            $access_token = $result->alipay_system_oauth_token_response->access_token;
            //var_dump($access_token);die;
            //Step3: 用access_token获取用户信息
            vendor("Alipay.AlipayUserInfoShareRequest");//调用sdk里面的AlipayUserInfoShareRequest类
            $request = new \AlipayUserInfoShareRequest();
            $result = $aop->execute ( $request, $access_token);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                $user_data = $result->$responseNode;

                $data = array();
                $data['sex']              = $user_data->gender=='m'?1:2;
                $data['province']      = $user_data->province;
                $data['city']             = $user_data->city;
                $data['person_name']   = $user_data->nick_name;
                $data['ali_openid']    = $user_data->user_id;
                $data['ali_name']      = $user_data->nick_name;
                $data['ali_img']       = $user_data->avatar;
                $data['addtime']       = date("Y-m-d H:i:s", time());
                $data['person_img']       = $user_data->avatar;
                $data['signtime']       = date("Y-m-d H:i:s", time());
                //$user = M("Member")->where(array("ali_openid"=> $user_data->user_id))->find();
                var_dump($user_data);die;
            }
        }
    }

    public function login(){
        if(!$this->oauth)
            $this->error('非法操作',U('User/login'));
        include_once  "plugins/login/{$this->oauth}/{$this->oauth}.class.php";
        $this->class_obj->login();
    }

    public function callback(){
        $data = $this->class_obj->respon();
        $logic = new UsersLogic();
        if(session('?user')){
            $res = $logic->oauth_bind($data);//已有账号绑定第三方账号
            if($res['status'] == 1){
                $this->success('绑定成功',U('User/index'));
            }else{
                $this->error('绑定失败',U('User/index'));
            }
        }
        $data = $logic->thirdLogin($data);
        if($data['status'] != 1)
            $this->error($data['msg']);
        session('user',$data['result']);
        setcookie('user_id',$data['result']['user_id'],null,'/');
        setcookie('is_distribut',$data['result']['is_distribut'],null,'/');
        $nickname = empty($data['result']['nickname']) ? '第三方用户' : $data['result']['nickname'];
        setcookie('uname',urlencode($nickname),null,'/');
        setcookie('cn',0,time()-3600,'/');
        // 登录后将购物车的商品的 user_id 改为当前登录的id
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($data['result']['user_id']);
        $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
//    	$cartLogic->login_cart_handle($this->session_id,$data['result']['user_id']);  //用户登录后 需要对购物车 一些操作
        if(isMobile())
            $this->success('登陆成功',U('Mobile/User/index'));
        else
            $this->success('登陆成功',U('User/index'));
    }
}