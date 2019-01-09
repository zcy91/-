
<?php
/*
 * 微信扫码登陆插件
 * 扫码登陆，请先注册微信开放平台账号，然后创建网站应用
 * 特别注意：如果需要同步手机微信直接登陆账户，务必在开放平台绑定微信公众号
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 */

class weixin{
	public $appid;
	public $secret;
	public $return_url;

	public function __construct($config){
		$this->appid = $config['appid'];
		$this->secret = $config['secret'];
		//$this->return_url = "http://".$_SERVER['HTTP_HOST']."/index.php/Home/ThirdLogin/callback/oauth/weixin";
		$this->return_url = "http://".$_SERVER['HTTP_HOST'].U('LoginApi/callback',array('oauth'=>'weixin'));
		
	}
	//构造要请求的参数数组，无需改动
	public function login(){
		$url = "https://open.weixin.qq.com/connect/qrconnect?appid={$this->appid}&redirect_uri=".urlencode($this->return_url)."&response_type=code&scope=snsapi_login&state=STATE#wechat_redirect";
		echo("<script> top.location.href='" . $url . "'</script>");
		exit;
	}

	public function respon(){
		$code = I('get.code');
		$access_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->appid.'&secret='.$this->secret.'&code='.$code.'&grant_type=authorization_code';
		if($code){
			$this->code = $_GET['code'];
			$result = $this->get_wx_contents($access_token_url);
			$result = json_decode($result,true);
			$user_info = $this->get_user_info($result['access_token'],$result['openid']);
			$user_info['oauth'] = 'weixin';
			$user_info['head_pic'] = $user_info['headimgurl'];
			return $user_info;
		}else{
			exit("No code");
		}
	}
	
	// 获取用户的信息
	private function get_user_info($access_token,$openid){
		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid;
		$user_info =  $this->get_wx_contents($url);
		return json_decode($user_info,true);
	}

	private function get_wx_contents($url){
		$ch = curl_init();
		$timeout = 10;
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$file_contents = curl_exec($ch);
		curl_close($ch);
		return $file_contents;
	}

}


?>