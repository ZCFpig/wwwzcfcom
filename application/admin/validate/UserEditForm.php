<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class UserEditForm extends Validate
{

    protected $rule = [
        'nick_name' => 'require',
        'password' => 'min:6',
        'trad_password' => 'min:6',

    ];

    protected $message = [
        'nick_name.require' => '请输入用户昵称',
        'password.min' => '密码至少6位数',
        'trad_password.min' => '交易密码至少6位数',
    ];


}