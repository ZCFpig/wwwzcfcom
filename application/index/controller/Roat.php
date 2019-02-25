<?php
namespace app\index\controller;


// use think\Controller;
use think\Db;
use think\Request;

class Roat
{
	//行情api
    public function Index(){

    	


    	$url = 'https://www.dmdce.com/api/Message/GetRealTimeQuotation';

            
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    $arr = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
	    curl_close($ch);

	    $content = json_decode($arr,true);

	    $data = '';

	    foreach ($content['data'] as $key => $value) {
	    	
	    	if($value['CurrencyName'] == 'BNC'){

	    		$data = $value;

	    	}

	    }

	    // echo 111;
	    echo '<pre>';
	    print_r($data);
	    echo date('Y-m-d H:i:s',$data['TimeStamp']);
	    echo '<pre>';

	    $list = Db::table('proportion')->count();

	    if($list > 60){

	    	$l = Db::table('proportion')->order('create_time asc')->limit(1)->find();

	    	Db::table('proportion')->where('id',$l['id'])->delete();

	    }

	    Db::table('proportion')->insert(['open' => $data['Open'] , 'cny' => $data['CNY'] , 'usd' => $data['USD'] , 'high' => $data['High'] , 'low' => $data['Low'] , 'highcny' => $data['HighCNY'] , 'lowcny' => $data['LowCNY'] , 'usdrate' => $data['USDRate'] , 'platfromusdrate' => $data['PlatformUSDRate'] , 'create_time' => time()]);
	    // echo 111;
	    


    }

    public function aaa(){

    	



    	// $url = 'https://www.dmdce.com/api/account/GetCurrencyQuotationInfo/';
    	// $post_data = array(
     //    "RecordID" => "10",
     //    "bearer" => "BNC");

            
	    // $ch = curl_init();
	    // curl_setopt($ch, CURLOPT_URL, $url);
	    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    // curl_setopt($ch, CURLOPT_POST, 1);
	    // curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	    // $arr = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
	    // curl_close($ch);

	    // $content = json_decode($arr,true);
	    
    	$a = mktime(8,0,0,11,28,2018);
    	$b = mktime(8,0,0,11,28,2018);

    	$url = 'https://www.dmdce.com/api/Message/GetRealTimeQuotation?st='.$a.'&ut='.$b;

            
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    $arr = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
	    curl_close($ch);

	    $content = json_decode($arr,true);
	    // echo 111;
	    echo '<pre>';
	    print_r($content);
	    echo '<pre>';
	    exit;
	    $list = Db::table('proportion')->count();

	    if($list > 60){

	    	$l = Db::table('proportion')->order('create_time asc')->limit(1)->find();

	    	Db::table('proportion')->where('id',$l['id'])->delete();

	    }

	    Db::table('proportion')->insert(['open' => $data['Open'] , 'cny' => $data['CNY'] , 'usd' => $data['USD'] , 'high' => $data['High'] , 'low' => $data['Low'] , 'highcny' => $data['HighCNY'] , 'lowcny' => $data['LowCNY'] , 'usdrate' => $data['USDRate'] , 'platfromusdrate' => $data['PlatformUSDRate'] , 'create_time' => time()]);
	    // echo 111;
	    


    }


}	