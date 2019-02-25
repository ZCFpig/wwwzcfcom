<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use think\Db;
use think\Request;
use app\common\entity\User;

class Goodscategory extends Admin {

    /**
     * @power 内容管理|商品分类
     * @rank 0
     */
    public function index(Request $request) {
        $categoryId = input('get.type');

        $goodsCategoryModel = new \app\common\entity\GoodsCategory();
        //商品列表
        $lists = $goodsCategoryModel->getGoodsCategoryPages($categoryId);

        return $this->render('index',[
            'lists'=>$lists,
            'cate' => \app\common\entity\GoodsCategory::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品
     */
    public function create() {
        return $this->render('edit', [
            'cate' => \app\common\entity\GoodsCategory::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品下级分类
     */
    public function createNext($id) {
        $entity = \app\common\entity\GoodsCategory::where('category_id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('createNext', [
            'info' => $entity,
            'cate' => \app\common\entity\GoodsCategory::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品管理@编辑商品
     */
    public function edit($id) {
        $entity = \app\common\entity\GoodsCategory::where('category_id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }
//        var_dump($entity);
//        var_dump(\app\common\entity\GoodsCategory::getAllCate());exit;
        return $this->render('edit', [
                    'info' => $entity,
                    'cate' => \app\common\entity\GoodsCategory::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品分类@添加一级分类
     */
    public function save(Request $request) {
        $res = $this->validate($request->post(), 'app\admin\validate\GoodsCategory');
//        var_dump($res);exit;
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $service = new \app\common\entity\GoodsCategory();
        $result = $service->addGoodsCategoryParent($request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/goodscategory')]);
    }

    /**
     * @power 内容管理|商品分类@添加下级分类
     */
    public function saveNextLevel(Request $request) {
        $res = $this->validate($request->post(), 'app\admin\validate\GoodsCategory');
//        var_dump($request->post());exit;
        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $service = new \app\common\entity\GoodsCategory();
        $result = $service->addGoodsCategorySon($request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/goodscategory')]);
    }

    /**
     * @power 内容管理|商品管理@编辑商品
     */
    public function update(Request $request, $id) {

        $res = $this->validate($request->post(), 'app\admin\validate\GoodsCategory');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $entity = $this->checkInfo($id);

        $service = new \app\common\entity\GoodsCategory();
        $result = $service->updateGoodsCategory($entity, $request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/goodscategory')]);
    }


    /**
     * @power 内容管理|商品管理@删除商品
     */
    public function delete(Request $request, $id) {
        $entity = $this->checkInfo($id);

        if (!$entity->delete()) {
            throw new AdminException('删除失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id) {
        $entity = \app\common\entity\GoodsCategory::where('category_id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * @power 内容管理|商品管理@添加商品分类
     */
    public function createCategory() {
        return $this->render('category', [
            'cate' => \app\common\entity\Goods::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|商品管理@添加商品分类
     */
    public function categorySave(Request $request) {
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
