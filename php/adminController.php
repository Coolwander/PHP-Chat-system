<?php
namespace App;


class adminController{

    function __construct() {
        // Verify CSFR
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(! app('csfr')->verifyToken(SECRET_KEY) ){
                header('HTTP/1.0 403 Forbidden');
                exit();
            }
        }

        if(app('auth')->isAuthenticated()){
            if(app('auth')->user()['user_type']!=1){
                header("Location: " . route('index'));
            }
        }else{
            header("Location: " . route('index'));
        }
    }

    function index() {
        $data = array();
        $data['users_count'] = app('db')->getValue('users', 'count(*)');
        $data['chatroom_count'] = app('db')->getValue('chat_rooms', 'count(id)');
        $data['private_chats_count'] = app('db')->getValue('private_chats', 'count(id)');
        $data['group_chats_count'] = app('db')->getValue('group_chats', 'count(id)');

        app('db')->orderBy("id","desc");
        $chat_rooms_list = app('db')->get('chat_rooms', array(0,10));
        $chat_rooms = array();
        foreach ($chat_rooms_list as $chat_room) {
            app('db')->join("chat_groups cg", "cg.id=gu.chat_group", "LEFT");
            app('db')->where ('cg.chat_room', $chat_room['id']);
            app('db')->where ('cg.slug', 'general');
            app('db')->get('group_users gu', null, 'gu.*');
            $chat_room['users_count'] = app('db')->count;
            array_push($chat_rooms, $chat_room);
        }
        $data['latest_rooms'] = $chat_rooms;

        app('db')->where ('user_type', Array(1, 2), 'IN');
        app('db')->orderBy("id","desc");
        $user_list = app('db')->get('users', array(0,10));
        $users = array();
        foreach ($user_list as $each_user) {
            if ($each_user['avatar']) {
                $each_user['avatar_url'] = URL."media/avatars/".$each_user['avatar'];
            } else {
                $each_user['avatar_url'] = URL."static/img/user.jpg";
            }
            array_push($users, $each_user);
        }
        $data['latest_users'] = $users;
        $data['lang_list'] = app('db')->get('languages');

        echo app('twig')->render('admin/index.html', $data);
    }

    function general() {
        $data = array();
        app('db')->where ('status', 1);
        app('db')->where ('is_visible', 1);
        $data['chatroom_list'] = app('db')->get('chat_rooms');
        $data['lang_list'] = app('db')->get('languages');
        $data['timezone_list'] = get_timezone_list(SETTINGS['timezone']);
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/general.html', $data);
    }

    function email() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/email.html', $data);
    }

    function timing() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/timing.html', $data);
    }

    function image() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/image.html', $data);
    }

    function color() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/color.html', $data);
    }

    function chatpage() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/chatpage.html', $data);
    }

    function homepage() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/homepage.html', $data);
    }

    function header() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/header.html', $data);
    }

    function footer() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/footer.html', $data);
    }

    function gif() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/gif.html', $data);
    }

    function policy() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/policy.html', $data);
    }

    function about() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/about.html', $data);
    }

    function contact() {
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/contact.html', $data);
    }

    function profanity(){
        $data = array();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/profanity.html', $data);
    }

    function chatroom_list() {
        $data = array();
        $chat_rooms_list = app('db')->get('chat_rooms');
        $chat_rooms = array();
        foreach ($chat_rooms_list as $chat_room) {
            app('db')->join("chat_groups cg", "cg.id=gu.chat_group", "LEFT");
            app('db')->where ('cg.chat_room', $chat_room['id']);
            app('db')->where ('cg.slug', 'general');
            app('db')->get('group_users gu', null, 'gu.*');
            $chat_room['users_count'] = app('db')->count;
            array_push($chat_rooms, $chat_room);
        }
        $data['chat_rooms'] = $chat_rooms;
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/chatroom_list.html', $data);
    }

    function chatroom_users(){
        $get_data = $_GET;
        $data = array();
        if (array_key_exists("room", $_GET)) {
            app('db')->where ('id', $get_data['room']);
            $chat_room = app('db')->getOne('chat_rooms');

            app('db')->where ('slug', 'general');
            app('db')->where ('chat_room', $get_data['room']);
            $chat_group = app('db')->getOne('chat_groups');

            app('db')->join("users u", "g.user=u.id", "LEFT");
            app('db')->where ('g.chat_group', $chat_group['id']);
            $group_users = app('db')->get('group_users g', null, 'g.*, u.*');
            $data['room_users'] = $group_users;
            $data['chat_room'] = $chat_room;
            $data['lang_list'] = app('db')->get('languages');
            echo app('twig')->render('admin/chatroom_users.html', $data);
        }
    }

    function chatroom_edit() {
        $get_data = $_GET;
        $data = array();
        if (array_key_exists("edit_room", $_GET)) {
            if($get_data['edit_room']){
                app('db')->where('id', $get_data['edit_room']);
                $room_data = app('db')->getOne('chat_rooms');
                $data['chat_room'] = $room_data;

                app('db')->where ('slug', 'general');
                app('db')->where ('chat_room', $get_data['edit_room']);
                $chat_group = app('db')->getOne('chat_groups');

                app('db')->join("users u", "g.user=u.id", "LEFT");
                app('db')->where ('g.chat_group', $chat_group['id']);
                $group_users = app('db')->get('group_users g', null, 'g.*, u.*');
                $data['room_users'] = $group_users;
            }
        }
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/chatroom_update.html', $data);
    }

    function user_list() {
        app('db')->where ('user_type', Array(1, 2, 4), 'IN');
        $user_list = app('db')->get('users');
        $users = array();
        foreach ($user_list as $each_user) {
            if ($each_user['avatar']) {
                $each_user['avatar_url'] = URL."media/avatars/".$each_user['avatar'];
            } else {
                $each_user['avatar_url'] = URL."static/img/user.jpg";
            }
            array_push($users, $each_user);
        }
        $data['user_list'] = $users;
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/user_list.html', $data);
    }

    function user_view() {
        $get_data = $_GET;
        if (array_key_exists("user", $_GET)) {
            if($get_data['user']){
                $user_data = app('auth')->user($get_data['user']);

                $sql = "SELECT
                    cr.name, cr.id, gu.created_at, gu.status as gu_status
                FROM
                    cn_chat_rooms cr, cn_chat_groups cg, cn_group_users gu
                WHERE
                    cg.chat_room = cr.id AND gu.chat_group=cg.id AND cg.slug='general' AND gu.user = ?";

                $user_data['user_rooms'] = app('db')->rawQuery(
                    $sql, array($user_data['id'])
                );
                $user_data['timezone_list'] = get_timezone_list($user_data['timezone']);
                $user_data['lang_list'] = app('db')->get('languages');
                include(BASE_PATH.'utils'.DS.'countries.php');
                $user_data['country_list'] = $countries;
                echo app('twig')->render('admin/user_view.html', $user_data);
            }
        }
    }

    function user_add() {
        $user_data = array();
        $user_data['timezone_list'] = get_timezone_list();
        $user_data['lang_list'] = app('db')->get('languages');
        include(BASE_PATH.'utils'.DS.'countries.php');
        $user_data['country_list'] = $countries;
        echo app('twig')->render('admin/user_add.html', $user_data);

    }


    function user_chats() {
        $get_data = $_GET;
        $user_chat = array();
        if (array_key_exists("user", $_GET) && array_key_exists("room", $_GET)) {
            if($get_data['user'] && $get_data['room']){
                $user_chat = app('chat')->get_active_list($get_data['room'], $get_data['user']);
            }
        }

        $user_chat['user_data'] = app('auth')->user($get_data['user']);
        $user_chat['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/user_chats.html', $user_chat);
    }

    function guest_list() {
        app('db')->where ('user_type', 3);
        $data['guest_list'] = app('db')->get('users');
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/guest_list.html', $data);
    }


    // load index admin_js file
    public function admin_js(){
        header("Content-Type: text/javascript");
        echo app('twig')->render('admin/js/admin.js');
    }

    // get chats for selected user or group
    public function load_chats(){
        $data = array();
        $post_data = app('request')->body;
        $_SESSION['last_loaded_count'] = 0;
        $from_user = $post_data['from_user'];
        if ($post_data['active_user']) {
            if($post_data['active_user'] > $from_user) {
                $user_1 = $from_user;
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = $from_user;
            }
            $chat_meta_data = app('chat')->getChatMetaData($from_user, $post_data['active_user'], $post_data['active_room']);
            $data['chat_meta_id'] = $chat_meta_data['id'];

            // get new messages
            $chats = app('chat')->getPrivateChats($user_1, $user_2, $post_data['active_room']);

        }else{
            $group_chat_meta_data = app('chat')->getGroupChatMetaData($from_user, $post_data['active_group'], $post_data['active_room']);
            if($group_chat_meta_data){
                $data['chat_meta_id'] = $group_chat_meta_data['id'];
            }else{
                $data['chat_meta_id'] = "";
            }

            // get new messages
            app('db')->join("users u", "c.sender_id=u.id", "LEFT");
            app('db')->where ('c.group_id', $post_data['active_group']);
            app('db')->where ('c.room_id', $post_data['active_room']);
            app('db')->where ('c.sender_id', $from_user);

            app('db')->orderBy('c.time','desc');
            $chats = app('db')->get('group_chats c', array($_SESSION['last_loaded_count'],20), 'c.*, u.first_name,  u.avatar, "group" as chat_type');
            $chats = array_reverse($chats);
        }

        $data['chats'] = $chats;

        return json_response($data);
    }

    // load more chats when scrolling up
    public function load_more_chats(){
        $data = array();
        $post_data = app('request')->body;
        $_SESSION['last_loaded_count'] += 20;
        $from_user = $post_data['from_user'];
        if ($post_data['active_user']) {
            if($post_data['active_user'] > $from_user) {
                $user_1 = $from_user;
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = $from_user;
            }
            $data['chats'] = app('chat')->getPrivateChats($user_1, $user_2, $post_data['active_room']);
        }else{
            $data['chats'] = app('chat')->getGroupChats($post_data['active_group'], $post_data['active_room']);
        }
        $data['chats'] = array_reverse($data['chats']);
        return json_response($data);
    }

    // get language list
    function languages() {
        $languages = app('db')->get('languages');
        $data['languages'] = $languages;
        $data['lang_list'] = $languages;
        echo app('twig')->render('admin/languages.html', $data);
    }

    // edit language
    function language_edit() {
        $get_data = $_GET;
        $data = array();
        if (array_key_exists("lang", $_GET)) {
            if($get_data['lang']){
                app('db')->where('code', $get_data['lang']);
                $data['language'] = app('db')->getOne('languages');
                $data['edit_type_name'] = "Update";
                $data['edit_type'] = "update";
            }
        }else{
            $data['edit_type_name'] = "Add New";
            $data['edit_type'] = "add";
        }
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/language_view.html', $data);
    }

    // load language transition
    function language_translation() {
        $get_data = $_GET;
        $data = array();
        if (array_key_exists("lang", $_GET)) {
            if($get_data['lang']){

                app('db')->join("translations tr", "tr.term_id=lt.id", "LEFT");
                app('db')->joinWhere('translations tr', 'tr.lang_code', $get_data['lang']);
                $data['lang_terms'] = app('db')->get('lang_terms lt', null, 'lt.term, lt.id as term_id, tr.translation');
                $data['selected_lang'] = $get_data['lang'];
                $data['languages'] = app('db')->get('languages');
            }
        }
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/language_translation.html', $data);
    }

    // rebuild translation phrases
    public function rebuild_translate(){
        $data = array();
        $data['updated_terms'] = collect_update_terms();
        $data['lang_list'] = app('db')->get('languages');
        echo app('twig')->render('admin/rebuild_translate.html', $data);
    }

    function registration_settings(){
        $data = array();
        echo app('twig')->render('admin/registration_settings.html', $data);
    }

    function advertisements(){
        $data = array();
        echo app('twig')->render('admin/advertisements.html', $data);
    }

}
