<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/31
 * Time: 11:35
 */

namespace app\admin\validate;


use think\Validate;

class Ratio extends Validate
{
    protected $rule = [
        'date' => 'require|unique:proportion',
        'ratio' => 'require|float',
    ];

    protected $message = [
        'date.require' => '日期不能为空',
        'date.unique' => '日期重复',
        'ratio.require' => '比例不能为空',
        'ratio.float' => '比例只能为数字',
    ];
}