<?php
namespace app\index\controller;


// use think\Controller;
use think\Db;
use think\Request;

class Ethcheck
{
    #定时查询
    public function Index(){

        $user = Db::table('user')->field('trade_address,id')->select();

        foreach ($user as $row) {

            if($row['trade_address'] == ''){

                continue;

            }

            $time = $_SERVER['REQUEST_TIME'];

            $url = 'https://api.etherscan.io/api?module=account&action=tokentx&contractaddress=0x87a963df987b3CDf0eE1a15A24438D006958665E&address='.$row['trade_address'].'&page=1&offset=20&sort=desc&apikey=BTBQ2D4KY94XAN266H6B99NR3ZPSEIXARN';

            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $arr = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
            curl_close($ch);

            $content = json_decode($arr,true);

            if($content['status'] == '0' || $content['result'] == ''){

                continue;

            }

            $cont = array_reverse($content['result'],true);

            $list = Db::table('user_daibi_log')->where('user_id',$row['id'])->order('timeStamp desc')->limit(1)->find();
           
            foreach ($cont as $val) {
                

                if($val['timeStamp'] < $list['timeStamp'] || $val['to'] != $row['trade_address']){
                    continue;
                }

                $check_hash = Db::table('user_daibi_log')->where('user_id',$row['id'])->where('hash',$val['hash'])->field('id')->find();

                if($check_hash){
                    continue;
                }

                $number = Db::table('block_wallet')->where('user_id',$row['id'])->field('number')->find();

                $l = pow(10,18); // 转化
                $magic = bcdiv($val['value'],$l,2);
                $remark = '钱包代币充值';

                // echo gettype($magic);
                // echo gettype($number['number']);


                Db::table('user_daibi_log')->insert(['user_id' => $row['id'] , 'magic' => $magic , 'old' => $number['number'] , 'new' => $number['number'] + $magic , 'eth_address' => $val['from'] , 'remark' => $remark , 'hash' => $val['hash'] , 'timeStamp' => $val['timeStamp'] , 'create_time' => $time , 'types' => 2]);
                Db::table('block_wallet')->where('user_id', $row['id'])->update(['number' => $number['number'] +  $magic]);
            
            }

        }
    } 



}