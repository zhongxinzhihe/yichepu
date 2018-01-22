<?php
namespace app\admin\controller;

use think\Page;
use think\Verify;
use think\Db;
use think\Session;

class Admin extends Base {

    public function index(){
        return $this->fetch();
    }
    
    /**
     * 修改管理员密码
     * @return \think\mixed
     */
    public function modify_pwd(){
        return $this->fetch();
    }
    
    public function admin_info(){
    	return $this->fetch();
    }
    
    public function adminHandle(){
    }
    
    
    /*
     * 管理员登陆
     */
    public function login(){
        if( IS_POST ) {
            //验证码判断
            $verify=new Verify();
            if (!$verify->check(I('post.vertify'))) {
                exit(json_encode(array('status'=>0,'msg'=>'验证码错误')));
            }
            // 用户名密码判断
            $condition['user_name']=$_POST['username']; //获取用户名
            $condition['password'] =$_POST['password'];//获取密码
            $condition['del_status']=0;//未删除
            $condition['password'] = encrypt($condition['password']);//密码加密
            $admin_info=M('admin')
            ->where($condition)
            ->find();

            //判断
            if ($admin_info) {
                if ($admin_info['check_status']!=1) {
                    exit(json_encode(array('status'=>0,'msg'=>'您还未通过审核或审核失败')));
                }
                // 将用户信息保存在session中
                session_start();
                session('admin_id',$admin_info['admin_id']);
                //传入地址
                $url=U('Admin/Index/index');
                exit(json_encode(array('status'=>1,'url'=>$url)));
            }else{
                exit(json_encode(array('status'=>0,'msg'=>'账号或密码有误')));
            }
        } 
        return $this->fetch();
    }
    
    /**
     * 退出登陆
     */
    public function logout(){
        session(null);
        $this->success('退出成功','Admin/Admin/login');
    }
    
    /**
     * 验证码获取
     */
    public function vertify()
    {
       $config=array(  
            'imageH' => 0,  
            'imageW' => 0,  
            'fontSize'=>30,  
            'fontttf' =>'4.ttf',  
            'length' => 4,  
        );  
        $verify=new Verify($config);  
        $verify->entry();  
        // $this->fetch();  
    }
    
    public function role(){
    }
    
    public function role_info(){
    	return $this->fetch();
    }
    
    public function roleSave(){
    }
    
    public function roleDel(){
    }
    
    public function log(){
    	return $this->fetch();
    }


	/**
	 * 供应商列表
	 */
	public function supplier()
	{
		return $this->fetch();
	}

	/**
	 * 供应商资料
	 */
	public function supplier_info()
	{
		
		return $this->fetch();
	}

	/**
	 * 供应商增删改
	 */
	public function supplierHandle()
	{

	}
}