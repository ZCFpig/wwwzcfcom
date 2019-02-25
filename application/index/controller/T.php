<?php

namespace app\index\controller;

use app\common\entity\User;
use app\common\entity\Config;
use think\Request;
use think\Controller;
use app\common\entity\Category;
use think\Db;
use app\common\entity\UserProduct;

class T extends Controller {

    public function test(){
//        $categoryModel = new Category();
//        //新增下级节点
//        $userlist = User::field('id,pid')->select();
//        $userlist = $userlist->toArray();
//        foreach($userlist as $v){
//            $categoryModel->addSubChild($v['id'], $v['pid']);
//        }
//        $res = $categoryModel->addSubChild(1, 0);
        //添加同级节点
//        $res = $categoryModel->addSameLevlChild(41, 20);
        //删除节点
//        $res = $categoryModel->deleteChild(51);
        //获取某个节点下的所有子节点fid(默认是全部)
//        $res = $categoryModel->getSubChild();
        //获取所有叶子节点
//        $res = $categoryModel->getLeafChild();
        //获取某个节点的所有父级fid
//        $res = $categoryModel->getParent(15);
        //获取某个节点下的节点深度(默认为所有)
//        $res = $categoryModel->getChildDepth(20, 2);
//        //收益问题
//        $sql = 'select user_id,sum(magic) addmagic from user_magic_log where types = 8 AND create_time > 1529510400 GROUP BY user_id';
//        $res = Db::query($sql);
//        foreach($res as $k =>$v){
//            User::where('id', $v['user_id'])->setInc('magic', $v['addmagic']);
//        }
        $model = new \app\common\service\Product\Compute();
        $startTime = microtime(true);
        $userProduct = UserProduct::where('status', UserProduct::STATUS_RUNNING)
                ->where('last_time','<>',0)
                ->where('last_time','<',1531756800)->select();
        $count = 0;
        foreach($userProduct as $k=>$v){
            $res = $model->income($v);
            if($res===true){
                $count++;
            }else{
                \app\common\helper\func::fput($v->id.' - '.$res);
            }
        }
        $endTime = microtime(true);
        echo '耗时：'.($endTime - $startTime);
    }

    public function userproduct(){
        $userProductList = UserProduct::where(['status' => 0])->select();
        $productList = [1 => 1100,2=>12000 ,3=> 130000,4=>1400000];
        foreach($userProductList as $k=>$info){
            $tmpTotal = $productList[$info->product_id];
            $cha = bcsub($tmpTotal,$info->total,8);
            echo $cha.'<br>';
            if($cha>0){
                $info->total = $tmpTotal;
                $res = $info->save();
                if($res){
                    $userInfo = User::where(['id' => $info->user_id])->find();
                    $userInfo->magic = bcadd($userInfo->magic, $cha,8);
                    $userInfo->save();
                }else{
                    var_dump($res);
                }
            }
        }
    }

    /**
     * 重建用户关系
     */
    public function rebuildrelation(){
        Db::query('truncate category');
        $categoryModel = new Category();
        $categoryModel->fid = 0;
        $categoryModel->lft = 1;
        $categoryModel->rgt = 2;
        $categoryModel->save(false);

        //重建
        $userlist = User::field('id,pid')->select();
        $userlist = $userlist->toArray();
        foreach($userlist as $v){
            $categoryModel->addSubChild($v['id'], $v['pid']);
        }
    }
    public function index() {
        return $this->fetch('index');
    }

    public function login() {
        return $this->fetch('login');
    }

    public function mall() {
        return $this->fetch('mall');
    }

    public function register() {
        return $this->fetch('register');
    }

    public function market() {
        return $this->fetch('market');
    }

    public function tradeList() {
        return $this->fetch('tradeList');
    }

}
