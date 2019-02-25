<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use app\index\model\SendemailCode;
use think\Validate;

class Useraddress extends Validate
{
    protected $rule = [
        'mobile' => 'require',
        'user_name' => 'require',
        'area' => 'require',
        'address_detail' => 'require',
    ];

    protected $message = [
        'mobile.require' => '联系电话不能为空',
        'user_name.require' => '联系人不能为空',
        'address_detail.require' => '地址详情不能为空',
        'area.require' => '地区未选择',
    ];

}