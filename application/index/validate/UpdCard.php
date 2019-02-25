<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 11:58
 */

namespace app\index\validate;


use think\Validate;

class UpdCard extends Validate
{
    protected $rule = [
        'card_id' => 'require',
        'code' => 'require',
    ];

    protected $message = [
        'card_id.require' => '身份证号码不能为空',
        'code.require' => '验证码号不能为空',
    ];
}