<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 11:58
 */

namespace app\index\validate;


use think\Validate;

class UpdInfo extends Validate
{
    protected $rule = [
        'trad_password' => 'require'
    ];

    protected $message = [
        'trad_password.require' => '二级密码不能为空',
    ];
}