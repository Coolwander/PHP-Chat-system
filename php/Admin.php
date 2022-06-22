<?php

/* Admin class for script administrator*/

class Admin
{
    // This function is responsible for saving settings all data
    function updateSettings($data){
        $status = true;
        $message = array();
        foreach ($data as $key => $value) {
            $validate_data = clean_and_validate($key, $value);
            $value = $validate_data[0];
            if($validate_data[1][0]){
                app('db')->where('name', $key);
                if(app('db')->getOne('settings')){
                    app('db')->where ('name', $key);
                    app('db')->update('settings', array('value' => $value));
                }else{
                    app('db')->insert ('settings', array('name' => $key, 'value' => $value));
                }
            }else{
                $status = false;
                array_push($message, $validate_data[1][1]);
            }
        }
        return array($status, $message);
    }

    // This function is responsible for saving chatroom data
    function updateChatroom($post_data, $FILES){
        $is_protected = 0;
        $pw_check = True;
        $is_visible = 0;
        $password = "";
        $new_room = false;
        if(array_key_exists("is_protected", $post_data)){
            $is_protected = 1;
            $password = $post_data['pin'];
            if(!$password){
                $pw_check = False;
            }
        }
        if(array_key_exists("is_visible", $post_data)){
            $is_visible = 1;
        }

        if($pw_check){
            if($post_data['room_id']){
                app('db')->where('id !=' . $post_data['room_id']);
            }
            app('db')->where('slug', $post_data['slug']);
            $exist_data = app('db')->getOne('chat_rooms');

            if(!$exist_data){
                $data = Array ("name" => $post_data['name'],
                               "description" => $post_data['description'],
                               "slug" => $post_data['slug'],
                               "is_protected" => $is_protected,
                               "pin" => $password,
                               "is_visible" => $is_visible,
                               "status" => $post_data['status']
                            );

                $status = true;
                $message = array();
                foreach ($data as $key => $value) {
                    $validate_data = clean_and_validate($key, $value);
                    $value = $validate_data[0];
                    if($key == 'pin'){
                        unset($data['pin']);
                        $data["password"] = $value;
                    }else{
                        $data[$key] = $value;
                    }

                    if(!$validate_data[1][0]){
                        $status = false;
                        array_push($message, $validate_data[1][1]);
                    }
                }

                if($status){
                    $allowed_users = $post_data['allowed_users'];

                    $allowed_user_array = array();
                    foreach($allowed_users as $allow_user){
                        $allowed_user_array[] = $allow_user;
                    }
                    $data['allowed_users'] = json_encode($allowed_user_array);

                    if($post_data['room_id']){
                        $room_id = $post_data['room_id'];
                        app('db')->where ('id', $room_id);
                        app('db')->update('chat_rooms', $data);
                    }else{
                        $new_room = true;
                        $data["created_by"] = app('auth')->user()['id'];
                        $room_id = app('db')->insert('chat_rooms', $data);
                    }

                    app('db')->where ('slug', 'general');
                    app('db')->where ('chat_room', $room_id);
                    if(!app('db')->getOne('chat_groups')){
                        $data = Array ("name" => "General",
                                       "slug" => "general",
                                       "chat_room" => $room_id,
                                       "status" => 1,
                                       "created_by" => app('auth')->user()['id'],
                                       "created_at" => app('db')->now()
                                    );
                        app('db')->insert('chat_groups', $data);
                    }

                    app('db')->where('id', $room_id);
                    $room_data = app('db')->getOne('chat_rooms');
                    $room_data['new_room'] = $new_room;

                    $image_status = true;
                    $image_message = "";
                    if(array_key_exists("cover_image", $FILES)){
                        if($FILES['cover_image']['name']){
                            $image = image($FILES['cover_image'], false, 'chatrooms', 480, 640);
                            if($image[0]){
                                if($room_data['cover_image']){
                                    $old_image = BASE_PATH . 'media/chatrooms/'.$room_data['cover_image'];
                                    if(file_exists($old_image)) {
                                        unlink($old_image);
                                    }
                                }

                                app('db')->where ('id', $room_id);
                                app('db')->update('chat_rooms', Array("cover_image" => $image[1]));
                            }else{
                                $image_status = false;
                                $image_message = $image[1];
                            }
                        }
                    }

                    if($image_status){
                        $update_room_return = array('true', 'Successfully Updated!', $room_data);
                    }else{
                        $update_room_return = array($image_status, array(array('cover_image' => [$image_message])), '');
                    }
                }else{
                    $update_room_return = array($status, $message, '');
                }
            }else{
                $update_room_return = array('false', array(array('slug' => ['Slug already exist!'])), '');
            }
        }else{
            $update_room_return = array('false', array(array('pin' => ['Room pin required!'])), '');
        }

        return json_response(["success" => $update_room_return[0], "message" => $update_room_return[1], "info" => $update_room_return[2]]);

    }

    // update language data
    function language_update($post_data){
        $data = Array ("code" => $post_data['code'],
                       "name" => $post_data['name'],
                       "country" => $post_data['country'],
                       "direction" => $post_data['direction'],
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
            if($post_data['edit_type'] == "update"){
                app('db')->where('code', $data['code']);
                app('db')->update('languages', $data);
            }else{
                app('db')->where('code', $data['code']);
                $language_exist = app('db')->getOne('languages');
                if ($language_exist) {
                    $status = false;
                    array_push($message, array(array('code' => ['Language code already exists!'])));
                } else {
                    $id = app('db')->insert ('languages', $data);
                }
            }
        }

        return array($status, $message);
    }

    // update language translation
    function update_translation($post_data){

        $status = true;
        $message = array();
        $data = array();
        foreach ($post_data as $key => $value) {
            if($key > 0 and $key != 'lang'){
                $validate_data = clean_and_validate($key, $value);
                $value = $validate_data[0];
                if($value){
                    array_push($data, array($post_data['lang'], $key, $value));
                }
                if(!$validate_data[1][0]){
                    $status = false;
                    array_push($message, $validate_data[1][1]);
                }
            }
        }

        if($status){
            $keys = Array("lang_code", "term_id", "translation");
            app('db')->where ('lang_code', $post_data['lang']);
            app('db')->delete('translations');
            $ids = app('db')->insertMulti('translations', $data, $keys);
        }

        return array($status, $message);
    }

    // delete selected users
    function delete_users($post_data){
        $status = true;
        $message = array();
        $delete_users = $post_data['delete_user_list'];

        foreach($delete_users as $delete_user){
            app('db')->where ('user', $delete_user);
            app('db')->delete('group_users');

            app('db')->where ('sender_id', $delete_user);
            app('db')->delete('group_chats');

            app('db')->where ('from_user', $delete_user);
            app('db')->delete('private_chat_meta');

            app('db')->where ('sender_id', $delete_user);
            app('db')->delete('private_chats');

            app('db')->where ('id', $delete_user);
            app('db')->delete('users');
        }

        return array($status, $message);
    }

}
