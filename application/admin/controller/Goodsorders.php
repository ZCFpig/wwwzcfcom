<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use think\Db;
use think\Request;
use app\common\entity\User;

class Goodsorders extends Admin {

    /**
     * @power 内容管理|订单管理
     * @rank 0
     */
    public function index(Request $request) {
        $search = input('get.search');
        $type = input('get.type');
        $goodsCategoryModel = new \app\common\entity\GoodsOrders();
        //订单列表
        $lists = $goodsCategoryModel->getGoodsOrdersPages($search,$type);
        // dump($lists);die;
        foreach ($lists as $v) {
            $img = json_decode($v['goods_pic']);
            $v['img'] = $img[0];
        }
        return $this->render('index',[
            'lists'=>$lists,
        ]);
    }


    /**
     * @power 内容管理|订单管理@确认发货
     */
    public function edit($order_number) {
        $query = \app\common\entity\GoodsOrders::alias('o')->field('o.*,u.mobile as user_mobile');
        $query = $query->where('o.order_number',$order_number);
        $entity = $query->leftJoin("user u", 'u.id = o.user_id')
//            ->order('create_time', 'desc')
            ->find();

        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('edit', [
                    'info' => $entity,
                    'cate' => \app\common\entity\GoodsOrders::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|订单管理@发货
     */
    public function update(Request $request, $order_number) {
        $res = $this->validate($request->post(), 'app\admin\validate\GoodsOrders');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $entity = $this->checkInfo($order_number);

        $service = new \app\common\entity\GoodsOrders();
        $result = $service->updateGoodsOrders($entity, $request->post(),$service::TYPE_SEND);

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/goodsorders')]);
    }

    /*
     * 取消发货
     * */
    public function cancel(Request $request, $order_number){
        $entity = $this->checkInfo($order_number);

        $service = new \app\common\entity\GoodsOrders();
        $result = $service->updateGoodsOrders($entity, $request->post(),$service::TYPE_CANCEL);
        if (!$result) {
            throw new AdminException('取消失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($order_number) {
        $entity = \app\common\entity\GoodsOrders::where('order_number', $order_number)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }



}
