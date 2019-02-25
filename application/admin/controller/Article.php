<?php

namespace app\admin\controller;

use app\admin\exception\AdminException;
use app\common\entity\Export;
use app\common\entity\ServiceInfo;
use think\Db;
use think\Request;
use app\common\entity\User;

class Article extends Admin {

    /**
     * @power 内容管理|文章管理
     * @rank 5
     */
    public function index(Request $request) {
        $entity = \app\common\entity\Article::field('*');
        if ($cate = $request->get('cate')) {
            $entity->where('category', $cate);
            $map['cate'] = $cate;
        }

        $list = $entity->paginate(15, false, [
            'query' => isset($map) ? $map : []
        ]);

        return $this->render('index', [
                    'list' => $list,
                    'cate' => \app\common\entity\Article::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|文章管理@添加文章
     */
    public function create() {
        return $this->render('edit', [
                    'cate' => \app\common\entity\Article::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|文章管理@编辑文章
     */
    public function edit($id) {
        $entity = \app\common\entity\Article::where('article_id', $id)->find();
        if (!$entity) {
            $this->error('用户对象不存在');
        }

        return $this->render('edit', [
                    'info' => $entity,
                    'cate' => \app\common\entity\Article::getAllCate()
        ]);
    }

    /**
     * @power 内容管理|文章管理@添加文章
     */
    public function save(Request $request) {
        $res = $this->validate($request->post(), 'app\admin\validate\Article');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }

        $service = new \app\common\entity\Article();
        $result = $service->addArticle($request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }
        //添加用户提醒
        if($request->post('category')==1 && $request->post('status')==1){
            User::update(['notice'=>1],['notice' => 0]);
        }

        return json(['code' => 0, 'toUrl' => url('/admin/article')]);
    }

    /**
     * @power 内容管理|文章管理@编辑文章
     */
    public function update(Request $request, $id) {

        $res = $this->validate($request->post(), 'app\admin\validate\Article');

        if (true !== $res) {
            return json()->data(['code' => 1, 'message' => $res]);
        }


        $entity = $this->checkInfo($id);

        $service = new \app\common\entity\Article();
        $result = $service->updateArticle($entity, $request->post());

        if (!$result) {
            throw new AdminException('保存失败');
        }

        return json(['code' => 0, 'toUrl' => url('/admin/article')]);
    }

    /**
     * 导出留言
     */
    public function exportMessage(Request $request) {
        $export = new Export();
        $entity = \app\common\entity\Message::field('m.*,u.mobile, u.nick_name')->alias('m');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $list = $entity->leftJoin("user u", 'm.user_id = u.id')
                ->order('m.create_time', 'desc')
                ->select();
        $filename = '留言列表';
        $header = array('会员昵称', '会员账号', '内容', '提交时间');
        $index = array('nick_name', 'mobile', 'content', 'create_time');
        $export->createtable($list, $filename, $header, $index);
    }

    /**
     * @power 内容管理|文章管理@删除文章
     */
    public function delete(Request $request, $id) {
        $entity = $this->checkInfo($id);

        if (!$entity->delete()) {
            throw new AdminException('删除失败');
        }

        return json(['code' => 0, 'message' => 'success']);
    }

    private function checkInfo($id) {
        $entity = \app\common\entity\Article::where('article_id', $id)->find();
        if (!$entity) {
            throw new AdminException('对象不存在');
        }

        return $entity;
    }

    /**
     * @power 内容管理|反馈列表
     * @method GET
     */
    public function messageList(Request $request) {
        $entity = \app\common\entity\Message::field('m.*,u.mobile, u.nick_name')->alias('m');
        if ($keyword = $request->get('keyword')) {
            $type = $request->get('type');
            switch ($type) {
                case 'mobile':
                    $entity->where('u.mobile', $keyword);
                    break;
                case 'nick_name':
                    $entity->where('u.nick_name', $keyword);
                    break;
            }
            $map['type'] = $type;
            $map['keyword'] = $keyword;
        }
        $list = $entity->leftJoin("user u", 'm.user_id = u.id')
                ->order('m.create_time', 'desc')
                ->paginate(15, false, [
            'query' => isset($map) ? $map : []
        ]);
        return $this->render('messageList', [
                    'list' => $list
        ]);
    }
    /**
     * 回复留言
     */
    public function replyMsg(Request $request){
        $id = $request->post("id");
        $uid = $request->post("user_id");
        $content = $request->post("content");
        if(trim($content)==''){
            return json(['code' => 1,'message'=> '请输入回复内容']);
        }
        //内容
        $data['content'] = $content;
        $data['message_id'] = $id;
        $data['create_time'] = time();
        $data['user_id'] = $uid;

        $res = \app\common\entity\MessageReply::insert($data);
        if ($res) {
            \app\common\entity\Message::update(['status' => 2], ['message_id' => $id]);
            return json(['code' => 0]);
        } else {
            return json(['code' => 1, 'message' => '回复失败']);
        }

    }

    /**
     * @power 内容管理|反馈列表@删除留言
     * @method GET
     */
    public function deleteMsg(Request $request) {
        $entity = \app\common\entity\Message::where("message_id", $request->get("id"))->delete();
        return json(['code' => 0, 'toUrl' => url('/admin/article/messageList')]);
    }

    #客服信息列表
    public function serviceList(){
        $list = ServiceInfo::select();
        return $this->render('servicelist',[
            'list' => $list
        ]);
    }

    #内容管理|客服信息编辑
    public function serviceedit(Request $request){
        $id = $request->param('id');
        $list = ServiceInfo::where('id',$id)->find();

        return $this->render('serviceedit',[
            'info' => $list
        ]);
    }

    #客服信息修改
    public function updservice(Request $request)
    {
        $id = $request->param('id');
        $qq = $request->post('qq');
        $name = $request->post('name');

        $wx = $request->post('wx');
        $phone = $request->post('phone');

        $data = [
            'name' => $name,
            'wx' => $wx,
            'qq' => $qq,
            'phone' => $phone,
            'create_time' => time()
        ];

        $updphoto = ServiceInfo::where('id',$id)->update($data);
        if ($updphoto){

            return json(['code' => 0, 'message' => '修改成功']);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }

    #内容管理|客服信息添加
    public function serviceadd(){
        return $this->render('serviceedit');
    }

    #客服信息添加
    public function saveservice(Request $request){

        $name = $request->post('name');
        $wx = $request->post('wx');
        $qq = $request->post('qq');
        $phone = $request->post('phone');

        $data = [
            'name' => $name,
            'wx' => $wx,
            'qq' => $qq,
            'phone' => $phone,
            'create_time' => time()
        ];

        $insphoto = ServiceInfo::insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功']);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }

    #客服信息删除
    public function servicedel(Request $request){

        $uid = $request->param('id');

        $del = ServiceInfo::where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }

    #内容管理|图片列表
    public function image(){
        $list = Db::table('image')->select();
        return $this->render('imagelist',[
            'list' => $list
        ]);
    }

    #内容管理|图片编辑
    public function imageedit(Request $request){
        $id = $request->param('id');
        $list = Db::table('image')->where('id',$id)->find();

        return $this->render('imageedit',[
            'info' => $list
        ]);
    }

    #图片修改
    public function updimage(Request $request)
    {
        $id = $request->param('id');
        $title = $request->post('title');

        $photo = $request->post('photo');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'sort' => $sort,
            'title' => $title,
            'update_time' => time()
        ];

        $updphoto = Db::table('image')->where('id',$id)->update($data);
        if ($updphoto){

            return json(['code' => 0, 'message' => '修改成功']);

        }

        return json(['code' => 1, 'message' => '修改失败']);

    }

    #内容管理|图片添加
    public function imageadd(){
        return $this->render('imageedit');
    }

    #图片添加
    public function saveimage(Request $request){

        $photo = $request->post('photo');
        $title = $request->post('title');
        $sort = $request->post('sort');

        $data = [
            'pic' => $photo,
            'title' => $title,
            'sort' => $sort,
            'create_time' => time()
        ];

        $insphoto = Db::table('image')->insert($data);

        if ($insphoto){

            return json(['code' => 0, 'message' => '添加成功']);

        }

        return json(['code' => 1, 'message' => '添加失败']);

    }

    #图片删除
    public function imagedel(Request $request){

        $uid = $request->param('id');

        $del = Db::table('image')->where('id',$uid)->delete();

        if ($del){

            return json(['code' => 0, 'message' => '删除成功']);

        }

        return json(['code' => 1, 'message' => '删除失败']);

    }

}
