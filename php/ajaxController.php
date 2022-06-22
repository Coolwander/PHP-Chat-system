<?php
namespace App;

/* This class is handling all the ajax requests */

class ajaxController{

    function __construct() {
        // Verify CSFR
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(! app('csfr')->verifyToken(SECRET_KEY) ){
                header('HTTP/1.0 403 Forbidden');
                exit();
            }
        }
    }

    // Saving received messages
    public function save_message(){
        $post_data = app('request')->body;
        $profanity_found = false;
        if (in_array($post_data['message_type'], array(2,6,7))) {
            $message_content = app('purify')->xss_clean($post_data['message_content']);
        }elseif ($post_data['message_type'] == 8) {
            $content = json_decode($post_data['message_content'], true);
            if (in_array($content['new_message']['new_type'], array(2,6,7))) {
                $content['new_message']['new_content'] = app('purify')->xss_clean($content['new_message']['new_content']);
                $message_content = json_encode($content);
            }else if ($content['new_message']['new_type'] == 1) {
                preg_match('/\b(?:(?:https?):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $content['new_message']['new_content'], $message_links);
                if (!empty($message_links[0])) {
                    $url_data = get_url_data($message_links[0]);
                    $url_data['url'] = $message_links[0];
                    $link_message_content = array();
                    $link_message_content['message'] = app('purify')->xss_clean($content['new_message']['new_content']);
                    $content['new_message']['new_content'] = array_merge($link_message_content,$url_data);
                    $content['new_message']['new_type'] = 5;
                    $message_content = json_encode($content,JSON_UNESCAPED_UNICODE);

                }else{
                    // Check profanity
                    if (isset(SETTINGS['profanity_filter']) && SETTINGS['profanity_filter'] == true) {
                        $message_content_filtered = profanity_filter($content['new_message']['new_content']);
                        if ($message_content_filtered != $content['new_message']['new_content']) {
                            $content['new_message']['new_content'] = $message_content_filtered;
                            $profanity_found = true;
                        }
                    }

                    $content['new_message']['new_content'] = app('purify')->xss_clean(clean($content['new_message']['new_content']));
                    $message_content = json_encode($content);
                }

            }else{
                $content['new_message']['new_content'] = app('purify')->xss_clean(clean($content['new_message']['new_content']));
                $message_content = json_encode($content);
            }

        }else{
            $message_content = app('purify')->xss_clean(clean($post_data['message_content']));
        }

        // Check profanity
        if(!in_array($post_data['message_type'], array(2,6,7,8))){
            if (isset(SETTINGS['profanity_filter']) && SETTINGS['profanity_filter'] == true) {
                $message_content_filtered = profanity_filter($message_content);
                if ($message_content_filtered != $message_content) {
                    $message_content = $message_content_filtered;
                    $profanity_found = true;
                }
            }
        }

        // get links inside message
        if ($post_data['message_type'] == 1) {
            preg_match('/\b(?:(?:https?):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $post_data['message_content'], $message_links);
            if (!empty($message_links[0])) {
                $url_data = get_url_data($message_links[0]);
                $url_data['url'] = $message_links[0];
                $link_message_content = array();
                $link_message_content['message'] = app('purify')->xss_clean($post_data['message_content']);
                $link_message_content = array_merge($link_message_content,$url_data);
                $message_content = json_encode($link_message_content,JSON_UNESCAPED_UNICODE);
                $post_data['message_type'] = 5; // message with links
            }
        }

        $chat_save = app('chat')->saveNewMessage(
            app('auth')->user()['id'],
            $message_content,
            app('purify')->xss_clean(clean($post_data['active_user'])),
            app('purify')->xss_clean(clean($post_data['active_group'])),
            app('purify')->xss_clean(clean($post_data['active_room'])),
            app('purify')->xss_clean(clean($post_data['message_type'])),
            app('purify')->xss_clean(clean($post_data['chat_meta_id']))
        );
        $chat_save['random_id'] = $post_data['random_id'];
        if ($profanity_found) {
            if ($post_data['message_type'] == 8){
                $chat_save['profanity_filtered'] = json_decode($message_content, true)['new_message']['new_content'];
            }else{
                $chat_save['profanity_filtered'] = $message_content;
            }
        }else{
            $chat_save['profanity_filtered'] = null;
        }
        return json_response($chat_save);
    }

    // main heartbeat function to keep the chat alive.
    public function heartbeat(){

        $last_seen_data = Array ( 'last_seen' => app('db')->now());
        app('db')->where ('id', app('auth')->user()['id']);
        app('db')->update ('users', $last_seen_data);

        $post_data = app('request')->body;
        $data = array();
        if($post_data['active_user']) {
            if($post_data['active_user'] > app('auth')->user()['id']) {
                $user_1 = app('auth')->user()['id'];
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = app('auth')->user()['id'];
            }

            $data['chat_type'] = "user";
            // get new messages
            app('db')->join("users u", "c.sender_id=u.id", "LEFT");
            app('db')->where ('c.id', $post_data['last_chat_id'], ">");
            app('db')->where ('c.user_1', $user_1);
            app('db')->where ('c.user_2', $user_2);
            app('db')->where ('c.room_id', $post_data['active_room']);
            app('db')->where ("c.sender_id != " . app('auth')->user()['id']);
            $chats = app('db')->get('private_chats c', null, 'c.*, u.user_name, u.first_name,  u.avatar, "private" as chat_type');

            $update_meta = array();
            $update_meta['chat_meta_id'] = $post_data['chat_meta_id'];
            $update_meta['is_typing'] = $post_data['is_typing'];
            app('chat')->updateChatMetaData($update_meta);

            $active_user = app('auth')->user($post_data['active_user']);
            $last_seen = strtotime($active_user['last_seen']);
            $seconds10 = strtotime("-10 seconds");

            //active user chat meta
            $active_user_chat_meta_data = app('chat')->getChatMetaData($post_data['active_user'], app('auth')->user()['id'], $post_data['active_room']);
            $data['last_seen'] = date('Y-m-d H:i:s', $last_seen);
            $data['seconds10'] = date('Y-m-d H:i:s', $seconds10);

            if($active_user_chat_meta_data['is_typing']){
                $active_user = app('auth')->user($post_data['active_user']);

                if($last_seen > $seconds10){
                    $data['typing_user'] = "typing...";
                }else{
                    $data['typing_user'] = 0;
                }
            }else{
                $data['typing_user'] = 0;
            }


            $from_user_chat_meta_data = app('chat')->getChatMetaData(app('auth')->user()['id'], $post_data['active_user'], $post_data['active_room']);
            $data['is_muted'] = $from_user_chat_meta_data['is_muted'];
        }else{
            $data['chat_type'] = "group";

            $typing_users = app('chat')->getGroupChatTypingUsers(app('auth')->user()['id'], $post_data['active_group'], $post_data['active_room']);
            $typing_user_count = count($typing_users);
            if($typing_user_count > 0){
                if (SETTINGS['display_name_format']=='username') {
                    $typing_msg = $typing_users[0]['user_name'];
                }else{
                    $typing_msg = $typing_users[0]['first_name'];
                }
                if($typing_user_count == 1){
                    $typing_msg .= " is ";
                }elseif($typing_user_count == 2){
                    if (SETTINGS['display_name_format']=='username') {
                        $typing_msg .= " & ".$typing_users[1]['user_name']. " are ";
                    }else{
                        $typing_msg .= " & ".$typing_users[1]['first_name']. " are ";
                    }

                }elseif ($typing_user_count > 2) {
                    $typing_msg .= " & ".($typing_user_count-1). " others are";
                }
                $typing_msg .= " typing...";
                $data['typing_user'] = $typing_msg;
            }else{
                $data['typing_user'] = 0;
            }

            // get new messages
            app('db')->join("users u", "c.sender_id=u.id", "LEFT");
            app('db')->where ('c.id', $post_data['last_chat_id'], ">");
            app('db')->where ('c.group_id', $post_data['active_group']);
            app('db')->where ("c.sender_id != " . app('auth')->user()['id']);
            $chats = app('db')->get('group_chats c', null, 'c.*, u.user_name, u.first_name,  u.avatar, "group" as chat_type');

            $update_meta = array();
            $update_meta['chat_meta_id'] = $post_data['chat_meta_id'];
            $update_meta['is_typing'] = $post_data['is_typing'];
            app('chat')->updateGroupChatMetaData($update_meta);

            $group_chat_meta_data = app('chat')->getGroupChatMetaData(app('auth')->user()['id'], $post_data['active_group']);
            $data['is_muted'] = $group_chat_meta_data['is_muted'];
        }

        // update chat read status
        app('chat')->updateChatReadStatus(
            app('auth')->user()['id'],
            $post_data['active_user'],
            $post_data['active_group'],
            $post_data['active_room'],
            $post_data['last_chat_id']
        );

        $data['chats'] = $chats;

        return json_response($data);
    }

    // get user selected chat details panel (right side panel)
    public function get_active_info(){
        $post_data = app('request')->body;
        $data = array();
        if($post_data['active_user']){
            // If selected chat is a user
            app('db')->join("private_chat_meta pc", "pc.to_user=u.id", "LEFT");
            app('db')->where('pc.from_user', app('auth')->user()['id']);
            app('db')->join("private_chat_meta pcr", "pcr.from_user=u.id", "LEFT");
            app('db')->where('pcr.to_user', app('auth')->user()['id']);
            app('db')->where('u.id', $post_data['active_user']);
            $cols = Array("u.*, pc.is_favourite, pc.is_muted, pc.is_blocked as blocked_by_you, pcr.is_blocked as blocked_by_him");
            $user_data = app('db')->getOne('users u', $cols);
            if (isset($user_data['avatar'])) {
                $user_data['avatar_url'] = URL."media/avatars/".$user_data['avatar'];
            } else {
                $user_data['avatar_url'] = URL."static/img/user.jpg";
            }
            $data['info_type'] = "user";
            $data['info'] = $user_data;

        }elseif ($post_data['active_group']) {
            // If selected chat is a group
            app('db')->join("group_users gu", "gu.chat_group=cg.id", "LEFT");
            app('db')->where("gu.user", app('auth')->user()['id']);
            app('db')->where ('cg.id', $post_data['active_group']);
            $group_data = app('db')->getOne("chat_groups cg", null, "cg.*, gu.unread_count, gu.is_muted");

            app('db')->where ('id', $group_data['chat_room']);
            $room_data = app('db')->getOne('chat_rooms');
            if ($room_data['cover_image']) {
                $room_data['cover_url'] = URL."media/chatrooms/".$room_data['cover_image'];
            }else {
                $room_data['cover_url'] = URL."static/img/group.png";
            }

            if ($group_data['cover_image']) {
                $group_data['cover_url'] = URL."media/chatgroups/".$group_data['cover_image'];
            } else {
                $group_data['cover_url'] = $room_data['cover_url'];

            }
            $group_data['room_data'] = $room_data;
            $data['info_type'] = "group";
            $data['info'] = $group_data;

            app('db')->join("users u", "g.user=u.id", "LEFT");
            app('db')->where ('g.chat_group', $post_data['active_group']);
            app('db')->where ('u.user_type', Array(1, 4, 2), 'IN');

            app('db')->orderBy('u.id', 'DESC', array($group_data['created_by']));
            app('db')->orderBy('u.user_type', 'ASC', array(1, 4, 2));
            app('db')->orderBy("u.last_seen","DESC");
            $group_users = app('db')->get('group_users g', array(0,20), 'g.*, u.*');

            $data['group_users'] = $group_users;

        }
        $data['shared_photos'] = app('chat')->getSharedData(app('auth')->user()['id'], $post_data['active_user'], $post_data['active_group'], $post_data['active_room'], 2, 12);
        $data['shared_files'] = app('chat')->getSharedData(app('auth')->user()['id'], $post_data['active_user'], $post_data['active_group'], $post_data['active_room'], 6, 10);

        return json_response($data);
    }

    // get chats for selected user or group
    public function load_chats(){
        unset($_SESSION['last_loaded_up']);
        unset($_SESSION['last_loaded_down']);
        $data = array();
        $post_data = app('request')->body;
        $_SESSION['last_loaded_count'] = 0;
        $_SESSION['last_loaded_up'] = false;
        if ($post_data['chat_id'] == 'false') {
            $post_data['chat_id'] = false;
        }
        if ($post_data['active_user']) {

            if (!is_numeric($post_data['active_user'])) {
                app('db')->where('user_name',  app('purify')->xss_clean(clean($post_data['active_user'])));
                $auto_user = app('db')->getOne('users');
                if ($auto_user) {
                    $post_data['active_user'] = $auto_user['id'];
                }
            }
            if($post_data['active_user'] > app('auth')->user()['id']) {
                $user_1 = app('auth')->user()['id'];
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = app('auth')->user()['id'];
            }
            $chat_meta_data = app('chat')->getChatMetaData(app('auth')->user()['id'], $post_data['active_user'], $post_data['active_room']);
            $data['chat_meta_id'] = $chat_meta_data['id'];

            // get new messages
            $chats = app('chat')->getPrivateChats($user_1, $user_2, $post_data['active_room'], $post_data['chat_id']);

        }else{
            $group_chat_meta_data = app('chat')->getGroupChatMetaData(app('auth')->user()['id'], $post_data['active_group'], $post_data['active_room']);
            if($group_chat_meta_data){
                $data['chat_meta_id'] = $group_chat_meta_data['id'];
            }else{
                $data['chat_meta_id'] = "";
            }

            // get new messages
            $chats = app('chat')->getGroupChats($post_data['active_group'], $post_data['active_room'], $post_data['chat_id']);
        }

        // update chat read status
        app('chat')->updateChatReadStatus(
            app('auth')->user()['id'],
            $post_data['active_user'],
            $post_data['active_group'],
            $post_data['active_room']
        );

        $data['last_updated_chat_time'] = app('chat')->getLastUpdatedTime(app('auth')->user()['id'], $post_data['active_user'], $post_data['active_group'], $post_data['active_room']);
        $data['chats'] = $chats;
        $data['active_user'] = $post_data['active_user'];
        return json_response($data);
    }

    // insert newly updated profile data to database
    public function save_profile(){
        $post_data = app('request')->body;
        $image_status = true;
        $image_message = "";
        if(array_key_exists("user_id", $post_data)){
            $user_id = $post_data['user_id'];
            $user_data = app('auth')->user($post_data['user_id']);
            $user_avatar = $user_data['avatar'];
            $admin_update = true;
        }else{
            $user_id = app('auth')->user()['id'];
            $user_avatar = app('auth')->user()['avatar'];
            $admin_update = false;
        }

        if(array_key_exists("avatar", $_FILES)){
            if($_FILES['avatar']['size'] > 0){
                $image = image($_FILES['avatar'], false, 'avatars', 150, 150);
                if($image[0]){
                    $old_image = BASE_PATH . 'media/avatars/'.$user_avatar;
                    if(file_exists($old_image)) {
                        unlink($old_image);
                    }
                }else{
                    $image_status = false;
                    $image_message = $image[1];
                }
            }
        }

        $data = Array ("first_name" => $post_data['first_name'],
                       "email" => $post_data['email'],
                       "about" => $post_data['about'],
                       "dob" => $post_data['dob'],
                       "sex" => $post_data['sex'],
                       "timezone" => $post_data['timezone'],
                       "country" => $post_data['country'],
                    );

        if(array_key_exists("available_status", $post_data)){
            $data['available_status'] = $post_data['available_status'];
        }

        if(array_key_exists("user_type", $post_data)){
            $data['user_type'] = $post_data['user_type'];
        }

        $status = true;
        $message = array();
        foreach ($data as $key => $value) {
            $validate_data = clean_and_validate($key, $value);
            $value = $validate_data[0];
            $data[$key] = $value;
            if(!$validate_data[1][0]){
                $status = false;
                array_push($message, $validate_data[1][1]);
            }
        }

        if($status){
            app('db')->where('email', $data['email']);
            app('db')->where("id != " . $user_id);
            $user_email_exist = app('db')->getOne('users');

            if ($user_email_exist) {
                $status = false;
                array_push($message, array('email' => ['Email already exists!']));
            } else {
                $data['id'] = $user_id;
                if($_FILES['avatar']['size'] > 0 && $image[0]){
                    $data['avatar'] = $image[1];
                }
                if($data['dob'] == "" or $data['dob'] == "1970-01-01"){
                    $data['dob'] = Null;
                }
                $save_profile = app('auth')->saveProfile($data, $admin_update);
                $status = $save_profile[0];
                $message = $save_profile[1];
            }
        }

        if($image_status){
            $profile_return = array($status, $message);
        }else{
            $profile_return = array($image_status, array(array('avatar' => [$image_message])));
        }

        return json_response(["success" => $profile_return[0], "message" => $profile_return[1]]);

    }

    // insert newly added profile data to database
    public function add_profile(){
        $post_data = app('request')->body;
        $image_status = true;
        $image_message = "";
        if(array_key_exists("avatar", $_FILES)){
            if($_FILES['avatar']['size'] > 0){
                $image = image($_FILES['avatar'], false, 'avatars', 150, 150);
                if($image[0]){
                    $old_image = BASE_PATH . 'media/avatars/'.app('auth')->user()['avatar'];
                    if(file_exists($old_image)) {
                        unlink($old_image);
                    }
                }else{
                    $image_status = false;
                    $image_message = $image[1];
                }
            }
        }

        $data = Array ("first_name" => $post_data['first_name'],
                       "user_name" => $post_data['user_name'],
                       "email" => $post_data['email'],
                       "about" => $post_data['about'],
                       "dob" => $post_data['dob'],
                       "sex" => $post_data['sex'],
                       "timezone" => $post_data['timezone'],
                       "country" => $post_data['country'],
                       "user_type" => $post_data['user_type'],
                       "available_status" => $post_data['available_status'],
                    );

        $status = true;
        $message = array();
        foreach ($data as $key => $value) {
            $validate_data = clean_and_validate($key, $value);
            $value = $validate_data[0];
            $data[$key] = $value;
            if(!$validate_data[1][0]){
                $status = false;
                array_push($message, $validate_data[1][1]);
            }
        }

        if($status){
            app('db')->where('email', $data['email']);
            $user_email_exist = app('db')->getOne('users');

            app('db')->where('user_name', $data['user_name']);
            $user_name_exist = app('db')->getOne('users');

            if ($user_email_exist) {
                $status = false;
                array_push($message, array('email' => ['Email already exists!']));
            }elseif ($user_name_exist) {
                $status = false;
                array_push($message, array('user_name' => ['Username already exists!']));
            } else {
                if($_FILES['avatar']['size'] > 0 && $image[0]){
                    $data['avatar'] = $image[1];
                }
                if($data['dob'] == "" or $data['dob'] == "0000-00-00"){
                    $data['dob'] = Null;
                }

                $data['password'] = password_hash(trim($post_data['password']), PASSWORD_DEFAULT);
                $data['user_status'] = 1;
                $data['created_at'] = app('db')->now();
                $add_profile = app('auth')->addProfile($data);
                $status = $add_profile[0];
                $message = $add_profile[1];
            }
        }

        if($image_status){
            $profile_return = array($status, $message);
        }else{
            $profile_return = array($image_status, array(array('avatar' => [$image_message])));
        }

        return json_response(["success" => $profile_return[0], "message" => $profile_return[1]]);

    }

    // get active user list
    public function online_list(){

        $post_data = app('request')->body;
        $data = array();
        $_SESSION['last_loaded_users_count'] = 0;
        if (app('auth')->isAuthenticated() == true) {
            $data = app('chat')->get_active_list($post_data['active_room']);
        }

        return json_response($data);
    }

    // get active user list
    public function load_more_online_list(){

        $post_data = app('request')->body;
        $data = array();
        $_SESSION['last_loaded_users_count'] += 20;
        if (app('auth')->isAuthenticated() == true) {
            $data = app('chat')->get_active_list($post_data['active_room']);
        }

        return json_response($data);
    }

    // get read status, seen status and chat times
    public function updated_chats(){

        $post_data = app('request')->body;
        $data = array();
        if($post_data['active_user']) {
            if($post_data['active_user'] > app('auth')->user()['id']) {
                $user_1 = app('auth')->user()['id'];
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = app('auth')->user()['id'];
            }

            // get newly updated chats
            app('db')->where ('updated_at', $post_data['last_updated_chat_time'], ">");
            app('db')->where ('user_1', $user_1);
            app('db')->where ('user_2', $user_2);
            app('db')->where ('room_id', $post_data['active_room']);
            app('db')->orderBy("updated_at","desc");
            $updated_chats = app('db')->get('private_chats');
        }else{
            // get newly updated chats
            app('db')->where ('updated_at', $post_data['last_updated_chat_time'], ">");
            app('db')->where ('group_id', $post_data['active_group']);
            app('db')->orderBy("updated_at","asc");
            $updated_chats = app('db')->get('group_chats');

        }

        $data['updated_chats'] = $updated_chats;

        return json_response($data);
    }

    // upload images to server
    public function send_images(){
        $uploaded_iamges = array();
        foreach ($_FILES['file']['tmp_name'] as $k => $v) {
            $file_array = array();
            $file_array['name'] = $_FILES['file']['name'][$k];
            $file_array['type'] = $_FILES['file']['type'][$k];
            $file_array['tmp_name'] = $_FILES['file']['tmp_name'][$k];
            $file_array['size'] = $_FILES['file']['size'][$k];

            $uploaded_image = chat_image_upload($file_array);
            array_push($uploaded_iamges, $uploaded_image);
        }

        echo json_encode($uploaded_iamges);
    }

    // upload files to server
    public function send_files(){
        $uploaded_files = array();
        foreach ($_FILES['file']['tmp_name'] as $k => $v) {
            $file_array = array();
            $extension = pathinfo($_FILES['file']['name'][$k], PATHINFO_EXTENSION);
            $file_name = pathinfo($_FILES['file']['name'][$k], PATHINFO_FILENAME);
            $tmp_name = $_FILES['file']['tmp_name'][$k];
            $size = $_FILES['file']['size'][$k];
            $full_file_name = $file_name.'.'.time().'.'.$extension;

            $return_array = array();
            $return_array['name'] = $full_file_name;
            $return_array['extenstion'] = $extension;
            $return_array['size'] = app('chat')->humanFileSize($size);
            move_uploaded_file($tmp_name, "media/chats/files/".$full_file_name);
            array_push($uploaded_files, $return_array);
        }

        echo json_encode($uploaded_files);
    }

    // construct stickers packages to show
    public function get_stickers(){
        $data = array();
        $directory = BASE_PATH . 'media' . DIRECTORY_SEPARATOR . 'stickers' . DIRECTORY_SEPARATOR;
        $escapedFiles = ['.','..',];
        $allowedFiles = ['jpg','jpeg','png','gif','webp'];
        $stickerDirs = [];
        $stickerDirList = scandir($directory);
        foreach ($stickerDirList as $stickerDir) {
            $stickerList = [];
            if (in_array($stickerDir, $escapedFiles)){
                continue;
            }
            if(is_dir($directory . $stickerDir)){
                $stickerListArray = scandir($directory . $stickerDir);
                foreach ($stickerListArray as $sticker) {
                    if (in_array($sticker, $escapedFiles)){
                        continue;
                    }
                    $file_ext = substr($sticker, strrpos($sticker, '.') + 1);
                    if (!in_array($file_ext, $allowedFiles)){
                        continue;
                    }

                    $stickerList[] =  $stickerDir . '/' . $sticker;
                }
                if($stickerList){
                    $stickerDirs[$stickerDir] = $stickerList;
                }
            }
        }
        arsort($stickerDirs);
        $data['stickers'] = $stickerDirs;
        echo json_encode($data);
    }


    // process active user restriction
    public function active_user_restriction(){
        $post_data = app('request')->body;
        if($post_data['current_status'] == 1){
            $new_status = 0;
        }else{
            $new_status = 1;
        }
        $update_meta = array();
        $update_meta['chat_meta_id'] = $post_data['chat_meta_id'];
        $update_meta[$post_data['restriction_type']] = $new_status;
        app('chat')->updateChatMetaData($update_meta);
        return json_response(["success" => 'true', "type" => $post_data['restriction_type'], "status" => $new_status]);
    }

    // process active group restriction
    public function active_group_restriction(){
        $post_data = app('request')->body;
        if($post_data['current_status'] == 1){
            $new_status = 0;
        }else{
            $new_status = 1;
        }
        $update_meta = array();
        $update_meta['chat_meta_id'] = $post_data['chat_meta_id'];
        $update_meta[$post_data['restriction_type']] = $new_status;
        app('chat')->updateGroupChatMetaData($update_meta);
        return json_response(["success" => 'true', "type" => $post_data['restriction_type'], "status" => $new_status]);
    }

    // change user status to online offline, busy and away
    public function change_user_status(){
        $post_data = app('request')->body;
        if($post_data['new_status']){
            $update_data = array('user_status' => $post_data['new_status'] );
            app('db')->where ('id', app('auth')->user()['id']);
            app('db')->update('users', $update_data);
            $_SESSION['user'] = app('auth')->user(app('auth')->user()['id']);
        }
    }

    // update admin settings
    public function update_settings(){
        $post_data = app('request')->body;
        $image_status = true;
        if($post_data['update_type'] == "image-settings"){
            $update_data = array();
            $image_message = array();
            foreach ($_FILES as $key => $each_file) {
                $current_image = "";
                $new_image = "";
                if(array_key_exists($key, SETTINGS)){ // check current image
                    $current_image = SETTINGS[$key]; // get current image
                }

                if($_FILES[$key]['size'] > 0){
                    $width = false;
                    $height = false;
                    if(array_key_exists($key, IMAGE_SIZE)){
                        if(array_key_exists('width', IMAGE_SIZE[$key])){
                            $width = IMAGE_SIZE[$key]['width'];
                        }
                        if(array_key_exists('height', IMAGE_SIZE[$key])){
                            $height = IMAGE_SIZE[$key]['height'];
                        }
                    }
                    $new_image = image($_FILES[$key], false, 'settings', $height, $width); // upload new image
                    if($new_image[0]){
                        $update_data[$key] = $new_image[1]; // assign to update_data array
                        if($current_image){ // delete current image
                            $current_image_path = BASE_PATH . 'media/settings/'.$current_image;
                            if(file_exists($current_image_path)) {
                                unlink($current_image_path);
                            }
                        }
                    }else{
                        $image_status = false;
                        array_push($image_message, array($key=>array($new_image[1])));
                    }
                }
            }

        }else {
            $update_data = $post_data;
        }

        unset($update_data['update_type']);
        $update_settings = app('admin')->updateSettings($update_data);
        if ($image_status == false) {
            return json_response(["success" => $image_status, "message" => $image_message]);
        }else{
            return json_response(["success" => $update_settings[0], "message" => $update_settings[1]]);
        }
    }

    // save chatroom details
    public function update_chatroom(){
        $post_data = app('request')->body;
        $update_chatroom = app('admin')->updateChatroom($post_data, $_FILES);

        return $update_chatroom;

    }

    // get chatroom details to admin
    public function get_chatroom(){
        $post_data = app('request')->body;
        $data = array();
        if (array_key_exists("edit_room", $post_data)) {
            if($post_data['edit_room']){
                app('db')->where('id', $post_data['edit_room']);
                $room_data = app('db')->getOne('chat_rooms');
                $data['chat_room'] = $room_data;

                app('db')->where ('slug', 'general');
                app('db')->where ('chat_room', $post_data['edit_room']);
                $chat_group = app('db')->getOne('chat_groups');

                app('db')->join("users u", "g.user=u.id", "LEFT");
                app('db')->where ('g.chat_group', $chat_group['id']);
                $group_users = app('db')->get('group_users g', null, 'g.*, u.*');
                $data['room_users'] = $group_users;
            }
        }

        echo app('twig')->render('chat_room_update.html', $data);
    }

    // user ban for chat rooms
    public function chatroom_user_restriction(){
        $post_data = app('request')->body;

        app('db')->where ('chat_room', $post_data['room_id']);
        $chat_groups = app('db')->get('chat_groups');

        foreach ($chat_groups as $chat_group) {
            app('db')->where ('user', $post_data['selected_user']);
            app('db')->where ('chat_group', $chat_group['id']);
            app('db')->update('group_users', array('status' => $post_data['restriction_type']));
        }

        if($post_data['restriction_type'] == "1"){
            return json_response(["success" => 'true', "message" => "User unkicked from this room"]);
        }elseif($post_data['restriction_type'] == "3"){
            return json_response(["success" => 'true', "message" => "User kicked from this room"]);
        }

    }

    // load more chats when scrolling up
    public function load_more_chats(){
        $data = array();
        $post_data = app('request')->body;
        $_SESSION['last_loaded_count'] += 20;
        if ($post_data['active_user']) {
            if($post_data['active_user'] > app('auth')->user()['id']) {
                $user_1 = app('auth')->user()['id'];
                $user_2 = $post_data['active_user'];
            }else{
                $user_1 = $post_data['active_user'];
                $user_2 = app('auth')->user()['id'];
            }
            $data['chats'] = app('chat')->getPrivateChats($user_1, $user_2, $post_data['active_room'], false, $post_data['direction']);
        }else{
            $data['chats'] = app('chat')->getGroupChats($post_data['active_group'], $post_data['active_room'], false, $post_data['direction']);
        }
        $data['chats'] = array_reverse($data['chats']);
        return json_response($data);
    }

    public function chatroom_search(){
        $post_data = app('request')->body;
        $q = app('purify')->xss_clean(clean($post_data['q']));
        $order_by = app('purify')->xss_clean(clean($post_data['order_by']));
        $data = array();

        if($post_data['created_by'] != 0){
            app('db')->where ('created_by', $post_data['created_by']);
        }else{
            if (!(app('auth')->user() && app('auth')->user()['user_type'] == 1)) {
                app('db')->where ('status', '1');
                app('db')->where ('is_visible', '1');
            }
        }

        if($q != ""){
            app('db')->where ("name", $q.'%', 'like');
        }
        if(array_key_exists("homepage_chat_room_limit", SETTINGS)){
            if (SETTINGS['homepage_chat_room_limit']) {
                $default_limit = SETTINGS['homepage_chat_room_limit'];
            }else{
                $default_limit = 6;
            }
        }else{
            $default_limit = 6;
        }
        $chat_rooms_list = app('db')->get('chat_rooms', array(0,$default_limit));
        $chat_rooms = array();
        foreach ($chat_rooms_list as $chat_room) {
            app('db')->join("chat_groups cg", "cg.id=gu.chat_group", "LEFT");
            app('db')->where ('cg.chat_room', $chat_room['id']);
            app('db')->where ('cg.slug', 'general');
            app('db')->get('group_users gu', null, 'gu.*');
            $chat_room['users_count'] = app('db')->count;
            array_push($chat_rooms, $chat_room);
        }
        if ($order_by == 'newest_first') {
            usort($chat_rooms, function($a, $b) {
                return $a['id'] <= $b['id'];
            });
        }else if($order_by == 'oldest_first'){
            usort($chat_rooms, function($a, $b) {
                return $a['id'] <=> $b['id'];
            });
        }else if($order_by == 'most_users_first'){
            usort($chat_rooms, function($a, $b) {
                return $a['users_count'] <= $b['users_count'];
            });
        }else if($order_by == 'least_users_first'){
            usort($chat_rooms, function($a, $b) {
                return $a['users_count'] <=> $b['users_count'];
            });
        }
        $data['chat_rooms'] = $chat_rooms;

        $chat_room_view = "small";
        if(array_key_exists("homepage_chat_room_view", SETTINGS)){
            if (SETTINGS['homepage_chat_room_view'] == 'large') {
                $chat_room_view = "large";
            }
        }

        if($chat_room_view == "small"){
            $html = app('twig')->render('chat_room_loop_small.html', $data);
        }else{
            $html = app('twig')->render('chat_room_loop_large.html', $data);
        }

        $retun_data = array();
        $retun_data['html'] = $html;
        return json_response($retun_data);
    }


    public function chatroom_load_more(){
        $post_data = app('request')->body;
        $chat_room_cont = app('purify')->xss_clean(clean($post_data['chat_room_cont']));
        $q = app('purify')->xss_clean(clean($post_data['q']));
        $data = array();
        if($post_data['created_by'] != 0){
            app('db')->where ('created_by', $post_data['created_by']);
        }else{
            if (!(app('auth')->user() && app('auth')->user()['user_type'] == 1)) {
                app('db')->where ('status', '1');
                app('db')->where ('is_visible', '1');
            }
        }
        if($q != ""){
            app('db')->where ("name", $q.'%', 'like');
        }
        if(array_key_exists("homepage_chat_room_limit", SETTINGS)){
            if (SETTINGS['homepage_chat_room_limit']) {
                $default_limit = SETTINGS['homepage_chat_room_limit'];
            }else{
                $default_limit = 6;
            }
        }else{
            $default_limit = 6;
        }
        $chat_rooms_list = app('db')->get('chat_rooms', array($chat_room_cont,$default_limit));
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

        $chat_room_view = "small";
        if(array_key_exists("homepage_chat_room_view", SETTINGS)){
            if (SETTINGS['homepage_chat_room_view'] == 'large') {
                $chat_room_view = "large";
            }
        }

        if($chat_room_view == "small"){
            $html = app('twig')->render('chat_room_loop_small.html', $data);
        }else{
            $html = app('twig')->render('chat_room_loop_large.html', $data);
        }

        $retun_data = array();
        $retun_data['html'] = $html;
        return json_response($retun_data);
    }


    // user ban for chat rooms
    public function delete_message(){
        $post_data = app('request')->body;
        if($post_data['chat_type'] == "group"){
            app('db')->where ('id', $post_data['message_id']);
            app('db')->update('group_chats', array('status' => 3, "updated_at" => app('db')->now()));
        }else{
            app('db')->where ('id', $post_data['message_id']);
            app('db')->update('private_chats', array('status' => 3, "updated_at" => app('db')->now()));
        }
        return json_response(["success" => 'true', "message" => "massage deleted"]);
    }


    // language add or update
    public function language_update(){
        $post_data = app('request')->body;
        $return = app('admin')->language_update($post_data);
        return json_response(["success" => $return[0], "message" => $return[1]]);
    }

    // delete language
    public function language_delete(){
        $post_data = app('request')->body;
        app('db')->where ('code', $post_data['lang']);
        app('db')->delete('languages');
        return json_response(["success" => true, "message" => ""]);
    }

    // update language translation
    public function update_translation(){
        $post_data = $_POST;
        $return = app('admin')->update_translation($post_data);
        return json_response(["success" => $return[0], "message" => $return[1]]);
    }

    // delete selected guest users
    public function delete_users(){
        $post_data = $_POST;
        $return = app('admin')->delete_users($post_data);
        return json_response(["success" => $return[0], "message" => $return[1]]);
    }

    // Chat Search
    public function chat_search(){
        $data = array();
        $post_data = app('request')->body;
        $q = app('purify')->xss_clean($post_data['q']);
        if ($q) {
            if ($post_data['active_user']) {
                if($post_data['active_user'] > app('auth')->user()['id']) {
                    $user_1 = app('auth')->user()['id'];
                    $user_2 = $post_data['active_user'];
                }else{
                    $user_1 = $post_data['active_user'];
                    $user_2 = app('auth')->user()['id'];
                }
                $data['chats'] = app('chat')->searchPrivateChats($user_1, $user_2, $post_data['active_room'], $q);
            }else{
                $data['chats'] = app('chat')->searchGroupChats($post_data['active_group'], $post_data['active_room'], $q);
            }
            $data['chats'] = array_reverse($data['chats']);
            return json_response($data);
        }
    }

    // Search Room Users
    public function room_user_search(){
        $post_data = app('request')->body;
        $data = array();
        $q = app('purify')->xss_clean($post_data['q']);
        if($q){
            if (app('auth')->isAuthenticated() == true) {
                $data = app('chat')->get_active_list($post_data['active_room'], false, $q, $post_data['search_from']);
            }
            return json_response($data);
        }
    }


    // upload audio files to server
    public function send_audio(){
        $post_data = app('request')->body;
        $decodedData = base64_decode($post_data['data']);
        $file_name = uniqid(rand(), true) . '.mp3';
        $fp = fopen("media/chats/audio/".$file_name, 'wb');
        if ($fp) {
            fwrite($fp, $decodedData);
            $return_array = array();
            $return_array['name'] = $file_name;
            $return_array['extenstion'] = 'mp3';
            $stat = fstat($fp);
            $return_array['size'] = app('chat')->humanFileSize($stat['size']);
            $return_array['duration'] = gmdate("i:s", $post_data['recordingTime']);
            fclose($fp);
            return json_response($return_array);
        }

    }

    public function get_message(){
        $post_data = app('request')->body;
        if($post_data['chat_type'] == 'group'){
            app('db')->join("users u", "c.sender_id=u.id", "LEFT");
            app('db')->where ('c.id', $post_data['chat_id']);
            $chat_data = app('db')->getOne('group_chats c', 'c.*, u.user_name, u.first_name');
        }else{
            app('db')->join("users u", "c.sender_id=u.id", "LEFT");
            app('db')->where ('c.id', $post_data['chat_id']);
            $chat_data = app('db')->getOne('private_chats c', 'c.*,  u.user_name, u.first_name,');
        }
        return json_response($chat_data);

    }

    // delete chatroom
    public function delete_chatroom(){
        $post_data = app('request')->body;
        app('db')->where ('id', $post_data['room_id']);
        app('db')->delete('chat_rooms');
        return json_response(["success" => true, "message" => ""]);
    }

    // delete all chats
    public function delete_chats(){
        $post_data = app('request')->body;
        app('db')->where ('room_id', $post_data['room_id']);
        app('db')->delete('group_chats');
        return json_response(["success" => true, "message" => ""]);
    }




}
