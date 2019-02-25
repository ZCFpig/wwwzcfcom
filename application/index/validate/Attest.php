<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 11:58
 */

namespace app\index\validate;


use think\Validate;

class Attest extends Validate
{
    protected $rule = [
        'card_id' => 'require',
        'card' => 'require',
        'trad_password' => 'require'
    ];

    protected $message = [
        'card_id.require' => '证件号不能为空',
        'card.require' => '银行卡号不能为空',
        'trad_password.require' => '二级密码不能为空',
    ];
}