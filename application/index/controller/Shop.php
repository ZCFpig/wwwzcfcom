<?php

namespace app\index\controller;

use app\common\entity\Goods;
use app\common\entity\MywalletLog;
use app\common\entity\MachineList;

use app\common\entity\Orders;
use app\common\entity\Proportion;
use app\common\entity\UserPackage;
use app\common\entity\WithdrawRatio;
use app\index\model\SendCode;
use think\Db;
use think\Request;
use app\common\entity\User;


class Shop extends Base
{

    public function index(Request $request)
    {
        if ($request->isPost()) {

            $page = $request->post('page');
            $limit = $request->post('limit');
            $category = $request->post('category_id');
            $keyword = htmlspecialchars(trim($request->post('keyword')));
            $goodsModel = new \app\common\entity\Goods();
            if (!empty($keyword) && !empty($category)) {
                // echo 'hck';
                $goodsLists = $goodsModel->hckGoodsLists($category, $keyword, $page, $limit);
            } elseif (!empty($category) && empty($keyword)) {
                // echo 'hc';

                $goodsLists = $goodsModel->hcGoodsLists($category, $page, $limit);
            } elseif (!empty($keyword) && empty($category)) {
                // echo 'hk';
                $goodsLists = $goodsModel->hkGoodsLists($keyword, $page, $limit);

            } else {

                $goodsLists = $goodsModel->getGoodsLists($page, $limit);

            }
            $usd = Proportion::order('create_time desc')->value('ratio');
            //商品列表
            if ($goodsLists) {
                foreach ($goodsLists as $k => &$v) {
                    $pics = $goodsModel->getGoodsPic($v['id']);
                    $img = json_decode($v['goods_pic']);
                    $goodsLists[$k]['pics'] = $pics;
                    $goodsLists[$k]['img'] = $img[0];
                    $v['goods_prices1'] = round($v['goods_prices'] / $usd, 2);
                }
                return json(['code' => 0, 'message' => '获取成功', 'info' => $goodsLists]);
            }
            return json(['code' => 1, 'message' => '获取失败']);
        }
        if ($request->isGet()) {
            $category = Db::table('goods_category')->select();
            return json(['code' => 0, 'message' => '获取成功', 'info' => $category]);

        }


    }

    public function detail(Request $request)
    {
        $goodsId = $request->post('goods_id');
        $goodsModel = new \app\common\entity\Goods();
        $goodsDetail = $goodsModel->getGoodsDetail($goodsId);
        $goodsDetail['goods_content'] = (new \app\common\entity\Article())->updImgUrl($goodsDetail['goods_content']);
        //轮播图
        $goodsImgs = $goodsModel->getGoodsPic($goodsDetail['id']);
        if ($goodsDetail) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $goodsDetail, 'img' => $goodsImgs]);
        }
        return json(['code' => 1, 'message' => '获取失败']);


    }


    /*
     * @param page
     * 下拉加载更多
     * */
    public function getGoods(Request $request)
    {
        $start = $request->get('page');
        $goodsModel = new \app\common\entity\Goods();
        //商品列表
        $goodsLists = $goodsModel->getGoodsLists($start * 6, 6);
        foreach ($goodsLists as $k => $v) {
            $pics = $goodsModel->getGoodsPic($v['id']);
            $goodsLists[$k]['pics'] = $pics[0];
        }
        $data['code'] = 0;
        $data['data'] = $goodsLists;
        return jsonp($data);
    }


    #获取商品详情
    public function getGoodsInfo(Request $request)
    {
        $ratioInfo = Db::table('proportion')->field('usd')->order('create_time desc')->find();
        $usd = $ratioInfo['usd'];
        $goods_id = $request->get('goods_id');
        $goodsModel = new \app\common\entity\Goods();
        $goodsInfo = $goodsModel->getGoodsDetail($goods_id);
        $pics = $goodsModel->getGoodsPic($goodsInfo['id']);
//                dump($v);die;
        $img = json_decode($goodsInfo['goods_pic']);
        $goodsInfo['param'] = [
            'num' => $goodsInfo['goods_num'],
            'brand' => $goodsInfo['goods_brand'],
            'size' => $goodsInfo['goods_size'],
            'weight' => $goodsInfo['goods_weight']
        ];
        $goodsInfo['pics'] = $pics;
        $goodsInfo['img'] = $img;
        $goodsInfo['goods_prices'] = round($goodsInfo['goods_prices'] / $usd, 2);

        return $this->fetch("detail", [
            'goodsInfo' => $goodsInfo
        ]);

    }

    #确定订单
    public function readyOrder(Request $request)
    {
        $uid = $this->userId;
        $num = $request->get('num');
        $goods_id = $request->get('goods_id');
//        $usd = Db::table('proportion')->order('create_time desc')->field('usd')->find();

        $goodsInfo = Goods::where('id', $goods_id)->find();

        $imgarr = json_decode($goodsInfo['goods_pic']);
        $goodsInfo['img'] = $imgarr[0];
        $walletInfo = \app\common\entity\Mywallet::where('user_id',$uid)->find();
        $goodsInfo['wallet_num'] = $walletInfo['number'];

        return json(['code' => 0, 'message' => '获取成功', 'info' => $goodsInfo ]);


    }


    private function setOrderNumber($memberId, $goods_id)
    {
        return date('Ymd') . $memberId . date('His') . $goods_id;
    }

    #购买接口
    public function buyOrder( Request $request)
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }

        $walletInfo = \app\common\entity\Mywallet::where('user_id',$uid)->find();
        $goods_id = $request->post('goods_id');
        $pay_num = $request->post('num');
        $user_name = $request->post('user_name');
        $mobile = $request->post('mobile');
        $address = $request->post('address');
        if (!$mobile || !$user_name || !$address){
            return json(['code' => 1, 'message' => '信息未填写全']);
        }

        $user = User::where('id', $this->userId)->find();

        $trad_password = $request->post('trad_password');

        $service = new \app\common\service\Users\Service();
        $result = $service->checkSafePassword($trad_password, $user);
        if (!$result) {
            return json(['code' => 1, 'message' => '二级密码输入错误']);
        }

        $goodsInfo = Goods::where('id',$goods_id)->find();
        $total_money = $goodsInfo['goods_prices'] * $pay_num;
        if ($walletInfo['number'] < $total_money){
            return json(['code' => 1, 'message' => '猪猪钱包余额不足!']);
        }
        $updateMywallet = \app\common\entity\Mywallet::where('user_id',$uid)->setDec('number', $total_money);
        $insMywalletLog = (new MywalletLog())->addLog($uid, $total_money, 'number', '购买商品', 1, 2);
        $orderNumber = $this->setOrderNumber($uid, $goods_id);
        $data = [
            'order_number' => $orderNumber,
            'user_id' => $uid,
            'user_name' => $user_name,
            'mobile' => $mobile,
            'address' => $address,
            'price' => $goodsInfo['goods_prices'],
            'goods_id' => $goods_id,
            'pay_num' => $pay_num,
            'pay_money' => $total_money,
            'pay_time' => time(),
            'status' => '1',
            'create_time' => time(),
        ];
        $res = Orders::insert($data);
        if ($res){
            return json(['code' => 0, 'message' => '购买成功,等待平台发货!']);

        }

        return json(['code' => 1, 'message' => '购买失败!']);

    }

    public function clickAddr()
    {
        return $this->fetch("clickaddr");

    }

    #订单列表
    public function orderList(Request $request)
    {
        if ($request->isPost()) {

            $uid = $this->userId;
            $status = $request->post('status');
            $page = $request->post('page');
            $limit = $request->post('limit');
            if ($status == '0' || empty($status)) {

                $orderList = Db::table('orders')->where('user_id', $uid)->page($page)->limit($limit)->order('pay_time desc')->select();
            } else {

                $orderList = Db::table('orders')->where('status', $status)->where('user_id', $uid)->page($page)->order('pay_time desc')->limit($limit)->select();
            }
            foreach ($orderList as $k => &$v) {
                $goodsInfo = Db::table('goods')->where('id', $v['goods_id'])->find();
                $img = json_decode($goodsInfo['goods_pic']);
                $v['goods_title'] = $goodsInfo['goods_title'];
                $v['img'] = $img[0];

                $v['pay_time'] = date("Y-m-d H:i:s", $v['pay_time']);
            }

            // dump($orderList);die;
            return json(['code' => 0, 'message' => '获取成功', 'info' => $orderList]);

        }
        return $this->fetch("orderlist", ['status' => $request->get('status')]);

    }

    #获取上一级
    public function getParents($uid)
    {
        static $parent = [];
        static $level = 0;
        $userInfo = Db::table('user')->where('id', $uid)->find();

        $pid = $userInfo['pid'];
        $level++;
        if ($level > '8') {
            return;
        }
        if ($pid == '0') {
            return;
        }
        $parent["$level"] = $pid;
        $this->getParents($pid);
        return $parent;

    }

    public function xx()
    {
        $uid = 14426;
        $a = $this->getParents($uid);
        var_dump($a);
    }




    public function bncLog($table, $uid, $types, $number, $old, $new)
    {
        $log = [
            'user_id' => $uid,
            'types' => $types,
            'number' => $number,
            'old' => $old,
            'new' => $new,
            'create_time' => time(),
        ];
        $res = Db::table($table)->insert($log);
        // dump($res);die;
        return $res;
    }


    #提币 bnc->block
    public function getBlock(Request $request)
    {
        $uid = $this->userId;
        $number = $request->post('number');
        $trad_password = $request->post('trad_password');
        if ($number % 50 != 0) {
            return json(['code' => 1, 'message' => '数量必须为50的整倍数']);

        }
        $validate = $this->validate($request->post(), '\app\index\validate\GetBlock');
        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }

        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkSafePassword($trad_password, $user);

        if (!$result) {
            return json(['code' => 1, 'message' => '交易密码输入错误']);
        }
        $withdraw_ratio = Db::table('withdraw_ratio')->where('id', '1')->find();
        $ratio = $withdraw_ratio['ratio'];

        $newNumber = $number - $number * $ratio;
        Db::startTrans();

        $block = Db::table('block_wallet')->where('user_id', $uid)->find();
        $bnc = Db::table('my_wallet')->where('user_id', $uid)->find();
        if ($bnc['ky_number'] < $number) {
            return json(['code' => 1, 'message' => '可用余额不足']);
        }

        $newBlock = $block['number'] + $newNumber;
        $newBnc = $bnc['ky_number'] - $number;
        $updBlock = Db::table('block_wallet')->where('user_id', $uid)->update(['number' => $newBlock]);
//        dump($updBlock);die;
        $updBnc = Db::table('my_wallet')->where('user_id', $uid)->update(['ky_number' => $newBnc]);
        if ($updBlock && $updBnc) {

            $log = [
                'user_id' => $uid,
                'types' => '提币',
                'number' => '-' . $number,
                'old' => $bnc['ky_number'] ? $bnc['ky_number'] : 0,
                'new' => $newBnc,
                'create_time' => time(),
            ];
            $inslog = Db::table('kybnc_log')->insert($log);
            if ($inslog) {

                Db::commit();
                return json(['code' => 0, 'message' => '提币成功']);

            }

        }
        Db::rollback();
        return json(['code' => 1, 'message' => '提币失败']);


    }


    #确认收货
    public function chilkOrder(Request $request)
    {
        $order_id = $request->post('order_id');
        $res = Db::table('orders')->where('id', $order_id)->find();
        if ($res['status'] !== 2) {
            return json(['code' => 1, 'message' => '操作失败']);

        }
        $upd = Db::table('orders')->where('id', $order_id)->update(['status' => 3, 'finish_time' => time()]);
        if ($upd) {
            return json(['code' => 0, 'message' => '操作成功']);
        }
        return json(['code' => 1, 'message' => '操作失败']);

    }


    #订单详情
    public function orderdetail(Request $request)
    {
        $order_id = $request->post('order_id');
        $orderInfo = Db::table('orders')->where('id', $order_id)->find();
        if (!$orderInfo) {
            return json(['code' => 1, 'message' => '订单不存在']);

        }
        // var_dump($orderInfo);die;
        $goodsInfo = Db::table('goods')->where('id', $orderInfo['goods_id'])->find();
        $img = json_decode($goodsInfo['goods_pic']);
        $orderInfo['goods_title'] = $goodsInfo['goods_title'];
        $orderInfo['img'] = $img[0];
        $orderInfo['oneprice'] = $goodsInfo['goods_prices'];
        $orderInfo['pay_time'] = date("Y-m-d H:i:s", $orderInfo['pay_time']);
        if ($orderInfo['status'] != 1){
            $orderInfo['send_time'] = date("Y-m-d H:i:s", $orderInfo['send_time']);

        }else{
            $orderInfo['send_time'] = '等待发货中';

        }
        if ($orderInfo){

            return json(['code' => 0, 'message' => '获取成功','info' =>$orderInfo ]);
        }
        return json(['code' => 1, 'message' => '获取失败']);

    }


    #加入购物车
    public function addShopCar(Request $request)
    {
        $uid = $this->userId;
        $goods_id = $request->post('goods_id');
        $num = $request->post('num');
        $find = Db::table('shopcar')->where('uid', $uid)->where('goods_id', $goods_id)->find();
        if ($find) {
            $upd = Db::table('shopcar')->where('uid', $uid)->where('goods_id', $goods_id)->update(['num' => $find['num'] + $num]);
            if ($upd) {
                return json(['code' => 0, 'message' => '添加成功']);
            }
            // dump($upd);die;
            return json(['code' => 1, 'message' => '添加失败']);

        }
        $log = [
            'uid' => $uid,
            'goods_id' => $goods_id,
            'num' => $num,
            'create_time' => time(),
        ];

        $res = Db::table('shopcar')->insert($log);
        if ($res) {
            return json(['code' => 0, 'message' => '添加成功']);

        }
        return json(['code' => 1, 'message' => '添加失败']);

    }

    #购物车列表
    public function shopCarList(Request $request)
    {
        if ($request->isPost()) {
            $uid = $this->userId;
            $page = $request->post('page');
            $limit = $request->post('count');
            $list = Db::table('shopcar')->where('uid', $uid)->page($page)->limit($limit)->select();
            $ratioInfo = Db::table('proportion')->field('usd')->order('create_time desc')->find();
            $usd = $ratioInfo['usd'];
            foreach ($list as $k => &$v) {
                $goodsInfo = Db::table('goods')->where('id', $v['goods_id'])->find();
                $img = json_decode($goodsInfo['goods_pic']);
                $v['goods_title'] = $goodsInfo['goods_title'];
                $v['goods_prices'] = round($goodsInfo['goods_prices'] / $usd, 2);
                $v['img'] = $img[0];

            }
            if (empty($list)) {
                return json(['code' => 0, 'message' => '暂无数据']);

            }

            if ($list) {
                return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

            }
            return json(['code' => 1, 'message' => '获取失败']);
        }
        return $this->fetch("shopCarList");

    }

    #删除购物车
    public function delShopCar(Request $request)
    {

        $uid = $this->userId;
        $car_id = $request->post('id');
        // dump($car_id);die;
        $caridarr = explode(",", $car_id);
        if (!$uid || !$car_id) return json(['code' => 1, 'msg' => '缺少参数']);
        $res = Db::table('shopcar')->where(['id' => $caridarr, 'uid' => $uid])->delete();
        if ($res) {
            return json(['code' => 0, 'msg' => '删除成功']);
        } else {
            return json(['code' => 1, 'msg' => '删除失败']);
        }
    }

    #编辑购物车
    public function updShopCar(Request $request)
    {
        if ($request->isPost()) {

            $uid = $this->userId;
            $car_id = $request->post('id');
            $num = $request->post('num');
            $log = [
                'num' => $num,
                'create_time' => time(),
            ];
            $res = Db::table('shopcar')->where('id', $car_id)->update($log);
            if ($res) {
                return json(['code' => 0, 'message' => '编辑成功']);

            }
            return json(['code' => 1, 'message' => '编辑失败']);
        }
        return $this->fetch("updShopCar");

    }

    #首页矿机商城
    public function packageIndex()
    {
        $list = UserPackage::select();
        foreach ($list as $v) {
            $v['hour_num'] = $v['num'] / 24;
        }
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }


    #每日签到
    public function everydaySign()
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }
        $sign_time = User::where('id', $uid)->value('sign_time');
        $now = date('Ymd', time());
        $old = date('Ymd', $sign_time);
        if ($now == $old) {
            return json(['code' => 1, 'message' => '今日已签到']);

        }
        $everyday_sign = WithdrawRatio::where('key', 'everyday_sign')->value('ratio');
        $insLog = (new MywalletLog())->addLog($uid, $everyday_sign, 'number', '每日签到', 1, 1);
        $upd = \app\common\entity\Mywallet::where('user_id', $uid)->setInc('number', $everyday_sign);
        $updSign_time = User::where('id', $uid)->update(['sign_time' => time()]);
        if ($upd) {
            return json(['code' => 0, 'message' => '签到成功']);

        }
        return json(['code' => 1, 'message' => '签到失败']);
    }

    #交易大厅列表
    public function marketList(Request $request)
    {
        $list = \app\common\entity\Market::where('types', 1)->where('status', 1)
            ->limit($request->post('limit'))
            ->page($request->post('page'))
            ->order('create_time desc')
            ->select();
        $ratio = WithdrawRatio::where('key', 'toShell')->value('ratio');
        foreach ($list as &$v) {
            $v['ratio'] = $ratio;
        }
        $count = \app\common\entity\Market::where('types', 1)->where('status', 1)->count();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #我的买入列表
    public function buyInfoList(Request $request)
    {
        $uid = $this->userId;
        $list = \app\common\entity\Market::where('user_id', $uid)
            ->where('types', 1)
            ->limit($request->post('limit'))
            ->order('create_time desc')
            ->page($request->post('page'))
            ->select();
        $count = \app\common\entity\Market::where('user_id', $uid)
            ->where('types', 1)
            ->count();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #我的买入交易列表
    public function buyNowInfoList(Request $request)
    {
        $uid = $this->userId;
        $list = \app\common\entity\Market::where('user_id', $uid)->where('types', 1)->where('status', 2)
            ->limit($request->post('limit'))
            ->order('create_time desc')
            ->page($request->post('page'))
            ->select();
        $count = \app\common\entity\Market::where('user_id', $uid)->where('types', 1)->where('status', 2)->count();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #我的出售列表
    public function sellInfoList(Request $request)
    {
        $uid = $this->userId;
        $list = \app\common\entity\Market::where('from_user_id', $uid)
            ->limit($request->post('limit'))
            ->order('create_time desc')
            ->page($request->post('page'))
            ->select();
        $count = \app\common\entity\Market::where('from_user_id', $uid)->count();

        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #我的出售交易列表
    public function sellNowInfoList(Request $request)
    {
        $uid = $this->userId;
        $list = \app\common\entity\Market::where('from_user_id', $uid)->where('status', 'in','2,3')
            ->limit($request->post('limit'))
            ->order('create_time desc')
            ->page($request->post('page'))
            ->select();
        $count = \app\common\entity\Market::where('from_user_id', $uid)->where('status', 'in','2,3')->count();

        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);

        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #申请买入
    public function buyNum(Request $request)
    {
        $uid = $this->userId;
        if ($request->isPost()) {
        	$is_up_sell = WithdrawRatio::where('key', 'is_up_sell')->value('ratio');
    		if ($is_up_sell == 0) {
       	     	return json(['code' => 1, 'message' => '交易关闭']);
    		}
            $is_up = WithdrawRatio::where('key', 'num_up')->value('ratio');
            if ($is_up == 1) {
                return json(['code' => 1, 'message' => '内排已开启，不可申请']);
            
            }
            $user_info = User::where('id', $uid)->find();
            if ($user_info['status'] == -1) {
                return json(['code' => 1, 'message' => '用户已被封禁']);

            }
            $market_count = \app\common\entity\Market::where('user_id', $uid)->where('status', 1)->count();
            $can_buy_num = WithdrawRatio::where('key', 'can_buy_num')->value('ratio');
            if ($market_count >= $can_buy_num) {
                return json(['code' => 1, 'message' => '已到最大挂买数']);
            }
            $now_time = date('H', time());
            $first_start_time = WithdrawRatio::where('key', 'first_time_start')->value('ratio');
            $first_end_time = WithdrawRatio::where('key', 'first_time_end')->value('ratio');
            $time_start = WithdrawRatio::where('key', 'time_start')->value('ratio');
            $time_end = WithdrawRatio::where('key', 'time_end')->value('ratio');
            $is_new = User::where('id', $uid)->value('is_new');
            if ($is_new == 0) {
                if ($now_time < $first_start_time || $now_time > $time_end) {
                    return json(['code' => 1, 'message' => '新用户请在' . floatval($first_start_time) . '-' . floatval($time_end) . '点申请']);
                }
            } else {
                if ($now_time < $time_start || $now_time > $time_end) {
                    return json(['code' => 1, 'message' => '用户请在' . floatval($time_start) . '-' . floatval($time_end) . '点申请']);
                }
            }
            if (!$request->post('num')) {
                return json(['code' => 1, 'message' => '缺少参数']);
            }
            $sq_num = $request->post('num');
            if ($sq_num != 5 && $sq_num != 10 && $sq_num != 20 && $sq_num != 50 && $sq_num != 100 && $sq_num != 150 && $sq_num != 200 ) {
                return json(['code' => 1, 'message' => '挂买数量不正确']);
            }
            $buy_interval = WithdrawRatio::where('key', 'buy_interval')->value('ratio');
            $last_market = \app\common\entity\Market::where('user_id', $uid)->order('create_time desc')->value('create_time');
            if ( (time() - $last_market) < $buy_interval ) {
                return json(['code' => 1, 'message' => '申请间隔不足'.floatval($buy_interval) .'秒']);
            }
            $market = (new \app\common\entity\Market())->addMarketList($uid, $request->post());
            if ($market) {
                return json(['code' => 0, 'message' => '申请成功']);

            }
            return json(['code' => 1, 'message' => '申请失败']);
        }
    }

    #撤销挂买
    public function delBuy(Request $request)
    {
        if ($request->isPost()) {
            $uid = $this->userId;
            $market_id = $request->post('market_id');
            $marketInfo = \app\common\entity\Market::where('id', $market_id)
                ->find();
            if ((time() - strtotime($marketInfo['create_time'])) < 30 * 60) {
                return json(['code' => 1, 'message' => '匹配时间未到30分钟']);
            }
            $del_market = \app\common\entity\Market::where('id', $market_id)->update(['status' => 6]);
            if ($del_market) {
                return json(['code' => 0, 'message' => '撤销成功']);
            }
            return json(['code' => 1, 'message' => '撤销失败']);

        }
    }

    #卖出
    public function shellNum(Request $request)
    {
        $uid = $this->userId;
        if ($request->isPost()) {
        	$is_up_sell = WithdrawRatio::where('key', 'is_up_sell')->value('ratio');
    		if ($is_up_sell == 0) {
            	return json(['code' => 1, 'message' => '交易关闭']);
    		}
            $is_up = WithdrawRatio::where('key', 'num_up')->value('ratio');
            if ($is_up == 1) {
                return json(['code' => 1, 'message' => '内排已开启，不可卖出']);
            
            }
            $user_info = User::where('id', $uid)->find();
            if ($user_info['status'] == -1) {
                return json(['code' => 1, 'message' => '用户已被封禁']);

            }
            $everyday_sell_num = WithdrawRatio::where('key', 'everyday_sell_num')->value('ratio');
            // $can_sell_num = WithdrawRatio::where('key', 'can_sell_num')->value('ratio');
            $now = date('Ymd', time());

            if (date('Ymd', $user_info['sell_time']) == $now) {

                // if ($user_info['today_sell'] >= $everyday_sell_num) {
                //     return json(['code' => 1, 'message' => '挂卖次数用完,请明日再来']);
                // }
            } else {
                $updtoday_sell = User::where('id', $uid)->update(['today_sell' => 0]);
            }

            $market_id = $request->post('market_id');
            $marketInfo = \app\common\entity\Market::where('id', $market_id)->find();
            // if ($marketInfo['num'] > $can_sell_num) {
            //     return json(['code' => 1, 'message' => '超过可卖出最大数量']);

            // }
            if ($marketInfo['status'] != 1) {
                return json(['code' => 1, 'message' => '该订单不存在']);
            }

            $shell_num = $marketInfo['num'];

            $mywallet = \app\common\entity\Mywallet::where('user_id', $uid)->find();

            $toshell_ratio = WithdrawRatio::where('key', 'toShell')->value('ratio');

            $shell_num_raio = $shell_num + bcmul($shell_num, $toshell_ratio, 5);

            if ($mywallet['sell_number'] < $shell_num_raio || $mywallet['can_sell'] < $shell_num) {
                return json(['code' => 1, 'message' => '可售资产或可用额度不足']);
            }

            $user = User::where('id', $this->userId)->find();

            $card_id = $request->post('card_id');
            if ($card_id != $user['card_id']) {
                return json(['code' => 1, 'message' => '身份证号输入错误']);

            }

            $trad_password = $request->post('trad_password');

            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($trad_password, $user);
            if (!$result) {
                return json(['code' => 1, 'message' => '二级密码输入错误']);
            }
            $marketInfoMobile = User::where('id', $marketInfo['user_id'])->value('mobile');

            //添加卖出可售资产记录
            $insShellNumLog = (new MywalletLog())->addLog($uid, $shell_num_raio, 'sell_number', '出售成功', 3, 2);
            //添加卖出可用额度记录
            $insCanShellLog = (new MywalletLog())->addLog($uid, $shell_num, 'can_sell', '出售成功', 4, 2);
            //扣除钱包可售资产
            $updShellNumber = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('sell_number', $shell_num_raio);
            //扣除钱包可用额度
            $updCanShell = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('can_sell', $shell_num);
            //更改订单状态
            $updMarketStatus = \app\common\entity\Market::where('id', $market_id)->update(['from_user_id' => $uid, 'status' => 2, 'matching_time' => date('Y-m-d H:i:s', time())]);
            if ($updShellNumber && $updMarketStatus && $updCanShell) {
                $updsell_time = User::where('id', $uid)->update(['sell_time' => time()]);
                $updtoday_sell = User::where('id', $uid)->setInc('today_sell', 1);
                $model = new SendCode($marketInfoMobile, 'info');
                $model->sendInfo('匹配成功');
                return json(['code' => 0, 'message' => '提交成功']);
            }
            return json(['code' => 1, 'message' => '申请失败']);

        }
    }

    #交易订单详情
    public function marketInfo(Request $request)
    {
        $uid = $this->userId;
        $market_id = $request->post('market_id');
        $list = \app\common\entity\Market::where('m.id', $market_id)
            ->alias('m')
            ->leftJoin('user u', 'm.from_user_id = u.id')
            ->leftJoin('user uu', 'm.user_id = uu.id')
            ->field('m.*,u.mobile,u.real_name,u.zfb,u.zfb_image_url as sell_zfb_img,u.card as sell_card,u.card_name as sell_card_name,uu.mobile as buy_mobile')
            ->find();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }
        return json(['code' => 1, 'message' => '获取失败']);

    }

    #确认付款上传付款截图
    public function updMarketPic(Request $request)
    {
    	$is_up_sell = WithdrawRatio::where('key', 'is_up_sell')->value('ratio');
    	if ($is_up_sell == 0) {
            return json(['code' => 1, 'message' => '交易关闭']);
    	}
        $uid = $this->userId;
        $market_id = $request->post('market_id');
        if (!$request->post('zfb_img')) {
            return json(['code' => 1, 'message' => '未上传图片']);

        }
        

        $marketInfo1 = \app\common\entity\Market::where('id', $market_id)->find();
        if ($marketInfo1['status'] != 2) {
        	return json(['code' => 1, 'message' => '订单尚未匹配']);
        }

        $upd = \app\common\entity\Market::where('id', $market_id)->update(['zfb_img' => $request->post('zfb_img'), 'pay_time' => date('Y-m-d H:i:s', time()), 'status' => 3]);
        if ($upd) {
            $marketForUser = \app\common\entity\Market::where('id',$market_id)->value('from_user_id');
            $from_user_mobile = User::where('id',$marketForUser)->value('mobile');
            $model = new SendCode($from_user_mobile, 'info');
            $model->sendInfo('买方已上传凭证');
            return json(['code' => 0, 'message' => '提交成功']);

        }
        return json(['code' => 1, 'message' => '申请失败']);

    }

    #确认已打款
    public function endMarket(Request $request)
    {
        if ($request->isPost()) {
        	$is_up_sell = WithdrawRatio::where('key', 'is_up_sell')->value('ratio');
        	if ($is_up_sell == 0) {
                return json(['code' => 1, 'message' => '交易关闭']);
        	}
            $uid = $this->userId;
            $market_id = $request->post('market_id');
            $marketInfo = \app\common\entity\Market::where('id', $market_id)->find();
//            dump($marketInfo);die;
            if ($marketInfo['status'] != 3) {
                return json(['code' => 1, 'message' => '确认失败']);
            }
            $upd = \app\common\entity\Market::where('id', $market_id)->update(['end_time' => date('Y-m-d H:i:s', time()), 'status' => 4]);
            if ($upd) {
                $marketInfoMobile = User::where('id', $marketInfo['from_user_id'])->value('mobile');
                //交易完成 增加买入用户钱包资产
                $ky_num_times = WithdrawRatio::where('key', 'ky_num_times')->value('ratio');
                $insnumLog = (new MywalletLog())->addLog($marketInfo['user_id'], $marketInfo['num'], 'number', '买入成功', 1, 1);
                $insCanShellLog = (new MywalletLog())->addLog($marketInfo['user_id'], $marketInfo['num'] * $ky_num_times, 'can_sell', '买入赠送可售额度', 4, 1);
                $updwallet_can_sell = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('can_sell', $marketInfo['num'] * $ky_num_times);
                $updwallet_number = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('number', $marketInfo['num']);
                $updwallet_total = \app\common\entity\Mywallet::where('user_id', $marketInfo['user_id'])->setInc('total_num', $marketInfo['num']);
                $upTeamhash = (new \app\common\entity\Mywallet())->upParentsHash($marketInfo['user_id'],$marketInfo['num']);

//                $updMachine = (new \app\common\entity\Mywallet())->getMachine($marketInfo['user_id']);
                if ($insnumLog && $updwallet_number) {
                    return json(['code' => 0, 'message' => '确认成功']);

                }
            }
            return json(['code' => 1, 'message' => '确认失败']);
        }
    }


    #租用矿机
    public function buyMachine(Request $request)
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }

        $level = $request->post('level');
        $user_package = UserPackage::where('level', $level)->find();
        $mywallet = \app\common\entity\Mywallet::where('user_id', $uid)->value('number');
        if ($mywallet < $user_package['money']) {
            return json(['code' => 1, 'message' => '余额不足']);
        }
        $insLog = (new MywalletLog())->addLog($uid, $user_package['money'], 'number', '租用矿机', 1, 2);
        $updMywallet = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('number', $user_package['money']);
        $addMachine = (new MachineList())->addList($uid, $level, 5);
        if ($updMywallet && $addMachine) {
            return json(['code' => 0, 'message' => '租用成功,正在挖矿中']);

        }
        return json(['code' => 1, 'message' => '租用失败']);

    }
}
