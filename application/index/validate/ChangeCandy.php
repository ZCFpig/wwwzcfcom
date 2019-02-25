<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 15:39
 */

namespace app\index\validate;


use think\Validate;

class ChangeCandy extends Validate
{
    protected $rule = [
        'candy' => "require|regex:^[1-9][0-9]*0{3}$",
        'trad_password' => "require",

    ];

    protected $message = [
        'trad_password.require' => '密码不能为空',
        'candy.require' => '糖果不能为空',
        'candy.regex' => '糖果必须为1000的整倍数',
    ];
}