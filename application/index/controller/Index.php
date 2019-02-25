<?php

namespace app\index\controller;

use app\common\entity\User;
use app\common\entity\Config;
use think\Request;
use think\Db;


class Index extends Base {

    public function index() {
        //跳转到商城
        //获取公告
       $article = new \app\index\model\Article();
       $articleList = $article->getArticleList(2);
       // dump($articleList);die;
//        //轮播图
//        $carouselModel = new \app\common\entity\Carousel();
//        $carouselList = $carouselModel->getListByTid(1);
//
//        $config = new Config();

//        return $this->fetch('index', [
//                    "list" => $articleList,
//                    "carouselList" => $carouselList,
//                    'credit_qrcode' => $config->getValue('credit_qrcode')
//        ]);
        $userInfo = Db::table('user')->where('id',$this->userId)->find();
        $block_wallet = Db::table('block_wallet')->where('user_id',$this->userId)->find();
        return $this->fetch('index',[
            'userInfo'=>$userInfo,
            'index_wallet'=>$block_wallet,
            "articleList" => $articleList,
        ]);
    }

    public function set(){
        $list = Db::table('user')->where('id',$this->userId)->field('mobile,id')->find();
        return $this->fetch('set',['list'=>$list]);

    }



    public function articlelist(){
        //获取公告
        User::update(['notice' => 0], ['id' => $this->userId]);
        $article = new \app\index\model\Article();
        $articleList = $article->getArticleList(1);
        return $this->fetch('articlelist', [
                    "list" => $articleList
        ]);
    }

    /**
     * 公告详情
     */
    public function articleinfo(Request $requset) {
        $articleId = $requset->get("articleId");
        if (!(int) $articleId) {
            $this->error("公告不存在！！");
        }
        $articleinfo = \app\common\entity\Article::where('article_id', $articleId)->find();
        if (!$articleinfo) {
            $this->error("公告不存在！！");
        }
        return $this->fetch("articleinfo", ['articleinfo' => $articleinfo]);
    }

    /**
     * 排行榜
     */
    public function phb() {
        //获取开采率排行榜 前20名
        $list = User::field('nick_name,avatar,product_rate')->order('product_rate', 'desc')->limit(20)
                ->select();
        return $this->fetch('phb', [
                    'list' => $list
        ]);
    }


    #充币
    public function into()
    {
        $uid = $this->userId;
        $list = Db::table('user')->where('id',$uid)->find();
        return $this->fetch('into',['list'=>$list]);
    }

    #提币
    public function out(Request $request)
    {   
        $uid = $this->userId;
        $withdraw_ratio = Db::table('withdraw_ratio')->where('id','5')->find();
        if ($request->isPost()) {

            $num = $request->post('withNum');
            $block_wallet = Db::table('block_wallet')->where('user_id',$uid)->find();
            if ($num > $block_wallet['number']) {
                return json(['code' => 1, 'message' => '账户余额不足']);
            }
            $trad_password = $request->post('trad_password');
            $money_addr = $request->post('withAddr');

            $validate = $this->validate($request->post(), '\app\index\validate\Indexout');
            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            $user = User::where('id', $uid)->find();
            $service = new \app\common\service\Users\Service();
            $result = $service->checkSafePassword($trad_password, $user);

            if (!$result) {
                return json(['code' => 1, 'message' => '交易密码输入错误']);
            }
            $ins = [
                'user_id' => $uid,
                'money_addr' => $money_addr,
                'num' => $num,
                'ratio_money' => $num * $withdraw_ratio['ratio'],
                'status' => 1,
                'create_time' => time()
            ];
            Db::startTrans();
            $res = Db::table('withdraw_money')->insert($ins);
            
            if ($res) {
                $updBlock = Db::table('block_wallet')->where('user_id',$uid)->update(['number'=> $block_wallet['number'] - $num]);
                if ($updBlock) {
                    $log = [
                        'user_id' => $uid,
                        'types' => '提币',
                        'number' => $num,
                        'old' => $block_wallet['number'],
                        'new' => $block_wallet['number'] - $num,
                        'create_time' => time()
                    ];
                    $inslog = Db::table('block_log')->insert($log);
                    if ($inslog) {
                        Db::commit();
                        return json(['code' => 0, 'message' => '提币申请提交成功']);
                        
                    }
                }
                
            }
            Db::rollback();
            return json(['code' => 1, 'message' => '提币申请提交失败']);

        }
        return $this->fetch('out',['list'=> $withdraw_ratio]);
    }

    #首页充值
    public function recharge()
    {
        return $this->fetch('recharge');
        
    }

}
