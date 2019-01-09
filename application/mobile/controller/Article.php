<?php

namespace app\mobile\controller;
class Article extends MobileBase {
    public function index(){
        $article_id = I('article_id/d',38);
    	$article = D('article')->where("article_id", $article_id)->find();
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
        M('article')->where("article_id", $article_id)->setInc('click'); // 统计点击数
    	$article = D('article')->alias('a')->join("__ARTICLE_CAT__ c","a.cat_id = c.cat_id")->where("article_id", $article_id)->find();
    	$this->assign('myarticle',$article);
    	$before_article = M('article') ->where("article_id < $article_id and is_open = 1")->order('article_id DESC')->limit(1)->find();
    	$after_article = M('article') ->where("article_id > $article_id and is_open = 1")->limit(1)->find();
    	$this->assign('before',$before_article);
    	$this->assign('after',$after_article);
//    	var_dump($before_article);
//    	var_dump($after_article);die;
        return $this->fetch();
    }
}