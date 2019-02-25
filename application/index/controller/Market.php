<?php

namespace app\index\controller;

use app\common\entity\Config;
use app\common\entity\MarketPrice;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\service\Market\Auth;
use app\index\model\SendCode;
use think\Request;

class Market extends Base {

    public function initialize() {
        $authModel = new Auth();
        $authModel->identity();
        parent::initialize();
    }

    public function index() {
        $marketPrice = new MarketPrice();
        $prices = $marketPrice->getCurrentPrice();

        $magic = User::where('id', $this->userId)->value('magic');

        //获取24小时交易量和交易额
        $marketNumber = Config::getValue('market_day_total');
        $marketTotal = Config::getValue('market_day_magic');

        if (!$marketNumber) {
            //统计
            $marketNumber = Orders::where('create_time', '>=', time() - 24 * 3600)->count();
        }
        if (!$marketTotal) {
            //统计
            $marketTotal = Orders::where('create_time', '>=', time() - 24 * 3600)->sum('number');
        }
        $config = new Config();
        $config->delCache();
        $config = new Config();

        $markerModel = new \app\index\model\Market();
        $mobile = request()->get('mobile', '');
        $data = $markerModel->getListByPage($this->userId, $mobile);
//        \app\common\helper\func::printarr($data);

        return $this->fetch('index', [
                    'user_id' => $this->userId,
                    'prices' => $prices,
                    'market_price_rate' => Config::getValue('market_rate'),
                    'number_min' => Config::getValue('market_min'),
                    'number_max' => Config::getValue('market_max'),
                    'magic' => $magic,
                    'market_number' => $marketNumber ? : 0,
                    'market_total' => $marketTotal ? : 0,
                    'loose_min' => $config->getValue('loose_min'),
                    'loose_max' => $config->getValue('loose_max'),
                    'whole_min' => $config->getValue('whole_min'),
                    'whole_max' => $config->getValue('whole_max'),
                    'sale_min' => $config->getValue('sale_min'),
                    'moneyName' => $config->getValue('web_money_name'),
                    'lists' => $data['data'],
                    'page' => $data['page'],
                    'mobile' => $mobile,
        ]);
    }

    /**
     * 买入
     */
    public function buy(Request $request) {
        if ($request->isPost()) {

            $validate = $this->validate($request->post(), '\app\index\validate\BuyForm');
            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            $user = User::where('id', $this->userId)->find();
            if ($user->is_buy != 1) {
                return json(['code' => 1, 'message' => '您账号禁止购买']);
            }

            $config = new Config();
            $config->delCache();
            $config = new Config();

            $loose_min = $config->getValue('loose_min');
            $loose_max = $config->getValue('loose_max');
            $whole_min = $config->getValue('whole_min');
            $whole_max = $config->getValue('whole_max');

            $number = $request->post('number');
            $price = $request->post('price');

//            if ($number < 100) {
//                if ($price > $loose_max || $price < $loose_min) {
//                    return json(['code' => 1, 'message' => '散币单价在' . $loose_min . '-' . $loose_max . '之间']);
//                }
//            }
//            if ($number >= 100) {
//                if ($price > $whole_max || $price < $whole_min) {
//                    return json(['code' => 1, 'message' => '整币单价在' . $whole_min . '-' . $whole_max . '之间']);
//                }
//            }
            //进行购买
            try {
                $model = new \app\index\model\Market();
                $model->buy($request->post('price'), $request->post('number'), $this->userId);

                return json(['code' => 0, 'message' => '买入成功', 'toUrl' => url('trade/buy')]);
            } catch (\Exception $e) {
                return json(['code' => 1, 'message' => $e->getMessage()]);
            }
        }
    }
    //短信通知
    private function sendMsg($mobile,$msg){
        header("Content-Type:text/html;charset=utf-8");
        $uid = 'cyl99999';
        $key = "d41d8cd98f00b204e980";
        $text = urlencode($msg);
        $url = "http://utf8.api.smschinese.cn/?Uid={$uid}&Key={$key}&smsMob={$mobile}&smsText={$text}";
        file_get_contents($url);
        return true;
    }

    /**
     * 买ta
     */
    public function buyTa(Request $request) {
        if ($request->isPost()) {
            $user = User::where('id', $this->userId)->find();
            if ($user->is_buy != 1) {
                return json(['code' => 1, 'message' => '您账号禁止购买']);
            }
            $orderId = intval($request->post('order_id'));

            $model = new \app\index\model\Market();

            try {

                $orderInfo = $model->buyTa($orderId, $this->userId);
                $saleUserInfo = User::where('id', $orderInfo['user_id'])->find();
                $config = new Config();
                $moneyName = $config->getValue('web_money_name');
                //买家提醒
                $msg = '您购买了'.$saleUserInfo->nick_name.'的'.$orderInfo->number.'个'.$moneyName.'，总价'.$orderInfo->total_price_china.'，订单编号：'.$orderInfo->order_number;
                $this->sendMsg($user->mobile, $msg);
                //卖家提醒
                $msg = $user->nick_name.'购买了您的'.$orderInfo->number.'个'.$moneyName.'，总价'.$orderInfo->total_price_china.'，订单编号：'.$orderInfo->order_number;
                $this->sendMsg($saleUserInfo->mobile, $msg);

                return json(['code' => 0, 'message' => '买入成功']);
            } catch (\Exception $e) {
                return json(['code' => 1, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * 卖ta
     */
    public function saleTa(Request $request) {
        if ($request->isPost()) {
            $user = User::where('id', $this->userId)->find();
            if ($user->is_sale != 1) {
                return json(['code' => 1, 'message' => '您账号禁止卖出']);
            }
            $orderId = intval($request->post('order_id'));

            //判断验证码
            $code = $request->post('code');
            $smodel = new SendCode($this->userInfo->mobile, 'market_sale');

            if (!$smodel->checkCode($code)) {
                return json(['code' => -1, 'message' => '验证码输入不正确']);
            }

            $model = new \app\index\model\Market();
            try {
                $model->checkSaleTa($orderId, $this->userId);
            } catch (\Exception $e) {
                return json(['code' => 1, 'message' => $e->getMessage()]);
            }

            //卖ta
            $result = $model->saleTa($orderId, $this->userId);

            if ($result) {
                $orderInfo = Orders::where('id', $orderId)->find();
                $buyUserInfo = User::where('id', $orderInfo['user_id'])->find();
                $config = new Config();
                $moneyName = $config->getValue('web_money_name');
                //卖家提醒
                $msg = '您向'.$buyUserInfo->nick_name.'出售了'.$orderInfo->number.'个'.$moneyName.'，总价'.$orderInfo->total_price_china.'，订单编号：'.$orderInfo->order_number;
                $this->sendMsg($user->mobile, $msg);
                //买家提醒
                $msg = $user->nick_name.'向您出售了'.$orderInfo->number.'个'.$moneyName.'，总价'.$orderInfo->total_price_china.'，订单编号：'.$orderInfo->order_number;
                $this->sendMsg($buyUserInfo->mobile, $msg);

                return json(['code' => 0, 'message' => '出售成功']);
            }

            return json(['code' => 1, 'message' => '出售失败']);
        }
    }

    /**
     * 卖出
     */
    public function sale(Request $request) {
        if ($request->isPost()) {
            $user = User::where('id', $this->userId)->find();
            if ($user->is_sale != 1) {
                return json(['code' => 1, 'message' => '您账号禁止卖出']);
            }

            $market = new \app\index\model\Market();
            if ($market->checkOrder($this->userId)) {
                return json(['code' => 1, 'message' => '你还有交易未完成，请先去完成交易']);
            }

            $config = new Config();
            $sale_min = $config->getValue('sale_min');
            $number = $request->post('number');
            if ($number < $sale_min) {
                return json(['code' => 1, 'message' => '挂卖单最低数量：' . $sale_min]);
            }

            //判断验证码
            $code = $request->post('code');
            $model = new SendCode($this->userInfo->mobile, 'market');

            if (!$model->checkCode($code)) {
                return json(['code' => -1, 'message' => '验证码输入不正确']);
            }

            $validate = $this->validate($request->post(), '\app\index\validate\SaleForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }


            $model = new \app\index\model\Market();
            $result = $model->sale($request->post('price'), $request->post('number'), $this->userId);

            if ($result) {
                return json(['code' => 0, 'message' => '卖出成功', 'toUrl' => url('trade/sale')]);
            }


            return json(['code' => 1, 'message' => '卖出失败']);
        }
    }

    //买它 发送短信验证码
    public function sendSale(Request $request) {
        if ($request->isPost()) {

            $orderId = intval($request->post('order_id'));
            $model = new \app\index\model\Market();

            try {
                $model->checkSaleTa($orderId, $this->userId);
                //发送验证码

                $model = new SendCode($this->userInfo->mobile, 'market_sale');

                if ($model->send()) {
                    return json(['code' => 0, 'message' => '你的验证码发送成功']);
//                    return json(['code' => 0, 'message' => $model->code]);
                }

                return json(['code' => 1, 'message' => '验证码发送失败']);
            } catch (\Exception $e) {

                return json(['code' => 1, 'message' => $e->getMessage()]);
            }
        }
    }

    //卖出 发送短信验证码
    public function send(Request $request) {
        if ($request->isPost()) {
            //是否允许卖出
            $userModel = new User();
            $userInfo = $userModel->where(['id' => $this->userId])->find();

            if ($userInfo['is_sale'] == -1) {
                return json(['code' => 1, 'message' => '您账号禁止卖出']);
            }

            //验证用户是否有交易中
            $market = new \app\index\model\Market();
            if ($market->checkOrder($this->userId)) {
                return json(['code' => 1, 'message' => '你还有交易未完成，请先去完成交易']);
            }

            $validate = $this->validate($request->post(), '\app\index\validate\SaleForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            //发送验证码

            $model = new SendCode($this->userInfo->mobile, 'market');

            if ($model->send()) {
                return json(['code' => 0, 'message' => '你的验证码发送成功']);
                //return json(['code' => 0, 'message' => $model->code]);
            }

            return json(['code' => 1, 'message' => '验证码发送失败']);
        }
    }

    //求购列表
    public function buyList(Request $request) {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $mobile = $request->get('mobile', '');

        $model = new \app\index\model\Market();
        $list = $model->getList(Orders::TYPE_BUY, $this->userId, $page, $limit, $mobile);

        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $list
        ]);
    }

    //出售列表

    public function saleList(Request $request) {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $model = new \app\index\model\Market();
        $mobile = $request->get('mobile', '');
        $list = $model->getList(Orders::TYPE_SALE, $this->userId, $page, $limit, $mobile);

        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $list
        ]);
    }

}
