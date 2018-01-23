<?php
/**
 * ThinkPHP [ WE CAN DO IT JUST THINK ]
 +----------------------------------------------------------------------
 * * Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
 * 网站地址: http://www.imshop.cn
 +----------------------------------------------------------------------
 * 
 * 
 +----------------------------------------------------------------------
 * $Author: IT宇宙人 2015-08-10 $
 *
 */ 
 
namespace app\home\controller; 
use app\home\logic\CartLogic;
use app\home\model\Pickup;
use app\home\model\UserAddress;
use think\Controller;
use think\Db;
class Cart extends Base {
    
    public $cartLogic; // 购物车逻辑操作类
    public $user_id = 0;
    public $user = array();    
    /**
     * 初始化函数
     */
    public function _initialize() {
        parent::_initialize();
        $this->cartLogic = new CartLogic();
        if(session('?user'))
        {
        	$user = session('user');
                $user = M('users')->where("user_id", $user['user_id'])->find();
                session('user',$user);  //覆盖session 中的 user
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
                // 给用户计算会员价 登录前后不一样
                if($user){
                    $user[discount] = (empty($user[discount])) ? 1 : $user[discount];
                    Db::execute("update `__PREFIX__cart` set member_goods_price = goods_price * {$user[discount]} where (user_id ={$user[user_id]} or session_id = '{$this->session_id}') and prom_type = 0");
                }
        }                        
    }

    public function cart(){
        return $this->fetch();
    }
    
    public function index(){
    	return $this->fetch('cart');
    }

    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        
        
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $goods_spec = I("goods_spec/a",array()); // 商品规格 
        $sql =  'select a.*,b.* from tb_order as a, tb_order_goods as b where a.order_id=b.order_id AND user_id='.$this->user_id.' AND goods_id='.$goods_id;
                 $orders = Db::query($sql); 
                 $flash = M('FlashSale')->where(array('goods_id'=>$goods_id))->field('buy_limit')->find();
                 $count = count($orders);
                 if ($count>=$flash['buy_limit']&&$flash) {
                        $result['status'] = -1;
                        $result['msg'] = '该商品限购'.$flash['buy_limit'].'件您已经买过'.$count.'件';
                                 }  
                   if ($result) {
                                   exit(json_encode($result)); 
                                 }                                      
        $result = $this->cartLogic->addCart($goods_id, $goods_num, $goods_spec,$this->session_id,$this->user_id); // 将商品加入购物车   
            
        exit(json_encode($result));       
    }
    
    /**
     * ajax 删除购物车的商品
     */
    public function ajaxDelCart()
    {       
        $ids = I("ids"); // 商品 ids        
        $result = M("Cart")->where("id", "in", $ids)->delete(); // 删除id为5的用户数据
        $return_arr = array('status'=>1,'msg'=>'删除成功','result'=>''); // 返回结果状态       
        exit(json_encode($return_arr));
    }
    
    
    /*
     * ajax 请求获取购物车列表
     */
    public function ajaxCartList()
    {
        $post_goods_num = I("goods_num/a",array()); // goods_num 购物车商品数量
        $post_cart_select = I("cart_select/a",array()); // 购物车选中状态
        $where['session_id'] = $this->session_id;// 默认按照 session_id 查询

        // 如果这个用户已经等了则按照用户id查询
        if($this->user_id){
            unset($where);
            $where['user_id'] = $this->user_id;
        }
        $_SESSION['shop_id']?$where['shop_id']=$_SESSION['shop_id']:$where['shop_id']=0;

        $cartList = M('Cart')->where($where)->getField("id,goods_num,selected,prom_type,prom_id");
            // var_dump($cartList);die();
        if($post_goods_num)
        {
            // 修改购物车数量 和勾选状态
            foreach($post_goods_num as $key => $val)
            {   
                $data['goods_num'] = $val < 1 ? 1 : $val;
                
                if($cartList[$key]['prom_type'] == 1) //限时抢购 不能超过购买数量
                {
                    $flash_sale = M('flash_sale')->where("id", $cartList[$key]['prom_id'])->find();
                    $data['goods_num'] = $data['goods_num'] > $flash_sale['buy_limit'] ? $flash_sale['buy_limit'] : $data['goods_num'];
                }
                
                $data['selected'] = $post_cart_select[$key] ? 1 : 0 ;                               
                if(($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected'])) 
                    M('Cart')->where("id", $key)->save($data);
            }
            $this->assign('select_all', input('post.select_all')); // 全选框
        }
             
        $result = $this->cartLogic->cartList($this->user, $this->session_id,1,1); // 选中的商品        
        if(empty($result['total_price']))
            $result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0);
        
        $this->assign('cartList', $result['cartList']); // 购物车的商品 

        $this->assign('total_price', $result['total_price']); // 总计
        return $this->fetch('ajax_cart_list');
    }
    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {   

        
        if($this->user_id == 0)
            $this->error('请先登陆',U('Home/User/login'));
        
        if($this->cartLogic->cart_count($this->user_id,1) == 0 ) 
            $this->error ('你的购物车没有选中商品','Cart/cart');
        
        $result = $this->cartLogic->cartList($this->user, $this->session_id,1,1); // 获取购物车商品        
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->cache(true,TPSHOP_CACHE_TIME)->select();// 物流公司                
        
        //$Model = new \think\Model(); // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的               
        $sql = "select c1.name,c1.money,c1.condition, c2.* from __PREFIX__coupon as c1 inner join __PREFIX__coupon_list as c2  on c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0  where c2.uid = :user_id  and ".time()." < c1.use_end_time and c1.condition <= :total_fee";
        
              // $result['total_price']['total_fee'] = 0;
              //  foreach ($result['cartList'] as $key=>$value) {
              //    $flash = M('FlashSale')->where(array('goods_id'=>$value['goods_id']))->field('buy_limit')->find();
              //    $sq =  'select a.*,b.* from tb_order as a, tb_order_goods as b where a.order_id=b.order_id AND user_id='.$value['user_id'].' AND goods_id='.$value['goods_id'];
              //    $orders = Db::query($sq);
              //   $count = count($orders);
              //   $cha = $flash['buy_limit']-$count;
              //  $cha=0;
              //    if ($cha<=0 && $flash) {
              //       $result['cartList'][$key]['goods_num'] = 0;
              //        $result['cartList'][$key]['goods_fee'] = $value['goods_price']*$result['cartList'][$key]['goods_num'];
              //        unset($result['cartList'][$key]);
              //    }elseif($cha>0&&$cha<$value['goods_num']){
              //       $result['cartList'][$key]['goods_num'] = $cha;
              //       $result['cartList'][$key]['goods_fee'] = $value['goods_price']*$result['cartList'][$key]['goods_num'];
              //    }elseif ($cha>0&&$cha>$value['goods_num']) {
              //       $result['cartList'][$key]['goods_num'] = $value['goods_num'];
              //       $result['cartList'][$key]['goods_fee'] = $value['goods_price']*$result['cartList'][$key]['goods_num'];
              //    }
              //  $result['total_price']['total_fee']+= $result['cartList'][$key]['goods_fee'];
               
              //  }
            
          $couponList = Db::query($sql,['user_id'=>$this->user_id,'total_fee'=>$result['total_price']['total_fee']]);      
        $this->assign('couponList', $couponList); // 优惠券列表
        $this->assign('shippingList', $shippingList); // 物流公司
       
        $cartlist = $result['cartList'];
        $this->assign('cartList', $cartlist); // 购物车的商品 
        // var_dump($result['total_price']);die();               
        $this->assign('total_price', $result['total_price']); // 总计

               //判断购物车中是否有养护产品
    
         $xuni = 0;
        foreach ($result['cartList'] as $key => $value) {
            if ($value['selected']==1) {
                $cid = M('Goods')->where(array('goods_id'=>$value['goods_id']))->find();
            if ($this->xuni($cid['cat_id'])) {
              $xuni = 1;
            }
          }
        }
        $this->assign('xuni',$xuni);
                                    
        return $this->fetch();
    }
       //判断是否是虚拟产品
    public function xuni($cid)
    {
        // $cid=$goods['cat_id'];
        $cat = M('GoodsCategory')->where(array('id'=>$cid))->find();

        $array = array();
        $array[] = $cid;
        while ( $cat['parent_id'] != 0) {
            $array[] = $cat['parent_id'];
            $cat = M('GoodsCategory')->where(array('id'=>$cat['parent_id']))->find();
        }
         if (in_array('165',$array)) {
            return true;
        }else{
            return false; 
        }
        
    }
    /*
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress(){
        $address_list = M('UserAddress')->where(['user_id'=>$this->user_id,'is_pickup'=>0])->select();
        if($address_list){
        	$area_id = array();
        	foreach ($address_list as $val){
        		$area_id[] = $val['province'];
                        $area_id[] = $val['city'];
                        $area_id[] = $val['district'];
                        $area_id[] = $val['twon'];                        
        	}    
                $area_id = array_filter($area_id);
        	$area_id = implode(',', $area_id);
        	$regionList = M('region')->where("id", "in", $area_id)->getField('id,name');
        	$this->assign('regionList', $regionList);
        }
        $address_where['is_default'] = 1;
        $c = M('UserAddress')->where(['user_id'=>$this->user_id,'is_default'=>1,'is_pickup'=>0])->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }

    public function test(){
        $user_id = 18991;
        echo crc32($user_id);
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 获取自提点信息
     */
    public function ajaxPickup()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        if (empty($province_id) || empty($city_id) || empty($district_id)) {
            exit("<script>alert('参数错误');</script>");
        }
        $user_address = new UserAddress();
        $address_list = $user_address->getUserPickup($this->user_id);
        $pickup = new Pickup();
        $pickup_list = $pickup->getPickupItemByPCD($province_id, $city_id, $district_id);
        $this->assign('pickup_list', $pickup_list);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_pickup');
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function replace_pickup()
    {
        $province_id = I('get.province_id/d');
        $city_id = I('get.city_id/d');
        $district_id = I('get.district_id/d');
        $region_model = M('region');
        $call_back = I('get.call_back');
        if (IS_POST) {
            echo "<script>parent.{$call_back}('success');</script>";
            exit(); // 成功
        }
        $address = array('province' => $province_id, 'city' => $city_id, 'district' => $district_id);
        $p = $region_model->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = $region_model->where(array('parent_id' => $province_id, 'level' => 2))->select();
        $d = $region_model->where(array('parent_id' => $city_id, 'level' => 3))->select();
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        $this->assign('call_back', $call_back);
        return $this->fetch();
    }

    /**
     * @author dyr
     * @time 2016.08.22
     * 更换自提点
     */
    public function ajax_PickupPoint()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        $pick_up_model = new Pickup();
        $pick_up_list = $pick_up_model->getPickupListByPCD($province_id,$city_id,$district_id);
        exit(json_encode($pick_up_list));
    }


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){

                                
        if($this->user_id == 0)
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        
        $address_id = I("address_id/d"); //  收货地址id
        $shipping_code =  I("shipping_code"); //  物流编号        
        $invoice_title = I('invoice_title'); // 发票
        $coupon_id =  I("coupon_id/d"); //  优惠券id
        $couponCode =  I("couponCode"); //  优惠券代码
        $pay_points =  I("pay_points/d",0); //  使用积分
        $user_money =  I("user_money/f",0); //  使用余额        
        $user_money = $user_money ? $user_money : 0;

        if($this->cartLogic->cart_count($this->user_id,1) == 0 ) exit(json_encode(array('status'=>-2,'msg'=>'你的购物车没有选中商品','result'=>null))); // 返回结果状态
        if(!$address_id) exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
        if(!$shipping_code) exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
		
		$address = M('UserAddress')->where("address_id", $address_id)->find();
    $where = ['user_id'=>$this->user_id,'selected'=>1];
    $_SESSION['shop_id']?$where['shop_id']=$_SESSION['shop_id']:$where['shop_id']=0;

		$order_goods = M('cart')->where($where)->select();
        $result = calculate_price($this->user_id,$order_goods,$shipping_code,0,$address[province],$address[city],$address[district],$pay_points,$user_money,$coupon_id,$couponCode);
		if($result['status'] < 0)
			exit(json_encode($result));      	
	// 订单满额优惠活动		                
        $order_prom = get_order_promotion($result['result']['order_amount']);
        $result['result']['order_amount'] = $order_prom['order_amount'] ;
        $result['result']['order_prom_id'] = $order_prom['order_prom_id'] ;
        $result['result']['order_prom_amount'] = $order_prom['order_prom_amount'] ;
        
        $car_price = array(
            'postFee'      => $result['result']['shipping_price'], // 物流费
            'couponFee'    => $result['result']['coupon_price'], // 优惠券            
            'balance'      => $result['result']['user_money'], // 使用用户余额
            'pointsFee'    => $result['result']['integral_money'], // 积分支付            
            'payables'     => number_format($result['result']['order_amount'], 2, '.', ''), // 应付金额
            'goodsFee'     => $result['result']['goods_price'],// 商品价格            
            'order_prom_id' => $result['result']['order_prom_id'], // 订单优惠活动id
            'order_prom_amount' => $result['result']['order_prom_amount'], // 订单优惠活动优惠了多少钱
        );
       
        // 提交订单        
        if($_REQUEST['act'] == 'submit_order')
        {  
            if(empty($coupon_id) && !empty($couponCode))
               $coupon_id = M('CouponList')->where("code", $couponCode)->getField('id');
            $result = $this->cartLogic->addOrder($this->user_id,$address_id,$shipping_code,$invoice_title,$coupon_id,$car_price); // 添加订单                        
            exit(json_encode($result));            
        }
            $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
            exit(json_encode($return_arr));           
    }	
    /**
     * ajax 获取订单商品价格 或者提交 订单
	 * 已经用心方法 这个方法 cart9  准备作废
     */
   
    /*
     * 订单支付页面
     */
    public function cart4(){
        
        $order_id = I('order_id/d');
        $order = M('Order')->where("order_id", $order_id)->find();
        
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){            
            $order_detail_url = U("Home/User/order_detail",array('id'=>$order_id));
            header("Location: $order_detail_url");
            exit;
        }
        //如果是预售订单，支付尾款
        if($order['pay_status'] == 2 && $order['order_prom_type'] == 4){
            $pre_sell_info = M('goods_activity')->where(array('act_id'=>$order['order_prom_id']))->find();
            $pre_sell_info = array_merge($pre_sell_info,unserialize($pre_sell_info['ext_info']));
            if($pre_sell_info['retainage_start'] > time()){
                $this->error('还未到支付尾款时间'.date('Y-m-d H:i:s',$pre_sell_info['retainage_start']));
            }
            if($pre_sell_info['retainage_end'] < time()){
                $this->error('对不起，该预售商品已过尾款支付时间'.date('Y-m-d H:i:s',$pre_sell_info['retainage_start']));
            }
        }
        $payment_where = array(
            'type'=>'payment',
            'status'=>1,
            'scene'=>array('in',array(0,2))
        );
        if($order['order_prom_type'] == 4){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        
        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);            
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);        
            }                
        }                
        
        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片        
        $this->assign('paymentList',$paymentList);        
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);        
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));

        return $this->fetch();
    }
 
    
    //ajax 请求购物车列表
    public function header_cart_list()
    {
    	$cart_result = $this->cartLogic->cartList($this->user, $this->session_id,0,1);
    	if(empty($cart_result['total_price']))
    		$cart_result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0);
    	
    	$this->assign('cartList', $cart_result['cartList']); // 购物车的商品
    	$this->assign('cart_total_price', $cart_result['total_price']); // 总计
        $template = I('template','header_cart_list');    	 
        return $this->fetch($template);		 
    }

    /**
     * 预售商品下单流程
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        if(empty($act_id)){
            $this->error('没有选择需要购买商品');
        }
        if(empty($goods_num)){
            $this->error('购买商品数量不能为0', U('Home/Activity/pre_sell', array('act_id' => $act_id)));
        }
        if($this->user_id == 0){
            $this->error('请先登陆');
        }
        $pre_sell_info = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        if(empty($pre_sell_info)){
            $this->error('商品不存在或已下架',U('Home/Activity/pre_sell_list'));
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];
            $this->error('预售商品库存不足，还剩下' . $buy_num . '件', U('Home/Activity/pre_sell', array('id' => $act_id)));
        }
        $pre_count_info = D('goods_activity')->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
        $pre_sell_price['cut_price'] = D('goods_activity')->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
        $pre_sell_price['goods_num'] = $goods_num;
        $pre_sell_price['deposit_price'] = floatval($pre_sell_info['deposit']);
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $invoice_title = I('invoice_title'); // 发票
            $shipping_code =  I("shipping_code"); //  物流编号
            $address_id = I("address_id/d"); //  收货地址id
            if(empty($address_id)){
                exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
            }
            if(empty($shipping_code)){
                exit(json_encode(array('status'=>-4,'msg'=>'请选择物流信息','result'=>null))); // 返回结果状态
            }
            $cart_logic = new CartLogic();
            $result = $cart_logic->addPreSellOrder($this->user_id, $address_id, $shipping_code, $invoice_title, $act_id, $pre_sell_price); // 添加订单
            exit(json_encode($result));
        }
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->select();// 物流公司
        $this->assign('pre_sell_info', $pre_sell_info);// 购物车的预售商品
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('pre_sell_price',$pre_sell_price);
        return $this->fetch();
    }


    public function map()
    {

        $ip = getIP();
        $content = file_get_contents("http://api.map.baidu.com/location/ip?ak=NjnIbhfMbZ0weXzGXbpBbqB78ozUKM8f&ip={$ip}&coor=bd09ll");
         $json = json_decode($content);
         $arr = array('lon'=>$json->{'content'}->{'point'}->{'x'},'lat'=>$json->{'content'}->{'point'}->{'y'}, $json->{'content'}->{'address'});
        $lat = $arr['lat'];
        $lon =$arr['lon'];
        $province = $json->{'content'}->{'address_detail'}->{'province'};
        // $province = '江苏省';
        $city = $json->{'content'}->{'address_detail'}->{'city'};
       $where = array();

        if (!empty($_POST['province'])&&isset($_POST['province'])) {
          $p = M('Area')->where(array('id'=>$_POST['province']))->find();
          
        }else{
          $province =  substr($province,0,strlen($str)-3);
          $p = M('Area')->where(array('name'=>$province))->find();
        

        }
        if ($p) {
             $citys = M('Area')->where(array('pid'=>$p['id'],'type'=>2))->select();
        }
        if (!empty($_POST['city'])&&isset($_POST['city'])) {
          $c =  M('Area')->where(array('id'=>$_POST['city']))->find();
          $city=$c['name'];
          $where['city'] = $c['id'];
        }
        $where['province'] = $p['id'];

       $province = $p['name'];
       $provinces =  M('Area')->where(array('pid'=>1,'type'=>1))->select();
        
        $where['type']='1';
        $where['status']='1';
       if (!empty($_POST['province'])&&isset($_POST['province'])) $where['province'] = $_POST['province'];
        if (!empty($_POST['city'])&&isset($_POST['city'])) $where['city'] = $_POST['city'];
        if (!empty($_POST['shop_type'])&&isset($_POST['shop_type'])) $where['shop_type'] = $_POST['shop_type'];


       if (!empty($_POST['check_num'])&&isset($_POST['check_num'])) {
         $markerArr = M('Admin')->where($where)->field('shop_name,shop_lat,shop_lon,shop_address,shop_logo,check_num')->order('check_num desc')->select();
       }else{
          $markerArr = M('Admin')->where($where)->field('shop_name,shop_lat,shop_lon,shop_address,shop_logo,check_num')->select();
       }
       

        $this->assign('lists',$markerArr);
        $markerArr = json_encode($markerArr);
        $this->assign('markerArr',$markerArr);
        $this->assign('lon',$lon);
        $this->assign('lat',$lat);
       $this->assign('citys',$citys);
       $this->assign('provinces',$provinces);
       $this->assign('province',$province);
       $this->assign('city',$city);

       return $this->fetch();
    }
    public function area()
       {
         if (IS_POST) {
           $pid = $_POST['id'];
           $array = M('Area')->where(array('pid'=>$pid))->select();
           echo json_encode(array('info'=>$array,'status'=>'1'));
         }else{
           exit(json_encode(array('status'=>'0')));
         }
       }


       public function order()
       {
          return $this->fetch();
       }

       public function paySuccess()
       {
         return $this->fetch();
       }
}
