<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\MywalletLog;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\entity\Currency;
use app\index\model\Market;
use think\Db;
use think\Request;
use app\common\command\InitMenu;

class Wallet extends Admin
{
    /**
     * @power 钱包管理|币种管理
     */
    public function index(Request $request)
    {

        $list = Currency::select();

        return $this->render('index', [
            'list' => $list
        ]);
    }


    /**
     * 添加币种
     */
    public function setadd(Request $request)
    {
        $config = new Currency();
        $config->title = $request->post('title');
        $config->islegal = $request->post('islegal');
        $config->address = $request->post('address');
        $config->imgs = $request->post('imgs');

        $config->createtime = $request->time();

        $entity = \app\common\entity\Currency::where('title', $config->title)->find();
        if ($entity) {
            throw new AdminException('币种已存在');
        }

        if ($config->save() === false) {
            throw new AdminException('添加失败');
        }
        return ['code' => 0, 'message' => '添加成功，2秒后自动跳转'];
    }

    /**
     * 添加修改币种
     */
    public function edit(Request $request)
    {

        $entity = $this->checkInfo($request->post('id'));

        $entity1 = \app\common\entity\Currency::where('title', $request->post('title'))->where('id', '<>', $request->post('id'))->find();
        // print_r($entity1);
        if ($entity1) {
            throw new AdminException('币种已存在');
        }

        $service = new \app\common\entity\Currency();

        $result = $service->editCurrency($entity, $request->post());

        if ($result === false) {
            throw new AdminException('修改失败');
        }

        return ['code' => 0, 'message' => '修改成功，2秒后自动跳转'];
    }

    private function checkInfo($id)
    {
        $entity = \app\common\entity\Currency::where('id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }


        return $entity;
    }


    #行情比例列表
    public function ratiolist(Request $request)
    {
        $list = Db::table('proportion')->select();
        return $this->render('ratiolist', [
            'list' => $list
        ]);
    }

    #行情比例添加
    public function ratioadd(Request $request)
    {
        $validate = $this->validate($request->post(), 'app\admin\validate\Ratio');

        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }
        $date = $request->post('date');
        $ratio = $request->post('ratio');
        $data = [
            'date' => $date,
            'ratio' => $ratio,
            'create_time' => time()
        ];
        $res = Db::table('proportion')->insert($data);
        if ($res) {
            return json(['code' => 0, 'message' => '添加成功']);
        }
        return json(['code' => 1, 'message' => '添加失败']);

    }


    #行情比例更新
    public function ratioupd(Request $request)
    {
        // $validate = $this->validate($request->post(), 'app\admin\validate\Ratio');

        // if ($validate !== true) {
        //     return json(['code' => 1, 'message' => $validate]);
        // }
        $date = $request->post('date');
        $ratio = $request->post('ratio');
        $id = $request->post('id');
        $data = [
            'date' => $date,
            'ratio' => $ratio,
            'create_time' => time()
        ];
        $res = Db::table('proportion')->where('id', $id)->update($data);
        if ($res) {
            return json(['code' => 0, 'message' => '修改成功']);
        }
        return json(['code' => 1, 'message' => '修改失败']);

    }

    #比例设置
    public function withdrawRatio()
    {
        $list = Db::table('withdraw_ratio')->order('id asc')->select();
        return $this->render('withdrawRatio', [
            'list' => $list
        ]);
    }


    #比例添加
    public function withdrawratioadd(Request $request)
    {
        $validate = $this->validate($request->post(), 'app\admin\validate\WithdrawRatio');

        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }
        $name = $request->post('name');
        $ratio = $request->post('ratio');
        $data = [
            'name' => $name,
            'ratio' => $ratio,
            'create_time' => time()
        ];
        $res = Db::table('withdraw_ratio')->insert($data);
        if ($res) {
            return json(['code' => 0, 'message' => '添加成功']);
        }
        return json(['code' => 1, 'message' => '添加失败']);

    }


    #比例修改
    public function withdrawratioupd(Request $request)
    {
        $validate = $this->validate($request->post(), 'app\admin\validate\WithdrawRatio');

        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }
        $name = $request->post('name');
        $ratio = $request->post('ratio');
        $id = $request->post('id');
        // var_dump($id);
        // var_dump($name);
        // var_dump($ratio);die;
        $data = [
            'ratio' => $ratio,
            'create_time' => time()
        ];

        $res = Db::table('withdraw_ratio')->where('id', $id)->update($data);
        if ($res) {
            return json(['code' => 0, 'message' => '修改成功']);
        }
        return json(['code' => 1, 'message' => '修改失败']);

    }

    #财务记录
    public function walletLogList(Request $request)
    {
        $entity = MywalletLog::alias('um')->field('um.*,u.mobile,u.level');
        if ($keyword = $request->get('keyword')) {
            $entity->where('u.mobile', $keyword);
            $map['keyword'] = $keyword;
        }
        if ($types = $request->get('types')) {
            $entity->where('um.types', $types);
            $map['types'] = $types;
        }

        $userTable = (new \app\common\entity\User())->getTable();

        $list = $entity->leftJoin("{$userTable} u", 'um.user_id = u.id')
            ->order('um.create_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        $count = '0';

        return $this->render('walletLogList', [
            'list' => $list,
            'count' => $count,
        ]);
    }

    #交易状态记录
    public function marketList(Request $request)
    {
        $entity = \app\common\entity\Market::alias('um')->field('um.*,u.mobile,uu.mobile as from_mobile');
        if ($keyword = $request->get('keyword')) {
            $entity->where('u.mobile', $keyword);
            $map['keyword'] = $keyword;
        }
        if ($types = $request->get('types')) {
            $entity->where('um.status', $types);
            $map['types'] = $types;
        }

        $userTable = (new \app\common\entity\User())->getTable();

        $list = $entity->leftJoin("{$userTable} u", 'um.user_id = u.id')
            ->leftJoin('user uu','um.from_user_id = uu.id')
            ->order('um.create_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        $count = '0';

        return $this->render('marketList', [
            'list' => $list,
            'count' => $count,
        ]);
    }


    #套餐配置
    public function package()
    {
        $result = Db::table('user_level_send')->order('id asc')->select();
        return $this->render('package', ['list' => $result]);
    }

    #添加配置
    public function packageSetadd(Request $request)
    {
        $data['level'] = $request->post('level');
        $data['num'] = $request->post('num');
        $data['money'] = $request->post('money');
        $data['total_num'] = $request->post('total_num');
        $result = Db::table('user_level_send')->insert($data);
        if (!$result) {
            return ['code' => 1, 'message' => '添加失败'];
        }
        return ['code' => 0, 'message' => '添加成功'];
    }

    #更改配置
    public function packageSetsave(Request $request)
    {
        $id = $request->post('id');
        $result = Db::table('user_level_send')->where('id', $id)->find();
        if (!$result) {
            throw new AdminException('操作错误');
        }

        $log = array(
            'zhitui_num' => $request->post('zhitui_num'),
            'zhitui_level' => $request->post('zhitui_level'),
            'hash_num' => $request->post('hash_num'),
            'three' => $request->post('three'),
        );
        $res = Db::table('user_level_send')->where('id', $id)->update($log);
        if (!$res) {
            return ['code' => 1, 'message' => '修改失败'];
        }
        return ['code' => 0, 'message' => '修改成功'];
    }

}
