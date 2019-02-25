<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/2
 * Time: 15:20
 */

namespace app\index\controller;
use app\common\entity\User as userModel;
use app\common\entity\UserDetail;
use app\common\entity\UserPhoto;
use app\common\entity\UserFriend;
use app\common\entity\UserAddress;
use app\common\entity\Recharge;
use think\Db;
use think\Request;
use think\Controller;
use service\emchat\Easemob;
use app\common\entity\Buyvip;
use app\common\entity\UserPackage;

class User extends Base
{


    /**
     * @power 用户地址|列表
     */
    public function useraddress(Request $request)
    {
        if ($request->isPost()){

            $list = $this->addresssearch($request);
            if ($list){

                return json(['code'=>0,'message'=>'请求成功','info'=>$list]);
            }
            return json(['code'=>1,'message'=>'请求失败']);
        }
        return $this->fetch('useraddress',['goods_id'=> $request->get('goods_id'),'num'=>$request->get('num')]);


    }
    /**
     * @power 用户地址|添加新收获地址
     */
    public function addaddress(Request $request)
    {
        if ($request->isPost()){
            $uid = $this->userId;
            $validate = $this->validate($request->post(), '\app\index\validate\Useraddress');
            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            $address = new UserAddress();
            $userAddress = $address->where('uid',$uid)->select();

            foreach ($userAddress as $k => $v){
                if($v['status'] == 1){
                    $address->where('uid',$v['uid'])->update(['status'=>0]);
                }
            }
            $result = $address->addRess($address,$request->post(),$uid);
            if($result) return json(['code'=>0,'message'=>'添加地址成功']);
            return json(['code'=>1,'message'=>'失败']);
        }
        return $this->fetch('addaddress',['goods_id'=> $request->get('goods_id'),'num'=>$request->get('num')]);

    }
    /**
     * @power 用户地址|修改|设为默认
     */
    public function updateaddress (Request $request)
    {
        $address_id = $request->get('address_id');
        if ($request->isPost()){

            $query = UserAddress::where('id', $address_id)->find();

            if (!$query) {
                return json(['code' => 1, 'message' => '地址信息不存在']);
            }
            if($request->post('status') == 1){
                $address = new UserAddress();
                $address->where('uid',$query['uid'])->update(['status'=>0]);
            }
            $result = $query->updateRess($query,$request->post());
            if(!$result) return json(['code'=>1,'message'=>'操作失败']);
            return json(['code' => 0, 'message' => '操作成功']);
        }
        $list = Db::table('user_address')->where('id',$address_id)->find();
        // var_dump($list);die;
        return $this->fetch('edit',['list'=> $list,'goods_id'=> $request->get('goods_id'),'num'=>$request->get('num')]);

    }
    /**
     * @power 用户地址|删除地址
     */
    public function deladdress (Request $request)
    {
        $id = $request->post('id');
        $query = UserAddress::where('id', $id)->find();
        if (!$query) {
            return json(['code' => 1, 'message' => '地址信息不存在']);
        }
        if($query['status'] == 1){
            return json(['code' => 1, 'message' => '默认地址不能删除']);
        }
        $result = $query->delete();
        if(!$result) return json(['code'=>1,'message'=>'操作失败']);
        return json(['code' => 0, 'message' => '操作成功']);
    }
    /**
     * @power 用户地址|地址信息查询
     * @rank 4
     */
    protected function addresssearch(Request $request)
    {
        $query = UserAddress::alias('ua')->field('ua.*');
        if ($status = $request->post('status')) {
            $query->where('ua.status',$status);
            $map['ua.status'] = $status;
        }
        $page = $request->post('page')?$request->post('page'):1;
        $limit = $request->post('count')?$request->post('count'):10;
        $userTable = (new userModel())->getTable();
        $list = $query
            ->leftJoin("$userTable u", 'u.id = ua.uid')
            ->where('uid',$this->userId)
            ->where(isset($map) ? $map : [])
            ->order('ua.create_time', 'desc')
            ->page($page)
            ->limit($limit)
            ->select();
        return $list;
    }

    #用户信息
    public function userInfo(Request $request){
        $uid = $request->post('uid');
        $userInfo = Db::table('user')->field('id,mobile,level')->where('id',$uid)->find();
        $wallet = Db::table('my_wallet')->where('user_id', $uid)->find();
        $userInfo['ky_number'] = $wallet['ky_number'];
        $userInfo['candy'] = $wallet['candy'];
        $userInfo['xf_number'] = $wallet['xf_number'];
        $userInfo['freeze'] = $wallet['freeze'];
        if ($userInfo){
            return json(['code' => 0, 'message' => '获取成功' , 'info' => $userInfo]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #个人中心
    public function userIndex(Request $request){
        $list = Db::table('user')->field('id,mobile,level')->where('id',$this->userId)->find();
        $level = new UserPackage();
        $list['level'] = $level->getOneCate($list['level']);
        return $this->fetch('userIndex',['list'=>$list]);
    }
}