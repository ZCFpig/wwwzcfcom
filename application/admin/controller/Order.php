<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Orders;
use app\common\entity\User;
use app\index\model\Market;
use think\Request;

class Order extends Admin
{
    /**
     * @power 交易市场|求购订单
     * @rank 4
     */
    public function index(Request $request)
    {   
        $list = $this->search($request, Orders::TYPE_BUY);
        return $this->render('list', [
            'list' => $list,
            'type' => Orders::TYPE_BUY
        ]);
    }

    /**
     * @power 交易市场|出售订单
     */
    public function show(Request $request)
    {
        $list = $this->search($request, Orders::TYPE_SALE);
        return $this->render('list', [
            'list' => $list,
            'type' => Orders::TYPE_SALE
        ]);
    }
    /**
     * @power 交易市场|手续费
     */
    public function charge(Request $request)
    {
        $list = $this->search($request,'',  Orders::STATUS_FINISH);

        $query = Orders::where('status',4);
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime && $endTime){
            $query->where('finish_time', '<', strtotime($endTime))
            ->where('finish_time', '>=', strtotime($startTime));
        }
        $total = $query->sum('charge_number');
        return $this->render('charge', [
            'list' => $list,
            'total' => sprintf('%.8f',$total),
        ]);
    }

    /**
     * @power 交易市场|出售订单@订单详细
     * @method GET
     */
    public function detail(Request $request)
    {
        $id = $request->get('id');
        $order = Orders::where('id', $id)->find();
        if (!$order) {
            $this->error('对象不存在');
        }
        $userInfo = User::where('id', $order->user_id)->find();
        if ($order->status > 1) {
            $targetUser = User::where('id', $order->target_user_id)->find();
        }
        return $this->render('detail', [
            'order' => $order,
            'userInfo' => $userInfo,
            'targetUser' => isset($targetUser) ? $targetUser : ''
        ]);
    }

    /**
     * @power 交易市场|出售订单@取消订单
     */
    public function delete(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->request('id');
            $order = Orders::where('id', $id)->find();
            if (!$order) {
                throw new AdminException('订单不存在');
            }
            if ($order->status > Orders::STATUS_PAY) {
                throw new AdminException('订单已付款了， 不能取消');
            }

            $result = false;
            if ($order->status == 1) {
                $service = new Market();
                if ($order->types == Orders::TYPE_BUY) {
                    $result = $service->cancelBuy($order);
                } elseif ($order->types == Orders::TYPE_SALE) {
                    $result = $service->cancelSale($order);
                }
            } else if ($order->status == 2) {
                $result = $order->cancel();
            }

            if (!$result) {
                throw new AdminException('取消失败');
            }

            return json(['code' => 0, 'message' => '取消成功']);
        }
    }

    /**
     * @power 交易市场|出售订单@确认收款
     */
    public function update(Request $request)
    {
        if ($request->isPost()) {
            $id = $request->request('id');
            $order = Orders::where('id', $id)->find();
            if (!$order) {
                throw new AdminException('订单不存在');
            }
            if ($order->status != Orders::STATUS_CONFIRM) {
                throw new AdminException('订单还没付款，不能操作');
            }
            $do = $request->request('do');
            $result = $do == 'cancel' ? $order->cancel() : $order->confirm();
            if (!$result) {
                throw new AdminException('操作失败');
            }

            return json(['code' => 0, 'message' => '取消成功']);
        }
    }

    protected function search($request, $type = '',$status = '')
    {
        $query = Orders::alias('o')->field('o.*,u.nick_name,u.mobile');
        if($type){
            $query->where('o.types', $type);
        }
        $status = $status ? $status : $request->get('status');
        if ($status) {
            $query->where('o.status', $status);
            $map['status'] = $status;
        }
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $query->where('u.mobile', $keyword);
                    break;
                case 'number':
                    $query->where('o.order_number', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $startTime = $request->get('startTime');
        $endTime = $request->get('endTime');
        if($startTime && $endTime){
            $query->where('finish_time', '<', strtotime($endTime))
            ->where('finish_time', '>=', strtotime($startTime));
            $map['startTime'] = $startTime;
            $map['endTime'] = $endTime;
        }

        $userTable = (new User())->getTable();
        $list = $query->leftJoin("$userTable u", 'u.id = o.user_id')
            ->order('create_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        return $list;
    }
}
