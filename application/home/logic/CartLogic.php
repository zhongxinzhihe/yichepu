<?php
/**
 * ThinkPHP [ WE CAN DO IT JUST THINK ]
 +----------------------------------------------------------------------
 * Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
 * 
 +----------------------------------------------------------------------
 * 
 * 
 +----------------------------------------------------------------------
 * Author: Alince
 * Date: 2015-09-09
 */

namespace app\home\logic;
use think\Model;
use think\Db;
/**
 * 购物车 逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class CartLogic extends Model
{

    
    /**
     * 加入购物车方法
     * @param type $goods_id  商品id
     * @param type $goods_num   商品数量
     * @param type $goods_spec  选择规格 
     * @param type $user_id 用户id
     */
    function addCart($goods_id,$goods_num,$goods_spec,$session_id,$user_id = 0)
    {       
        M('Cart')->where(array('selected'=>1))->save(array('selected'=>0));
        $goods = M('Goods')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->find(); // 找出这个商品        
        $data = array(                    
                    'user_id'         => $user_id,   // 用户id
                    'session_id'      => $session_id,   // sessionid
                    'goods_id'        => $goods_id,   // 商品id
                    'goods_sn'        => $goods['goods_sn'],   // 商品货号
                    'goods_name'      => $goods['goods_name'],   // 商品名称
                    'market_price'    => $goods['market_price'],   // 市场价
                    'goods_price'     => $goods['shop_price'],  // 购买价
                    'member_goods_price' => $goods['shop_price'],  // 会员折扣价 默认为 购买价
                    'goods_num'       => $goods_num, // 购买数量                 
                    'add_time'        => time(), // 加入购物车时间
                    'prom_type'       => $goods['prom_type'],   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                    'prom_id'         => $goods['prom_id'],   // 活动id  
                    'selected'        =>1//默认是选中状态                  
        );           
      $_SESSION['shop_id']?$data['shop_id']=$_SESSION['shop_id']:false;
       // 如果商品购物车已经存在 
      $catr_goods = M('Cart')->where(array('goods_id'=>$goods_id,'selected'=>1))->find();
       if($catr_goods) 
       {          
           // 如果购物车的已有数量加上 这次要购买的数量  大于  库存输  则不再增加数量
            // if(($catr_goods['goods_num'] + $goods_num) > $goods['store_count'])
                // $goods_num = 0;//目前商品没有库存
            $result = M('Cart')->where("id", $catr_goods['id'])->save(  array("goods_num"=> ($catr_goods['goods_num'] + $goods_num)) ); // 数量相加
            $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量 
            setcookie('cn',$cart_count,null,'/');
            return array('status'=>1,'msg'=>'成功加入购物车','result'=>$cart_count);
       }
       else
       {         
             $insert_id = M('Cart')->add($data);
             $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量
             setcookie('cn',$cart_count,null,'/');
             return array('status'=>1,'msg'=>'成功加入购物车','result'=>array('id'=>$insert_id,'cart_count'=>$cart_count));
       }     
            $cart_count = cart_goods_num($user_id,$session_id); // 查找购物车数量 
            return array('status'=>-5,'msg'=>'加入购物车失败','result'=>$cart_count);        
    }
    
    /**
     * 购物车列表 
     * @param type $user   用户
     * @param type $session_id  session_id
     * @param type $selected  是否被用户勾选中的 0 为全部 1为选中  一般没有查询不选中的商品情况
     * $mode 0  返回数组形式  1 直接返回result
     */
    function cartList($user = array() , $session_id = '', $selected = 0,$mode =0)
    {                   
        
        $where = " 1 = 1 ";
        //if($selected != NULL)
        //    $where = " selected = $selected "; // 购物车选中状态
        $bind = array();
        if($user[user_id])// 如果用户已经登录则按照用户id查询
        {
             $where .= " and user_id = $user[user_id] ";
             // 给用户计算会员价 登录前后不一样             
        }           
        else
        {
            $where .= " and session_id = :session_id";
            $bind['session_id'] = $session_id;
            $user[user_id] = 0;
        }
          $_SESSION['shop_id']?$where.=' and shop_id='.$_SESSION['shop_id']: $where.=' and shop_id=0';                     
        $cartList = M('Cart')->where($where)->bind($bind)->select();  // 获取购物车商品

        $anum = $total_price =  $cut_fee = 0;

        foreach ($cartList as $k=>$val){
        	$cartList[$k]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];
        	$cartList[$k]['store_count']  = getGoodNum($val['goods_id'],$val['spec_key']); // 最多可购买的库存数量        	
                $anum += $val['goods_num'];
                
                // 如果要求只计算购物车选中商品的价格 和数量  并且  当前商品没选择 则跳过
                if($selected == 1 && $val['selected'] == 0)
                    continue;
                
                $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price'];                
        	$total_price += $val['goods_num'] * $val['member_goods_price'];
        }

        $total_price = array('total_fee' =>$total_price , 'cut_fee' => $cut_fee,'num'=> $anum,); // 总计        
        setcookie('cn',$anum,null,'/');

        if($mode == 1) return array('cartList' => $cartList, 'total_price' => $total_price);
        return array('status'=>1,'msg'=>'','result'=>array('cartList' =>$cartList, 'total_price' => $total_price));
    }

    /**
     * 计算商品的的运费
     * @param type $shipping_code 物流 编号
     * @param type $province 省份
     * @param type $city 市
     * @param type $district 区
     * @return int
     */
    function cart_freight2($shipping_code, $province, $city, $district, $weight)
    {

        if ($weight == 0) return 0; // 商品没有重量
        if ($shipping_code == '') return 0;
        // 先根据 镇 县 区找 shipping_area_id
        $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$district])->getField('shipping_area_id');
        // 先根据市区找 shipping_area_id
        if ($shipping_area_id == false)
            $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$city])->getField('shipping_area_id');

        // 市区找不到 根据省份找shipping_area_id
        if ($shipping_area_id == false)
            $shipping_area_id = M('AreaRegion')->where("shipping_area_id in (select shipping_area_id from  " . C('database.prefix') . "shipping_area where shipping_code = :shipping_code) and region_id = :region_id")->bind(['shipping_code'=>$shipping_code,'region_id'=>$province])->getField('shipping_area_id');

        // 省份找不到 找默认配置全国的物流费
        if ($shipping_area_id == false) {
            // 如果市和省份都没查到, 就查询 tp_shipping_area 表 is_default = 1 的  表示全国的  select * from `tp_plugin`  select * from  `tp_shipping_area` select * from  `tp_area_region`
            $shipping_area_id = M("ShippingArea")->where(['shipping_code'=>$shipping_code,'is_default'=>1])->getField('shipping_area_id');
        }
        if ($shipping_area_id == false)
            return 0;
        /// 找到了 shipping_area_id  找config
        $shipping_config = M('ShippingArea')->where("shipping_area_id", $shipping_area_id)->getField('config');
        $shipping_config = unserialize($shipping_config);
        $shipping_config['money'] = $shipping_config['money'] ? $shipping_config['money'] : 0;

        // 1000 克以内的 只算个首重费
        if ($weight < $shipping_config['first_weight']) {
            return $shipping_config['money'];
        }
        // 超过 1000 克的计算方法
        $weight = $weight - $shipping_config['first_weight']; // 续重
        $weight = ceil($weight / $shipping_config['second_weight']); // 续重不够取整
        $freight = $shipping_config['money'] + $weight * $shipping_config['add_money']; // 首重 + 续重 * 续重费

        return $freight;
    }
  
    /**
     * 获取用户可以使用的优惠券
     * @param type $user_id  用户id 
     * @param type $coupon_id 优惠券id
     * $mode 0  返回数组形式  1 直接返回result
     */
    public function getCouponMoney($user_id, $coupon_id,$mode)
    {
        if($coupon_id == 0)
        {
            if($mode == 1) return 0;    
            return array('status'=>1,'msg'=>'','result'=>0);            
        }        
        $couponlist = M('CouponList')->where("uid", $user_id)->where('id', $coupon_id)->find(); // 获取用户的优惠券
        if(empty($couponlist)) {
            if($mode == 1) return 0;    
            return array('status'=>1,'msg'=>'','result'=>0);
        }            
        
        $coupon = M('Coupon')->where("id", $couponlist['cid'])->find(); // 获取 优惠券类型表
        $coupon['money'] = $coupon['money'] ? $coupon['money'] : 0;
       
        if($mode == 1) return $coupon['money'];
        return array('status'=>1,'msg'=>'','result'=>$coupon['money']);        
    }
    
    /**
     * 根据优惠券代码获取优惠券金额
     * @param type $couponCode 优惠券代码
     * @param type $order_momey Description 订单金额
     * return -1 优惠券不存在 -2 优惠券已过期 -3 订单金额没达到使用券条件
     */
    public function getCouponMoneyByCode($couponCode,$order_momey)
    {
        $couponlist = M('CouponList')->where("code", $couponCode)->find(); // 获取用户的优惠券
        if(empty($couponlist)) 
            return array('status'=>-9,'msg'=>'优惠券码不存在','result'=>'');
        if($couponlist['order_id'] > 0){
            return array('status'=>-20,'msg'=>'该优惠券已被使用','result'=>'');
        }
        $coupon = M('Coupon')->where("id", $couponlist['cid'])->find(); // 获取优惠券类型表
        if(time() > $coupon['use_end_time'])  
            return array('status'=>-10,'msg'=>'优惠券已经过期','result'=>'');
        if($order_momey < $coupon['condition'])
            return array('status'=>-11,'msg'=>'金额没达到优惠券使用条件','result'=>'');
        if($couponlist['order_id'] > 0)
            return array('status'=>-12,'msg'=>'优惠券已被使用','result'=>'');
        
        return array('status'=>1,'msg'=>'','result'=>$coupon['money']);
    }
    
    /**
     *  添加一个订单
     * @param type $user_id  用户id     
     * @param type $address_id 地址id
     * @param type $shipping_code 物流编号
     * @param type $invoice_title 发票
     * @param type $coupon_id 优惠券id
     * @param type $car_price 各种价格
     * @param type $user_note 用户备注
     * @return type $order_id 返回新增的订单id
     */
    public function addOrder($user_id,$session_id,$address_id=0,$shipping_code=0,$invoice_title='',$coupon_id = 0,$car_price='',$user_note='')
    {
        
        // 仿制灌水 1天只能下 50 单  // select * from `tp_order` where user_id = 1  and order_sn like '20151217%' 
        // $order_count = M('Order')->where("user_id",$user_id)->where('order_sn', 'like', date('Ymd')."%")->count(); // 查找购物车商品总数量
        // if($order_count >= 50) 
        //     return array('status'=>-9,'msg'=>'一天只能下50个订单','result'=>'');            
        
         // 0插入订单 order
        // $address = M('UserAddress')->where("address_id", $address_id)->find();
        // $shipping = M('Plugin')->where("code", $shipping_code)->cache(true,TPSHOP_CACHE_TIME)->find();

        $cartList = M('cart')->where(['user_id'=>$user_id,'selected'=>1,'session_id'=>$session_id])->select();
        $count = count($cartList);
        if($count==0) return array('status'=>0,'msg'=>'请重新选择下单'); 
        $result = new_calculate_price($user_id,$cartList);

        $data = array(
                'order_sn'         => date('YmdHis').rand(1000,9999), // 订单编号
                'user_id'          =>$user_id, // 用户id
                'total_amount'     =>$result['result']['total_amount'],// 订单总额
                'order_amount'     =>$result['result']['order_amount'],//'应付款金额',                
                'add_time'         =>time(), // 下单时间                
                'user_note'        =>$user_note, // 用户下单备注          
        );
        $_SESSION['shop_id']?$data['shop_id']=$_SESSION['shop_id']:$data['shop_id']=0;
        $data['order_id'] = $order_id = M("Order")->insertGetId($data);
        $order = $data;//M('Order')->where("order_id", $order_id)->find();
        if(!$order_id)
            return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);
                
        // 记录订单操作日志
        $action_info = array(
            'order_id'        =>$order_id,
            'action_user'     =>$user_id,            
            'action_note'     => '您提交了订单，请等待系统确认',
            'status_desc'     =>'提交订单', //''
            'log_time'        =>time(),
        );
        M('order_action')->insertGetId($action_info);             
        
        // 1插入order_goods 表
        foreach($cartList as $key => $val)
        {
           $goods = M('goods')->where("goods_id", $val['goods_id'])->cache(true,TPSHOP_CACHE_TIME)->find();
           $data2['order_id']           = $order_id; // 订单id
           $data2['goods_id']           = $val['goods_id']; // 商品id
           $data2['goods_name']         = $val['goods_name']; // 商品名称
           $data2['goods_sn']           = $val['goods_sn']; // 商品货号
           $data2['goods_num']          = $val['goods_num']; // 购买数量
           $data2['market_price']       = $val['market_price']; // 市场价
           $data2['goods_price']        = $val['goods_price']; // 商品价               为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
           $data2['spec_key']           = $val['spec_key']; // 商品规格
           $data2['spec_key_name']      = $val['spec_key_name']; // 商品规格名称
           $data2['member_goods_price'] = $val['member_goods_price']; // 会员折扣价
           $data2['cost_price']         = $goods['cost_price']; // 成本价
           $data2['give_integral']      = $goods['give_integral']; // 购买商品赠送积分         
           $data2['prom_type']          = $val['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
           $data2['prom_id']            = $val['prom_id']; // 活动id
           $data2['is_appoint']            = $goods['is_appoint']; //是否指定消费商家
           $data2['is_ctime']            = $goods['is_ctime']; //是否指定消费时间
           $order_goods_id              = M("OrderGoods")->insertGetId($data2);
           if ($data2['is_appoint']==1) {
                $shops = M('Guseb')->where(array('goods_id'=>$val['goods_id']))->field('shop_id')->select();
               
                if (is_array($shops)) {
                    foreach ($shops as $key => $value) {
                        $map = array();
                        $map['rec_id'] =  $order_goods_id;
                        $map['shop_id'] = $value['shop_id'];
                       $res = M('Oguseb')->add($map);

                    }
                    # code...
                }
               
           }
           if ($data2['is_ctime']==1) {
               $times = M('ConsumeTime')->where(array('goods_id'=>$val['goods_id']))->find();
               unset($times['id']);
               unset($times['goods_id']);
               $times['rec_id'] =  $order_goods_id;
              $result = M('Ogtime')->add($times);
               
           }
           // 扣除商品库存  扣除库存移到 付完款后扣除
           //M('Goods')->where("goods_id = ".$val['goods_id'])->setDec('store_count',$val['goods_num']); // 商品减少库存
        } 
        
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付 
        if($data['order_amount'] == 0)
        {                        
            update_pay_status($order['order_sn']);
        }           
        
        // 4 删除已提交订单商品
        M('Cart')->where(['user_id' => $user_id,'selected' => 1])->delete();
      
        // 5 记录log 日志
        $data4['user_id'] = $user_id;
        // $data4['user_money'] = -$car_price['balance'];
        // $data4['pay_points'] = -($car_price['pointsFee'] * tpCache('shopping.point_rate'));
        $data4['change_time'] = time();
        $data4['desc'] = '下单消费';
        $data4['order_sn'] = $order['order_sn'];
        $data4['order_id'] = $order_id;    
        // 如果使用了积分或者余额才记录
        ($data4['user_money'] || $data4['pay_points']) && M("AccountLog")->add($data4);
        
        // 如果有微信公众号 则推送一条消息到微信
        $user = M('users')->where("user_id", $user_id)->find();
        // if($user['oauth']== 'weixin')
        // {
        //     $wx_user = M('wx_user')->find();
        //     $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
        //     $wx_content = "你刚刚下了一笔订单:{$order['order_sn']} 尽快支付,过期失效!";
        //     $jssdk->push_msg($user['openid'],$wx_content);
        // }
    	//用户下单, 发送短信给商家
    	$res = checkEnableSendSms("3");
    	$sender = tpCache("shop_info.mobile");
    	
    	if($res && $res['status'] ==1 && !empty($sender)){
    		 
    	    $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
    	    $resp = sendSms("3", $sender, $params);
    	} 	
        return array('status'=>1,'msg'=>'提交订单成功','result'=>$order_id); // 返回新增的订单id        
    }
    
    /**
     * 查看购物车的商品数量
     * @param type $user_id
     * $mode 0  返回数组形式  1 直接返回result
     */
    public function cart_count($user_id,$mode = 0){
        $count = M('Cart')->where(['user_id' => $user_id , 'selected' => 1])->count();
        if($mode == 1) return  $count;
        
        return array('status'=>1,'msg'=>'','result'=>$count);         
    }
        
   /**
    * 获取商品团购价
    * 如果商品没有团购活动 则返回 0
    * @param type $attr_id
    * $mode 0  返回数组形式  1 直接返回result
    */
   public function get_group_buy_price($goods_id,$mode=0)
   {
       $group_buy = M('GroupBuy')->where(['goods_id' => $goods_id,'start_time'=>['<=',time()],'end_time'=>['>=',time()]])->find(); // 找出这个商品
       if(empty($group_buy))       
            return 0;
       
        if($mode == 1) return $group_buy['groupbuy_price'];
        return array('status'=>1,'msg'=>'','result'=>$group_buy['groupbuy_price']);       
   }  
   
   /**
    * 用户登录后 需要对购物车 一些操作
    * @param type $session_id
    * @param type $user_id
    */
   public function login_cart_handle($session_id,$user_id)
   {
	   if(empty($session_id) || empty($user_id))
	     return false;
        // 登录后将购物车的商品的 user_id 改为当前登录的id            
        M('cart')->where("session_id", $session_id)->save(array('user_id'=>$user_id));
                
        // 查找购物车两件完全相同的商品
        // $cart_id_arr = DB::query("select id from `__PREFIX__cart` where user_id = $user_id group by  goods_id,spec_key having count(goods_id) > 1");
        // if(!empty($cart_id_arr))
        // {
        //     $cart_id_arr = get_arr_column($cart_id_arr, 'id');
        //     $cart_id_str = implode(',', $cart_id_arr);
        //     M('cart')->delete($cart_id_str); // 删除购物车完全相同的商品
        // }
   }
    /**
     * 添加预售商品订单
     * @param $user_id
     * @param $address_id
     * @param $shipping_code
     * @param $invoice_title
     * @param $act_id
     * @param $pre_sell_price
     * @return array
     */
    public function addPreSellOrder($user_id,$address_id,$shipping_code,$invoice_title,$act_id,$pre_sell_price)
    {
        // 仿制灌水 1天只能下 50 单
        $order_count = M('Order')->where("user_id= $user_id and order_sn like '".date('Ymd')."%'")->count(); // 查找购物车商品总数量
        if($order_count >= 50){
            return array('status'=>-9,'msg'=>'一天只能下50个订单','result'=>'');
        }
        $address = M('UserAddress')->where(array('address_id' => $address_id))->find();
        $shipping = M('Plugin')->where(array('code' => $shipping_code))->find();
        $data = array(
            'order_sn'         => date('YmdHis').rand(1000,9999), // 订单编号
            'user_id'          =>$user_id, // 用户id
            'consignee'        =>$address['consignee'], // 收货人
            'province'         =>$address['province'],//'省份id',
            'city'             =>$address['city'],//'城市id',
            'district'         =>$address['district'],//'县',
            'twon'             =>$address['twon'],// '街道',
            'address'          =>$address['address'],//'详细地址',
            'mobile'           =>$address['mobile'],//'手机',
            'zipcode'          =>$address['zipcode'],//'邮编',
            'email'            =>$address['email'],//'邮箱',
            'shipping_code'    =>$shipping_code,//'物流编号',
            'shipping_name'    =>$shipping['name'], //'物流名称',
            'invoice_title'    =>$invoice_title, //'发票抬头',
            'goods_price'      =>$pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],//'商品价格',
            'total_amount'     =>$pre_sell_price['cut_price'] * $pre_sell_price['goods_num'],// 订单总额
            'add_time'         =>time(), // 下单时间
            'order_prom_type'  => 4,
            'order_prom_id'    => $act_id
        );
        if($pre_sell_price['deposit_price'] == 0){
            //无定金
            $data['order_amount'] = $pre_sell_price['cut_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        }else{
            //有定金
            $data['order_amount'] = $pre_sell_price['deposit_price'] * $pre_sell_price['goods_num'];//'应付款金额',
        }
        $order_id = Db::name('order')->insertGetId($data);
//        M('goods_activity')->where(array('act_id'=>$act_id))->setInc('act_count',$pre_sell_price['goods_num']);
        if($order_id === false){
            return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);
        }
        logOrder($order_id,'您提交了订单，请等待系统确认','提交订单',$user_id);
        $order = M('Order')->where("order_id = $order_id")->find();
        $goods_activity = M('goods_activity')->where(array('act_id'=>$act_id))->find();
        $goods = M('goods')->where(array('goods_id'=>$goods_activity['goods_id']))->find();
        $data2['order_id']           = $order_id; // 订单id
        $data2['goods_id']           = $goods['goods_id']; // 商品id
        $data2['goods_name']         = $goods['goods_name']; // 商品名称
        $data2['goods_sn']           = $goods['goods_sn']; // 商品货号
        $data2['goods_num']          = $pre_sell_price['goods_num']; // 购买数量
        $data2['market_price']       = $goods['market_price']; // 市场价
        $data2['goods_price']        = $goods['shop_price']; // 商品团价
        $data2['cost_price']         = $goods['cost_price']; // 成本价
        $data2['member_goods_price'] = $pre_sell_price['cut_price']; //预售价钱
        $data2['give_integral']      = $goods_activity['integral']; // 购买商品赠送积分
        $data2['prom_type']          = 4; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠 ,4 预售商品
        $data2['prom_id']    = $goods_activity['act_id'];
        Db::name('order_goods')->insert($data2);
        // 如果有微信公众号 则推送一条消息到微信
        $user = M('users')->where("user_id = $user_id")->find();
        if($user['oauth']== 'weixin')
        {
            $wx_user = M('wx_user')->find();
            $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
            $wx_content = "你刚刚下了一笔预售订单:{$order['order_sn']} 尽快支付,过期失效!";
            $jssdk->push_msg($user['openid'],$wx_content);
        }
        return array('status'=>1,'msg'=>'提交订单成功','result'=>$order_id); // 返回新增的订单id
    }
}