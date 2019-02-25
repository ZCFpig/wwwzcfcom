<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Charge;
use app\common\entity\Product as productModel;
use think\Db;
use think\Request;

class Product extends Admin {

    /**
     * @power 产品管理|茶园列表
     * @rank 3
     */
    public function index(Request $request) {
        return $this->render('index', [
                    'list' => productModel::paginate(15)
        ]);
    }

    /**
     * @power 产品管理|充值卡列表
     */
    public function chargelist(Request $request) {
        return $this->render('chargelist', [
                    'list' => Charge::paginate(15)
        ]);
    }

    public function chargeInfo(Request $request) {
        $id = $request->get('id');
        $charge = Charge::where('id', $id)->find();
        if (!$charge) {
            $this->error('充值卡不存在');
        }
        return $this->render('charge', ['info' => $charge]);
    }

    public function createCharge() {
        return $this->render('charge');
    }

    public function saveCharge(Request $request) {
        $id = (int) $request->get('id');
        $charge = Charge::where('id', $id)->find();
        if (!$charge) {
            $charge = new Charge();
            $charge->create_time = time();
        }
        $charge->logo = $request->post('logo');
        $charge->name = $request->post('name');
        $charge->qrcode = $request->post('qrcode');
        $charge->price = $request->post('price');

        if ($charge->save()) {
            return json(['code' => 0, 'message' => '编辑充值卡成功']);
        } else {
            return json(['code' => 0, 'message' => '编辑充值卡失败']);
        }
    }

    public function deleteCharge(Request $request) {
        $id = $request->get('id');
        $charge = Charge::where('id', $id)->find();
        if (!$charge) {
            return json(['code' => 1, 'message' => '充值卡不存在']);
        }
        Charge::where('id', $id)->delete();
        return json(['code' => 0, 'message' => '删除成功', 'toUrl' => url('product/chargelist')]);
    }

    /**
     * @power 产品管理|茶园列表@上架茶园
     * @method POST
     */
    public function downShelve($id) {
        $entity = $this->checkInfo($id);
        $entity->status = 1;

        if (!$entity->save()) {
            throw new AdminException('上架失败');
        }
        return json(['code' => 0, 'message' => 'success']);
    }

    /**
     * @power 产品管理|茶园列表@编辑茶园
     */
    public function edit($id) {
        $entity = productModel::where('id', $id)->find();
        if (!$entity) {
            $this->error('对象不存在');
        }

        return $this->render('edit', [
                    'info' => $entity,
        ]);
    }

    /**
     * @power 产品管理|茶园列表@下架茶园
     * @method POST
     */
    public function shelve($id) {
        $entity = $this->checkInfo($id);
        $entity->status = 0;

        if (!$entity->save()) {
            throw new AdminException('下架失败');
        }
        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id) {
        $entity = productModel::where('id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * @power 产品管理|茶园列表@编辑茶园
     */
    public function update(Request $request, $id) {
        $entity = $this->checkInfo($id);

        $entity->product_name = $request->post('product_name');
        $entity->rate_min = $request->post('rate_min');
        $entity->rate_max = $request->post('rate_min');
        $entity->yield_min = $request->post('yield_min');
        $entity->yield_max = $request->post('yield_min');
        $entity->price = $request->post('price');
        $entity->period = $request->post('period');
//        $entity->jewel_price = $request->post('jewel_price');

        if ($entity->save() === false) {
            throw new AdminException('保存失败');
        }
        return json(['toUrl' => url('/admin/product/index')]);
    }

}
