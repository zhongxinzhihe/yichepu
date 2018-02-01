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
 * $Author: Alince 2015-08-10 $
 */
namespace app\mobile\controller;
use think\AjaxPage;
use think\Page;
use think\Db;
class Goods extends MobileBase {
    public function index(){
        return $this->fetch();
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 商品列表页
     */
    public function goodsList(){
    	$filter_param = array(); // 帅选数组
        $shop_id = I('shop_id');
    	$id = I('id/d',1); // 当前分类id
        $q = urldecode(trim(I('q',''))); // 关键字搜索
    	$cat_id_arr = getCatGrandson ($id);
        $where = array();
        $where['del_status']=0;
        $where['is_on_sale']=1;
        $where['cat_id']=array('in',implode(',', $cat_id_arr));
        if(!empty($q)) $where['goods_name']=array('like','%'.$q.'%');
        if($_SESSION['shop_type']==1) $where['shop_id']=$_SESSION['shop_id'];
        $filter_goods_id = M('goods')->where($where)->getField("goods_id",true); 
    	
    	$count = count($filter_goods_id);
    	$page = new Page($count,4);
    	if($count > 0)
    	{
    		$goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->limit($page->firstRow.','.$page->listRows)->order('sort desc,goods_id desc')->select();
    	}
    	$this->assign('goods_list',$goods_list);
    	$this->assign('cat_id',$id);
        $this->assign('q',$q);
    	$this->assign('page',$page);// 赋值分页输出
    	C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList() {
        $where =' WHERE 1=1';

        $cat_id  = I("id/d",0); // 所选择的商品分类id
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " AND cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count,10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit ".$page->firstRow.','.$page->listRows;
        $list = DB::query("select *  from __PREFIX__goods $where $order $limit");

        $this->assign('lists',$list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
       exit($html);
    }

    /**
     * 商品详情页
     */
    public function goodsInfo(){
       if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
        }
         // update_pay_status('201706261054283664');  
        C('TOKEN_ON',true);        
        $goodsLogic = new \app\home\logic\GoodsLogic();
        $goods_id = I("get.id/d");
        $where['del_status']=0;
        $where['goods_id'] = $goods_id;
        $goods = M('Goods')->where($where)->find();
        if(empty($goods)){
            $this->error('此商品不存在或者已下架');
        }
        //判断是不是分销的商品
        $share_uid = I('get.share_uid');
        if(isset($share_uid)&&!empty($share_uid)&&is_numeric($share_uid)&&is_array($share)){
            
          $_SESSION['share_uid'] = $share_uid;
          $_SESSION['share_gid'] = $goods_id;
          $user_id = $_SESSION['user']['user_id'];
          $views =json_decode($share['views']) ;
          if(!is_array($views)) $views = array();
          $views[] = $user_id;
          $share['views'] = json_encode($views);
          $share['view_num'] = $share['view_num']+1;
          $scan['share_id'] = $share['id'];
          $scan['uid'] = $user_id;
          $scanData = M('scan_share')->where($scan)->find();

          if($scanData){
            $num = $scanData['scan_num']+1;
            M('scan_share')->where(array('id'=>$scanData['id']))->save(array('scan_num'=>$num));
           
          }else{
            $scan['add_time'] = time();
            if(!is_null($scan['uid'])){
             M('scan_share')->add($scan);
            }
          } 

          $this->assign('first_share',$share_uid);
        }
        $goods_images_list = M('GoodsImages')->where("goods_id", $goods_id)->select(); // 商品 图册
        $cat_id = $goods['cat_id'];
        $this->assign('cases',$cases);//购车方案  
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('goods',$goods);
        $this->assign('infos',$infos);
        return $this->fetch();
    }

    /**
     * 商品详情页
     */
    public function detail(){
        //  form表单提交
        C('TOKEN_ON',true);
        $goods_id = I("get.id/d");
        $goods = M('Goods')->where("goods_id", $goods_id)->find();
        $this->assign('goods',$goods);
        return $this->fetch();
    }

    /*
     * 商品评论
     */
    public function doComment(){
        if (IS_POST) {
            $goods_id = $_POST['goods_id'];
        }
        
        return $this->fetch();
    }
    /*
     * 商品评论
     */
    public function comment(){
        $goods_id = I("goods_id/d",0);
        $this->assign('goods_id',$goods_id);
        return $this->fetch();
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment(){        
        $goods_id = I("goods_id/d",0);

        $commentType = I('commentType',0); // 0 全部 1好评 2 中评 3差评
        $where['is_show']=1;
        $where['goods_id']=$goods_id;
        if($commentType!=0){
        	$where['ctype'] = $commentType;
        }
        $count = M('Comment')->where($where)->count();
		$page_count = 5;
        $page = new AjaxPage($count,$page_count);
        $list = M('Comment')
				->alias('c')
				->join('__USERS__ u','u.user_id = c.user_id','LEFT')
				->where($where)
				->order("add_time desc")
				->limit($page->firstRow.','.$page->listRows)
				->select();

		$replyList = M('Comment')->where(['goods_id'=>$goods_id,'parent_id'=>['>',0]])->order("add_time desc")->select();

        foreach($list as $k => $v){
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片            
        }
		$this->assign('goods_id',$goods_id);//商品id
        $this->assign('commentlist',$list);// 商品评论
		$this->assign('commentType',$commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('replyList',$replyList); // 管理员回复
		$this->assign('count', $count);//总条数
		$this->assign('page_count', $page_count);//页数
		$this->assign('current_count', $page_count*I('p'));//当前条
		$this->assign('p', I('p'));//页数
        return $this->fetch();
    }
    
    /*
     * 获取商品规格
     */
    public function goodsAttr(){
        $goods_id = I("get.goods_id/d",0);
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);
        return $this->fetch();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $rank= I('get.rank');
        //以兑换量（购买量）排序
        if($rank == 'num'){
            $ranktype = 'sales_sum';
            $order = 'desc';
        }
        //以需要积分排序
        if($rank == 'integral'){
            $ranktype = 'exchange_integral';
            $order = 'desc';
        }
        $point_rate = tpCache('shopping.point_rate');
        $goods_where = array(
            'is_on_sale' => 1,  //是否上架
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));

        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandson($cat_id));
        }
        //我能兑换
        $user_id = cookie('user_id');
        if (!empty($user_id)) {
            //获取用户积分
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }
        $goods_where['exchange_integral'] =  $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->order($ranktype ,$order)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('totalPages',$page->totalPages);//总页数
        if(IS_AJAX){
            return $this->fetch('ajaxIntegralMall'); //获取更多
        }
        return $this->fetch();
    }

     /**
     * 商品搜索列表页
     */
    public function search(){
        $shop_id = I('shop_id',0);
        if($shop_id==0){$_SESSION['shop_id']?$shop_id=$_SESSION['shop_id']:false;}
        
    	$filter_param = array(); // 帅选数组
    	$id = I('get.id/d',0); // 当前分类id
    	$brand_id = I('brand_id/d',0);    	    	
    	$sort = I('sort','goods_id'); // 排序
    	$sort_asc = I('sort_asc','asc'); // 排序
    	$price = I('price',''); // 价钱
    	$start_price = trim(I('start_price','0')); // 输入框价钱
    	$end_price = trim(I('end_price','0')); // 输入框价钱
    	if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱   	 
    	$filter_param['id'] = $id; //加入帅选条件中
    	$brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中    	    	
    	$price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $shop_id  && ($filter_param['shop_id'] = $shop_id); //加入帅选条件中
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        if($qtype){
            $this->assign('type',$qtype);
        	$filter_param['qtype'] = $qtype;
        	$where[$qtype] = 1;
        }

        if($q) $where['goods_name'] = array('like','%'.$q.'%');
        
    	$goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
        $_SESSION['shop_id']?$where['shop_id']=$_SESSION['shop_id']:false;
        $where['del_status']=0;
        // $where['is_vip']=0;
    	$filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id",true);

    	// 过滤帅选的结果集里面找商品
    	if($brand_id || $price)// 品牌或者价格
    	{
    		$goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
    		$filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
    	}

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->get_filter_selected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

    	$filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
    	$filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
    	$filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search',1); // 获取指定分类下的帅选品牌    	 
    
    	$count = count($filter_goods_id);
    	$page = new Page($count,12);

    	if($count > 0)
    	{
    		$goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();

    		$filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
    		if($filter_goods_id2)
    			$goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
    	}

    	$goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
    	$this->assign('goods_list',$goods_list);
    	$this->assign('goods_category',$goods_category);
    	$this->assign('goods_images',$goods_images);  // 相册图片
    	$this->assign('filter_menu',$filter_menu);  // 帅选菜单     
    	$this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
    	$this->assign('filter_price',$filter_price);// 帅选的价格期间    	
    	$this->assign('filter_param',$filter_param); // 帅选条件    	
    	$this->assign('page',$page);// 赋值分页输出
    	$this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
         $this->assign('newCates',getCatGrandson (138));// xinche
        $this->assign('usedCates',getCatGrandson (164));// xinche
        $this->assign('curingCates',getCatGrandson (165));// xinche
    	C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch('goodsList');
    }

    /**
     * 商品搜索列表页
     */
    public function ajaxSearch()
    {
        return $this->fetch();
    }

    /**
     * 品牌街
     */
    public function brandstreet()
    {
        $getnum = 9;   //取出数量
        $goods=M('goods')->where(array('is_recommend'=>1,'is_on_sale'=>1))->page(1,$getnum)->cache(true,TPSHOP_CACHE_TIME)->select(); //推荐商品
        for($i=0;$i<($getnum/3);$i++){
            //3条记录为一组
            $recommend_goods[] = array_slice($goods,$i*3,3);
        }
        $where = array(
            'is_hot' => 1,  //1为推荐品牌
        );
        $count = M('brand')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count,20);
        $show = $Page->show();// 分页显示输出
        $brand_list = M('brand')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        $this->assign('Page',$show);  //赋值分页输出
        $this->assign('recommend_goods',$recommend_goods);  //品牌列表
        $this->assign('brand_list',$brand_list);            //推荐商品
        if(I('is_ajax')){
            return $this->fetch('ajaxBrandstreet');
        }
        return $this->fetch();
    }

    /**
     * 看相似
     * $author lxl
     * $time 2017-1
     */
//    public function similar(){
//        $good_id = I('id/d','');
//        $now_good = M('goods')->where("goods_id=$good_id")->find();   //当前商品
//        $where_id_list = M('goods')->field('cat_id,spec_type')->where("goods_id=$good_id")->find(); //相似条件，分类id,类型id
//        $count = M('goods')->where($where_id_list) ->count();    //符合条件的总数
//        $pagesize = C('PAGESIZE');  //每页显示数
//        $Page = new Page($count,$pagesize);
//        $goods_list = M('goods')->where($where_id_list) ->limit($Page->firstRow.','.$Page->listRows)->select();  //获取相似商品
//        $this->assign('goods_list',$goods_list);
//        $this->assign('now_good',$now_good);
//        if(I('is_ajax')){
//            return $this->fetch('ajax_similar');
//        }
//        return $this->fetch();
//    }
//    
public function ajaxShare()
{
    if (IS_POST) {
        $shop_id = I('post.shop_id');
        $goods_id = I('post.goods_id');
        $user_id = I('post.user_id');
        $first_share = I('post.first_share','0');
        // $old = M('Wxshare')->where(array('shop_id'=>$shop_id,'goods_id'=>$goods_id,'user_id'=>$user_id))->find();
        if($old) exit(json_encode(array('status'=>1,'msg'=>'您已经分享过')));
        $data = I('post.');
        if($first_share==$user_id){$data['first_share']=0;}
        $data['add_time']=time();
        // if(M('Wxshare')->add($data)) {
        //     if($data['first_share']!=0){
        //        $map['first_id'] = $data['first_share'];
        //        $map['second_id'] = $data['user_id'];
        //        $result = M('share')->where($map)->find();
        //        if (!$result) {
        //            $map['add_time']=time();
        //            M('share')->add($map);
        //        }
        //     }
        //     exit(json_encode(array('status'=>1,'msg'=>'分享成功')));
        // }else{
        //     exit(json_encode(array('status'=>0,'msg'=>'分享失败')));
        // }

    }
}


public function shops()
{
    $q = I('q');
    $map = array();
    $map['type'] = 1;
    $map['check_status'] = 1;
    $map['shop_name'] = array('like','%'.$q.'%');
    $count = M('Admin')->where($map)->count();
    $page = new AjaxPage($count,'10');
    $data = M('Admin')->where($map)->field('admin_id,shop_name,shop_logo')->limit($page->firstRow.','.$page->listRows)->select();
    $this->assign('list',$data);
    $this->assign('q',$q);
    if (IS_AJAX) {
       return $this->fetch('ajaxShops');
    }
    
    return $this->fetch();
}

//商品详情页分类点击
public function infoderent()
{
       $shop_id = I('shop_id',0);
        $filter_param = array(); // 帅选数组
        $id = I('get.id/d',0); // 当前分类id
        $brand_id = I('brand_id/d',0);              
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱       
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中                
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
        $shop_id  && ($filter_param['shop_id'] = $shop_id); //加入帅选条件中
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        if($qtype){
            $this->assign('type',$qtype);
            $filter_param['qtype'] = $qtype;
            $where[$qtype] = 1;
        }
        if($q) $where['goods_name'] = array('like','%'.$q.'%');
        
        $goodsLogic = new \app\home\logic\GoodsLogic(); // 前台商品操作逻辑类
        $_SESSION['shop_id']?$where['shop_id']=$_SESSION['shop_id']:false;
        $filter_goods_id = M('goods')->where($where)->cache(true)->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->get_filter_selected($sel);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search',1); // 获取指定分类下的帅选品牌        
        
        $count = count($filter_goods_id);
        $page = new Page($count,12);
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id", "in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list',$goods_list);
        $this->assign('goods_category',$goods_category);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单     
        $this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间      
        $this->assign('filter_param',$filter_param); // 帅选条件        
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch('search');

}

//预约到点=店
public function subscribe()
{
    $goods_id = I('goods_id/d');
    $goods = M('Goods')->where(array('goods_id'=>$goods_id))->find();
    $this->assign('goods',$goods);
    return $this->fetch();
}

}