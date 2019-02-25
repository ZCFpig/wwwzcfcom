<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 16:56
 */

namespace app\admin\validate;


use think\Validate;

class Package extends Validate
{
    protected $rule = [
        'level' => 'require',
        'money' => 'require|number',
        'times' => 'require|number',
        'day_power' => 'require|number',
        'recast' => 'require|number',
        'total' => 'require|number',
        'share' => 'require|number',

    ];

    protected $message = [
        'level.require' => '会员等级不能为空',
        'money.require' => '价格不能为空',
        'money.number' => '价格只能为数字',
        'times.require' => '增值倍数不能为空',
        'times.number' => '增值倍数只能为数字',
        'day_power.require' => '日算力不能为空',
        'day_power.number' => '日算力只能为数字',
        'recast.require' => '复投倍数不能为空',
        'recast.number' => '复投倍数只能为数字',
        'total.require' => '闭环总倍数不能为空',
        'total.number' => '闭环总倍数只能为数字',
        'share.require' => '节点分享算力不能为空',
        'share.number' => '节点分享算力只能为数字',
    ];

}