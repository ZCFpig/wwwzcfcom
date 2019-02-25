<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 13:49
 */

namespace app\index\controller;


use app\common\entity\ServiceInfo;
use think\Db;
use think\Request;

class Article extends Base
{

    #新闻公告列表
    public function artList(Request $request){
        $list = \app\common\entity\Article::where('status',1)->select();
        if ($list) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);
    }

    #新闻公告详情
    public function artInfo(Request $request){

        $article_id = $request->get('article_id');
        $list = \app\common\entity\Article::where('article_id',$article_id)->find();
        if ($list) {
            $article = new \app\common\entity\Article();
//            dump( $list['content']);die;
            $list['content'] = $article->updImgUrl($list['content']);

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);
    }




    #获取客服
    public function getServiceInfo(){
        $list = ServiceInfo::select();
        if ($list) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);
    }

    #获取图片
    public function getImg(){
        $list = Db::table('image')->where('id',1)->find();
        if ($list) {

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);
    }


    #获取服务条款
    public function getService()
    {
        $list = \app\common\entity\Article::where('article_id',3)->find();
        if ($list) {
            $article = new \app\common\entity\Article();
//            dump( $list['content']);die;
            $list['content'] = $article->updImgUrl($list['content']);

            return json(['code' => 0, 'message' => '获取成功', 'info' => $list]);

        }

        return json(['code' => 1, 'message' => '获取失败']);
    }
    




}