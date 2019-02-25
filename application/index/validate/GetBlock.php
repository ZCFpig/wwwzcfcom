<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 15:39
 */

namespace app\index\validate;


use think\Validate;

class GetBlock extends Validate
{
    protected $rule = [
        'number' => "require",
        'trad_password' => "require",
    ];

    protected $message = [
        'trad_password.require' => '密码不能为空',
        'number.require' => '数量不能为空',
    ];
}