<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 16:36
 */

namespace app\index\validate;


use think\Validate;

class UserInfoAdd extends Validate
{
    protected $rule = [
        'real_name' => 'require|checkNumber',
        'card_id' => 'require',
        'card_name' => 'require',
        'card' => 'require',
    ];

    protected $message = [
        'number.require' => '购买数量不能为空',
        'price.require' => '单价不能为空',
    ];
}