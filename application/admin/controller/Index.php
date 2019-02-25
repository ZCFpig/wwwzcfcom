<?php
namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\admin\service\rbac\Users\Service;
use app\common\entity\ManageUser;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\entity\VoucherList;
use app\common\entity\UserProduct;
use think\Db;
use think\facade\Session;
use think\Request;

class Index extends Admin
{
    public function index()
    {
        //会员数量
        $user['total'] = User::count();
        $user['today'] = User::whereTime('register_time', 'today')->count();
        $user['auth'] = User::where('is_certification', User::AUTH_SUCCESS)->count();
        $user['no_auth'] = User::where('is_certification', User::AUTH_ERROR)->count();
        $user['sell_number'] = \app\common\entity\Mywallet::sum('sell_number');
        $user['can_sell'] = \app\common\entity\Mywallet::sum('can_sell');
        $user['total_voucher'] = VoucherList::where('status',2)->sum('num');
        //获取昨日手续费
        $time = strtotime(date('Y-m-d', time()));
//        $chargeNumber = Orders::where('status', Orders::STATUS_FINISH)
//            ->where('finish_time', '<', $time)
//            ->where('finish_time', '>=', $time - 24 * 3600)
//            ->sum('charge_number');
//        $re = $this->getLevel();
//        var_dump($re);die;
        return $this->render('index', [
            'userLevel' => $this->getLevel(),
            'magic' => $this->getMagic(),
            'order' => $this->getOrders(),
            'user' => $user,
//            'charge_number' => $chargeNumber
        ]);
    }

    //修改密码
    public function updateInfo(Request $request)
    {
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\admin\validate\ChangePassword');

            if ($validate !== true) {
                throw new AdminException($validate);
            }

            //判断原密码是否相等
            $model = new \app\admin\service\rbac\Users\Service();
            $user = ManageUser::where('id', $model->getManageId())->find();
            $oldPassword = $model->checkPassword($request->post('old_password'), $user);
            if (!$oldPassword) {
                throw new AdminException('原密码错误');
            }

            $user->password = $model->getPassword($request->post('password'), $user->getPasswordSalt());

            if ($user->save() === false) {
                throw new AdminException('修改失败');
            }

            return json(['code' => 0, 'message' => '修改成功', 'toUrl' => url('login/index')]);
        }
        return $this->render('change');
    }

    //获取交易数据
    protected function getOrders()
    {
        $match = Orders::where('status', Orders::STATUS_DEFAULT)->sum('number');
        $pay = Orders::where('status', Orders::STATUS_PAY)->sum('number');
        $confirm = Orders::where('status', Orders::STATUS_CONFIRM)->sum('number');
        $finish = Orders::where('status', Orders::STATUS_FINISH)->sum('number');

        return [
            'match' => $match,
            'pay' => $pay,
            'confirm' => $confirm,
            'finish' => $finish
        ];

    }

    //统计功能 会员等级处理
    protected function getLevel()
    {
        $model = new User();
        $userTable = $model->getTable();
        $sql = <<<SQL
SELECT count(*) as total,`level` FROM {$userTable} GROUP BY `level`
SQL;
        $userLevel = Db::query($sql);
        $data = [];
        foreach ($userLevel as $item) {
            $data[$item['level']] = $item['total'];
        }
        return $data;
    }

    //魔盒统计
    protected function getMagic()
    {
        $running = UserProduct::where('status', UserProduct::STATUS_RUNNING)->count();
        $stop = UserProduct::where('status', UserProduct::STATUS_STOP)->count();

        return [
            'running' => $running,
            'stop' => $stop
        ];
    }

    //退出系统
    public function logout()
    {
        $service = new Service();
        $service->logout();

        $this->redirect('admin/login');
    }

    public function clear()
    {
        //清除所有session
        Session::destroy();
    }
}