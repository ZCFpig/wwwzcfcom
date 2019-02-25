<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use think\Db;
use think\Request;
use app\common\entity\User;

class Shop extends Admin
{

    /**
     * @power 内容管理|商品管理
     * @rank 0
     */
    public function index()
    {
        $categoryId = input('get.type');
        $goodsModel = new \app\common\entity\Goods();
        //商品列表
        $goodsLists = $goodsModel->getGoodsPages($categoryId);

        return $this->render('index', [
            'goodsLists' => $goodsLists,
            'cate' => \app\common\entity\GoodsCategory::getAllCate(),
        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品
     */
    public function create()
    {
        return $this->render('edit', [
            'cate' => \app\common\entity\GoodsCategory::getAllCate(),
            'levelcate' => \app\common\entity\UserPackage::getAllCate(),

        ]);
    }

    /**
     * @power 内容管理|商品管理@编辑商品
     */
    public function edit($id)
    {
        $entity = \app\common\entity\Goods::where('id', $id)->find();
        $entity['goods_pic'] = json_decode($entity['goods_pic']);
//        dump($entity);die;
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('edit', [
            'info' => $entity,
            'cate' => \app\common\entity\GoodsCategory::getAllCate(),
            'levelcate' => \app\common\entity\UserPackage::getAllCate(),

        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品
     */
    public function save(Request $request)
    {
//        var_dump($request->post());die;
        $res = $this->validate($request->post(), 'app\admin\validate\Goods');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }
        $service = new \app\common\entity\Goods();
        $result = $service->addGoods($request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }
        //添加用户提醒
        if ($request->post('category') == 1 && $request->post('status') == 1) {
            User::update(['notice' => 1], ['notice' => 0]);
        }

        return json(['code' => 0, 'toUrl' => url('/admin/shop')]);
    }

    /**
     * @power 内容管理|商品管理@编辑商品
     */
    public function update(Request $request, $id)
    {

        $res = $this->validate($request->post(), 'app\admin\validate\Goods');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }


        $entity = $this->checkInfo($id);

        $service = new \app\common\entity\Goods();
        $result = $service->updateGoods($entity, $request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/shop')]);
    }


    /**
     * @power 内容管理|商品管理@删除商品
     */
    public function delete(Request $request, $id)
    {
        $entity = $this->checkInfo($id);

        if (!$entity->delete()) {
            throw new AdminException('删除失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id)
    {
        $entity = \app\common\entity\Goods::where('id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * @power 内容管理|商品管理@添加商品分类
     */
    public function createCategory()
    {
        return $this->render('category', [
            'cate' => \app\common\entity\Goods::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品分类
     */
    public function categorySave(Request $request)
    {
        $res = $this->validate($request->post(), 'app\admin\validate\CategoryForm');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $service = new \app\common\entity\GoodsCategory();
        $result = $service->addGoodsCategoryParent($request->post());


        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/shop')]);
    }


}
