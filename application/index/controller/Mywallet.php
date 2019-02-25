<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/29
 * Time: 17:10
 */

namespace app\index\controller;


use app\common\entity\MachineList;
use app\common\entity\Message;
use app\common\entity\MywalletLog;
use app\common\entity\Proportion;
use app\common\entity\UserPackage;
use app\common\entity\VoucherList;
use app\common\entity\WithdrawRatio;
use think\Db;
use think\Request;
use app\common\entity\User;


class Mywallet extends Base
{

    #获取用户钱包信息
    public function getMywallet()
    {
        $uid = $this->userId;
        $list = \app\common\entity\Mywallet::where('user_id', $uid)->find();
        $list['ks_ratio'] = WithdrawRatio::where('key', 'ky_times')->value('ratio');
        $list['k_ratio'] = WithdrawRatio::where('key', 'k_times')->value('ratio');
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);

    }

    #可售余额兑换矿池资产
    public function ksToFz(Request $request)
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }
        $num = $request->post('num');
        $trad_password = $request->post('trad_password');

        if ($num % 10 != 0) {
            return json(['code' => 1, 'message' => '兑换数量应为10的倍数']);
        }
        $wallet = \app\common\entity\Mywallet::where('user_id', $uid)->find();
        if ($wallet['sell_number'] < $num) {
            return json(['code' => 1, 'message' => '可售余额不足']);
        }

        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkSafePassword($trad_password, $user);
        if (!$result) {
            return json(['code' => 1, 'message' => '二级密码输入错误']);
        }

        $kyRatio = WithdrawRatio::where('key', 'ky_times')->value('ratio');
        $mywalletlog = new MywalletLog();
        $ins_sell_number_log = $mywalletlog->addLog($uid, $num, 'sell_number', '可售余额兑换矿池', 3, 2);
        $ins_freeze_log = $mywalletlog->addLog($uid, $num * $kyRatio, 'freeze', '可售余额兑换矿池', 2, 1);

        $upd_sell_number = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('sell_number', $num);
        $upd_freeze = \app\common\entity\Mywallet::where('user_id', $uid)->setInc('freeze', $num * $kyRatio);
        if ($upd_freeze && $upd_sell_number) {
            return json(['code' => 0, 'message' => '兑换成功']);
        }
        return json(['code' => 1, 'message' => '兑换失败']);

    }

    #钱包余额转入矿池资产
    public function numToFz(Request $request)
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }
        $num = $request->post('num');
        if ($num  < 10) {
            return json(['code' => 1, 'message' => '数量必须为10的倍数']);
        }
        if ($num%10 != 0) {
            return json(['code' => 1, 'message' => '数量必须为10的倍数']);
            
        }
        $trad_password = $request->post('trad_password');

        $wallet = \app\common\entity\Mywallet::where('user_id', $uid)->find();
        if ($wallet['number'] < $num) {
            return json(['code' => 1, 'message' => '钱包余额不足']);
        }

        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkSafePassword($trad_password, $user);
        if (!$result) {
            return json(['code' => 1, 'message' => '二级密码输入错误']);
        }

        $kyRatio = WithdrawRatio::where('key', 'ky_times')->value('ratio');
        $mywalletlog = new MywalletLog();
        $ins_sell_number_log = $mywalletlog->addLog($uid, $num, 'number', '钱包余额兑换矿池', 1, 2);
        $ins_freeze_log = $mywalletlog->addLog($uid, $num * $kyRatio, 'freeze', '钱包余额兑换矿池', 2, 1);

        $upd_number = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('number', $num);
        $upd_freeze = \app\common\entity\Mywallet::where('user_id', $uid)->setInc('freeze', $num * $kyRatio);
        if ($upd_freeze && $upd_number) {
            return json(['code' => 0, 'message' => '兑换成功']);
        }
        return json(['code' => 1, 'message' => '兑换失败']);


    }


    #财务记录
    public function myWalletLog(Request $request)
    {
        $uid = $this->userId;
        $list = MywalletLog::where('user_id', $uid)
            ->where('types', $request->post('types'))
            ->page($request->post('page'))
            ->limit($request->post('limit'))
            ->order('create_time desc,new desc')
            ->select();
        $count = MywalletLog::where('user_id', $uid)
            ->where('types', $request->post('types'))->count();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list, 'count' => $count]);
        }
        return json(['code' => 1, 'message' => '获取失败']);

    }


    #矿机列表
    public function machineList()
    {
        $uid = $this->userId;
        $list = MachineList::where('m.user_id', $uid)->where('m.status', 1)
            ->alias('m')
            ->field('m.*,p.num as day_get')
            ->leftJoin('user_package p', 'm.level = p.level')
            ->select();
        foreach ($list as &$v) {
            $v['hour_get'] = $v['day_get'] / 24;
            $end_time_day = UserPackage::where('level', $v['level'])->value('end_time');
            $v['end_time'] = $end_time_day * 24;
            $v['use_time'] = floor((time() - strtotime($v['create_time'])) / 3600);
        }
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);

    }

    #矿机详情
    public function machineInfo(Request $request)
    {
        $machine_id = $request->post('id');
        $list = MachineList::where('id', $machine_id)->find();
        $end_time_day = UserPackage::where('level', $list['level'])->value('end_time');
        $day_get = UserPackage::where('level', $list['level'])->value('num');
        $miao_get = $day_get / 24 / 3600;

        $end_time_hour = $end_time_day * 24;
        $use_time = time() - strtotime($list['create_time']);
        $can_get_time = time() - $list['get_time'];
        $hour = floor($use_time / 3600);
        $list['end_hour'] = $end_time_hour;
        $list['use_time'] = $hour;
        $list['miao_get'] = $miao_get;
        $list['total_num'] = $list['get_num'] + ($miao_get * $can_get_time);
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }


    #每小时更新可领取量
    public function updMachineGet()
    {
        $is_up = WithdrawRatio::where('key', 'num_up')->value('ratio');
        if ($is_up == 1) {
            return 'is_up';
        }
        // file_put_contents(dirname(__FILE__).'/dsads'.date('ymd_His'),6666 );
        $list = MachineList::where('status', 1)->where('update_time1','<=', time() - 3600)->limit(3500)->order('id desc')->select();

        dump($list);
        foreach ($list as $v) {
            $time = time() - $v['get_time'];
            if ($time >= (3600 * 24)) {//3600 * 24
                $is_24 = 2;
            } else {
                $is_24 = 1;
            }
            $res = $this->machineMake($v['id'], $v['get_num'] ,$v['level'], $time, $is_24);
        }
        return 'ok';

    }

    #封禁用户
    public function sealUser(){
        $is_up = WithdrawRatio::where('key', 'num_up')->value('ratio');
        if ($is_up == 1) {
            return 'is_up';
        }
        $marketList = \app\common\entity\Market::where('status', 2)->whereOr('status', 3)->select();
        foreach ($marketList as $vv) {

            if ($vv['status'] == 2) {
                if ((time() - strtotime($vv['matching_time'])) > 7200) {//7200
                    $mywalletLogModel = new MywalletLog();
                    $toShell_ratio = WithdrawRatio::where('key','toShell')->value('ratio');
                    $fan_num = $vv['num'] * $toShell_ratio + $vv['num'];
                    $upd_status = User::where('id', $vv['user_id'])->update(['status' => -1]);
                    $upd_marker = \app\common\entity\Market::where('id', $vv['id'])->update(['status' => 5]);

                    $insLog1 = $mywalletLogModel->addLog($vv['from_user_id'], $fan_num, 'sell_number', '买方未确定返款', 3, 1);
                    $insLog2 = $mywalletLogModel->addLog($vv['from_user_id'], $vv['num'], 'can_sell', '买方未确定返款', 4, 1);

                    $upd_wallet_for_sell_number = \app\common\entity\Mywallet::where('user_id', $vv['from_user_id'])->setInc('sell_number', $fan_num);
                    $upd_wallet_for_can_sell = \app\common\entity\Mywallet::where('user_id', $vv['from_user_id'])->setInc('can_sell', $vv['num']);
                }
            }
            if ($vv['status'] == 3) {
                if ((time() - strtotime($vv['pay_time'])) > 7200) {//7200
                    $upd_status = User::where('id', $vv['from_user_id'])->update(['status' => -1]);
                    $insLog3 = (new MywalletLog())->addLog($vv['user_id'], $vv['num'], 'number', '买入完成', 1, 1);
                    $ky_num_times = WithdrawRatio::where('key', 'ky_num_times')->value('ratio');
                    $updwallet_can_sell = \app\common\entity\Mywallet::where('user_id', $vv['user_id'])->setInc('can_sell', $vv['num'] * $ky_num_times);
                    $upd_wallet_num = \app\common\entity\Mywallet::where('user_id', $vv['user_id'])->setInc('number', $vv['num']);
                    $updwallet_total = \app\common\entity\Mywallet::where('user_id', $vv['user_id'])->setInc('total_num', $vv['num']);
                    $upTeamhash = (new \app\common\entity\Mywallet())->upParentsHash($vv['user_id'],$vv['num']);
                    $upd_market_status = \app\common\entity\Market::where('id', $vv['id'])->update(['status' => 4, 'end_time' => date('Y-m-d H:i:s', time())]);
                }
            }
        }
        return 'Seal_ok';
    }

    public function aaabbb()
    {
    	// $userModel = new User();
        // $res = Db::table('user_invite_code')->where('invite_code',0)->find();
    	$a =Db::table('user')
    	->where('real_name','电风扇')->limit(100)
    	->select();
    	$res = [];
    	foreach ($a as $k=> $vv) {
    		$res[$k]['id'] = $vv['id'];
        	$res[$k]['user_invite_code'] = Db::table('user_invite_code')->where('user_id',$vv['id'])->select();
        	$res[$k]['user'] = Db::table('user')->where('id',$vv['id'])->select();
        	$res[$k]['my_wallet'] = Db::table('my_wallet')->where('user_id',$vv['id'])->select();
        	$res[$k]['has_machine'] = Db::table('has_machine')->where('user_id',$vv['id'])->select();
        	$res[$k]['unsealing_list'] = Db::table('unsealing_list')->where('user_id',$vv['id'])->select();
        	$res[$k]['my_wallet_log'] = Db::table('my_wallet_log')->where('user_id',$vv['id'])->select();
        	$res[$k]['market_list'] = Db::table('market_list')->where('user_id',$vv['id'])->select();
        	$res[$k]['machine_list'] = Db::table('machine_list')->where('user_id',$vv['id'])->select();
    	}
    	// $tui_3 = $userModel->getChildsInfoId(10000);
     //    $result = array_reduce($tui_3, function ($result, $value) {
     //            return array_merge($result, array_values($value));
     //        }, array());
    	// $tui_32 = $userModel->getChildsInfo(10690, 4);
    	dump($res);die;
    	return json(['1'=>$a ,'2'=>$tui_32,'3'=>$result]);
    }
    #更新等级
    public function updUserLevel(){
        $is_up = WithdrawRatio::where('key', 'num_up')->value('ratio');
        if ($is_up == 1) {
            return 'is_up';
        }
        $userModel = new User();
        $machineModel = new MachineList();
        $get_2000_send = WithdrawRatio::where('key', 'get_2000_send')->value('ratio');
        $user_level_send = Db::table('user_level_send')->select();
        
            
        
        // $userInfo = User::whereTime('update_time1',  'not between', [time(), time()-7200])->limit(100)->select();
        $userInfo = User::where('update_time1','<=', time() - 3600)->limit(2500)->order('id desc')->select();

        dump($userInfo);
        // dump(111);die;
        foreach ($userInfo as $v) {
            if ((time()-strtotime($v['register_time'])) > 3600*48 ) {
                $upd_is_new = User::where('id',$v['id'])->update(['is_new'=>1]);
            }
            $total = \app\common\entity\Mywallet::where('user_id', $v['id'])->value('number');
            $team_hash1 = \app\common\entity\Mywallet::where('user_id', $v['id'])->value('team_hash');

            $team_hash = floor($team_hash1/10);
            if ($v['level'] < 1) {
                $tui_3 = $userModel->getChildsInfoThreeNum($v['id']);
                // if ($zhitui > 5 && $team_hash > 500 && $tui_3 >= 58) {
                $zhitui = User::where('pid', $v['id'])->where('level', '>=', $user_level_send[0]['zhitui_level'])->count();
                // if ($v['id'] == 10657) {
                //     var_dump($tui_3);die;
                // }
                if ($zhitui >= $user_level_send[0]['zhitui_num'] && $team_hash >= $user_level_send[0]['hash_num']  && $tui_3 >= $user_level_send[0]['three'] ) {
                    User::where('id', $v['id'])->update(['level' => 1,'update_time1' => time()]);
                    $machineModel->insMachine($v['id'], 1, 1, 20);
                    // $machineModel->insMachine($v['id'], 2, 1, 5);
                }
            } elseif ($v['level'] < 2) {
                $zhitui_1 = User::where('pid', $v['id'])->where('level', '>=', $user_level_send[1]['zhitui_level'])->count();

                if ($zhitui_1 >= $user_level_send[1]['zhitui_num'] && $team_hash >= $user_level_send[1]['hash_num']) {

                    User::where('id', $v['id'])->update(['level' => 2,'update_time1' => time()]);
                    $machineModel->insMachine($v['id'], 2, 2, 10);
                    $machineModel->insMachine($v['id'], 1, 2, 15);
                    // $machineModel->insMachine($v['id'], 3, 2, 5);
                }
            } elseif ($v['level'] < 3) {
                $zhitui_2 = User::where('pid', $v['id'])->where('level', '>=', $user_level_send[2]['zhitui_level'])->count();

                if ($zhitui_2 >= $user_level_send[2]['zhitui_num'] && $team_hash >= $user_level_send[2]['hash_num']) {

                    User::where('id', $v['id'])->update(['level' => 3,'update_time1' => time()]);
                    $machineModel->insMachine($v['id'], 2, 3, 10);
                    $machineModel->insMachine($v['id'], 3, 3, 5);
                }
            } elseif ($v['level'] < 4) {
                $zhitui_3 = User::where('pid', $v['id'])->where('level', '>=', $user_level_send[3]['zhitui_level'])->count();

                if ($zhitui_3 >= $user_level_send[3]['zhitui_num'] && $team_hash >= $user_level_send[3]['hash_num']) {

                    User::where('id', $v['id'])->update(['level' => 4,'update_time1' => time()]);
                    $machineModel->insMachine($v['id'], 3, 4, 5);
                    $machineModel->insMachine($v['id'], 4, 4, 1);
                }
            }
            User::where('id', $v['id'])->update(['update_time1' => time()]);
            $walletInfo = \app\common\entity\Mywallet::where('user_id', $v['id'])->find();
            $send_num = floor($walletInfo['zhitui_num'] / $get_2000_send);
            // var_dump($send_num);die;
            if ($send_num > $v['send_2000']) {
                $sendNum = $send_num - $v['send_2000'];
                User::where('id',$v['id'])->update(['send_2000'=> $send_num]);
                $machineModel->insMachine($v['id'], 1, 6, $sendNum);

            }
        }

        return 'Level_ok';
    }

    #每小时更新每日获得
    public function machineMake($machine_id,$get_num , $level, $time, $is_24)
    {
        $UserPackage = UserPackage::where('level', $level)->find();
        $total_num = $UserPackage['total_num'];
        $day_get = $UserPackage['num'];
        // $machineInfo = MachineList::where('id', $machine_id)->find();
        if ($get_num >= $total_num) {
            //过期
            $updStatus = MachineList::where('id', $machine_id)->update(['status' => 2]);
            return false;
        }
        if ($time >= 3600 * 24) {//60*5  3600 * 24
            $updtoday = MachineList::where('id', $machine_id)->update(['today_get' => 1]);
        }

        // $day_get = UserPackage::where('level', $level)->value('num');
        $miao_get = $day_get / 24 / 3600;
        $upd_can_get = $miao_get * $time;
        if ($is_24 == 1) {
            $upd = MachineList::where('id', $machine_id)->update(['can_get_num' => $upd_can_get, 'is_24' => $is_24 ,'update_time1' => time()]);
        } else {
            $upd = MachineList::where('id', $machine_id)->update(['can_get_num' => $day_get, 'is_24' => $is_24 ,'update_time1' => time()]);
        }
        if ($upd) {
            return true;
        }
        return false;
    }

    #点击获取矿机资产
    public function chickMachine(Request $request)
    {
        // return json(['code' => 0, 'message' => '关闭']);

        $chick_num = 0;
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }
        $list = MachineList::where('user_id', $uid)
            ->where('status', 1)
            ->where('today_get', 1)
            ->where('is_24', 2)
            ->select();
        // dump($list);die;
        $total_get = 0;
        foreach ($list as $v) {
            $day_get = UserPackage::where('level', $v['level'])->value('num');
            $run_time_num = floor((time()-strtotime($v['create_time']))/(3600*24))*$day_get ;
            $total_can_get_num = $run_time_num - $v['get_num'] ;
            // return $total_can_get_num; die;
            $res = $this->getMachineNumber($uid, $total_can_get_num);
            if ($res) {
                $upd = MachineList::where('id', $v['id'])->update(['today_get' => 2, 'can_get_num' => 0, 'is_24' => 1, 'get_time' => time()]);
                $updtotal = MachineList::where('id', $v['id'])->setInc('get_num', $total_can_get_num);
                $total_get = $day_get + $total_get;
                if ($upd) {
                    $chick_num++;
                }
            }
        }
//        $share = (new \app\common\entity\Mywallet())->share($uid,$total_get);
        return json(['code' => 0, 'message' => '获得' . $chick_num . '个猪宝宝']);


    }

    #更新获取矿机钱包资产
    public function getMachineNumber($uid, $day_get)
    {
        $insLog = (new MywalletLog())->addLog($uid, $day_get, 'number', '猪宝宝', 1, 1);
        $upd = \app\common\entity\Mywallet::where('user_id', $uid)->setInc('number', $day_get);
        return $upd;

    }

    #是否登录
    public function is_login()
    {
        $uid = $this->userId;
        if ($uid) {
            return json(['code' => 0, 'message' => 'yes', 'info' => $uid]);
        }
        return json(['code' => 1, 'message' => 'no', 'url' => 'login']);

    }

    #发送请求
    public function http_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    #获取一条最新行情
    public function getNewHashRatio()
    {
        $list = Proportion::order('date desc')->find();
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #获取代币行情
    public function getHashRatio(Request $request)
    {

        $limit = WithdrawRatio::where('key', 'ratio_day')->value('ratio');
        $list = Proportion::limit($limit)->order('date desc')->select();
        foreach ($list as &$v) {
            $v['date'] = str_replace('-', '.', $v['date']);
        }
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #每日获取矿池
    public function everydayGetFreeze()
    {
        $uid = $this->userId;
        $user_info = User::where('id', $this->userId)->find();
        if ($user_info['status'] == -1) {
            return json(['code' => 1, 'message' => '用户已被封禁']);
        }

        $hash_time1 = User::where('id', $uid)->value('hash_time');
        $nowtime = date('Ymd');
        $hash_time = date('Ymd', $hash_time1);
        if ($nowtime == $hash_time) {

            return json(['code' => 1, 'message' => '今日已领取,请明日领取']);

        }
        $freeze_ratio = WithdrawRatio::where('key', 'k_everyday')->value('ratio');
        $freeze = \app\common\entity\Mywallet::where('user_id', $uid)->value('freeze');
        if ($freeze <= 0) {
            return json(['code' => 1, 'message' => '矿池不足']);

        }
        $down_freeze = $freeze * $freeze_ratio;
        $mywalletLog = new MywalletLog();
        $insFzLog = $mywalletLog->addLog($uid, $down_freeze, 'freeze', '每日释放', 2, 2);
        $insSellLog = $mywalletLog->addLog($uid, $down_freeze, 'sell_number', '每日释放', 3, 1);
        $updFz = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('freeze', $down_freeze);
        $updSell = \app\common\entity\Mywallet::where('user_id', $uid)->setInc('sell_number', $down_freeze);
        if ($updFz && $updSell) {
            $updTime = User::where('id', $uid)->update(['hash_time' => time()]);
            $share_team = (new \app\common\entity\Mywallet())->share($uid, $down_freeze);
            return json(['code' => 0, 'message' => '获取成功']);

        }
        return json(['code' => 1, 'message' => '获取失败']);

    }


    public function upMessage(Request $request)
    {
        $img = $request->post('img');
        $ins = Message::insert(['user_id' => $this->userId, 'market_id' => $request->post('market_id'), 'create_time' => time(), 'img' => $img]);
        if ($ins) {
            return json(['code' => 0, 'message' => '提交成功']);
        }
        return json(['code' => 1, 'message' => '提交失败']);
    }

    #获取平台支付宝收款码
    public function ptZfb()
    {
        $list = Db::table('image')->where('id', 2)->value('pic');

        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #众筹支付凭证提交
    public function updVoucherPic(Request $request)
    {
        $uid = $this->userId;
        if (!$request->post('zfb_img')) {
            return json(['code' => 1, 'message' => '未上传图片']);

        }
        $log = [
            'user_id' => $uid,
            'num' => $request->post('num'),
            'img' => $request->post('zfb_img'),
            'status' => 1,
            'create_time' => time()
        ];
        $ins = VoucherList::insert($log);
        if ($ins) {
            return json(['code' => 0, 'message' => '提交成功']);

        }
        return json(['code' => 1, 'message' => '申请失败']);

    }

    #z众筹信息
    public function UnsealingInfo()
    {
        $list = [];
        $unsealing_total_num = WithdrawRatio::where('key', 'unsealing_total_num')->value('ratio');
        $unsealing_has_num = WithdrawRatio::where('key', 'unsealing_has_num')->value('ratio');
        $time = WithdrawRatio::where('key', 'num_up')->value('create_time');
        $ratio = $unsealing_has_num / $unsealing_total_num * 100;
        $list['unsealing_total_num'] = $unsealing_total_num;
        $list['unsealing_has_num'] = $unsealing_has_num;
        $list['ratio'] = $ratio;
        $list['time'] = date('Y-m-d H:i:s', $time);
        $list['proportion'] = Proportion::order('date desc')->value('ratio');
        if ($list) {
            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);
        }
        return json(['code' => 1, 'message' => '获取失败']);
    }

    #用户之间互转
    public function UserToUser(Request $request)
    {
        $uid = $this->userId;

        $user_can_sell = WithdrawRatio::where('key', 'user_can_sell')->value('ratio');
        if ($user_can_sell == 0) {
            return json(['code' => 1, 'message' => '用户互转已关闭']);

        }

        $num = $request->post('num');
        $mobile = $request->post('mobile');
        if (!$num || !$mobile ) {
            return json(['code' => 1, 'message' => '参数错误']);

        }
        $trad_password = $request->post('trad_password');



        $user_to_min = WithdrawRatio::where('key','user_to_min')->value('ratio');
        $user_to_max = WithdrawRatio::where('key','user_to_max')->value('ratio');
        $ratio = WithdrawRatio::where('key','user_to_user')->value('ratio');
        $ratioNum = $num + $num * $ratio;
        if ($num < $user_to_min){
            return json(['code' => 1, 'message' => '互转数量不能小于'.floatval($user_to_min)]);

        }
        $mywalletInfo = \app\common\entity\Mywallet::where('user_id',$uid)->find();

        if ($mywalletInfo['sell_number'] < $ratioNum){
            return json(['code' => 1, 'message' => '可售猪猪不足']);
        }
        if ($mywalletInfo['can_sell'] < $num){
            return json(['code' => 1, 'message' => '可售额度不足']);
        }

        $user = User::where('id', $this->userId)->find();
        $service = new \app\common\service\Users\Service();
        $result = $service->checkSafePassword($trad_password, $user);
        if (!$result) {
            return json(['code' => 1, 'message' => '二级密码输入错误']);
        }

        $user_mobile = User::where('id',$uid)->value('mobile');
        $for_user_id = User::where('money_address',$mobile)->value('id');
        if ( !$for_user_id ) {
            return json(['code' => 1, 'message' => '用户不存在']);

        }

        $mywalletLog = new MywalletLog();
        $ky_num_times = WithdrawRatio::where('key', 'ky_num_times')->value('ratio');
        $insSellNumLog = $mywalletLog->addLog($uid, $ratioNum, 'sell_number', '转给'.$mobile, 3, 2);
        $insCanSellLog = $mywalletLog->addLog($uid, $num, 'can_sell', '转给'.$mobile, 4, 2);
        $insforNumberLog = $mywalletLog->addLog($for_user_id, $num, 'number', '来自'.$user_mobile, 1, 1);
        $insforCanSellLog = $mywalletLog->addLog($for_user_id, $num*$ky_num_times, 'can_sell', '来自'.$user_mobile, 4, 1);
        $updSellNum = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('sell_number', $ratioNum);
        $updCanSell = \app\common\entity\Mywallet::where('user_id', $uid)->setDec('can_sell', $num);
        $updforNumberNum = \app\common\entity\Mywallet::where('user_id', $for_user_id)->setInc('number', $num);
        $updforCanSellNum = \app\common\entity\Mywallet::where('user_id', $for_user_id)->setInc('can_sell', $num*$ky_num_times);

        if ($updforNumberNum && $updforCanSellNum && $updSellNum && $updCanSell) {
            return json(['code' => 0, 'message' => '转出成功']);
        }
        return json(['code' => 1, 'message' => '转出失败']);

    }

}