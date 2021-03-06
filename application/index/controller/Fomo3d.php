<?php

namespace app\index\controller;

use app\index\model\SiteAuth;
use app\common\entity\Keychange;
use app\common\entity\FomoGame;
use app\common\entity\FomoTeam;
use app\common\entity\FomoConfig;
use app\common\entity\Buykey;
use app\common\entity\Divide;
use app\common\entity\Bonus;
use app\common\entity\User;
use app\common\entity\FomoWithdraw;
use think\Controller;
use think\Request;
use think\Db;
use app\common\entity\UserInviteCode;

class Fomo3d extends Base {
     
    public function initialize() {
        
        parent::initialize();
    }

    public function index(Request $request) {

        $InCode = $request->get('incode')??'';
           
    	//查看该期信息
    	$FomoGame = new FomoGame();
        $FomoGamelist = $FomoGame->where('status',1)->order('id','desc')->find();

        $buykey = new buykey();
        $buykeystatistics = $buykey->field('sum(expense) as sumexpense,teamid')->where('periods',$FomoGamelist['id'])->where('status',1)->group('teamid')->select();

       	$statistic = [];
       	$statisticarr = [];


       	foreach ($buykeystatistics as $value) {
       		$statistic[$value['teamid']] = $value;
       		$statisticarr[] = $value['teamid'];
       	}
   		

        $allteam = [];

        if(!empty($FomoGamelist)){
        	//查看队伍情况
        	$allteam = unserialize($FomoGamelist['team_ids']);

			$flag = [];

			foreach ($allteam as &$value) {
				$flag[] = $value['teamid'];
				$team = FomoTeam::where('id',$value['teamid'])->find();
				$value['content'] = $team['content'];
				$value['intro'] = $team['intro'];

				if(in_array($value['teamid'], $statisticarr)){
					$value['expense'] = $statistic[$value['teamid']]['sumexpense'];
				}else{
					$value['expense'] = 0;
				}
				
			}

			array_multisort($flag, SORT_ASC, $allteam);
        }

        $information = $buykey->field('sum(expense) as sumexpense,sum(bonus) as sumbonus,sum(keynum) as sumkeynum')->where('periods',$FomoGamelist['id'])->where('status',1)->find();
        $FomoConfig = new FomoConfig();
        $addseconds = $FomoConfig->getValue('addseconds');
        $FomoGamelist['qrcodes'] = $FomoConfig->getValue('path');


        $FomoGamelist['sumexpense'] = $information['sumexpense'];
        $FomoGamelist['sumbonus'] = $information['sumbonus'];
        $FomoGamelist['sumkeynum'] = $information['sumkeynum'];
        $FomoGamelist['sumtime'] = $information['sumkeynum']*$addseconds;
        $FomoGamelist['sumyear'] = $FomoGamelist['sumtime']/86400;

        if( round($FomoGamelist['sumyear']/365,2) > 0){
        	$FomoGamelist['sumyear'] = round($FomoGamelist['sumyear']/365,2).' 年';
        }else{
        	$FomoGamelist['sumyear'] = round($FomoGamelist['sumyear']/12,2).' 月';
        }
   		
   		//查看最后下单的人
        $endbuy = $buykey->field('user_id')->where('periods',$FomoGamelist['id'])->where('status',1)->order('id','desc')->find();
      	if(count($endbuy)>0){
      		$enduser = User::where('id',$endbuy['user_id'])->find();
   			$FomoGamelist['nick_name'] = $enduser['nick_name'];
      	}


   		$FomoGamelist['myselfkey'] = 0;
   		$FomoGamelist['promptlybonus'] = 0;
   		$FomoGamelist['bonus'] = 0;
   		$FomoGamelist['lockbonus'] = 0;


   		if($this->userId){
   			//查看用户
   			$member = User::where('id',$this->userId)->find();

   			//查看本期已买key
   			$buykeymyself = $buykey->field('sum(keynum) as sumkeynum')->where('periods',$FomoGamelist['id'])->where('status',1)->where('user_id',$this->userId)->find();
   			$FomoGamelist['myselfkey'] = $buykeymyself['sumkeynum'];

   			$promptlybonus = Bonus::field('sum(bonus) as sumbonus')->where('periods',$FomoGamelist['id'])->where('types',1)->where('user_id',$this->userId)->find();
   			$FomoGamelist['promptlybonus'] = $promptlybonus['sumbonus'];

   			$FomoGamelist['bonus'] = $member['bonus'];

   			//查看当前该期所有的key 总和
	        $keynumtotal =  $buykey->where('status',1)->where('periods',$FomoGamelist['id'])->column('sum(keynum)');
	        $keynumtotal = $keynumtotal[0];

			$ratio = bcdiv($FomoGamelist['myselfkey'],$keynumtotal,8);

	        $FomoGamelist['lockbonus'] = $FomoGamelist['capital']>0? bcmul(bcmul($FomoGamelist['capital'],$FomoGamelist['bonus_scale'],8),$ratio,8)/100 :0;
   		}
        
        $nick_name = $this->userInfo?$this->userInfo->nick_name:'';
        $invitedCode = $this->userId?UserInviteCode::getCodeByUserId($this->userId):'';

        return $this->fetch('index', [
        	'allteam'=>$allteam,
        	'fomogame'=>$FomoGamelist,
            'userId'=>$this->userId,
            'nickname'=>$nick_name,
            'invitedCode'=>$invitedCode,
            'InCode'=>$InCode
        ]);
    }



    public function buyKey(Request $request) {

    	$tixToBuy = intval($request->post('tixToBuy'))??'';
    	$teamid = intval($request->post('teamid'))??'';
    	$paytype = intval($request->post('paytype'))??'';
        $userid = $this->userId;
        $member = User::where('id',$userid)->find();


    	if(empty($tixToBuy) || $tixToBuy<=0 ){
            return json([
                'code' => -1,
                'message' => '参数有误！',
            ]);
    	}

    	if(empty($teamid)){
            return json([
                'code' => -1,
                'message' => '请选择队伍！',
            ]);
    	}

    	if(empty($paytype) || !in_array($paytype,array(1,2,3)) ){
            return json([
                'code' => -1,
                'message' => '支付类型有误！',
            ]);
    	}


        if(empty($userid) || empty($member) ){
            return json([
                'code' => -1,
                'message' => '您还没登录！',
            ]);
        }



    	//查看key 兑换比例
        $keychange = new Keychange();
        $keyvalue = $keychange->getKey();


    	//计算需要多ETH
    	$needeth = bcmul($tixToBuy,$keyvalue,8);

    	//支付区间
        switch ($paytype) {
            //BTH
            case '1':
                # code...
                break;

            //金库
            case '2':
                //查看小金库够不够钱
                if($member['bonus'] < $needeth){
                    return json([
                        'code' => -1,
                        'message' => '小金库金额不足！',
                    ]);
                }

                break;

            //二维码
            case '3':
                # code...
                break;
        }




    	//查看该期信息
        $FomoGame = FomoGame::where('status',1)->order('id','desc')->find();

        $teamlist = unserialize($FomoGame['team_ids']);
        $team = $teamlist[$teamid];

        if(empty($team) || empty($FomoGame)){
        	return json([
                'code' => -1,
                'message' => '参数有误！',
            ]);
        }


        if(time() > $FomoGame['endtime']){

        	return json([
                'code' => -1,
                'message' => '本期已结束！',
            ]);
        
        }


        $FomoConfig = new FomoConfig();
        $keySet = $FomoConfig->getALLConfig();


 		$capital = bcdiv(bcmul($needeth,$team['pond_scale'],8),100,8);
 		$bonus = bcdiv(bcmul($needeth,$team['bonus_scale'],8),100,8);
 		$inviteaward = bcdiv(bcmul($needeth,$keySet['inviteaward'],8),100,8);
 		$teamaward = bcdiv(bcmul($needeth,$keySet['teamaward'],8),100,8);
 		$dropaward = bcdiv(bcmul($needeth,$keySet['dropaward'],8),100,8);

    	$buyKeyarr = [
    		'periods'=> $FomoGame['id'],
    		'user_id'=>$this->userId,
    		'keynum'=>$tixToBuy,
    		'expense'=>$needeth,
    		'keyval'=>$keyvalue,
    		'teamid'=>$teamid,
    		'capital'=>$capital,
    		'bonus'=>$bonus,
    		'inviteaward'=>$inviteaward,
    		'teamaward'=>$teamaward,
    		'dropaward'=>$dropaward,
    		'status'=>1,
    		'paytime'=>time(),
    		'paytype'=>$paytype,
    		'createtime'=>time(),
    	];

    	$Buykey = new Buykey();


        Db::startTrans();
        try {

            //扣钱
            switch ($paytype) {
                //BTH
                case '1':
                    # code...
                    break;

                //金库
                case '2':
                 
                //二维码
                case '3':
                    # code...
                    break;
            }


            $Buykey->save($buyKeyarr);
            $Buykeyid = $Buykey->id;

            //更新该期资金池累积和空投
            $Divide = new Divide();
            $Divide->upDivide($Buykeyid);


             //控制key价格增加 更新倒计时
            $keychange->addKey($tixToBuy);

            //发放分红
            $Bonus = new Bonus();
            $Bonus->giveBonus($Buykeyid);


            Db::commit();

        } catch (\Exception $e) {

            Db::rollback();

            return json([
                'code' => -1,
                'message' => '系统繁忙！',
            ]);
        }

        return json([
            'code' => 0,
            'message' => '',
        ]);

    }





    public function indexdata(Request $request) {

 		$op = $request->post('op')??'';

        if($op=='start'){

            $data = array();

            //查看key 兑换比例
            $keychange = new Keychange();
            $keyvalue = $keychange->getKey();
            $data['keyvalue'] = $keyvalue;
    
            //查看该期信息
            $FomoGame = FomoGame::where('status',1)->order('id','desc')->find();

            if(!empty($FomoGame)){
            	$data['endtime'] = date('m/d/Y H:i:s',$FomoGame['endtime']);
            }
            

            return json([
                'code' => 0,
                'message' => 'success',
                'data' => $data,
            ]);

        }
    }


    public function withdraw(Request $request) {

        $withdrawnum =  $request->post('withdrawnum')?floatval($request->post('withdrawnum')):'';
        $userid = $this->userId;
        $member = User::where('id',$userid)->find();


        if(empty($userid) || empty($member) ){
            return json([
                'code' => -1,
                'message' => '您还没登录！',
            ]);
        }


        if(empty($withdrawnum) || $withdrawnum <= 0){
            return json([
                'code' => -1,
                'message' => '参数有误！',
            ]);
        }

        if($member['bonus'] < $withdrawnum){
            return json([
                'code' => -1,
                'message' => '小金库金额不足！',
            ]);
        }

        $User = new User();
        $wssn =  $User->setWithdrawNumber($userid);

        $withdrawarr = [
            'user_id'=>$userid,
            'wssn'=>$wssn,
            'money'=>$withdrawnum,
            'status'=>0,
            'createtime'=>time(),
        ];

        Db::startTrans();
        try {

            //扣钱
            $User->setBonus($userid,'bonus',-$withdrawnum,'提取减少');

            $FomoWithdraw = new FomoWithdraw();
            $result = $FomoWithdraw->save($withdrawarr);

            if (!$result) {
                throw new \Exception('操作失败');
            }

            Db::commit();

        } catch (\Exception $e) {

            Db::rollback();
        }

        return json([
            'code' => 0
        ]);

    }



}
