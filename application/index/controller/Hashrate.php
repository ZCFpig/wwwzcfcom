<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/23
 * Time: 11:26
 */

namespace app\index\controller;


use think\Db;
use think\Request;

class Hashrate extends Base
{

    #每日点击签到
    public function everyGet(Request $request)
    {

        $uid = $this->userId;
        Db::startTrans();
        $my_wallet = Db::table('my_wallet')->where('user_id',$uid)->find();
        $userInfo = Db::table('user')->where('id',$uid)->find();
        $now = date('md',time());
        $old = date('md',$userInfo['sign_time']);
        if ($now == $old){
            return json(['code' => 1, 'message' => '今日已签到']);

        }

        $wallet = Db::table('my_wallet')->where('user_id', $uid)->setInc('candy', 10);
        $updsign = Db::table('user')->where('id',$uid)->update(['sign_time'=>time()]);
        if ($wallet && $updsign) {
            // $shop = new Shop;
            // $inslog = $shop->bncLog('kybnc_log',$uid,'每日签到','10',$my_wallet['candy'],$my_wallet['candy'] + 10);
            
            
            Db::commit();
            return json(['code' => 0, 'message' => '签到成功，获得糖果+10！']);
            
        }
        Db::rollback();
        return json(['code' => 1, 'message' => '签到失败']);

    }

    #购买会员套餐
    public function buyPackage(Request $request)
    {
        $level = $request->post('level');
        $uid = $this->userId;
        $userInfo = Db::table('user')->where('id',$uid)->find();
        $package = Db::table('user_package')->where('level', $level)->find();
        if(!$package){
            return json(['code' => 1, 'message' => '没有该套餐']);

        }
        if ( $userInfo['level'] >= $level ) {
            return json(['code' => 1, 'message' => '请购买更高级套餐']);
            
        }
        $my_wallet = Db::table('my_wallet')->where('user_id', $uid)->find();
        if ($my_wallet['number'] < $package['money']) {
            return json(['code' => 1, 'message' => '余额不足']);
            
        }
        Db::startTrans();

        $upduser = Db::table('user')->where('id', $uid)->update(['level' => $level]);

        if ($upduser) {
            $number = $package['money'] * 0.5;
            $ky_number = $number * 0.8;
            $xf_number = $number * 0.2;
            $freeze = $number * $package['times'];
            $insdata = [
                'money_type' => 'BNC',
                'number' => $my_wallet['number'] - $package['money'] ,
                'freeze' => $freeze + $my_wallet['freeze'],
                'xf_number' => $xf_number + $my_wallet['xf_number'],
                'ky_number' => $ky_number + $my_wallet['ky_number'],
                'update_time' => time()
            ];

            $upd = Db::table('my_wallet')->where('user_id', $uid)->update($insdata);
            if ($upd) {
                $log = [
                    'user_id' => $uid,
                    'types' => '购买套餐',
                    'money' => $package['money'],
                    'number' => $number,
                    'ky_number' => $ky_number,
                    'xf_number' => $xf_number,
                    'old' => $my_wallet['ky_number']? $my_wallet['ky_number']: 0,
                    'new' => $ky_number + $my_wallet['ky_number'],
                    'create_time' => time(),
                ];
                $shop = new Shop;
                $yslog = $shop->bncLog('ysbnc_log',$uid,'购买套餐','-'.$package['money'],$my_wallet['number'],$my_wallet['number'] - $package['money'] );
                $fzlog = $shop->bncLog('fzbnc_log',$uid,'购买套餐','+'.$freeze,$my_wallet['freeze'],$freeze + $my_wallet['freeze']);
                $xflog = $shop->bncLog('xfbnc_log',$uid,'购买套餐','+'.$xf_number,$my_wallet['xf_number'],$xf_number + $my_wallet['xf_number']);
                $kylog = $shop->bncLog('kybnc_log',$uid,'购买套餐','+'.$ky_number,$my_wallet['ky_number'],$ky_number + $my_wallet['ky_number']);
                if ($yslog && $fzlog && $xflog && $kylog) {

                    Db::commit();
                    return json(['code' => 0, 'message' => '购买成功']);
                }
            }
        }
        Db::rollback();
        return json(['code' => 1, 'message' => '购买失败']);
    }


    #复投
    public function buyPackageAgain(Request $request)
    {

        $uid = $this->userId;
        $userInfo = Db::table('user')->where('id', $uid)->find();
        $level = $userInfo['level'];
        $number = $request->post('number');
        if ($number % 20 != 0 ) {
            return json(['code' => 1, 'message' => '复投数量需为20 的整倍数']);
            
        }
        $my_wallet = Db::table('my_wallet')->where('user_id', $uid)->find();
        if ($my_wallet['ky_number'] < $number ) {
            return json(['code' => 1, 'message' => '账户可用资产余额不足']);
            
        }
        $package = Db::table('user_package')->where('level', $level)->find();

        // var_dump($my_wallet['number'].'|'.$my_wallet['freeze']);
        if ($userInfo['has_ft'] > $number * $package['recast']) {

            Db::startTrans();

            // $number = $package['money'] * 0.5;
            // $ky_number = $number * 0.8;
            // $xf_number = $number * 0.2;
            $freeze = $number * $package['recast'];
            $insdata = [
                'ky_number' => $my_wallet['ky_number'] - $number,
                'freeze' => $freeze + $my_wallet['freeze'],
                // 'xf_number' => $xf_number + $my_wallet['xf_number'],
                // 'ky_number' => $ky_number + $my_wallet['ky_number'],
                'update_time' => time()
            ];
            $upd = Db::table('my_wallet')->where('user_id', $uid)->update($insdata);
            if ($upd) {
                $log = [
                    'user_id' => $uid,
                    'types' => '复投',
                    // 'money' => $package['money'],
                    'number' => '-'.$number,
                    'old' => $my_wallet['ky_number'],
                    'new' => $my_wallet['ky_number'] - $number,
                    'create_time' => time(),
                ];
                $inslog = Db::table('kybnc_log')->insert($log);
                $shop = new Shop;
                $fzlog = $shop->bncLog('fzbnc_log',$uid,'复投','+'.$freeze,$my_wallet['freeze'],$freeze + $my_wallet['freeze']);
                if ($inslog && $fzlog ) {

                    Db::commit();
                    return json(['code' => 0, 'message' => '复投成功']);
                }
            }
            Db::rollback();
        }
        return json(['code' => 1, 'message' => '复投失败，数量超过闭环总倍数']);
    }


    #获取算力列表
    public function getHashList(Request $request)
    {
        $uid = $this->userId;
        $list = Db::table('hash_list')->where('user_id',$uid)->where('status','1')->select();
        if ($list) {
            foreach ($list as &$v) {
                $v['create_time'] = date('Y-m-d H:i',$v['create_time']);
            }
            return json(['code' => 0, 'message' => '获取成功','info' => $list ]);
            
        }
        return json(['code' => 1, 'message' => '暂无红包']);

    }

    #每日获取算力
    public function everydatGetHsah()
    {
        $a = 0;
        $user = Db::table('user')->where('level','>','0')->where('status',1)->select(); 
        // dump($user);;die;  
        foreach ($user as $v) {
            Db::startTrans();
            $updhashtime = Db::table('user')->where('id',$v['id'])->update(['hash_time'=>time()]);
            $my_wallet = Db::table('my_wallet')->where('user_id', $v['id'])->find();
            if ($my_wallet['freeze'] == '0') {
                continue;
            }

            $package = Db::table('user_package')->where('level',$v['level'])->find();
            $dayGet = $my_wallet['freeze'] * $package['day_power'];
            $freeze = $my_wallet['freeze'] - $dayGet;
            $ky_number = $dayGet * 0.8 + $my_wallet['ky_number'];
            $xf_number = $dayGet * 0.2 + $my_wallet['xf_number'];
            $data = [
                'freeze' => $freeze,
                'update_time' => time()
            ];
            $upd = Db::table('my_wallet')->where('user_id', $v['id'])->update($data);
            $hashdata = [
                'user_id' => $v['id'],
                'ky' => $dayGet * 0.8,
                'xf' => $dayGet * 0.2,
                'create_time' => time(),
            ];
            $insHash = Db::table('hash_list')->insert($hashdata);

            if (!$upd || !$insHash) {
                Db::rollback();
            }
            $shop = new Shop;
            $fzlog = $shop->bncLog('fzbnc_log',$v['id'],'挖矿算力','-'.$dayGet,$my_wallet['freeze'],$freeze);
            
            if (!$fzlog ) {
                
                Db::rollback();
                // return json(['code' => 0, 'message' => '挖矿成功']);
            }
                $a ++;
                Db::commit();
            // 回滚事务
            // return json(['code' => 1, 'message' => '挖矿失败']);
        }
        echo $a;
    }

    #点击获取
    public function clickHash(Request $request)
    {
        $hash_id = $request->post('hash_id');
        $hashInfo = Db::table('hash_list')->where('id',$hash_id)->find();
        $my_wallet = Db::table('my_wallet')->where('user_id', $hashInfo['user_id'])->find();
        $newWalletLog = [
            'ky_number' => $my_wallet['ky_number'] + $hashInfo['ky'],
            'xf_number' => $my_wallet['xf_number'] + $hashInfo['xf'],
            'update_time' => time()
        ];
        Db::startTrans();
        $shop = new Shop;
        $updHashStatus = Db::table('hash_list')->where('id',$hash_id)->delete();
        $my_wallet = Db::table('my_wallet')->where('user_id', $hashInfo['user_id'])->update($newWalletLog);
        $xflog = $shop->bncLog('xfbnc_log',$hashInfo['user_id'],'挖矿算力','+'.$hashInfo['xf'],$my_wallet['xf_number'],$my_wallet['xf_number'] + $hashInfo['xf']);
        $kylog = $shop->bncLog('kybnc_log',$hashInfo['user_id'],'挖矿算力','+'.$hashInfo['ky'],$my_wallet['ky_number'],$my_wallet['ky_number'] + $hashInfo['ky']);
        if ($my_wallet && $xflog && $kylog && $updHashStatus ) {
            Db::commit();
            return json(['code' => 0, 'message' => '提取成功']);
            
        }
        Db::rollback();
        return json(['code' => 1, 'message' => '提取失败']);

    }

    #每日获取算力 每天点击挖矿红包
    public function everydayGet(Request $request)
    {
        $uid = $this->userId;
        $userInfo = Db::table('user')->where('id',$uid)->find();
        $my_wallet = Db::table('my_wallet')->where('user_id', $uid)->find();

        if ($userInfo['level'] == '-1') {
            return json(['code' => 1, 'message' => '暂无红包']);
            
        }

        $now = date('md',time());
        $old = date('md',$userInfo['hash_time']);
        $a = $now - $old;
        // var_dump($now);
        // var_dump($old);
        // var_dump($a);die;
        if ($now == $old){
            return json(['code' => 1, 'message' => '今日已点红包']);
        }

        Db::startTrans();
        $updhashtime = Db::table('user')->where('id',$uid)->update(['hash_time'=>time()]);

        $package = Db::table('user_package')->where('level',$userInfo['level'])->find();
        $dayGet = $my_wallet['freeze'] * $package['day_power'];
        $freeze = $my_wallet['freeze'] - $dayGet;
        $ky_number = $dayGet * 0.8 + $my_wallet['ky_number'];
        $xf_number = $dayGet * 0.2 + $my_wallet['xf_number'];
        $data = [
            'freeze' => $freeze,
            'xf_number' => $xf_number,
            'ky_number' => $ky_number,
            'update_time' => time()
        ];
        $upd = Db::table('my_wallet')->where('user_id', $uid)->update($data);
        if ($upd) {
            $log = [
                'user_id' => $uid,
                'types' => '挖矿红包',
                'ky_number' => '+'.$dayGet * 0.8,
                'xf_number' => '+'.$dayGet * 0.2,
                'number' => '+'.$dayGet,
                'old' => $my_wallet['ky_number'],
                'new' => $dayGet * 0.8 + $my_wallet['ky_number'],
                'create_time' => time(),
            ];
            $inslog = Db::table('bnc_log')->insert($log);
            Db::commit();
            return json(['code' => 0, 'message' => '挖矿成功']);
        }
        // 回滚事务
        Db::rollback();
        return json(['code' => 1, 'message' => '挖矿失败']);

    }




       
}