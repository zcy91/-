<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.icngo.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 2015-11-21
 */
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use think\Page;
use think\Verify;
use think\Db;

class Distribut extends MobileBase {
        /*
        * 初始化操作
        */
    public function _initialize() {
        parent::_initialize();
        if(tpCache('distribut.switch')==0){
            $this->error('分销功能已关闭',U('Mobile/User/index'));
        }
        if(session('?user'))
        {
        	$user = session('user');
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        }
        $nologin = array(
        	'login','pop_login','do_login','logout','verify','set_pwd','finished',
        	'verifyHandle','reg','send_sms_reg_code','find_pwd','check_validate_code',
        	'forget_pwd','check_captcha','check_username','send_validate_code',
        );
        if(!$this->user_id && !in_array(ACTION_NAME,$nologin)){
        	header("location:".U('Mobile/User/login'));
        	exit;
        }
        if($user['is_distribut'] == 1){ //是分销商才查找用户店铺信息
            $user_store = Db::name('user_store')->where("user_id", $this->user_id)->find();
            $this->userStore=$user_store;
            $this->assign('store',$user_store);
        }

        $order_count = Db::name('order')->where("user_id", $this->user_id)->count(); // 我的订单数
        $goods_collect_count = Db::name('goods_collect')->where("user_id", $this->user_id)->count(); // 我的商品收藏
        $comment_count = Db::name('comment')->where("user_id", $this->user_id)->count();//  我的评论数
        $coupon_count = Db::name('coupon_list')->where("uid", $this->user_id)->count(); // 我的优惠券数量
        $first_nickname = Db::name('users')->where("user_id", $this->user['first_leader'])->getField('nickname');
        $level_name = Db::name('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        $this->assign('level_name',$level_name);
        $this->assign('first_nickname',$first_nickname);
        $this->assign('order_count',$order_count);
        $this->assign('goods_collect_count',$goods_collect_count);
        $this->assign('comment_count',$comment_count);
        $this->assign('coupon_count',$coupon_count);

    }

    /**
     * 分销用户中心首页（分销中心）
     */
    public function index(){
        // 销售额 和 我的奖励
        $result = DB::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;

         $lower_count[1] = Db::name('users')->where("first_leader", $this->user_id)->count();
         $lower_count[2] = Db::name('users')->where("second_leader", $this->user_id)->count();
         $lower_count[3] = Db::name('users')->where("third_leader", $this->user_id)->count();


        $result2 = DB::query("select status,count(1) as c , sum(goods_price) as goods_price from `__PREFIX__rebate_log` where user_id = :user_id group by status",['user_id'=>$this->user_id]);
        $level_order = convert_arr_key($result2, 'status');
        for($i = 0; $i <= 5; $i++)
        {
            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
        }

        $money['withdrawals_money'] = Db::name('withdrawals')->where(['user_id'=>$this->user_id,'status'=>1])->sum('money'); // 已提现财富
        $money['achieve_money'] = Db::name('rebate_log')->where(['user_id'=>$this->user_id,'status'=>3])->sum('money');  //累计获得佣金
        $time=strtotime(date("Y-m-d"));
        $money['today_money'] = Db::name('rebate_log')->where("user_id=$this->user_id and status in(2,3) and create_time>$time")->sum('money');    //今日收入

        $this->assign('level_order',$level_order); // 下线订单
        $this->assign('lower_count',$lower_count); // 下线人数
        $this->assign('sales_volume',$result['goods_price']); // 销售额
        $this->assign('reward',$result['money']);// 奖励
        $this->assign('money',$money);
        return $this->fetch();
    }

    /**
     * 下线列表(我的团队)
     */
    public function lower_list(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $level = I('get.level',1);
        $q = I('post.q','','trim');
        $condition = array(1=>'first_leader',2=>'second_leader',3=>'third_leader');

        $where = "{$condition[$level]} = {$this->user_id}";
        $bind = array();
        if($q){
            $where .= " and (nickname like :q1 or user_id = :q2 or mobile = :q3)";
            $bind['q1'] = "%$q%";
            $bind['q2'] = $q;
            $bind['q3'] = $q;
        }

        $count = Db::name('users')->where($where)->bind($bind)->count();
        $page = new Page($count,C('PAGESIZE'));
        $lists = Db::name('users')
            ->field('nickname,user_id,distribut_money,reg_time,head_pic')
            ->where($where)->bind($bind)
            ->limit("{$page->firstRow},{$page->listRows}")
            ->order('user_id desc')
            ->select();
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('lists',$lists); // 下线
        if(I('is_ajax'))
        {
            return $this->fetch('ajax_lower_list');
        }
        return $this->fetch();
    }

    /**
     * 下线订单列表（分销订单）
     */
    public function order_list(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $status = I('get.status',0);
        $where = array('user_id'=>$this->user_id,'status'=>['in',$status]);
        $count = M('rebate_log')->where($where)->count();
        $Page  = new Page($count,C('PAGESIZE'));
        $list = M('rebate_log')->where($where)->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select(); //分成订单记录
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if(!empty($user_id_list))
            $userList = M('users')->where("user_id", "in", implode(',', $user_id_list))->getField('user_id,nickname,mobile,head_pic');  //购买者信息
        /*获取订单商品*/
        $model = new UsersLogic();
        foreach ($list as $k => $v) {
            $data = $model->get_order_goods($v['order_id']);
            $list[$k]['goods_list'] = $data['result'];
        }
        $this->assign('count', $count);// 总人数
        $this->assign('page', $Page->show());// 赋值分页输出
        $this->assign('userList',$userList); //
        $this->assign('list',$list); // 下线
        if(I('is_ajax')){
            return $this->fetch('ajax_order_list');
        }
        return $this->fetch();
    }


    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
     * 个人推广二维码 （我的名片）
     */
    public function qr_code(){
        $ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=User&a=reg&first_leader={$this->user_id}"); //默认分享链接
        if($this->user['is_distribut'] == 1)
            $this->assign('ShareLink',$ShareLink);
        return $this->fetch();
    }

    /**
     * 平台分销商品列表
     * @author  lxl
     * @time2017-4-6
     */
    public function goods_list(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $GoodsLogic = new \app\admin\logic\GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $ids1 = array_multi2single(Db::name('user_distribution')->field('goods_id')->where(array('user_id'=>$this->user_id))->select());    //查找用户已添加的商品ID
        $ids= implode(',',$ids1)=='' ? 0:implode(',',$ids1);  //解决没添加时会报错
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $where = ' commission > 0 ';
        $cat_id = I('cat_id/d');
        $bind = array();
        if($cat_id > 0)  //分类
        {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if($key_word)   //搜索
        {
            $where = "$where and (goods_name like :key_word1)" ;
            $bind['key_word1'] = "%$key_word%";
        }
        $brand_id = I('brind_id/d');      //品牌
        if($brand_id>0){
            $where .= " and brand_id = $brand_id ";
        }
        $count = Db::name('Goods')->where($where)->bind($bind)->count();
        $Page  = new Page($count,C('PAGESIZE'));
        $goodsList = Db::name('Goods')->field('goods_name,goods_id,commission,shop_price,cat_id,brand_id')->where("$where and goods_id not in($ids)")->bind($bind)->order("$sort $sort_asc")->limit($Page->firstRow.','.$Page->listRows)->cache(true)->select();
        $this->assign('categoryList',$categoryList);    //品牌
        $this->assign('brandList',$brandList);  //分类
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$Page->show());
        $this->assign('pager',$Page);
        if(I('is_ajax')){
            return $this->fetch('ajax_goods_list');
        }
        return $this->fetch();
    }

    /**
     * 添加分销商品
     * @author  lxl
     * @time2017-4-6
     */
    public function add_goods(){
        $user =$this->user;
        if($this->user_id == 0){  //判断登录是否有效
            $this->redirect('Mobile/User/index');
        }
        $data=I('post.');
        foreach($data as $k=>$v){
            $data[$k]['user_id']= $this->user_id;
            $data[$k]['user_name']= $user['nickname'] ;
            $data[$k]['addtime']= time();
        }
        $result=Db::name('user_distribution')->insertAll($data); //添加
        if($result){
            $this->success('成功',U('Mobile/Distribut/goods_list'));
        }else{
            $this->error('失败');
        }
    }

    /**
     * 店铺设置
     * @author  lxl
     * @time2017-4-6
     */
    public function set_store(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        if(IS_POST){
            $data = input('post.');
            $UserStoreValidate = \think\Loader::validate('UserStore');
            if (!$UserStoreValidate->batch()->check($data)) {
                $return = ['status' => 0,'msg' => '操作失败','result' => $UserStoreValidate->getError()];
                $this->ajaxReturn($return);
            }
            // 上传图片
            if (!empty($_FILES['store_img']['tmp_name'])) {
                $files = request()->file('store_img');
                $save_url = 'public/upload/user_tore';
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $files->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    $return_imgs[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($files->getError());
                }
            }
                if (!empty($return_imgs)) {
                    $data['store_img'] = implode(',', $return_imgs);
                }
                    $data['store_time']=time();
                if($this->userStore == null){ //添加
                    $data['user_id'] = $this->user_id;
                    $addres = Db::name('user_store')->add($data);
                    if($addres){
                        $return = ['status' =>1,'msg' => '添加店铺信息成功', 'result' =>''];
                    }else{
                        $return = ['status' => 0,'msg' => '添加店铺信息失败', 'result' =>''];
                    }
                }else{ //修改
                    $upres = Db::name('user_store')->where(array('user_id'=>$this->user_id))->update($data);
                    if($upres){
                        $return = ['status' =>1, 'msg' => '修改店铺信息成功','result' =>''];
                    }else{
                        $return =['status' => 0, 'msg' => '修改店铺信息失败','result' =>''];
                    }
                }
            $this->ajaxReturn($return);
            exit;
            }
        return $this->fetch();
    }

    /**
     * 用户分销商品
     * @author  lxl
     * @time2017-4-6
     */
    public function my_store(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $userDistributionModel = M('user_distribution');
        $goods_ids = $userDistributionModel->where(array('user_id'=>$this->user_id))->getField('goods_id',true);  //用户分销商品id
        $ids = !empty($goods_ids) ? implode(',',$goods_ids) : 0;  //以,号拼接ID
        $Page  = new Page(count($goods_ids),C('PAGESIZE'));
        $goodsModel = M('goods');
        $goodsWhere = " goods_id in ($ids) ";
        $lists = $goodsModel->where($goodsWhere)
            ->field('goods_id,goods_name,shop_price')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();  //查找商品信息
        $countWhere = ' is_on_sale =1 and commission > 0 '; //公共统计条件
        $statistics['user_possess_goods'] = $goodsModel->where($countWhere)->count(); //平台全部分销商品
        $statistics['user_promotion_goods'] = $goodsModel->where("prom_type=1 and $countWhere")->count();  //平台全部促销分销商品
        $statistics['user_new_goods'] = $goodsModel->where("is_new=1 and $countWhere")->count();  //平台全部新品分销商品
        $this->assign('show',$Page->show());
        $this->assign('lists', $lists);
        $this->assign('statistics', $statistics);
        if(I('is_ajax')){
            return $this->fetch('ajax_my_store');
        }
        return $this->fetch();
    }


    /**
     * 新手必看
     * @author  lxl
     * @time2017-4-6
     */
    public function must_see(){
        $article = D('article')->field('article_id,title,content')->where(["cat_id"=>95,'is_open'=>1])->cache(true)->select();
        $this->assign('article',$article);
        return $this->fetch();
    }

    /**
     *分销排行
     * @author  lxl
     * @time2017-4-6
     */
    public function rankings(){
        $user = $this->user;
        $sort= I('sort','distribut_money');
//        $count = Db::name('users')->where("is_distribut = 1")->count(); //统计符合条件的总数
        $Page = new Page(200,C('PAGESIZE'));  //考虑用户不会看那么下去，不找那么多了
        $where = array('is_distribut' => 1);
        $lists = Db::name('users')->where(array('is_distribut' => 1))->order("$sort desc")->limit($Page->firstRow.','.$Page->listRows)->select(); //获排行列表
        $where["$sort"] = array('gt',$user["$sort"]);
        $place = Db::name('users')->where($where)->count($sort); //用户排行名
        $this->assign('lists',$lists);
        $this->assign('page',$Page->show());
        $this->assign('firsRrow',$Page->firstRow);  //当前分页开始数
        $this->assign('place',$place+1);  //当前分页开始数
        if(I('is_ajax')){
            return $this->fetch('ajax_rankings');
        }
        return $this->fetch();
    }

    /**
     * 佣金记录
     * @author  lxl
     * @time2017-4-6
     */
    public function rebate_log(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $status = I('status',''); //日志状态
        $sort_asc = I('sort_asc','desc');  //排序
        $sort  = I('sort','create_time'); //排序条件
        $where['user_id'] = $this->user_id;
        if($status!=''){
            $where['status']= $status ;
        }
        $count = Db::name('rebate_log')->where($where)->count(); //统计符合条件的数量
        $Page = new Page($count,C('PAGESIZE'));
        $lists = Db::name('rebate_log')->where($where)->order("$sort  $sort_asc")->limit($Page->firstRow.','.$Page->listRows)->cache(true)->select(); //查询日志
        $this->assign('lists',$lists);
        if(I('is_ajax')){
            return $this->fetch('ajax_rebate_log');
        }
        return $this->fetch();
    }
}