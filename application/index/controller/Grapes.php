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

use service\WechatService;
use think\Request;
use app\common\model\UserReal;
use app\common\model\User;
use app\common\model\UserGrapes;
use think\Model;

/**
 * 应用入口控制器
 */
class Grapes extends Base
{

    public function index()
    {
        $user = session('home_user');
        // 用户信息
        $user_model = new User();
        $user = $user_model->get($user['id']);
        // 可获取葡萄数据
        $user_grapes = model('user_grapes')
                        ->where([
                            'mobile'=>$user['mobile'],
                            'status'=>1,
                        ])
                        ->select()
                        ->toArray();
        // 生成葡萄方法
        $result = $this->makeGrapes();
        // 超时过期
        model('user_grapes')->overdue($user['mobile']);
    	return $this->fetch('index',[
    		'user' => $user,
            'user_grapes' => $user_grapes
    	]);
    }
    //实名认证
    public function realName ()
    {
        $user = session('home_user');
        $user_model = new User();
        $user = $user_model->get($user['id']);
        return $this->fetch('login/upload_ID_card',[
            'user' => $user
        ]);
    }
    //实名图片上传
    public function upload()
    {
        $user = session('home_user');
        $user_model = new User();
        $user = $user_model->get($user['id']);
        $id_num = request()->post('idcard');
        $upload = request()->file('upload');
        $upload_back = request()->file('upload_back');
        if( !$upload && !$upload_back ){
            echo '<script type="text/javascript"> window.history.back(-1);alert("请选择图片");  </script>';
            die;
        }
        if(  empty($id_num) ){
            echo '<script type="text/javascript"> window.history.back(-1);alert("请输入身份证号");  </script>';
            die;
        }
        $resUp = $upload->move( 'static/upload');
        $resBack = $upload_back->move( 'static/upload');
        if ($resUp&&$resBack) {
            $userReal = new UserReal();
            $data['user_id'] = $user['id'];
            $data['id_num'] = $id_num;
            $data['positive'] = $resUp->getSaveName();
            $data['back'] = $resBack->getSaveName();
            $result = $userReal->addReal($data);
            if($result){
                $res = Db('user')->where('id',$user['id'])->update(['real_pass'=>3]);
                if($res) return $this->index();
            }
            return $this->index();
        }
    }
   	// 增加葡萄数
    public function addGrapes(Request $request) 
    {

        $user = session('home_user');
        $user_model = new User();
        $user = $user_model->get($user['id']);
        if ($user['real_pass'] === 1 ) return error(1);
        if ($user['real_pass'] === 3 ) return error(3);
    	$params = $request->param();
        // 获取葡萄数据
        $user_grapes = model('UserGrapes')->get($params['id']);
        if (!$user_grapes) return error('葡萄数据不存在');
        if ($user_grapes['status'] > 1) return error('葡萄已被领取或已过期');

    	// 增加葡萄数
    	$user->where(['id'=>$user['id']])
            ->inc('grapes',$user_grapes['grapes'])
            ->inc('grapes_jlc',$user_grapes['grapes'])
            ->inc('grapes_calculation',$user_grapes['grapes'])
            ->update();
        // 更新葡萄状态与领取时间
        $user_grapes->save(['status'=>2,'get_time'=>time()]);

    	// 验证葡萄数
    	$user->checkGrapes($user['id']);

        // 获取当前可获取数量
        $now_num = $user_grapes->canGetGrapes($user['mobile']);

        return success('成功',['user'=>$user->get($user['id']),'now_num'=>$now_num]);
    }
    // 生成葡萄数
    public function makeGrapes() 
    {

        $user = session('home_user');
        $user_model = new User();
        $user = $user_model->get($user['id']);
        $user_grapes = new UserGrapes();

        $last = $user_grapes->getLastUserGrapes($user['mobile']);
        // 上次生成时间大于72小时
        if ( strtotime( $last['create_time'])+72*60*60 < time()) {
            // 计算上次生成与当前时间相差生成个数
            $should = floor((time()-strtotime("-3 day"))/60/15);
        } else {
            // 计算上次生成与当前时间相差生成个数
            $should = floor((time()-strtotime( $last['create_time']))/60/15);
        }
        // // 每15分钟生成葡萄数量
        $num = ceil($user['calculation']/24/4*100000)/100000;
        $res = model('UserGrapes')->addUserGrapes($user,$num,$should);
        return $res;
    }
}
