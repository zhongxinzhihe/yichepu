<?php
/**
 * ThinkPHP [ WE CAN DO IT JUST THINK ]
 +----------------------------------------------------------------------
 * * Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
 * 
 +----------------------------------------------------------------------
 * 
 * 
 +----------------------------------------------------------------------
 * $Author: 
 */
namespace app\mobile\controller;
use app\home\logic\UsersLogic;
use Think\Db;
class Index extends MobileBase {

    public function index(){ 

        //轮播
        $banners = M('Ad')->where(array('enabled'=>1))->order('orderby DESC')->select();

       $this->assign('banners',$banners);

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
        $arr = glob("D:/wamp/www/svn_imshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_imshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
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
    
    public function ajaxGetMore(){
    	$p = I('p/d',1);
        $where['is_recommend']=1;
        $where['is_on_sale']=1;
        $_SESSION['shop_id']?$where['shop_id']=$_SESSION['shop_id']:$where['shop_id']=0;
    	$favourite_goods = M('goods')->where($where)->order('goods_id DESC')->page($p,10)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
    	$this->assign('favourite_goods',$favourite_goods);
    	return $this->fetch();
    }

    public function test()
    {
        return $this->fetch();
    }

    public function pop()
    {
      return $this->fetch();
    }
}