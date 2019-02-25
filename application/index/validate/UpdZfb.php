<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 11:58
 */

namespace app\index\validate;


use think\Validate;

class UpdZfb extends Validate
{
    protected $rule = [
        'zfb_image_url' => 'require',
        'code' => 'require',
    ];

    protected $message = [
        'zfb_image_url.require' => '支付宝二维收款码不能为空',
        'code.require' => '验证码号不能为空',
    ];
}