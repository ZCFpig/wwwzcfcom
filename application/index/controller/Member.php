<?php

namespace app\index\controller;

use app\common\entity\Charge;
use app\common\entity\HasMachine;
use app\common\entity\MachineList;
use app\common\entity\UnsealingList;
use app\common\entity\MywalletLog;
use app\common\entity\UserInviteCode;
use app\common\entity\UserPackage;
use app\common\entity\WithdrawRatio;
use app\common\service\Market\Auth;
use app\common\service\Users\Identity;
use app\index\model\SendCode;
use app\index\validate\RegisterForm;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Grafika\Color;
use Grafika\Grafika;
use think\facade\Env;
use think\facade\Session;
use think\facade\Url;
use think\Request;
use app\common\service\Users\Service;
use app\common\entity\User;
use app\common\entity\Config;
use app\common\entity\UserProduct;
use app\common\entity\UserMagicLog;
use Zxing\Qrcode\QRCodeReader;
use think\Db;
use app\common\entity\UserCount;
use app\common\entity\Category;

class Member extends Base
{

    public function index()
    {

        //获取缓存用户详细信息
        $userInfo = User::where('id', $this->userId)->find();
        //获取用户冻结资金 和交易总数
        $freeze = $userInfo->getFreeze();
        $config = new Config();
        $config->delCache();
        $config = new Config();
        $all_money = bcadd($userInfo['magic'], $freeze['freeze'], 8);

        return $this->fetch('memberinfo', [
            'list' => $userInfo,
            'freeze' => $freeze,
            'all_money' => $all_money,
            'credit_qrcode' => $config->getValue('credit_qrcode'),
            'money_name' => $config->getValue('web_money_name'),
        ]);
    }

    public function charge()
    {
        return $this->fetch('charge', ['list' => Charge::select()]);
    }

    /**
     * 设置页面
     */
    public function set()
    {
        //获取缓存用户详细信息
        // $identity = new Identity();
        // $identity->delCache($this->userId);
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);
        return $this->fetch('set', ["list" => $userInfo]);
    }


    /**
     * 联盟
     */
    public function union()
    {
        //统计所有
        $categoryModel = new Category();
        $fids = $categoryModel->getSubChild($this->userId);
        $userTotal = $userRate = 0;
        $userList = [];
        //剔除自己
        if (isset($fids[0])) {
            unset($fids[0]);
        }
        if ($fids) {
            $userList = User::where('id', 'in', $fids)->field('id,avatar,pid,mobile,nick_name,product_rate,invite_count,register_time')->select();
            $userList = $userList->toArray();
            $userModel = new User();
            foreach ($userList as $k => $v) {
                $userList[$k]['team_rate'] = $userModel->getTeamRate($v['id']);
            }
            $userTotal = count($userList);
            $userRate = array_sum(array_column($userList, 'product_rate'));
        }
        $userInfo = User::where('id', $this->userId)->find();

        return $this->fetch('union', [
                "list" => $userInfo,
                "userList" => $userList,
                "userTotal" => $userTotal,
                "userRate" => sprintf('%.5f', $userRate),
            ]
        );
    }

    /**
     * 联盟
     */
    public function unionold()
    {
        $userInfo = User::where('id', $this->userId)->find();
        $usercountInfo = UserCount::where('user_id', $this->userId)->find();
        //获得直推会员
        $userList = $userInfo->getChilds($this->userId);
        $userTotal = $userRate = 0;
        $userCountByIdList = [];
        if ($userList) {
            $userIds = [];
            foreach ($userList as $v) {
                $userIds[] = $v->id;
            }
            $usercountList = UserCount::where('user_id', 'in', $userIds)->select();
            $sumRate = 0;
            foreach ($usercountList as $k => $v) {
                $sumRate += $v['rate'];
                $userCountByIdList[$v['user_id']]['rate'] = $v['rate'];
            }
            if ($usercountInfo) {
                $userTotal = $usercountInfo->total;
                $userRate = $usercountInfo->rate + $sumRate;
            }
        }
        return $this->fetch('union', [
                "list" => $userInfo,
                "userList" => $userList,
                "userTotal" => $userTotal,
                "userRate" => $userRate,
                "userCountByIdList" => $userCountByIdList,
            ]
        );
    }

    /**
     * 团队
     */
    public function team()
    {
        $userInfo = User::where('id', $this->userId)->find();

        if ($userInfo['tid']) {
            //工会信息
            $teamInfo = \app\common\entity\Team::where('id', $userInfo['tid'])->find();
            //查询会长
            $leaderInfo = User::where('id', $teamInfo['uid'])->find();
            //若是有团队
            return $this->fetch('teamInfo', [
                    "teamInfo" => $teamInfo,
                    "leaderInfo" => $leaderInfo,
                    "userId" => $this->userId
                ]
            );
        } else {
            //若是没有团队
            return $this->fetch('teamList');
        }
    }

    /**
     * 团队成员列表
     */
    public function teamUserList(Request $request)
    {
        $tid = $request->post('tid', 0);
        $page = $request->post('page', 1);
        $tuid = $request->post('tuid', 0);

        $model = new \app\common\entity\TeamUser();
        $list = $model->getList($tid, $page, $tuid);

        return $this->ajaxreturn($list, '', true);
    }

    /**
     * 退出工会
     */
    public function exitTeam()
    {
        $userInfo = User::where('id', $this->userId)->find();
        if ($userInfo->tid <= 0) {
            return $this->ajaxreturn('', '你还没有加入工会');
        }
        $teamInfo = \app\common\entity\Team::where('id', $userInfo->tid)->find();
        if (!$teamInfo) {
            $userInfo->tid = 0;
            $userInfo->save();
            return $this->ajaxreturn('', '', true);
        }
        $teamUserModel = new \app\common\entity\TeamUser();
        $res = $teamUserModel->where('uid', $this->userId)->delete();
        if ($res) {
            $teamInfo->count = $teamInfo['count'] - 1;
            $calculationnew = $teamInfo->team_calculation - $userInfo->product_rate;
            $teamInfo->team_calculation = $calculationnew < 0 ? 0 : $calculationnew;
            $teamInfo->save();
            $userInfo->tid = 0;
            $userInfo->save();
        } else {
            //删除失败
            return $this->ajaxreturn('', '操作失败');
        }
    }

    /**
     * 创建公会
     */
    public function teamCreate(Request $request)
    {
        if ($request->isAjax()) {
            $result = $this->validate($request->post(), 'app\index\validate\TeamForm');
            if ($result !== true) {
                return json([
                    'code' => -1,
                    'message' => $result,
                ]);
            }
            $userInfo = User::where('id', $this->userId)->find();
            $temModel = new \app\common\entity\Team();
            $tid = $temModel->addTeam($request->post(), $userInfo);
            if ($tid) {
                //添加team user
                $teamUserModel = new \app\common\entity\TeamUser();
                $teamUserModel->save([
                    'tid' => $tid,
                    'uid' => $this->userId,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                $userInfo->tid = $tid;
                $userInfo->save();
                return json([
                    'code' => 0,
                    'toUrl' => url('team'),
                    'message' => '创建成功 '
                ]);
            } else {
                return json([
                    'code' => -1,
                    'message' => '创建失败',
                ]);
            }
        }
        $userInfo = User::where('id', $this->userId)->find();
        if ($userInfo['tid']) {
            $this->redirect('team');
        }
        return $this->fetch('teamCreate', [
            'userInfo' => $userInfo,
        ]);
    }


    /**
     * 公会列表
     * @param Request $request
     * @return type
     */
    public function teamList(Request $request)
    {
        $page = $request->post('page', 1);
        $limit = $request->post('limit', 1);

        $model = new \app\common\entity\Team();
        $list = $model->getList($page, $limit);

        return json([
            'status' => true,
            'info' => 'success',
            'data' => $list
        ]);
    }

    /**
     * 修改密码
     */
    public function updatePassword(Request $request)
    {
        $validate = $this->validate($request->post(), '\app\index\validate\PasswordForm');

        if ($validate !== true) {
            return json(['code' => 1, 'message' => $validate]);
        }

        $oldPassword = $request->post('old_pwd');
        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkPassword($oldPassword, $user);

        if (!$result) {
            return json(['code' => 1, 'message' => '原密码输入错误']);
        }

        //修改
        $user->password = $service->getPassword($request->post('new_pwd'));

        if ($user->save() === false) {
            return json(['code' => 1, 'message' => '修改失败']);
        }

        return json(['code' => 0, 'message' => '修改成功']);
    }

    /**
     * 新手解答
     */
    public function articleList()
    {
        //获取缓存用户详细信息
        $article = new \app\index\model\Article();
        $articleList = $article->getArticleList(2);
        return $this->fetch('articleList', ["list" => $articleList]);
    }

    /**
     * 问题留言
     */
    public function submitMsg(Request $request)
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        //内容
        $data['content'] = $request->post("content");
        $data['create_time'] = time();
        $data['user_id'] = $this->userId;

        $res = \app\common\entity\Message::insert($data);
        if ($res) {
            return json(['code' => 0, 'message' => '提交成功', 'toUrl' => url('member/message')]);
        } else {
            return json(['code' => 1, 'message' => '提交失败']);
        }
    }

    /**
     * 反馈列表 - 发件箱
     */
    public function sendbox()
    {
        $entity = \app\common\entity\Message::field('m.*, u.nick_name, u.avatar')
            ->alias("m")
            ->leftJoin("user u", 'm.user_id = u.id')
            ->where('m.user_id', $this->userId)
            ->order('m.create_time', 'desc')
            ->select();
        return $this->fetch("sendbox", ['list' => $entity]);
    }


    /**
     * 实名认证下一步
     */
    public function lastreal(Request $request)
    {
        $data['real_name'] = $request->get("real_name");
        $data['card_id'] = $request->get("card_id");

        if (!$data['real_name'] || !$data['card_id']) {
            $this->error("请输入姓名和身份证号！！");
        }

        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        return $this->fetch("lastreal", ['list' => $userInfo, "data" => $data]);
    }


    /**
     * 修改个人信息
     */
    public function updateUser(Request $request)
    {
        //获取缓存用户详细信息
        $identity = new Identity();
        $userInfo = $identity->getUserInfo($this->userId);

        $user = new Service();

        $data = array();

        $card = $request->post("card"); //银行卡号
        if ($card) {
            if ($user->checkMsg("card", $card, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该银行卡号已经被绑定了']);
            } else {
                $data['card'] = $card;
            }
        }
        $card_name = $request->post("card_name"); //开户行
        if ($card_name) {
            $data['card_name'] = $card_name;
        }
        $zfb = $request->post("zfb"); //支付宝
        if ($zfb) {
            if ($user->checkMsg("zfb", $zfb, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该支付宝号已经被绑定了']);
            } else {
                $data['zfb'] = $zfb;
            }
        }
        $zfb_image_url = $request->post("zfb_image_url");

        if ($zfb_image_url) {
            $data['zfb_image_url'] = $zfb_image_url;
        }
        $wx = $request->post("wx"); //微信
        if ($wx) {
            if ($user->checkMsg("wx", $wx, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该微信号已经被绑定了']);
            } else {
                $data['wx'] = $wx;
            }
        }
        $wx_image_url = $request->post("wx_image_url");
        if ($wx_image_url) {
            $data['wx_image_url'] = $wx_image_url;
        }
        $real_name = $request->post("real_name"); //真实姓名
        if ($real_name) {
            $data['real_name'] = $real_name;
        }
        $card_id = $request->post("card_id"); //身份证号
        if ($card_id) {
            if ($user->checkMsg("card_id", $card_id, $userInfo->user_id)) {
                return json(['code' => 1, 'message' => '该身份证号已经被绑定了']);
            } else {
                $data['card_id'] = $card_id;
            }
        }
        $card_left = $request->post("card_left"); //身份证反面
        if ($card_left) {
            $data['card_left'] = $card_left;
        }
        $card_right = $request->post("card_right"); //身份证反面
        if ($card_right) {
            $data['card_right'] = $card_right;
        }
        $avatar = $request->post("avatar"); //头像
        if ($avatar) {
            $data['avatar'] = $avatar;
        }
        $money_address = $request->post("money_address"); //头像
        if ($money_address) {
            $data['money_address'] = $money_address;
        }

        $res = \app\common\entity\User::where('id', $this->userId)->update($data);
        // dump(\app\common\entity\User::getLastsql());die;
        if ($res) {
            //更新缓存
            $identity->delCache($this->userId);
            return json(['code' => 0, 'message' => '修改成功']);
        } else {
            return json(['code' => 1, 'message' => '修改失败']);
        }
    }


    private function getHour($timestamp, $timestamp2 = '')
    {
        $timestamp2 = $timestamp2 ? $timestamp2 : time();
        $diff = abs($timestamp2 - $timestamp);
        $hour = round($diff / 3600, 1);
        return $hour;
    }

    /**
     * 动画页面
     */
    public function income(Request $request)
    {
        $id = $request->get('id');
        $user_product = new UserProduct();
        $magicInfo = $user_product->getInfo($id, $this->userId);
        $magicInfo['hour'] = 0;
        if ($magicInfo['end_time'] > time()) {
            $magicInfo['hour'] = $this->getHour(time(), $magicInfo['end_time']);
        }
        return $this->fetch("income", ["magicInfo" => $magicInfo, 'moneyName' => Config::getValue("web_money_name")]);
    }

    /**
     * 清除缓存
     */
    public function delCache()
    {
        $identity = new Identity();
        $identity->delCache($this->userId);
    }

    /**
     * 登录到交易市场
     */
    public function login(Request $request)
    {
        if ($request->isPost()) {
            $password = $request->post('password');
            if (!$password) {
                return json(['code' => 1, 'message' => '请输入密码']);
            }
            $auth = new Auth();
            if (!$auth->check($password)) {
                return json(['code' => 1, 'message' => '密码错误']);
            }
            $url = Session::get('prev_url');
            Session::delete('prev_url');
            return json(['code' => 0, 'message' => '登录成功', 'toUrl' => $url]);
        }
        Session::set('prev_url', !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('market/index'));
        return $this->fetch('login');
    }


    /**
     * 退出登录
     */
    public function logout()
    {
        $service = new Identity();
        $res = $service->logout();
//        var_dump($res);die;
        return json(['code' => 0, 'message' => '退出成功']);

//        $this->redirect('pulics/login');
    }

    /**
     * 推广
     */
    public function spread()
    {
        //获取当前用户的推广码
        //$path = url('qrcode');
        $code = User::where('id', $this->userId)->value('mobile');

        $fileName = Env::get('app_path') . '../public/code/qrcode_' . $code . '.png';
//        if (!file_exists($fileName)) {
        $path = $this->qrcode($code);

        ob_clean();
        $editor = Grafika::createEditor(['Gd']);

        $background = Env::get('app_path') . '../public/static/img/zhaomubg.png';

        $editor->open($image1, $background);
//            $editor->text($image1, $code, 20, 220, 575, new Color('#ffffff'), '', 0);
        $editor->open($image2, $path);
        $editor->blend($image1, $image2, 'normal', 0.9, 'top-left', 260, 570);
        $editor->save($image1, Env::get('app_path') . '../public/code/qrcode_' . $code . '.png');
//        }

        return $this->fetch('spread', [
            'path' => '/code/qrcode_' . $code . '.png'
        ]);
    }

    protected function qrcode($code)
    {
        //$code = UserInviteCode::where('user_id', $this->userId)->value('invite_code');
        $path = Env::get('app_path') . '../public/code/' . $code . '.png';

//        if (!file_exists($path)) {
        ob_clean();
        $url = url('publics/register', ['code' => $code], 'html', true);
        $qrCode = new \Endroid\QrCode\QrCode();

        $qrCode->setText($url);
        $qrCode->setSize(360);
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(10);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 100]);
        //$qrCode->setLabel('Scan the code', 16, __DIR__.'/../assets/fonts/noto_sans.otf', LabelAlignment::CENTER);
//            $qrCode->setLogoPath(Env::get('app_path') . '../public/static/img/logo5.png');
//            $qrCode->setLogoWidth(60);
        $qrCode->setValidateResult(false);

        header('Content-Type: ' . $qrCode->getContentType());
        $content = $qrCode->writeString();

        $path = Env::get('app_path') . '../public/code/' . $code . '.png';

        file_put_contents($path, $content);
//        }

        return $path;
    }

    #修改支付密码
    public function safepassword(Request $request)
    {
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\index\validate\PasswordForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            //判断原密码是否相等
            $oldPassword = $request->post('old_pwd');
            $user = User::where('id', $this->userId)->find();
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($oldPassword, $user);

            if (!$result) {
                return json(['code' => 1, 'message' => '原密码输入错误']);
            }

            //修改
            $user->trad_password = $service->getPassword($request->post('new_pwd'));

            if (!$user->save()) {
                return json(['code' => 1, 'message' => '修改失败']);
            }

            return json(['code' => 0, 'message' => '修改成功']);
        }

    }

    #问题反馈
    public function opinion(Request $request)
    {
        if ($request->isPost()) {

            $uid = $this->userId;
            $content = htmlspecialchars(trim($request->post('content')));
            if ($content) {
                $res = Db::table('message')->insert(['content' => $content, 'user_id' => $uid, 'create_time' => time()]);
                if ($res) {
                    return json(['code' => 0, 'message' => '提交成功']);
                }
            }
            return json(['code' => 1, 'message' => '提交失败']);
        }
        return $this->fetch('opinion');

    }

    #实名认证
    public function attest(Request $request)
    {
        if ($request->isPost()) {

            $validate = $this->validate($request->post(), '\app\index\validate\Attest');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            $trad_password = $request->post('trad_password');
            $card_id = $request->post('card_id');
            $preg_card='/^([\d]{17}[xX\d]|[\d]{15})$/';
            if(!preg_match($preg_card,$card_id)){
                return json(['code' => 1, 'message' => '身份证格式不对']);

            }

            $user = User::where('id', $this->userId)->find();
            if ($user['real_pass'] == 2) {
                return json(['code' => 1, 'message' => '已认证']);
                
            }
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($trad_password, $user);
            if (!$result) {
                return json(['code' => 1, 'message' => '二级密码输入错误']);
            }
            $uid = $this->userId;
            $data = [
                'card_id' => $request->post('card_id'),
                'card' => $request->post('card'),
                'card_name' => $request->post('card_name'),
                'real_pass' => '2',
            ];
            $send_num = WithdrawRatio::where('key','send_num')->value('ratio');
            $send_spig = WithdrawRatio::where('key','send_spig')->value('ratio');
            $insLog_sm = (new \app\common\entity\MywalletLog())->addLog($uid, $send_num, 'freeze', '实名赠送', 2, 1);
            $updWallet = \app\common\entity\Mywallet::where('user_id',$uid)->setInc('freeze',$send_num);
            $insMachine = (new MachineList())->insMachine($uid,1,7,$send_spig);
            $res = User::where('id', $uid)->update($data);
            $is_up = WithdrawRatio::where('key','num_up')->value('ratio');
            if ($is_up == 1){
                \app\common\entity\Mywallet::where('user_id',$user['pid'])->setInc('can_sell',2);
                $ins_cansell_log = (new MywalletLog())->addLog($user['pid'],2,'can_sell','认筹直推奖励',4,1);
            }
            if ($res) {

                return json(['code' => 0, 'message' => '修改成功']);
            }
            return json(['code' => 1, 'message' => '修改失败']);
        }
        if ($request->isGet()) {

            $list = User::where('id', $this->userId)->field('mobile,real_name,card_id,card,card_name,real_pass')->find();
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }

    }

    #邀请好友
    public function invite()
    {
        $userInfo = Db::table('user_invite_code')->where('user_id', $this->userId)->find();
        $app = Db::table('app_down')->where('app', 'down')->find();
        $userInfo['url'] = $app['url'];
        return $this->fetch('invite', ['list' => $userInfo]);

    }

    #我的团队
    public function myTeam(Request $request)
    {

        $uid = $this->userId;
        if ($request->isPost()) {
//             $limit = $request->post('count')?$request->post('count'):10;
//             $page = $request->post('page')?$request->post('page'):1;
//             $list = Db::table('user')->where('pid',$uid)
//                 ->field('id,mobile,level')
//                 ->page($page)
//                 ->limit($limit)
//                 ->select();
// //            var_dump($list);die;
//             $pack = new UserPackage();
//             foreach ($list as &$v){
//                 $v['level'] = $pack->getOneCate($v['level']);
//             }
            $list = $this->getChildName($request->post('num'));

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }
        $childs = Db::table('user')->where('pid', $uid)->select();
        $list = count($childs);
        $child = $this->getChild($childs);

        return $this->fetch('myteam', ['list' => $list, 'child' => $child]);

    }

    #获取第几级
    public function getChildName($num)
    {
        // $num = $request->post('num');
        $uid = $this->userId;
        // $uid = 14412;
        //直推
        // $num = 1;
        $one = Db::table('user')->field('id,pid,level,mobile')->where('pid', $uid)->select();
        $two = [];
        $three = [];
        foreach ($one as $v) {
            $res = Db::table('user')->field('id,pid,level,mobile')->where('pid', $v['id'])->select();
            foreach ($res as $a) {
                $two[] = $a;

            }
        }
        foreach ($two as $b) {
            $res1 = Db::table('user')->field('id,pid,level,mobile')->where('pid', $b['id'])->select();
            foreach ($res1 as $c) {
                $three[] = $c;
            }
        }
        // dump($two);die;
        $pack = new UserPackage();

        if ($num == '1') {
            foreach ($one as &$c) {
                $c['mobile'] = substr($c['mobile'], 0, 3) . "****" . substr($c['mobile'], 7, 4);
                $c['level'] = $pack->getOneCate($c['level']);
            }
            return $one;
        } elseif ($num == '2') {
            foreach ($two as &$c) {
                $c['mobile'] = substr($c['mobile'], 0, 3) . "****" . substr($c['mobile'], 7, 4);
                $c['level'] = $pack->getOneCate($c['level']);
            }
            return $two;

        } elseif ($num == '3') {
            foreach ($three as &$c) {
                $c['mobile'] = substr($c['mobile'], 0, 3) . "****" . substr($c['mobile'], 7, 4);
                $c['level'] = $pack->getOneCate($c['level']);
            }
            return $three;

        }

    }


    #获取所有下级人数
    public function getChild($child)
    {
        static $count = 0;
        $count1 = count($child);
        $count = $count + $count1;
        // dump($child);die;
        foreach ($child as $v) {
            if ($count1 == 0) {
                return;
            }
            $cc = Db::table('user')->field('id')->where('pid', $v['id'])->select();
            $res = $this->getChild($cc);
        }

        return $count;

    }

    #发送修改支付宝验证码
    public function sendUpdZfb(Request $request)
    {
        if ($request->isPost()) {
            $mobile = $request->post('mobile');
            //检验手机号码
            if (!preg_match('#^1\d{10}$#', $mobile, $m)) {
                return json(['code' => 1, 'message' => '手机号码格式不正确']);
            }
            //判断手机号码是否注册
            if (!User::checkMobile($mobile)) {
                return json(['code' => 1, 'message' => '此账号不存在，请重新填写']);
            }
            $model = new SendCode($mobile, 'updzfb');
            if ($model->send()) {
                return json(['code' => 0, 'message' => '你的验证码发送成功.']);//.$model->code
            }

            return json(['code' => 1, 'message' => '发送失败']);
        }
    }

    #修改支付宝
    public function UpdZfb(Request $request)
    {
        if ($request->isPost()) {

            $validate = $this->validate($request->post(), '\app\index\validate\UpdZfb');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            $form = new RegisterForm();
            if (!$form->checkZfb($request->post('code'), $request->post('mobile'))) {
                return json(['code' => 1, 'message' => '验证码输入错误']);
            }

            $uid = $this->userId;
            $data = [
                'zfb' => $request->post('zfb'),
                'zfb_image_url' => $request->post('zfb_image_url'),
                // 'wx' => $request->post('wx'),
                // 'wx_image_url' => $request->post('wx_image_url'),
                'card' => $request->post('card'),
                'card_name' => $request->post('card_name'),

            ];
            $res = User::where('id', $uid)->update($data);
            if ($res) {
                return json(['code' => 0, 'message' => '修改成功']);
            }
            return json(['code' => 1, 'message' => '修改失败']);
        }
        if ($request->isGet()) {

            $list = User::where('id', $this->userId)->field('mobile,real_name,zfb,zfb_image_url,card_id')->find();
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }

    }


    #发送修改身份证验证码
    public function sendUpdCard(Request $request)
    {
        if ($request->isPost()) {
            $mobile = $request->post('mobile');
            //检验手机号码
            if (!preg_match('#^1\d{10}$#', $mobile, $m)) {
                return json(['code' => 1, 'message' => '手机号码格式不正确']);
            }
            //判断手机号码是否注册
            if (!User::checkMobile($mobile)) {
                return json(['code' => 1, 'message' => '此账号不存在，请重新填写']);
            }
            $model = new SendCode($mobile, 'updcard');
            if ($model->send()) {
                return json(['code' => 0, 'message' => '你的验证码发送成功.']);//.$model->code
            }

            return json(['code' => 1, 'message' => '发送失败']);
        }
    }

    #修改身份证
    public function UpdCard(Request $request)
    {
        if ($request->isPost()) {

            $validate = $this->validate($request->post(), '\app\index\validate\UpdCard');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            $form = new RegisterForm();
            if (!$form->checkCard($request->post('code'), $request->post('mobile'))) {
                return json(['code' => 1, 'message' => '验证码输入错误']);
            }

            $uid = $this->userId;
            $data = [
                'card_id' => $request->post('card_id'),

            ];
            $res = User::where('id', $uid)->update($data);
            if ($res) {
                return json(['code' => 0, 'message' => '修改成功']);
            }
            return json(['code' => 1, 'message' => '修改失败']);
        }
        if ($request->isGet()) {

            $list = User::where('id', $this->userId)->field('mobile,real_name,card_id')->find();
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }

    }

    #个人资料
    public function updInfo(Request $request)
    {
        if ($request->isPost()) {

            $validate = $this->validate($request->post(), '\app\index\validate\UpdInfo');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            $trad_password = $request->post('trad_password');

            $user = User::where('id', $this->userId)->find();
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($trad_password, $user);
            if (!$result) {
                return json(['code' => 1, 'message' => '二级密码输入错误']);
            }
            $uid = $this->userId;
            $data = [
                'eth_address' => $request->post('eth_address'),
                'btc_address' => $request->post('btc_address'),
                'zfb_image_url' => $request->post('zfb_image_url'),
                'card' => $request->post('card'),
                'card_name' => $request->post('card_name'),
            ];
            $res = User::where('id', $uid)->update($data);
            if ($res) {
                return json(['code' => 0, 'message' => '修改成功']);
            }
            return json(['code' => 1, 'message' => '修改失败']);
        }
        if ($request->isGet()) {

            $list = User::where('u.id', $this->userId)->alias('u')
                ->leftJoin('user p', 'u.pid = p.id')
                ->field('u.mobile,u.real_name,u.money_address,u.zfb,u.zfb_image_url,u.wx,u.wx_image_url,u.card,u.card_name,u.btc_address,u.eth_address,u.register_time,u.card_id,p.mobile as pmobile')->find();
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }

    }

    #获取邀请码
    public function getInviteCode()
    {

        $list = (new UserInviteCode())->getCodeByUserId($this->userId);
        return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
    }

    #用户解封
    public function userUnsealing(Request $request)
    {
        $mobile = $request->post('mobile');

        $user = User::where('mobile', $mobile)->find();

        if ($user['status'] == '1') {
            return json(['code' => 1, 'message' => '此账号未被封禁']);

        }
        $uid = $user['id'];

        if (!User::checkMobile($mobile)) {
            return json(['code' => 1, 'message' => '此账号不存在，请重新填写']);
        }

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

        // $unsealing_num = UserPackage::where('level', $user['level'])->value('unsealing_num');

        // $mywallet = \app\common\entity\Mywallet::where('user_id', $uid)->find();

        // var_dump($mywallet['can_sell']);die;
        // if ($mywallet['can_sell'] < $unsealing_num) {
        //     return json(['code' => 1, 'message' => '可售额度不足']);
        // }
        // $insLog = (new \app\common\entity\MywalletLog())->addLog($uid, $unsealing_num, 'can_sell', '解封', 4, 2);
        // $updCanSell = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('can_sell', $unsealing_num);

        // if ($updCanSell) {
//            $updUserStatus = User::where('id', $uid)->update(['status' => 1]);
            $insUnsealing = UnsealingList::insert(['user_id'=>$uid,'status'=>1,'create_time'=>time()]);

            if ($insUnsealing) {
                return json(['code' => 0, 'message' => '解封申请中']);

            }
        // }

    }

    #用户中心个人信息)
    public function gerUserInfo()
    {
        $uid = $this->userId;
        $list = User::where('u.id', $uid)
            ->alias('u')
            ->field('u.mobile,u.avatar,u.level,w.*')
            ->leftJoin('my_wallet w', 'u.id = w.user_id')
            ->find();
        $has_machine = MachineList::where('user_id', $uid)->where('status', 1)->count();
        $list['has_machine'] = $has_machine;
        $total_machine_num = MachineList::where('user_id', $uid)->sum('get_num');
        $list['total_machine_num'] = $total_machine_num;
        $user = new User();
        // $list['bonus_total'] = $list['team_hash'];
        $list['zhitui'] = $user->where('pid', $uid)->count();
        $childs = $user->where('pid', $uid)->field('id')->select();
        $list['team_num'] = $user->getChildsNum($childs);
        $list['team_hash'] = floor($list['team_hash']/10);
        if ($list) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);


    }


    #获取团队
    public function getTeamInfo(Request $request)
    {
        if ($request->post('uid')){
            $uid = $request->post('uid');
        }else{
            $uid = $this->userId;
        }
        $list = User::where('pid',$uid)->field('mobile,pid,real_name,level,id')->select();
        foreach ($list as &$v){
            $v['child_num'] = User::where('pid',$v['id'])->count();
            $v['zhitui'] = User::where('pid',$v['id'])->count();
            $v['team_hash'] = floor((\app\common\entity\Mywallet::where('user_id',$v['id'])->value('team_hash'))/10);
        }
        $count = User::where('pid',$uid)->field('mobile,pid,real_name,level,id')->count();

        if ($list) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list , 'count' => $count]);

        }

        return json(['code' => 1, 'message' => '获取失败']);


    }

    public function aaaa(){
        $res = (new User())->getZhitui(10053);
        return $res;
    }

    public function bbbb(){

        $res = (new \app\common\entity\Mywallet())->share(10053,200);
        return $res;

    }


}
