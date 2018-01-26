<?php
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
use app\admin\model\GoodsCategory;

class Goods extends Base {
    
    /**
     *  商品分类列表
     */
    public function categoryList(){ 
        $GoodsLogic = new GoodsLogic(); 

        if ($_SESSION['type']==0)
         {
            $cat_list = $GoodsLogic->goods_cat_list();
         }else{
            $cat_list = $GoodsLogic->goods_cat_list("shop_id=".$_SESSION['admin_id']);
         }              
        
        $this->assign('cat_list',$cat_list);    
        return $this->fetch();
    }
    
    /**
     * 添加修改商品分类
     * 手动拷贝分类正则 ([\u4e00-\u9fa5/\w]+)  ('393','$1'), 
     * select * from tp_goods_category where id = 393
        select * from tp_goods_category where parent_id = 393
        update tp_goods_category  set parent_id_path 0= concat_ws('_','0_76_393',id),`level` = 3 where parent_id = 393
        insert into `tp_goods_category` (`parent_id`,`name`) values 
        ('393','时尚饰品'),
     */
    public function addEditCategory(){
        $GoodsLogic=new GoodsLogic();
        if (IS_GET) {
            $where=array();
            $where['id']=I('GET.id',0);
            $goods_category_info=D('GoodsCategory')
                ->where($where)
                ->find();
            $level_cat=$GoodsLogic
                ->find_parent_cat($goods_category_info['id']);//获取分类默认选中的下拉框
            unset($where['id']);
            $where['parent_id']=0;
            $cat_list=M('goods_category')
                ->where($where)
                ->select();//已经改成联动菜单
            $this->assign('level_cat',$level_cat);
            $this->assign('cat_list',$cat_list);
            $this->assign('goods_category_info',$goods_category_info);
            return $this->fetch('_category');
            exit;
        }

        $GoodsCategory=D('GoodsCategory');

        $type=I('id')>0?2:1;//标识自动验证时的 场景 1表示插入 2表示更新

        //ajax提交验证
        if (I('is_ajax') == 1) {
            //数据验证
            $validate=\think\Loader::validate('GoodsCategory');
            if (!$validate->batch()->check(input('post.'))) {
                $error=$validate->getError();
                $error_msg=array_values($error);
                $return_arr=array(
                    'status'=>-1,
                    'msg'=>$error_msg[0],
                    'data'=>$error,
                );
                $this->ajaxReturn($return_arr);
            }else{
                $data=input('post.');
                $GoodsCategory->data($data,true);//收集数据
                $GoodsCategory->parent_id=I('parent_id_1');
                input('parent_id_2')&&($GoodsCategory->parent_id=input('parent_id_2'));
                //编辑判断
                if ($type==2) {
                    $children_where=array(
                        'parent_id_path'=>array('like','%_'.I('id')."_%")
                    );
                    $children=M('goods_category')
                        ->where($children_where)
                        ->max('level');
                    if (I('parent_id_1')) {
                        $map=array();
                        $map['id']=I('parent_id_1');
                        $parent_level=M('goods_category')
                            ->where($map)
                            ->getField('level',false);
                        if (($parent_level+$children)>3) {
                            $return_arr=array(
                                'status'=>-1,
                                'msg'=>'商品分类最多为三级',
                                'data'=>'',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                    if (I('parent_id_2')) {
                        $map=array();
                        $map['id']=I('parent_id_2');
                        $parent_level=M('goods_category')
                            ->where($map)
                            ->getField('level',false);
                        if (($parent_level+$children)>3) {
                            $return_arr=array(
                                'status'=>-1,
                                'msg'=>'商品分类最多为三级',
                                'data'=>'',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                }

                if ($GoodsCategory->id>0&&$GoodsCategory->parent_id==$GoodsCategory->id) {
                    //编辑
                    $return_arr=array(
                        'status'=>-1,
                        'msg'=>'分佣比例不得超过100%',
                        'data'=>'',
                    );
                    $this->ajaxReturn($return_arr);
                }
                if ($type==2) {
                    $GoodsCategory
                        ->isUpdate(true)
                        ->save();//写入数据到数据库
                    $GoodsLogic->refresh_cat(I('id'));
                }
                else{
                    $GoodsCategory->save();//写入数据到数据库
                    $insert_id=$GoodsCategory->getLastInsID();
                    $GoodsLogic->refresh_cat($insert_id);
                }
                $return_arr=array(
                    'status'=>1,
                    'msg'=>'操作成功',
                    'data'=>array('url'=>U('Admin/Goods/categoryList')),
                );
                $this->ajaxReturn($return_arr);
            }
        }

    }     
    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        $id=$this->request->param('id');
        //判断子分类
        $GoodsCategory=M('goods_category');
        $count=$GoodsCategory
            ->where("parent_id = {$id}")
            ->count("id");
        $count>0&&$this->error('该分类下有产品或子分类不得删除！',U('Admin/Goods/categoryList'));
        //删除分类
        DB::name('goods_category')
            ->where('id',$id)
            ->delete();
        $this->success('操作成功！！！',U('Admin/Goods/categoryList'));
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){ 
        //获取数据
        $data=M('Goods');
        //搜索条件
        $key_words=I("key_words");
        $condition=array();
        if ($_SESSION['type']==1) $condition['shop_id']=$_SESSION['admin_id'];
        //模糊搜索
        if (!empty($key_words)) $condition['goods_name']=array('like','%'.$key_words.'%');
        //分页设置
        $count= $data
            ->where($condition)
            ->count();
        $page = new \think\Page($count,10);
        $show = $page->show();
        $list = $data
            ->limit($page->firstRow.','.$page->listRows)
            ->where($condition)
            ->order('goods_id DESC')
            ->select();

        $cat_id = I('cat_id');
        $this->assign('cat_id',$cat_id);

        $this->assign('page',$show);
        $this->assign('goodsList',$list);
        return $this->fetch();
    }                                           

    /**
     * 添加修改商品
     */
    public function addEditGoods()
    {  
        $cat_id=I('get.cat_id');
        $GoodsLogic=new GoodsLogic();
        $Goods=new \app\admin\model\Goods();
        $type=I('goods_id')>0?2:1;//标识自动验证时的场景1表示插入2表示更新

        //ajax提交验证
        if ((I('is_ajax')==1)&&IS_POST) {
            $data=I('post.');
            $data['goods_images'] = array_filter($data['goods_images']);
                $data['op_id'] = $_SESSION['admin_id'];//操作员
                if ($_SESSION['type']==1) $data['shop_id'] = $_SESSION['admin_id'];
                $Goods->data($data,true); // 收集数据
         
                $Goods->on_time = time(); // 上架时间

            I('cat_id_2') && ($Goods->cat_id = I('cat_id_2'));
            I('cat_id_3') && ($Goods->cat_id = I('cat_id_3'));
            I('extend_cat_id_2') && ($Goods->extend_cat_id = I('extend_cat_id_2'));
            I('extend_cat_id_3') && ($Goods->extend_cat_id = I('extend_cat_id_3'));
            $Goods->shipping_area_ids = implode(',',I('shipping_area_ids/a',[]));
            $Goods->shipping_area_ids = $Goods->shipping_area_ids ? $Goods->shipping_area_ids : '';
            $Goods->goods_type = I('goods_type');
            $Goods->spec_type = $Goods->goods_type; 

            if ($type == 2) {
                $goods_id = I('goods_id');
                $Goods->edit_time = time();//添加时间
                $Goods->isUpdate(true)->save(); // 写入数据到数据库                 
                // 修改商品后购物车的商品价格也修改一下
                M('cart')->where("goods_id = $goods_id and spec_key = ''")->save(array(
                        'market_price'=>I('market_price'), //市场价
                        'goods_price'=>I('shop_price'), // 本店价
                        'member_goods_price'=>I('shop_price'), // 会员折扣价                        
                    ));                    
                } else {
                    $Goods->add_time = time();//添加时间
                    $Goods->save(); // 写入数据到数据库                    
                    $goods_id = $insert_id = $Goods->getLastInsID();
                }
            $Goods->afterSave($goods_id);
            $GoodsLogic->saveGoodsAttr($goods_id,$data); // 处理商品 属性
            $GoodsLogic->saveGoodsProgramme($goods_id,$data);//处理商品金融方案
            $GoodsLogic->saveGoodsTags($goods_id,$data);//处理商品服务范围
            $GoodsLogic->saveGoodsUserLevel($goods_id,$data);//处理商品会员的可见度
            $GoodsLogic->savaUsedShops($goods_id,$data);//可用门店
            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => U('admin/Goods/goodsList')),
            );
            exit(json_encode($return_arr));

        }
        $goodsInfo = M('Goods')->where(array('goods_id'=>I('GET.id', 0),'del_status'=>0))->find();
        $level_cat = $GoodsLogic->find_parent_cat($goodsInfo['cat_id']); // 获取分类默认选中的下拉框
        $level_cat2 = $GoodsLogic->find_parent_cat($goodsInfo['extend_cat_id']); // 获取分类默认选中的下拉框
        $where = array();
        $where['parent_id'] = 0;
        $cat_list = M('goods_category')->where($where)->select(); // 已经改成联动菜单
        $map = array();
        $_SESSION['type']==1?$map['shop_id']= $_SESSION['admin_id']:false;
        $goodsType = M("GoodsType")->where($map)->select();
        // $suppliersList = M("suppliers")->select(); 
        $goodsImages = M("GoodsImages")->where('goods_id =' . I('GET.id', 0))->select();
        $programmes = $GoodsLogic->getGoodsProgramme(I('GET.id', 0));
        $goodsTag = M('GoodsTag')->where(array('goods_id'=>I('GET.id', 0)))->field('tag_id')->select();
        $tags = M('Tag')->where(array('del_status'=>0))->select();
        // $userLevel = M('UserLevel')->select();
        $goodsLevel =  M('GoodsLevel')->where(array('goods_id'=>I('GET.id', 0)))->select();
        !empty($goodsInfo['cat_id'])?$cat_id = $goodsInfo['cat_id']:false;
        $gtags = array();
        if (is_array($goodsTag)) {
           foreach ($goodsTag as $key => $tag) {
            $gtags[] = $tag['tag_id'];
            }
        }

        $gLevels = array();
        if (is_array($goodsLevel)) {
           foreach ($goodsLevel as $key => $level) {
            $gLevels[] = $level['level_id'];
            }
        }
        $usedShop = $GoodsLogic->getUsedBusiness(I('GET.id', 0));
        $this->assign('times',$times);
        $this->assign('usedShop',$usedShop);
        $this->assign('goods_shipping_area_ids',$goods_shipping_area_ids);
        $this->assign('shipping_area',$shipping_area);
        $this->assign('plugin_shipping',$plugin_shipping);
        $this->assign('level_cat', $level_cat);
        $this->assign('level_cat2', $level_cat2);
        $this->assign('cat_list', $cat_list);
        $this->assign('goodsType', $goodsType);
        $this->assign('goodsInfo', $goodsInfo);  // 商品详情
        $this->assign('goodsImages', $goodsImages);  // 商品相册
        $this->assign('programmes', $programmes);// 商品金融方案
        $this->assign('cat_id',$cat_id);//当前添加分类的id
        $this->assign('tags',$tags);//标签
        $this->assign('gtags',$gtags);//商品已经添加的标签
        $this->assign('gLevels',$gLevels);//会员等级
        $this->initEditor(); // 编辑器
        return $this->fetch('curingGoods');


    }



    public function ajax_attributes()
     {
        $cat_id = I('cat_id');
        $goodsAttribute =M('Attribute')->alias('a')
          ->field('a.*,av.id AS attr_val_id,av.attr_val ')
          ->join('__ATTR_VAL__ av','av.attribute_id = a.id','LEFT')
          ->where(array('a.cat_id'=>$cat_id,'del_status'=>0,'av.goods_id'=>I('goods_id')))
          ->select();
          $allAtrributes = M('Attribute')->where(array('cat_id'=>I('cat_id')))->select();
          foreach ($goodsAttribute as $k => $val) {
              foreach ($allAtrributes as $key => $value) {
                if ($val['id']==$value['id']) {
                   $allAtrributes[$key]=$val;
                }
              }
          }

          $this->assign('attributes', $allAtrributes);// 商品属性
          return $this->fetch();
     } 

    public function search_goods()
    {
        $GoodsLogic = new GoodsLogic;
        // $_SESSION['type']==1? $brandList = $GoodsLogic->getSortBrands('shop_id='.$_SESSION['admin_id'].' and del_status=0'):$brandList = $GoodsLogic->getSortBrands('del_status=0');
        // $this->assign('brandList', $brandList);
        $_SESSION['type']==1? $categoryList = $GoodsLogic->getSortCategory('shop_id='.$_SESSION['admin_id'].' and del_status=0'):$categoryList = $GoodsLogic->getSortCategory('del_status=0');
        
        $this->assign('categoryList', $categoryList);

        $goods_id = I('goods_id');
        $where = ' is_on_sale = 1 and store_count>0 ';//搜索条件
        if (!empty($goods_id)) {
            $where .= " and goods_id not in ($goods_id) ";
        }
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        if (I('cat_id')) {
            $this->assign('cat_id', I('cat_id'));
            $grandson_ids = getCatGrandson(I('cat_id'));
            $where = " $where  and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        if (I('brand_id')) {
            $this->assign('brand_id', I('brand_id'));
            $where = "$where and brand_id = " . I('brand_id');
        }
        if (!empty($_REQUEST['keywords'])) {
            $this->assign('keywords', I('keywords'));
            $where = "$where and (goods_name like '%" . I('keywords') . "%' or keywords like '%" . I('keywords') . "%')";
        }
        $_SESSION['type']==1?$where.=' and shop_id='.$_SESSION['admin_id']:false;
        $count = M('goods')->where($where)->count();
        $Page = new Page($count, 10);
        $goodsList = M('goods')->where($where)->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();//分页显示输出
        $this->assign('page', $show);//赋值分页输出
        $this->assign('goodsList', $goodsList);
        $this->assign('pager', $Page);//赋值分页输出
        return $this->fetch();
    }


   
    /**
    *
    *每个分类属性列表
    */
     public function catAttributeList(){
        $cat_id = I('get.cat_id');
        $where = array();
        $where['cat_id'] = $cat_id;
        $goodsTypeList = M('GoodsCategory')->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        $this->assign('cat_id',$cat_id);
        return $this->fetch();
     }
    

    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList(){
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = array(); // 搜索条件                        
        // I('type_id')   && $where = "$where and type_id = ".I('type_id') ;   
        if(I('type_id')) $where['cat_id']=I('type_id');
            $where['del_status']=0; 
            $keywords = I('keywords');
            if (!empty($keywords)) {
                      $where['attr_name']=array('like', '%' . $keywords . '%');
                 } 
                 
        // 关键词搜索               
        $model = M('Attribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsAttributeList = $model->where($where)->order('id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $goodsTypeList = M("GoodsType")->getField('id,name');
        $attr_input_type = array(0=>'手工录入',1=>' 从列表中选择',2=>' 多行文本框');
        $this->assign('attr_input_type',$attr_input_type);
        $this->assign('goodsTypeList',$goodsTypeList);        
        $this->assign('goodsAttributeList',$goodsAttributeList);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
    }   
    
    /**
     * 添加修改编辑  商品属性
     */
    public  function addEditGoodsAttribute(){
           $cat_id = I('cat_id');
            

           $type = I('attr_id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        
           if(IS_POST){
              $data=I('post.');
              $data['cat_id'] = $data['type_id'];
              foreach ($data as $key => $value) {
                  $data[$key] = trim($value);
              }
              if($type==1){
                $count = M('Attribute')->where(array('attr_name'=>$data['attr_name'],'cat_id'=>$data['type_id'],'del_status'=>0))->count();

                if($count>0) exit(json_encode(array('status'=>0,'msg'=>'此类型的该属性已存在')));
                
                $data['add_time'] = time();
                $result= M('Attribute')->add($data);

                $allChildren = $this->getChildCates($cat_id);
                foreach ($allChildren as $k => $child) {
                    $data['cat_id'] = $child;
                    M('Attribute')->add($data);
                }

              }else{
                $data['edit_time'] = time();
                $allChildren = $this->getChildCates($cat_id);
                
                $result= M('Attribute')->where(array('id'=>I('attr_id')))->save($data);
              }
              
            if ($result!==false) {
               exit(json_encode(array('status'=>1,'msg'=>'添加成功')));
            }else{
                exit(json_encode(array('status'=>0,'msg'=>'添加失败')));
            }

           }else{
            $data = M('Attribute')->where(array('id'=>I('attr_id'),'del_status'=>0))->find();
           }
           if (!empty($cat_id)&&is_numeric($cat_id)) {
              $data['cat_id'] = $cat_id;
           }
          
           $categoryList = M('GoodsCategory')->select();
           $this->assign('goodsAttribute',$data);
           $this->assign('cat_id',$cat_id);
           $this->assign('categorys',$categoryList);
           return $this->fetch('_goodsAttribute');
    }

    /**
     * 删除商品属性
     */
    public function delGoodsAttribute()
    {
        $id = input('id');
        // 判断 有无商品使用该属性
        $count = M("AttrVal")->where("attribute_id = {$id}")->count("1");
        $count > 0 && $this->error('有商品使用该属性,不得删除!',U('Admin/Goods/goodsAttributeList'));
        // 删除 属性
        M('Attribute')->where("id = {$id}")->save(array('del_status'=>1,'edit_time'=>time()));
        $this->success("操作成功!!!");
    } 

    /**
    *获取该分类下的子分类id
    *
    */ 

    private function getChildCates($cat_id)
     {
        $allChildren = array();
        $secondChildren = M('GoodsCategory')->where(array('parent_id'=>$cat_id))->select();
        foreach ($secondChildren as $key => $second) {
            $allChildren[] = $second['id'];
            $thirdChildren = M('GoodsCategory')->where(array('parent_id'=>$second['id']))->select();
            foreach ($thirdChildren as $k => $third) {
               $allChildren[] = $third['id'];
            }
        }
        return $allChildren;
     } 
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',            
                'goods_attribute' => 'attr_id',
                'ad' =>'ad_id',            
        );        
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];        
        $model->save();   
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',                        
            'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn($return_arr);
    }

    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }
        
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $goods_id = $_GET['id'];
        $error = '';
        if($error)
        {
            $return_arr = array('status' => -1,'msg' =>$error,'data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
            $this->ajaxReturn($return_arr);
        }
        
        // 删除此商品        
        // M("Goods")->where('goods_id ='.$goods_id)->save(array('del_status'=>1));
        M("Goods")->where('goods_id ='.$goods_id)->delete();  //商品表
        M("cart")->where('goods_id ='.$goods_id)->delete();  // 购物车
        M("comment")->where('goods_id ='.$goods_id)->delete();  //商品评论
        M("goods_images")->where('goods_id ='.$goods_id)->delete();  //商品相册
        M("goods_attr")->where('goods_id ='.$goods_id)->delete();  //商品属性             
                     
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
        $this->ajaxReturn($return_arr);
    }       

    /**
     * 初始化编辑器链接     
     * 本编辑器参考 地址 http://fex.baidu.com/ueditor/
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('admin/Ueditor/imageUp',array('savepath'=>'goods'))); // 图片上传目录
        $this->assign("URL_imageUp", U('admin/Ueditor/imageUp',array('savepath'=>'article'))); //  不知道啥图片
        $this->assign("URL_fileUp", U('admin/Ueditor/fileUp',array('savepath'=>'article'))); // 文件上传s
        $this->assign("URL_scrawlUp", U('admin/Ueditor/scrawlUp',array('savepath'=>'article')));  //  图片流
        $this->assign("URL_getRemoteImage", U('admin/Ueditor/getRemoteImage',array('savepath'=>'article'))); // 远程图片管理
        $this->assign("URL_imageManager", U('admin/Ueditor/imageManager',array('savepath'=>'article'))); // 图片管理        
        $this->assign("URL_getMovie", U('admin/Ueditor/getMovie',array('savepath'=>'article'))); // 视频上传
        $this->assign("URL_Home", "");
    }    
    
    /**
     * 删除商品相册图
     */
    public function del_goods_images()
    {
        $path = I('filename','');
        M('goods_images')->where("image_url = '$path'")->delete();
    }

    public function tagLists()
    {
        $count = M('Tag')->where(array('del_status'=>0))->count();
        $page = new Page($count,10);
        $list = M('Tag')->where(array('del_status'=>0))->limit($page->firstRow.','.$page->listRows)->order('id desc')->select();
        $this->assign('list',$list);
        $this->assign('page',$page->show);
        return $this->fetch();
    }


    public function addEditTag()
    {
        if (IS_GET) {
            $id=I('get.id');
            if (empty($id)||!is_numeric($id)){
                 return $this->fetch();
            }else{
                 $data = M('Tag')->where(array('id'=>$id))->find();

                $this->assign('data',$data) ;
                 return $this->fetch();
            }
    
        }

        if (IS_POST) {
            $data = I('post.');
            if (!empty($data['id'])&&is_numeric($data['id'])) {
               $res = M('Tag')->where(array('id'=>$data['id']))->save(array('name'=>$data['name']));
            }else{
                $res = M('Tag')->add($data);
            }


            if ($res!==false) {
               $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }
        
    }

    public function del_tag()
    {
        $id = I('get.id');
        $res = M('Tag')->where(array('id'=>$id))->save(array('del_status'=>1));
        if ($res!==false) {
           exit(json_encode(array('status'=>1,'msg'=>'删除成功')));
        }else{
           exit(json_encode(array('status'=>0,'msg'=>'删除失败'))); 
        }
    }
}