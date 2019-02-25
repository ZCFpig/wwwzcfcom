<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class GoodsOrders extends Validate
{

    protected $rule = [
        'express' => 'require',
        'expressnumber' => 'require',
    ];

    protected $message = [
        'express.require' => '快递公司不能为空',
        'expressnumber.require' => '快递单号不能为空',
    ];


}