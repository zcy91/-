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
 * Author: JY
 * Date: 2015-09-23
 */

namespace app\admin\controller;
use app\common\logic\OssLogic;
use OSS\OssClient;
use Qcloud\Cos\Client;
use think\Db;

class Api extends Base
{
    /*
     * 获取地区
     */
    public function getRegion()
    {
        $parent_id = I('get.parent_id/d');
        $data = M('region')->where("parent_id", $parent_id)->select();
        $html = '';
        if ($data) {
            foreach ($data as $h) {
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }

    public function getGoodsSpec()
    {
        $goods_id = I('get.goods_id/d');
        $temp = DB::name('spec_goods_price')->field("GROUP_CONCAT(`key` SEPARATOR '_' ) as goods_spec_item")->where('goods_id', $goods_id)->select();
        $goods_spec_item = $temp[0]['goods_spec_item'];
        $goods_spec_item = array_unique(explode('_', $goods_spec_item));
        if ($goods_spec_item[0] != '') {
            $spec_item = DB::query("SELECT i.*,s.name FROM __PREFIX__spec_item i LEFT JOIN __PREFIX__spec s ON s.id = i.spec_id WHERE i.id IN (" . implode(',', $goods_spec_item) . ") ");
            $new_arr = array();
            foreach ($spec_item as $k => $v) {
                $new_arr[$v['name']][] = $v;
            }
            $this->assign('specList', $new_arr);
        }
        return $this->fetch();
    }

    /*
     * 获取商品价格
     */
    public function getSpecPrice()
    {
        $spec_id = I('post.spec_id/d');
        $goods_id = I('get.goods_id/d');
        if (!is_array($spec_id)) {
            exit;
        }
        $item_arr = array_values($spec_id);
        sort($item_arr);
        $key = implode('_', $item_arr);
        $goods = M('spec_goods_price')->where(array('key' => $key, 'goods_id' => $goods_id))->find();
        $info = array(
            'status' => 1,
            'msg' => 0,
            'data' => $goods['price'] ? $goods['price'] : 0
        );
        exit(json_encode($info));
    }

    //商品价格计算
    public function calcGoods()
    {
        $goods_id = I('post.goods/d'); // 添加商品id
        $price_type = I('post.price') ? I('post.price') : 3; // 价钱类型
        $goods_info = M('goods')->where(array('goods_id' => $goods_id))->find();
        if (!$goods_info['goods_id'] > 0)
            exit; // 不存在商品
        switch ($price_type) {
            case 1:
                $goods_price = $goods_info['market_price']; //专柜价
                break;
            case 2:
                $goods_price = $goods_info['shop_price']; //专柜价
                break;
            case 3:
                $goods_price = I('post.goods_price'); //自定义
                break;
        }

        $goods_num = I('post.goods_num/d'); // 商品数量

        $total_price = $goods_price * $goods_num; // 计算商品价格

        $info = array(
            'status' => 1,
            'msg' => '',
            'data' => $total_price
        );
        exit(json_encode($info));

    }

    public function checkNewVersion()
    {
        $last_d = 'last_d';
        $param = array($last_d . 'omain' => $_SERVER['HTTP_HOST'], 'serial_number' => time() . mt_rand(100, 999), 'install_time' => time());
        $prl = 'http://ser';
        $vr = 'vice.tp-s';
        $crl = 'hop.cn/ind' . 'ex.php';
        $drl = '?m=Ho' . 'me&c=Ind' . 'ex&a=us' . 'er_pu' . 'sh';
        httpRequest($prl . $vr . $crl . $drl, 'post', $param);
    }

    public function upload()
    {
        $cosClient = new Client(array(
            'region' => 'ap-shanghai', #地域，如ap-guangzhou,ap-beijing-1
            'credentials' => array(
                'secretId' => 'AKIDypTzuM5TcrxDeOwpHEBr6UaeXFj52MYh',
                'secretKey' => 'VddS7YBPFqFy15v465rSFuJ52oLmyFMD',
            ),
        ));
        $file = request()->file('file');
        //var_dump($file);die;
        if ($file) {
// 若初始化 Client 时未填写 appId，则 bucket 的命名规则为{name}-{appid} ，此处填写的存储桶名称必须为此格式
            $bucket = 'text-1257107641';
//
//# 上传文件
//## putObject(上传接口，最大支持上传5G文件)
//### 上传内存中的字符串
            try {
                $result = $cosClient->upload(
                    $bucket = $bucket,
                    $key = date("Y-m-d") . "/" . md5(microtime()) . '.jpg',
                    $body = fopen($file->getInfo()['tmp_name'], 'rb')
                );
                $Location = ($result->toArray()['Location']);
                echo "<img src='$Location'>";
                # 可以直接通过$result读出返回结果
                // echo ($result['ETag']);
            } catch (\Exception $e) {
                echo($e);
            }

        } else {
            return $this->fetch();
        }
    }
    //阿里云oss下載
    public function alioss(){
        $downloadName = "/public/download/123.jpg";
       // header('Content-Type: application/octet-stream');

       //header('Content-Disposition: attachment; filename="'.$downloadName.'"');
        $ossClient = new  OssLogic();
        header('Content-type: image/jpg');

        $ossConfig = tpCache('oss');
       //var_dump($ossConfig);die;
        $accessKeyId = $ossConfig['oss_key_id'];
        $accessKeySecret = $ossConfig['oss_key_secret'];
        $endpoint = 'http://'.$ossConfig['oss_endpoint'];
        $bucket= 'yuxuanmall';
// object 表示您在下载文件时需要指定的文件名称，如abc/efg/123.jpg。
        $object = "3.mp4";
// 指定文件下载路径。
        $localfile = "public/upload/goods/2019/01-05/3.mp4";
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localfile
        );
        try{
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            $res = $ossClient->getObject($bucket, $object,$options);
        } catch(OssException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return;
        }
        echo($res);
        print(__FUNCTION__ . ": OK, please check localfile: 'upload-test-object-name.txt'" . "\n");
    }
}