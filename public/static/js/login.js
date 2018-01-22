/*
 * 登录页面js 
*/
$(function(){
    // 初始化页面时验证是否勾选记住密码
    if ($.cookie("save")=="true") {
        $("#save").attr("checked",true)
        $("#userN").val($.cookie("userName"))
        $("#pasN").val($.cookie("passWord"))
    }
    $('.login-user').children('input').focus(function(){
        $('.login-user').css('background-color','#F1F5F9');
        $('.block1').show();
    })
    $('.login-user').children('input').blur(function(){
        $('.login-user').css('background-color','#fff');
        $('.block1').hide();
    })
    $('.login-pas').children('input').focus(function(){
        $('.login-pas').css('background-color','#F1F5F9');
        $('.block2').show();
    })
    $('.login-pas').children('input').blur(function(){
        $('.login-pas').css('background-color','#fff');
        $('.block2').hide();
    })
    $('.btn').children('input').click(function(){
        if ($("#pasW").val()=="" && $("#userN").val()=="") {
            $("#tip1").text("请输入用户名和密码");
        }
        else if($("#userN").val()=="") {
            $("#tip1").text("请输入用户名");
        }
        else if($("#pasW").val()=="") {
            $("#tip1").text("请输入密码");
        }
        else if($("#vertify").val()=="") {
            $("#tip1").text("请输入验证码");
        }else {        
            var ajaxUrl="/index.php/Admin/Admin/login";
            $.ajax({
                type:"post",
                dataType:'json',
                url:ajaxUrl,
                async:false,
                data:{username:$('#userN').val(),password:$('#pasW').val(),vertify:$('#vertify').val()},
                success:function(data){
                    if (data.status!=1) {
                        $("#tip1").text(data.msg);
                        //更换验证码
                        $('#imgVerify').attr("src",'/Admin/Admin/vertify.html'+'?'+Math.random())
                        //清空输入栏
                        $('#userN,#pasW,#vertify').val("");
                        return false
                    }else{
                        top.location.href = data.url;
                    }
                },
                error:function(XMLHttpRequest, textStatus, errorThrown){
                    $('#error').html('<span class="error">网络失败，请刷新页面后重试!</span>');
                }
            })
        } 
    })
})
// 保存信息
function saveUserInfo(){
    if ($("#save").is(':checked')==true) {
        var userN=$("#userN").val()
        var pasW=$("#pasW").val()
        $.cookie("save","true",{expires:7})
        $.cookie("userName",userN,{expires:7})
        $.cookie("passWord",pasW,{expires:7})
    }else{
        $.cookie("save","false",{expires:-1})
        $.cookie("userName",null,{expires:-1})
        $.cookie("passWord",null,{expires:-1})
    }
}