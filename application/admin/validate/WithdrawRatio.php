<?php


namespace app\admin\validate;


use think\Validate;

class WithdrawRatio extends Validate
{
    protected $rule = [
        'ratio' => 'require|float',
        'name' => 'require',
    ];

    protected $message = [
        'ratio.require' => '比例不能为空',
        'name.require' => '名称不能为空',
        'ratio.float' => '比例只能为数字',
    ];
}