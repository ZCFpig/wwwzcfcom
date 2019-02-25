<?php

namespace app\index\controller;

use app\common\entity\User;
use app\common\entity\LegalConfig;
use app\common\entity\LegalReturnlog;
use app\common\entity\LegalReportcentre;
use app\common\service\Users\Service;
use app\index\validate\RegisterForm;
use app\index\model\SendCode;
use think\Controller;
use think\Request;
use think\Db;


class Baodan extends Base {


    public function index() {

        $userid = $this->userId;
        $member = User::where('id',$userid)->find();

        $LegalConfig = new LegalConfig();
        $member['exchangeratio'] = $LegalConfig->getValue('exchangeratio');

        $member['addbaodan'] = LegalReportcentre::where('user_id',$userid)->where('status',1)->column('sum(money)');
        $member['addbaodan'] = $member['addbaodan'][0]??0;

        return $this->fetch('index', [
            'member'=>$member
        ]);
    }

    public function shares() {

        $userid = $this->userId;
        $member = User::where('id',$userid)->find();

        $url = 'http://'.$_SERVER['SERVER_NAME'].url('publics/index',['code'=>$member['mobile']]);

        return $this->fetch('shares', [
            'url'=>$url
        ]);
    }

    public function submitBd() {

        $LegalConfig = new LegalConfig();
        $agreement = $LegalConfig->getValue('agreement');
        $pid =  $this->userId;

        return $this->fetch('submitBd', [
            'agreement'=>$agreement,
            'pid'=> $pid
        ]);
    }

    public function submitBdsub(Request $request) {

        $type = $request->post('type')??'';
        $money = $request->post('money')??'';
        $voucher = $request->post('voucher')??'';

        if( !in_array($type, array(1,2) )){
            return json([
                'code' => -1,
                'message' => '报单类型有误！'
            ]);
        }

        if($money <= 0){
            return json([
                'code' => -1,
                'message' => '报单金额有误！'
            ]);
        }

        if(empty($voucher)){
            return json([
                'code' => -1,
                'message' => '请上传报单凭证！'
            ]);
        }
    


        Db::startTrans();
        try {


            if($type==1){

                $code = $request->post('code')??'';

                $data = [];
                $data['nick_name'] = $request->post('nick_name')??'';
                $data['mobile'] = $request->post('mobile')??'';
                $data['pid'] = $request->post('pid')??'';


                //查看该推荐人存不存在
                $member = User::where('mobile',$data['mobile'])->find();

                if(!empty($member)){
                    return json(['code' => -1, 'message' => '手机号已被注册！']);
                }

                if (!$this->checkCode($code, $data['mobile'])) {
                    return json(['code' => -1, 'message' => '验证码输入错误！']);
                }

                if(empty($data['nick_name']) || empty($data['mobile']) || empty($data['pid']) ){
                     return json(['code' => -1, 'message' => '参数有误！']);
                }
                //查看该推荐人存不存在
                $pidmem = User::where('id',$data['pid'])->find();

                if(empty($pidmem)){
                    return json(['code' => -1, 'message' => '推荐人输入有误！']);
                }

                //先插入会员表
                $entity = new \app\common\entity\User();
                $service = new Service();

                $entity->mobile = $data['mobile'];
                $entity->nick_name = $data['nick_name'];
                $entity->password = $service->getPassword( substr($data['mobile'],-6) );
                $entity->trad_password = $service->getPassword( substr($data['mobile'],-6) );
                $entity->register_time = time();
                $entity->register_ip = $_SERVER["REMOTE_ADDR"];
                $entity->status = \app\common\entity\User::STATUS_DEFAULT;
                $entity->is_certification = \app\common\entity\User::AUTH_ERROR;
                $entity->pid = $data['pid'];

                //保存
                $entity->save();

                $insertid = $entity->id;


                $user_id = $insertid;
                $help_id = $this->userId;

            }else if($type==2){

                $user_id = $this->userId;
                $help_id = 0;
            }

            $LegalConfig = new LegalConfig();
            $market = $LegalConfig->getValue('exchangeratio');


            $arr = [
                'user_id'=>$user_id,
                'help_id'=>$help_id,
                'money'=>$money,
                'voucher'=>$voucher,
                'market'=>$market,
                'status'=>0,
                'createtime'=>time()
            ];

            $LegalReportcentre = new LegalReportcentre();
            $LegalReportcentre->save($arr);


            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
        }


        
        return json(['code' => 0, 'message' => '报单成功']);

    }

    public function recordBd() {


        $userid = $this->userId;
        $LegalReportcentre = new LegalReportcentre();
        $recordBd = $LegalReportcentre->where('help_id',$userid)->select();

        foreach ($recordBd as &$value) {
            $mem = User::where('id',$value['user_id'])->find();
            $value['nick_name'] = $mem['nick_name'];
        }
        unset($value);

        //查看报单总金额
        $baodanmoney = $LegalReportcentre->where('help_id',$userid)->where('status','>=',0)->column('sum(money)');
        $baodanmoney = $baodanmoney[0]??0;

        $noexamine = $LegalReportcentre->where('help_id',$userid)->where('status','=',0)->column('sum(money)');
        $noexamine = $noexamine[0]??0;

        return $this->fetch('recordBd', [
            'recordBd'=> $recordBd,
            'baodanmoney'=>$baodanmoney,
            'noexamine'=>$noexamine,
        ]);
    }



    public function myBd() {

        $userid = $this->userId;
        $member = User::where('id',$userid)->find();

        $LegalReportcentre = new LegalReportcentre();
        $recordBd = $LegalReportcentre->where('user_id',$userid)->select();

        //查看报单总金额
        $baodanmoney = $LegalReportcentre->where('user_id',$userid)->where('status','>=',0)->column('sum(money)');
        $baodanmoney = $baodanmoney[0]??0;

        $noexamine = $LegalReportcentre->where('user_id',$userid)->where('status','=',0)->column('sum(money)');
        $noexamine = $noexamine[0]??0;

        return $this->fetch('myBd', [
            'recordBd'=> $recordBd,
            'member'=> $member,
            'baodanmoney'=>$baodanmoney,
            'noexamine'=>$noexamine,
        ]);
    }


    public function checkCode($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'register');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }




}
