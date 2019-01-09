<?php
$home_config = [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
	//默认错误跳转对应的模板文件
	'dispatch_error_tmpl' => 'public:dispatch_jump',
	//默认成功跳转对应的模板文件
	'dispatch_success_tmpl' => 'public:dispatch_jump', 
	'TMPL_CACHE_ON' => false,
	'TMPL_CACHE_ON' => false,//禁止模板编译缓存 
	'HTML_CACHE_ON' => false,//禁止静态缓存 
];

$html_config = include_once 'html.php';
return array_merge($home_config,$html_config);
?>