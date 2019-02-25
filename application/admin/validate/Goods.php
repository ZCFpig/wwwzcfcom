<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class Goods extends Validate
{

    protected $rule = [
        'title' => 'require',
        'category' => 'require',
        'photo' => 'require',
        'prices' => 'require',
        'content' => 'require',
        'num' => 'require',
        'weight' => 'require',
        // 'bnc' => 'float',
    ];

    protected $message = [
        'photo.require' => '图片不能为空',
        'title.require' => '标题不能为空',
        'content.require' => '内容不能为空',
        'category.require' => '请选择分类',
        'prices.require' => '价格不能为空',
        'num.require' => '库存不能为空',
        'weight.require' => '净含量不能为空',
        // 'bnc.require' => 'BNC只能为数字',
    ];


}