<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class UserForm extends Validate
{

    protected $rule = [
//        'mobile' => 'require|regex:^1[3,4,5,7,8][0-9]{9}$',
        'mobile' => 'require',
        'nick_name' => 'require',
        'password' => 'require|min:6',
        'trad_password' => 'require|min:6',

    ];

    protected $message = [
        'mobile.require' => '手机号码不能为空',
//        'mobile.regex' => '手机号码格式不正确',
        'nick_name.require' => '请输入用户昵称',
        'password.require' => '请输入密码',
        'password.min' => '密码至少6位数',
        'trad_password.require' => '请输入交易密码',
        'trad_password.min' => '交易密码至少6位数',
    ];


}