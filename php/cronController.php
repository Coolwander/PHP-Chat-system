<?php
namespace App;

/* This class is handling all the requests in the cron*/

class cronController{

    function delete_guests() {
        app('db')->where ('user_type', 3);
        app('db')->where ('last_seen <= (NOW() - interval '.SETTINGS['guest_inactive_hours'].' hour) OR last_seen IS NULL');
        $users = app('db')->get('users', null, 'id');
        foreach ($users as $user) {
            $delete_user = $user['id'];
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

    }
}
