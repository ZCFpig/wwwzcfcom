<?php
namespace app\index\controller;

use app\common\entity\Orders;
use think\Request;

class Upload extends Base
{

    public function uploadEditor()
    {
        $uploadModel = new \app\common\service\Upload\Service('image');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => [$uploadModel->fileName]
            ]);
        }
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }


    public function uploadImg() {
        $uploadModel = new \app\common\service\Upload\Service('file');
        if ($uploadModel->upload()) {
            return json([
                'errno' => 0,
                'data' => [$uploadModel->fileName]
            ]);
        }
        return json([
            'errno' => 1,
            'fail ' => $uploadModel->error
        ]);
    }


    public function order(Request $request)
    {
        $orderId = intval($request->post('orderId'));
        $order = Orders::where('id', $orderId)->find();
        if (!$order) {
            return json(['code' => 1, 'message ' => '操作错误']);
        }
        if ($order->status != Orders::STATUS_PAY) {
            return json(['code' => 1, 'message ' => '操作错误']);
        }
        if ($order->user_id != $this->userId && $order->target_user_id != $this->userId) {
            return json(['code' => 1, 'message ' => '操作错误']);
        }

        $uploadModel = new \app\common\service\Upload\Service('image');
        if ($uploadModel->upload()) {

            $order->image = $uploadModel->fileName;

            if ($order->save()) {
                return json(['code' => 0, 'url' => $uploadModel->fileName]);
            }

            return json(['code' => 1, 'url' => '上传失败']);
        }
        return json(['code' => 1, 'message ' => $uploadModel->error]);
    }
}
