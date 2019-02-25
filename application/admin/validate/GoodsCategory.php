<?php
namespace app\admin\validate;

use think\Request;
use think\Validate;

class GoodsCategory extends Validate
{

    protected $rule = [
        'category_title' => 'require',
    ];

    protected $message = [
        'category_title.require' => '商品分类不能为空',
    ];


}