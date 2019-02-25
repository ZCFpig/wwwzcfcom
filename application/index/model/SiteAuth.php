<?php

namespace app\index\model;

use app\common\entity\Config;

class SiteAuth {

    //判断站点是否开启
    public static function checkSite() {
        $switch = Config::getValue('web_switch');
        if (!$switch) {
            return Config::getValue('web_close_message') ? : '站点关闭';
        }

        return true;
    }

    //判断交易市场是否开启
    public static function checkMarket() {
        $switch = Config::getValue('web_switch_market');
        if (!$switch) {
            return '交易市场已关闭';
        }
        $startTime = Config::getValue('web_start_time') ? : 0;
        $endTime = Config::getValue('web_end_time') ? : 0;
        if ($startTime && time() < strtotime(date('Y-m-d') . ' ' . $startTime)) {

            return '市场开启时间为' . $startTime . '-' . $endTime;
        }

        if ($endTime && time() > strtotime(date('Y-m-d') . ' ' . $endTime)) {

            return '市场开启时间为' . $startTime . '-' . $endTime;
        }

        return true;
    }

    //php alert
    public function alert($message, $jumpUrl = '') {
        if ($jumpUrl) {
            $js = "function(){ window.location.href = '{$jumpUrl}'}";
        } else {
            $js = "''";
        }
        $html = <<<EOF
<html>
    <head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
		<title>温馨提示</title>
		<link href="/static/css/mui.min.css" rel="stylesheet" />
		
		<script src="/static/js/mui.min.js"></script>
		<script type="text/javascript" charset="utf-8">
		    mui.init();
		</script>
	</head>
	<body>
	    <script>
            window.onload = function(){
                mui.alert('{$message}','温馨提示',$js);
	        }
	    </script>
	</body>
</html>
EOF;
        echo $html;
    }

    /**
     * 判断交易市场是否开启
     */
    public function checkAuth() {
        $startTime = Config::getValue('web_start_time');
        $endTime = Config::getValue('web_end_time');
        $startTime = strtotime(date('Y-m-d') . ' ' . $startTime);
        $endTime = strtotime(date('Y-m-d') . ' ' . $endTime);
        if (time() < $startTime) {
            return sprintf('交易市场开市时间为%s-%s', $startTime, $endTime);
        }
        if (time() > $endTime) {
            return sprintf('交易市场开市时间为%s-%s', $startTime, $endTime);
        }
        return true;
    }

}
