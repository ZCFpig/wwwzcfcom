<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class ChangePassword extends Validate{

    protected $rule = [
        'old_password' => 'require',
        'password' => 'require',
        'password_confirmation' => 'require|confirm:password',
    ];

    protected $message = [
        'old_password.require' => '原密码不能为空',
        'password.require' => '新密码不能为空',
        'password_confirmation.require' => '确认密码不能为空',
        'password_confirmation.confirm' => '两次密码不相同',
    ];

}