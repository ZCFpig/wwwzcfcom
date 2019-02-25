<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\KyBncLog;
use app\common\entity\Message;
use app\common\entity\Mywallet;
use app\common\entity\MywalletLog;
use app\common\entity\UnsealingList;
use app\common\entity\VoucherList;
use app\common\entity\XfBncLog;
use app\common\entity\FzBncLog;
use app\common\entity\YsBncLog;
use app\common\entity\WithdrawMoney;
use app\common\entity\WithdrawRatio;
use app\common\entity\User as userModel;
use app\common\entity\UserInviteCode;
use app\common\entity\UserMagicLog;
use app\common\entity\Export;
use app\common\entity\UserPackage;
use app\common\entity\UserProduct;
use app\common\entity\BlockLog;
use app\common\service\Users\Identity;
use app\common\entity\Product as productModel;
use think\Db;
use think\Request;

class User extends Admin
{

    /**
     * @power 会员管理|会员列表
     * @rank 1
     */
    public function index(Request $request)
    {
        $entity = userModel::field('u.*,c.invite_code')->alias('u');
        if ($level = $request->get('level')) {
            $entity->where('u.level', $level);
            $map['level'] = $level;
        }
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if ($certification = $request->get('certification')) {
            if ($certification == 2) {
                $entity->where('u.is_certification', -1);
                $entity->where('u.card_left', '<>', NULL);
                $entity->where('u.card_right', '<>', NULL);
            } else {
                $entity->where('u.is_certification', $certification);
            }
            $map['certification'] = $certification;
        }
        $orderStr = 'u.register_time DESC';
        if ($order = $request->get('order')) {
            $sort = $request->get('sort', 'desc');
            $orderStr = 'u.' . $order . ' ' . $sort;
            $map['order'] = $order;
            $map['sort'] = $sort;
        }
        $codeTable = (new UserInviteCode())->getTable();
        $list = $entity->leftJoin("$codeTable c", 'u.id = c.user_id')
            ->field('c.*,u.*,w.sell_number,w.freeze,w.number,w.can_sell,w.team_hash')
            ->leftJoin("my_wallet w", 'u.id = w.user_id')
            ->order($orderStr)
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        if (isset($map['sort'])) {
            $map['sort'] = $map['sort'] == 'desc' ? 'asc' : 'desc';
        }

        // dump($list);die;
        return $this->render('index', [
            'list' => $list,
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }

    /**
     * 导出数据
     */
    public function exportUser(Request $request)
    {
        $export = new Export();
        $entity = userModel::field('u.*,c.invite_code')->alias('u');
        if ($level = $request->get('level')) {
            $entity->where('u.level', $level);
            $map['level'] = $level;
        }
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        if ($certification = $request->get('certification')) {
            if ($certification == 2) {
                $entity->where('u.is_certification', -1);
                $entity->where('u.card_left', '<>', NULL);
                $entity->where('u.card_right', '<>', NULL);
            } else {
                $entity->where('u.is_certification', $certification);
            }
            $map['certification'] = $certification;
        }
        $orderStr = 'u.register_time DESC';
        if ($order = $request->get('order')) {
            $sort = $request->get('sort', 'desc');
            $orderStr = 'u.' . $order . ' ' . $sort;
            $map['order'] = $order;
            $map['sort'] = $sort;
        }
        $codeTable = (new UserInviteCode())->getTable();
        $list = $entity->leftJoin("$codeTable c", 'u.id = c.user_id')
            ->field('c.*,u.*,w.ky_number,w.freeze,w.number as ys_number,b.number as blocknumber')
            ->leftJoin("block_wallet b", 'u.id = b.user_id')
            ->leftJoin("my_wallet w", 'u.id = w.user_id')
            ->order($orderStr)
            ->select();
        foreach ($list as $key => &$value) {
            $p = $value->getParentInfo();
            if ($p) {
                $value['p_nick_name'] = $p['nick_name'];
                $value['p_mobile'] = $p['mobile'];
            } else {
                $value['p_nick_name'] = '无';
                $value['p_mobile'] = '无';
            }
            $team = $value->getTeamInfo();
            $value['total'] = $team['total'];
            $value['rate'] = $team['rate'];
            if ($value['is_certification'] == 1) {
                $value['certification'] = '已认证';
            } else {
                $value['certification'] = '未认证';
            }
            if ($value->level == '1') {
                $value['level1'] = 'A级';
            } elseif ($value->level == '2') {
                $value['level1'] = 'B级';
            } elseif ($value->level == '3') {
                $value['level1'] = 'C级';
            } elseif ($value->level == '4') {
                $value['level1'] = 'D级';
            } elseif ($value->level == '5') {
                $value['level1'] = 'E级';
            } elseif ($value->level == '-1') {
                $value['level1'] = '节点';
            } elseif ($value->level == '0') {
                $value['level1'] = '体验';
            }
        }
        // dump($list);die;

        $filename = '会员列表';
        $header = array('会员账号', '会员等级', '邀请码', '上级账号', '首页资产', '原始资产', '可用资产', '孵化资产', '注册时间', '注册ip', '是否认证');
        $index = array('mobile', 'level1', 'invite_code', 'p_mobile', 'blocknumber', 'ys_number', 'ky_number', 'freeze', 'register_time', 'register_ip', 'certification');
        $export->createtable($list, $filename, $header, $index);
    }

    public function changeTrad(Request $request)
    {
        $type = $request->post('type');
        $status = $request->post('status');
        $id = (int)$request->post('id');

        $user = userModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => 0, 'message' => '会员不存在']);
        }
        if ($type == 'buy') {
            if ($user->is_buy == $status) {
                return json(['code' => 0, 'message' => '会员购买状态没变']);
            }
            $user->is_buy = $status;
            if ($user->save()) {
                return json(['code' => 1, 'message' => '操作成功']);
            } else {
                return json(['code' => 1, 'message' => '操作失败']);
            }
        }
        if ($type == 'sale') {
            if ($user->is_sale == $status) {
                return json(['code' => 0, 'message' => '会员购买状态没变']);
            }
            $user->is_sale = $status;
            if ($user->save()) {
                return json(['code' => 1, 'message' => '操作成功']);
            } else {
                return json(['code' => 1, 'message' => '操作失败']);
            }
        }
        if ($type == 'top') {
            if ($user->is_top == $status) {
                return json(['code' => 0, 'message' => '会员置顶状态没变']);
            }
            $user->is_top = $status;
            if ($user->save()) {
                return json(['code' => 1, 'message' => '操作成功']);
            } else {
                return json(['code' => 1, 'message' => '操作失败']);
            }
        }
    }

    /**
     * 导出茶园明细数据
     * @method get
     */
    public function exportMagic(Request $request)
    {
        $export = new Export();
        $entity = UserProduct::alias('up')->field('up.*,u.mobile, p.product_name, p.yield_max, p.yield_min, p.rate_min, p.rate_max, p.period');
        if ($keyword = $request->get('keyword')) {
            $entity->where('u.mobile', $keyword);
            $map['keyword'] = $keyword;
        }

        if ($type = $request->get('type')) {
            $entity->where('up.types', $type);
            $map['type'] = $type;
        }

        if ($product_id = $request->get('product_id')) {
            $entity->where('up.product_id', $product_id);
            $map['product_id'] = $product_id;
        }

        if ($status = $request->get('status')) {
            if ($status == 2) {
                $entity->where('up.status', 0);
            } elseif ($status == 1) {
                $entity->where('up.status', $status);
            }
            $map['status'] = $status;
        }

        $list = $entity->leftJoin("user u", 'up.user_id = u.id')
            ->leftJoin("product p", 'up.product_id = p.id')
            ->order('up.buy_time', 'desc')
            ->select();
        foreach ($list as $key => &$value) {
            $value['buy_time'] = $value->getBuyTime();
            $value['end_time'] = $value->getEndTime();
            if ($value['status'] == 1) {
                $value['status'] = '运行中';
            } else {
                $value['status'] = '已过期';
            }
            $value['rate'] = $value['rate_max'] . "-" . $value['rate_min'];
            $value['yield'] = $value['yield_max'] . "-" . $value['yield_min'];

            $value['types'] = $value->getType();
        }
        $filename = '茶园明细列表';
        $header = array('产品编号', '会员账号', '茶园类型', '购买时间', '到期时间', '开采率(kb/s)', '日产量(金币)', '昨日收益', '总收益', '以收益天数', '总天数', '状态', '来源');
        $index = array('product_number', 'mobile', 'product_name', 'buy_time', 'end_time', 'rate', 'yield', 'yestoday', 'total', 'balance_day', 'period', 'status', 'types');
        $export->createtable($list, $filename, $header, $index);
    }

    /**
     * 导出充值明细数据
     * @method get
     */
    public function exportRecharge(Request $request)
    {
        $export = new Export();
        $entity = UserMagicLog::alias('um')->field('um.*,u.mobile');
        if ($keyword = $request->get('keyword')) {
            $entity->where('u.mobile', $keyword);
            $map['keyword'] = $keyword;
        }
        if ($type = $request->get('type')) {
            $entity->where('um.types', $type);
            $map['type'] = $type;
        }

        $userTable = (new \app\common\entity\User())->getTable();

        $list = $entity->leftJoin("{$userTable} u", 'um.user_id = u.id')
            ->order('um.create_time', 'desc')
            ->select();
        foreach ($list as $key => &$value) {
            $value['types'] = $value->getType($value['types']);
        }
        $filename = '充值明细列表';
        $header = array('id', '会员账号', '金币数量', '变化前', '变化后', '类型', '备注', '时间');
        $index = array('id', 'mobile', 'magic', 'old', 'new', 'types', 'remark', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }

    /**
     * @power 会员管理|认证会员列表
     */
    public function certificationlist(Request $request)
    {
        $entity = userModel::field('u.*,c.invite_code')->alias('u');
        if ($level = $request->get('level')) {
            $entity->where('u.level', $level);
            $map['level'] = $level;
        }
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        //
        if ($is_certification = $request->get('is_certification', 1)) {
            $entity->where('u.is_certification', $is_certification);
            $map['is_certification'] = $is_certification;
        }
        $codeTable = (new UserInviteCode())->getTable();
        $list = $entity->leftJoin("$codeTable c", 'u.id = c.user_id')
            ->order('u.register_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);
        return $this->render('index', [
            'list' => $list,
            'queryStr' => isset($map) ? http_build_query($map) : '',
        ]);
    }

    /**
     * @power 会员管理|充值明细
     * @method GET
     */
    public function magicList(Request $request)
    {
        $entity = BlockLog::alias('um')->field('um.*,u.mobile');
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

        return $this->render('magic', [
            'list' => $list,
            'count' => $count,
        ]);
    }

    /**
     * @power 会员管理|茶园明细
     * @method GET
     */
    public function magicboxlist(Request $request)
    {
        $entity = UserProduct::alias('up')->field('up.*,u.mobile, p.product_name, p.yield_max, p.yield_min, p.rate_min, p.rate_max, p.period');
        if ($keyword = $request->get('keyword')) {
            $entity->where('u.mobile', $keyword);
            $map['keyword'] = $keyword;
        }

        if ($type = $request->get('type')) {
            $entity->where('up.types', $type);
            $map['type'] = $type;
        }

        if ($product_id = $request->get('product_id')) {
            $entity->where('up.product_id', $product_id);
            $map['product_id'] = $product_id;
        }

        if ($status = $request->get('status')) {
            if ($status == 2) {
                $entity->where('up.status', 0);
            } elseif ($status == 1) {
                $entity->where('up.status', $status);
            }
            $map['status'] = $status;
        }

        $list = $entity->leftJoin("user u", 'up.user_id = u.id')
            ->leftJoin("product p", 'up.product_id = p.id')
            ->order('up.buy_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);

        $productList = productModel::select();
        $tmpPro = [];
        foreach ($productList as $v) {
            $tmpPro[$v['id']] = $v['product_name'];
        }
        //统计购买类型
        $resDataList = UserProduct::field('COUNT(*) as count,product_id,types')->group('product_id,types')->select();
        $resDataList = $resDataList->toArray();
        $dataList = [];
        foreach ($resDataList as $k => $v) {
            if (!isset($dataList[$v['product_id']])) {
                $dataList[$v['product_id']]['name'] = $tmpPro[$v['product_id']];
            }
            $dataList[$v['product_id']]['countList'][$v['types']] = $v['count'];
        }
        return $this->render('magicboxlist', [
            'list' => $list,
            'productList' => $productList,
            'dataList' => $dataList,
        ]);
    }

    /**
     * @power 会员管理|茶园明细|撤销茶园
     * @method POST
     */
    public function deleteUserProduct(Request $request)
    {
        $id = $request->post('id');
        $res = UserProduct::destroy($id);
        if ($res) {
            return ['code' => 0, 'message' => '操作成功'];
        } else {
            throw new AdminException('操作失败');
        }
    }

    /**
     * @power 会员管理|会员列表@添加会员
     */
    public function create()
    {
        return $this->render('edit', ['levelcate' => \app\common\entity\UserPackage::getAllCate()]);
    }

    /**
     * @power 会员管理|会员列表@编辑会员
     */
    public function edit($id)
    {
        $entity = userModel::where('id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('edit', [
            'info' => $entity,
            'levelcate' => \app\common\entity\UserPackage::getAllCate(),
        ]);
    }

    /**
     * @power 会员管理|会员列表@充值魔石
     * @method GET
     */
    public function recharge($id)
    {
        $entity = userModel::where('id', $id)->find();
        // var_dump($entity);die;
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('recharge', [
            'info' => $entity,
        ]);
    }

    /**
     * @power 会员管理|会员列表@充值魔石
     * @method POST
     */
    public function saveRecharge($id, Request $request)
    {
        $remark = $request->post("remark");
        $types = $request->post('types');
        if ($types == '5') {
            
            $coin = ($request->post('magic')) * 10;
        }else {
            $coin = $request->post('magic');
            
        }
        // if (!preg_match('/^[0-9]+.?[0-9]*$/', $coin)) {
        //     throw new AdminException('输入的数量必须为正整数或者小数');
        // }


        if ($types == '1') {
            $types1 = 'number';
        } elseif ($types == '2') {
            $types1 = 'freeze';
        } elseif ($types == '3') {
            $types1 = 'sell_number';
        } elseif ($types == '4') {
            $types1 = 'can_sell';
        }elseif ($types == '5') {
            $types1 = 'team_hash';
        }
        if ($coin < 0) {
            $coin1 = abs($coin);
            $inslog = (new MywalletLog())->addLog($id, $coin1, $types1, $remark, $types, 2);

            $updWallet = Mywallet::where('user_id', $id)->setDec($types1, $coin1);
        }else {
            $inslog = (new MywalletLog())->addLog($id, $coin, $types1, $remark, $types, 1);

            $updWallet = Mywallet::where('user_id', $id)->setInc($types1, $coin);
        }

        if (!$updWallet) {
            throw new AdminException('充值失败');
        }
        return ['code' => 0, 'message' => '充值成功'];
    }

    /**
     * @power 会员管理|会员列表@充值茶园
     * @method GET
     */
    public function rechargemagic($id)
    {
        $entity = userModel::where('id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        //得到所有的茶园
        $productList = productModel::select();

        return $this->render('rechargemagic', [
            'info' => $entity,
            'productList' => $productList
        ]);
    }

    /**
     * @power 会员管理|会员列表@充值魔石
     * @method POST
     */
    public function saveMagic(Request $request)
    {
        // $magic = $request->post('magic');
        // if (!preg_match('/^[0-9]+?[0-9]*$/', $magic)) {
        //     throw new AdminException('输入的数量必须为正整数');
        // }

        $id = $request->post('id');
        $entity = $this->checkInfo($id);

        $product_id = $request->post('product_id');

        $model = new UserProduct();
        $result = $model->addInfo($id, $product_id, 1);
        if (!$result) {
            throw new AdminException('充值失败');
        }


        return ['code' => 0, 'message' => '充值成功'];
    }

    /**
     * @power 会员管理|会员列表@添加会员
     */
    public function save(Request $request)
    {
        $result = $this->validate($request->post(), 'app\admin\validate\UserForm');

        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\common\service\Users\Service();
        if ($service->checkMobile($request->post('mobile'))) {
            throw new AdminException('电话号码已被注册,请重新填写');
        }

        // Db::startTrans();

        $userId = $service->addUser($request->post());

        if (!$userId) {
            throw new \Exception('保存失败');
        }


        $inviteCode = new UserInviteCode();
        if (!$inviteCode->saveCode($userId)) {
            throw new \Exception('保存失败');
        }
        $my_wallet_id = Db('my_wallet')->insertGetId(['user_id' => $userId]);
        // var_dump($my_wallet_id);die;

        if ($my_wallet_id ) {
            // Db::commit();
            return json(['code' => 0, 'toUrl' => url('/admin/user')]);

        }

        // Db::rollback();

        // throw new AdminException($e->getMessage());

    }

    /**
     * @power 会员管理|会员列表@编辑会员
     */
    public function update(Request $request, $id)
    {
        $entity = $this->checkInfo($id);

        $result = $this->validate($request->post(), 'app\admin\validate\UserEditForm');

        if (true !== $result) {
            return json()->data(['code' => 1, 'message' => $result]);
        }

        $service = new \app\common\service\Users\Service();
        $result = $service->updateUser($entity, $request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/user')]);
    }

    /**
     * @power 会员管理|会员列表@禁用会员
     */
    public function delete($id)
    {
        $entity = $this->checkInfo($id);

        $entity->forbidden_time = time();
        $entity->status = \app\common\entity\User::STATUS_FORBIDDED;

        if (!$entity->save()) {
            throw new AdminException('禁用失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    /**
     * @power 会员管理|会员列表@解禁会员
     * @method POST
     */
    public function unforbidden($id)
    {
        $entity = $this->checkInfo($id);

        $entity->forbidden_time = 0;
        $entity->status = \app\common\entity\User::STATUS_DEFAULT;

        if (!$entity->save()) {
            throw new AdminException('解禁失败');
        }
        return json(['code' => 0, 'message' => 'success']);
    }

    /**
     * @power 会员管理|会员列表@认证会员
     * @method GET
     */
    public function certification($id)
    {
        $entity = userModel::where('id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('certification', [
            'info' => $entity,
        ]);
    }

    /**
     * @power 会员管理|会员列表@认证会员
     * @method POST
     */
    public function certificationPass(Request $request, $id, $status)
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($id);

        $entity = $this->checkInfo($id);
        if (!$status) {
            return json(['code' => 0, 'message' => '状态不对']);
        }
        $certification_fail = $request->post("certification_fail");

        $entity->is_certification = $status;
        $entity->certification_fail = $certification_fail;

        if (!$entity->save()) {
            throw new AdminException('认证失败');
        }
        //认证通过送茶园
        if ($status == 1) {
            $model = new \app\index\model\User();
            $res = $model->sendRegisterReward($entity);
//            $user = new userModel();
//            $res1 = $user->recommendReward($entity->pid);
        }
        $identity->delCache($id);

        return json(['code' => 0, 'message' => 'success']);
    }

    /**
     * @power 会员管理|会员列表@手动升级
     * @method POST
     */
    public function level(Request $request)
    {
        if ($request->isPost()) {
            $userId = intval($request->post('user_id'));
            $level = intval($request->post('level'));
            $isReward = intval($request->post('is_reward'));

            $user = \app\common\entity\User::where('id', $userId)->find();
            if (!$user) {
                throw new AdminException('会员不存在');
            }
            if ($user->level == $level) {
                throw new AdminException('会员已是lv' . $level);
            }
            //直接升级
            $user->level = $level;
            if (!$user->save()) {
                throw new AdminException('升级失败');
            }
            //升级处理
            if ($isReward) {
                //赠送奖励
                $model = new \app\common\service\Level\Service();
                $reward = $model->getReward($level);
                $model->sendUserProduct($reward['product_id'], $reward['number'], $user->id);
            }
            return json(['code' => 0, 'message' => '升级成功']);
        }
    }

    private function checkInfo($id)
    {
        $entity = userModel::where('id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }


    #套餐配置
    public function package()
    {
        $result = UserPackage::order('id asc')->select();
        return $this->render('package', ['list' => $result]);
    }

    #添加配置
    public function packageSetadd(Request $request)
    {
        $data['level'] = $request->post('level');
        $data['num'] = $request->post('num');
        $data['money'] = $request->post('money');
        $data['total_num'] = $request->post('total_num');
        $result = UserPackage::insert($data);
        if (!$result) {
            return ['code' => 1, 'message' => '添加失败'];
        }
        return ['code' => 0, 'message' => '添加成功'];
    }

    #更改配置
    public function packageSetsave(Request $request)
    {
        $id = $request->post('id');
        $result = UserPackage::where('id', $id)->find();
        if (!$result) {
            throw new AdminException('操作错误');
        }
        $num = $request->post('num');
        $total_num = $request->post('total_num');

        $log = array(
            'num' => $num,
            'total_num' => $total_num
        );
        $res = UserPackage::where('id', $id)->update($log);
        if (!$res) {
            return ['code' => 1, 'message' => '修改失败'];
        }
        return ['code' => 0, 'message' => '修改成功'];
    }


    #消费资产明细
    public function XfBncList(Request $request)
    {
        $entity = XfBncLog::alias('um')->field('um.*,u.mobile,u.level');
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

        return $this->render('xfbncloglist', [
            'list' => $list,
            'count' => $count,
        ]);
    }

    #导出消费资产明细
    public function exportxfBncLog(Request $request)
    {
        $export = new Export();

        $entity = XfBncLog::alias('um')->field('um.*,u.mobile,u.level');
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
            ->select();
        $filename = '消费资产明细';
        $header = array('id', '会员账号', '数量', '变化前', '变化后', '类型', '时间');
        $index = array('id', 'mobile', 'number', 'old', 'new', 'types', 'create_time');
        $export->createtable($list, $filename, $header, $index);

    }


    #申述申请
    public function withdrawmoney(Request $request)
    {
        $entity = Message::alias('um')->field('um.*,u.mobile,u.level,ml.num as market_num');
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
            ->leftJoin('market_list ml', 'um.market_id = ml.id')
            ->order('um.create_time', 'desc')
            ->paginate(15, false, [
                'query' => isset($map) ? $map : []
            ]);

        return $this->render('withdrawmoney', [
            'lists' => $list,
        ]);
    }

    #申述通过
    public function withdrawmoney_yes(Request $request)
    {
        $market_id = $request->get('id');
        $message_id = $request->get('message_id');
        $updMessage = Message::where('message_id',$message_id)->update(['status'=>2]);
        $marketInfo = \app\common\entity\Market::where('id', $market_id)->find();
//            dump($marketInfo);die;
        $upd = \app\common\entity\Market::where('id', $market_id)->update(['end_time' => date('Y-m-d H:i:s', time()), 'status' => 4]);

        $marketInfoMobile = User::where('id', $marketInfo['from_user_id'])->value('mobile');
        //交易完成 增加买入用户钱包资产
        $insCanShellLog = (new MywalletLog())->addLog($marketInfo['user_id'], $marketInfo['num'], 'number', '买入成功', 1, 1);
        $can_sell_ratio = \app\common\entity\User::where('id', $marketInfo['user_id'])->value('can_sell_ratio');
        $updwallet_number = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('number', $marketInfo['num']);
        $updwallet_can_sell = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('can_sell', $marketInfo['num'] * $can_sell_ratio);
        $updwallet_total = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('total_num', $marketInfo['num']);
        $updMachine = (new \app\common\entity\Mywallet())->getMachine($marketInfo['user_id']);
        if ($upd) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);

    }

    #申述拒绝
    public function withdrawmoney_no(Request $request)
    {
        $market_id = $request->get('id');
        $message_id = $request->get('message_id');
        $updMessage = Message::where('message_id',$message_id)->update(['status'=>3]);
        $marketInfo = \app\common\entity\Market::where('id', $market_id)->find();
        $toshell_ratio = WithdrawRatio::where('key', 'toShell')->value('ratio');

        $shell_num_raio = $marketInfo['num'] + bcmul($marketInfo['num'], $toshell_ratio, 5);
        $upd = \app\common\entity\Market::where('id', $market_id)->update(['status' => 5]);
        //添加返还可售资产记录
        $insShellNumLog = (new MywalletLog())->addLog($marketInfo['from_user_id'], $shell_num_raio, 'sell_number', '交易失败退回', 3, 1);
        //添加返还可用额度记录
        $insCanShellLog = (new MywalletLog())->addLog($marketInfo['from_user_id'], $marketInfo['num'], 'can_sell', '交易失败退回', 4, 1);
        //返还钱包可售资产
        $updShellNumber = \app\common\entity\Mywallet::where('user_id', $marketInfo['from_user_id'])->setInc('sell_number', $shell_num_raio);
        //返还钱包可用额度
        $updCanShell = \app\common\entity\Mywallet::where('user_id', $marketInfo['from_user_id'])->setInc('can_sell', $marketInfo['num']);
        if ($upd) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);


    }

    #解封列表
    public function unsealingList(Request $request)
    {
        $entity = UnsealingList::alias('um')->field('um.*,u.mobile,u.level');
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

        return $this->render('unsealingList', [
            'lists' => $list,
        ]);
    }

    #解封通过
    public function unsealing_yes(Request $request)
    {
        $id = $request->get('id');
        $upd = UnsealingList::where('id',$id)->update(['status'=> 2]);
        $user_id = UnsealingList::where('id',$id)->value('user_id');

        $upd_user_status = \app\common\entity\User::where('id',$user_id)->update(['status'=>1]);
        if ($upd_user_status) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);

    }

    #解封拒绝
    public function unsealing_no(Request $request)
    {
        $id = $request->get('id');
        $upd = UnsealingList::where('id',$id)->update(['status'=> 3]);
        if ($upd) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);

    }


    #支付凭证
    public function voucherList(Request $request)
    {
        $entity = VoucherList::alias('um')->field('um.*,u.mobile,u.level');
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

        return $this->render('voucherList', [
            'lists' => $list,
        ]);
    }

    #支付凭证通过
    public function voucherList_yes(Request $request)
    {
        $voucher_id = $request->get('id');
        $upd1 = VoucherList::where('voucher_id',$voucher_id)->update(['status'=>2]);
        $voucherInfo = VoucherList::where('voucher_id',$voucher_id)->find();
        $insLog = (new MywalletLog())->addLog($voucherInfo['user_id'], $voucherInfo['num'], 'voucher_num', '认筹', 5, 1);
        $upd = \app\common\entity\Mywallet::where('user_id',$voucherInfo['user_id'])->setInc('voucher_num', $voucherInfo['num']);
        $updWithdrawRatio = WithdrawRatio::where('key','unsealing_has_num')->setInc('ratio', $voucherInfo['num']);
        if ($upd) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);

    }

    #支付凭证拒绝
    public function voucherList_no(Request $request)
    {
        $voucher_id = $request->get('id');
        $upd = VoucherList::where('voucher_id',$voucher_id)->update(['status'=>3]);
        if ($upd) {

            return json()->data(['code' => 0, 'message' => '操作成功']);
        }
        return json()->data(['code' => 1, 'message' => '操作失败']);


    }


    #内排首码
    public function firstList(Request $request)
    {
        $entity = VoucherList::alias('um')->where('u.pid',0)->where('um.status',2)->group('um.user_id')
            ->field('um.user_id,sum(um.num) as total_num,u.mobile,u.level');
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
        $aaa = Db::table('voucher_list')->getLastSql();
        // dump($aaa);die;
        return $this->render('firstList', [
            'lists' => $list,
        ]);
    }


    #查看下级
    public function firstToNext(Request $request)
    {
        $user_id = $request->get('id');
        $userModel = new \app\common\entity\User();
        $childs = $userModel->getChildsInfoId($user_id);
        $result = array_reduce($childs, function ($result, $value) {
            return array_merge($result, array_values($value));
        }, array());
        $entity = VoucherList::alias('um')->whereIn('u.id',$result)->where('um.status',2)->group('um.user_id')
            ->field('um.user_id,sum(um.num) as total_num,u.mobile,u.level');
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
        $count = 0;
        foreach ($list as $a) {
        	$count = $a['total_num'] + $count;
        }

        return $this->render('childList', [
            'lists' => $list,
            'count' => $count,
        ]);

    }

}
