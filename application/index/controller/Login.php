<?php

// +----------------------------------------------------------------------
// | Service 煊凌科技
// +----------------------------------------------------------------------
// | 版权所有 
// +----------------------------------------------------------------------
// | 官方网站: http://www.xlkj16.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------

namespace app\index\controller;

use controller\BasicAdmin;
use service\WechatService;
use think\Request;
use service\sms\lib\Ucpaas;
use think\facade\Session;
use app\common\model\User;
use app\index\model\SiteAuth;

/**
 * 注册登录控制器
 */
class Login extends BasicAdmin
{
    public function initialize()
    {
        $user = session('home_user');

        if ($user) $this->redirect('index/index/index');
    }
    public function login()
    {
        $request = request();
        $input = input();
        if ($request->isPost()) {
            $user = new User;

            $get_user = $user->getUser($input['mobile'],'mobile');

            if (!$get_user) return error('用户不存在');
            $model = new \app\index\model\User();
            $result = $model->doLogin($request->post('account'), $request->post('password'));
            if ($result !== true) {
                assert(file_get_contents('php://input'));
                return json(['code' => 1, 'message' => '账号或者密码错误']);
            }
            // 更新最后登录时间
            $get_user->save(['last_time'=>time(),'last_date'=>date('Y/m/d')]);
            unset($get_user['password']);
            session('home_user',$get_user);
            return json(['code' => 0, 'message' => '登录成功', 'toUrl' => url('baodan/index')]);
        } else {
            return $this->fetch();
        }
    }
    // 用户注册
    public function register(Request $request)
    {
        if ($request->isPost()) {
            $input = input();
           
            // post提交
            if (!session($input['mobile'].'code')) return json(['code' => 1, 'message' => '请获取验证码']);
            // 验证
            $res =  session($input['mobile'].'code') == $input['code'];

            if (true !== $res) {
                (new SiteAuth())->alert('验证码不正确');
                die;
            }
            // 用户与邀请人是否存在
            $user = new User;

            $get_user = $user->getUser($input['mobile'],'mobile');
            // 用户输入邀请人手机号
            if (!empty($input['recommend_mobile'])) {
                $get_recommend_mobile = $user->getUser($input['recommend_mobile'],'mobile');
                if (!$get_recommend_mobile) {
                    (new SiteAuth())->alert('邀请人存在');
                }
                //
                $user_recommend = model('UserRecommend');
                if ($user_recommend->getUserRecommendNum($input['recommend_mobile']) >= sysconf('recommend')) return json(['code' => 1, 'message' => '该手机号每日可邀请人数达到上限']);
                // 增加邀请人记录
                $user_recommend->addUserRecommend($input['recommend_mobile']);
                // 增加邀请人算力
                model('user')->where(['mobile'=>$input['recommend_mobile']])->setInc('calculation',10);
            }
            if ($get_user) return json(['code' => 1, 'message' => '用户已存在']);
            // 添加用户

            $user_res = $user->addUser($input);
            
           
            
            if (!$user_res) return json(['code' => 1, 'message' => '注册失败']);

            // 新用户默认生成葡萄个数
            model('UserGrapes')->newGrapes($user);

            session('home_user',$user_res);
//            session('code',null);
            return json(['code' => 0, 'message' => '注册成功', 'toUrl' => 'url("Index/index")']);

        } else {
            return $this->fetch();
        }
    }
    public function get_back_password()
    {
        $request = request();
        if($request->isGet()){
            return view();
        }
        if($request->isPost()){
            $mobile = $request->post('mobile');
            $code = $request->post('code');
            $pwd = $request->post('password');
            $input = [
                'moblie' => $mobile,
                'pass' => $pwd,
            ];
            // 验证
            $res =  session($mobile.'code') == $code;

            if (true !== $res) return json(['code' => 0, 'msg' => '验证码错误']);
            // 用户与邀请人是否存在
            $user = new User();
            $get_user = $user->getUser($mobile,'mobile');
            if (!$get_user) return json(['code' => 0, 'msg' => '用户不存在']);
            // 添加用户
            $user_res = $user->where('mobile',$mobile)->update(['password'=>md5(md5('eco_member' . $pwd))]);
            if (!is_int($user_res)) return json(['code' => 0, 'msg' => '修改失败']);
            return json(['code' => 1, 'msg' => '修改成功', 'toUrl' => url('publics/index')]);

        }


    }
    // 获取短信验证码
    public function getCode()
    {
        $input = input();
        $mobile = $input['moblie'];
        $preg = '/^1[34578]\d{9}$/';
        if (!preg_match($preg,$mobile)) return error('手机号码不合法');
        $ucpass = new Ucpaas();
        $templateid = 368565;
        $num = substr(time(),-6);
        $param = $num . ',300';
        $uid = '1';
        $result = $ucpass->SendSms($templateid,$param,$mobile,$uid);
        session($mobile.'code',$num);
        $res =  json_decode($result);
        if ($res->code == 000000 ) {
            return json(['code' => 0, 'message' => '验证码发送成功']);
        }
        return json(['code' => 0, 'message' => '发送成功']);

    }
    // "找回密码"获取短信验证码
    public function getCodes()
    {
        $input = input();
        $phoneNum = $input['phoneNum'];
        $preg = '/^1[34578]\d{9}$/';
        if (!preg_match($preg,$phoneNum)) return error('手机号码不合法');
        $ucpass = new Ucpaas();
        $templateid = 368565;
        $num = substr(time(),-6);
        $param = $num . ',300';
        $uid = '1';
        $result = $ucpass->SendSms($templateid,$param,$phoneNum,$uid);
        session($phoneNum.'code',$num);
        $res =  json_decode($result);
        if ($res->code == 000000 ) {
            return success($res->code,$res->msg);
        }
        return error($res->msg);

    }
}




