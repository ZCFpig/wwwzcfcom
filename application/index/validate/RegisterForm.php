<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use think\Validate;

class RegisterForm extends Validate
{
    protected $rule = [
        'invite_code' => 'checkInvite',
        'real_name' => 'require',
        'mobile' => 'require|regex:^1\d{10}$|checkMobile',
        'code' => 'require',
        'zfb' => 'require',
        'password' => 'require|min:6',
        'safe_password' => 'require'
    ];

    protected $message = [
        // 'invite_code.require' => '邀请码不能为空',
        'real_name.require' => '真实姓名不能为空',
        'mobile.require' => '账号不能为空',
        'mobile.regex' => '账号格式不正确',
        'zfb.require' => '支付宝账号不能为空',
        'code.require' => '验证码不能为空',
        'password.require' => '登录密码不能为空',
        'password.min' => '登录密码至少为6位',
        'safe_password.confirm' => '两次密码不一样'
        // 'safe_password.require' => '交易密码不能为空',
        // 'safe_password.min' => '交易密码至少为6位'
    ];

    public function checkInvite($value, $rule, $data = [])
    {
        //判断邀请码是否存在
        if ($value == '20190214888') {
            return true;
        }
        if (!UserInviteCode::getUserIdByCode($value)&&$value || $value == '0') {
            return '邀请码不存在';
        }
        return true;
    }

    public function checkMobile($value, $rule, $data = [])
    {
        if (\app\common\entity\User::checkMobile($value)) {
            return '此账号已被注册，请重新填写';
        }
        return true;
    }

    public function checkCode($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'register');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }

    public function checkChange($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'change-password');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }


    public function checkZfb($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'updzfb');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }


    public function checkCard($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'updcard');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }

}