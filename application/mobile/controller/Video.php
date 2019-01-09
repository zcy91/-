<?php

namespace app\mobile\controller;
class Video extends MobileBase {
    public function index(){
        $article_id = I('article_id/d',38);
    	$article = D('video')->where("article_id", $article_id)->find();
    	$this->assign('article',$article);
        return $this->fetch();
    }


    /**
     * 文章内列表页
     */
    public function articleList(){
        $list = M('Article')->where("cat_id IN(1,2,3,4,5,6,7)")->select();
        $this->assign('list',$list);
        return $this->fetch();
    }
    /**
     * 文章内容页
     */
    public function article(){
    	$article_id = I('article_id/d',1);
        M('video')->where("article_id", $article_id)->setInc('click'); // 统计点击数
    	$article = D('video')->alias('a')->join("__VIDEO_CAT__ c","a.cat_id = c.cat_id",'LEFT')->join("__VIDEO_DETAIL__ d","d.video_id = a.article_id",'LEFT')->where("article_id", $article_id)->order("mulusort asc")->select();
    	$this->assign('myarticle',$article);
    	//var_dump($article);die;
        return $this->fetch();
    }
}