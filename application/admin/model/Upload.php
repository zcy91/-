<?php
/**
 * Created by PhpStorm.
 * User: fbi
 * Date: 2018/12/18 0018
 * Time: 10:58
 */

namespace app\admin\model;


use Qcloud\Cos\Client;

class Upload
{
    function uploadFile($fileInfo,$uploadPath = 'public/videos',$flag=true,$allowExt=array('jpeg','jpg','gif','png','mp4'),$maxSize = 2097152*10)
    {
// 判断错误号
        if ($fileInfo ['error'] > 0) {
            switch ($fileInfo ['error']) {
                case 1 :
                    $mes = '上传文件超过了PHP配置文件中upload_max_filesize选项的值';
                    break;
                case 2 :
                    $mes = '超过了表单MAX_FILE_SIZE限制的大小';
                    break;
                case 3 :
                    $mes = '文件部分被上传';
                    break;
                case 4 :
                    $mes = '没有选择上传文件';
                    break;
                case 6 :
                    $mes = '没有找到临时目录';
                    break;
                case 7 :
                case 8 :
                    $mes = '系统错误';
                    break;
            }
            echo($mes);
            return false;
        }
        $ext = pathinfo($fileInfo ['name'], PATHINFO_EXTENSION);

        if (!is_array($allowExt)) {
            exit('系统错误');
        }
// 检测上传文件的类型
        if (!in_array($ext, $allowExt)) {
            exit ('非法文件类型');
        }
        $maxSize = 2097152*10; // 2M
// 检测上传文件大小是否符合规范
        if ($fileInfo ['size'] > $maxSize) {
            exit ('上传文件过大');
        }
//检测图片是否为真实的图片类型
//$flag=true;
        if ($flag) {
//        if (!getimagesize($fileInfo['tmp_name'])) {
//            exit('不是真实图片类型');
//        }
        }
// 检测文件是否是通过HTTP POST方式上传上来
        if (!is_uploaded_file($fileInfo ['tmp_name'])) {
            exit ('文件不是通过HTTP POST方式上传上来的');
        }
//$uploadPath = 'uploads';
        $filedir = gmdate("Ymd");
        $uploadPath = $uploadPath.'/'.$filedir;
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
            chmod($uploadPath, 0777);
        }

//        $uniName = md5(uniqid(microtime(true), true)) . '.' . $ext;
        //腾讯云
        $uniName = date("Y-m-d") . "/" .md5(uniqid(microtime(true), true)) . '.' . $ext;
        $destination = $uploadPath . '/' . $uniName;
//        if (!move_uploaded_file($fileInfo ['tmp_name'], $destination)) {
//            exit ('文件移动失败');
//        }
        //腾讯云上传
        $cosClient = new Client(array(
            'region' => 'ap-shanghai', #地域，如ap-guangzhou,ap-beijing-1
            'credentials' => array(
                'secretId' => 'AKIDypTzuM5TcrxDeOwpHEBr6UaeXFj52MYh',
                'secretKey' => 'VddS7YBPFqFy15v465rSFuJ52oLmyFMD',
            ),
        ));
// 若初始化 Client 时未填写 appId，则 bucket 的命名规则为{name}-{appid} ，此处填写的存储桶名称必须为此格式
            $bucket = 'text-1257107641';
//# 上传文件
//## putObject(上传接口，最大支持上传5G文件)
//### 上传内存中的字符串
            try {
                $result = $cosClient->upload(
                    $bucket = $bucket,
                    $key = $uniName,
                    $body = fopen($fileInfo['tmp_name'], 'rb')
                );
                $Location = ($result->toArray()['Location']);

                # 可以直接通过$result读出返回结果
                // echo ($result['ETag']);
            } catch (\Exception $e) {
                echo($e);
            }


// 	return array(
// 		'newName'=>$destination,
// 		'size'=>$fileInfo['size'],
// 		'type'=>$fileInfo['type']
// 	);
//返回文件的路径
        //return $destination;
//        return json_encode(['code'=>200,'path'=>'/'.$Location]);
        //腾讯云
        return json_encode(['code'=>200,'path'=>$Location]);
    }
}