<?php

/* user authentication class */

class Auth
{
    // Check whether user is logged in
    public function isAuthenticated()
    {
        if (isset($_SESSION['user'])) {
            return true;
        } else {
            return false;
        }
    }

    // Log in user by email and password
    public function authenticate($email, $password)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            app('db')->where('email', $email);
            if ($user = app('db')->getOne('users')) {
                if($user['available_status'] == 1){
                    $passwprd_verify = password_verify($password, $user['password']);
                    if ($passwprd_verify) {
                        $_SESSION['user'] = $this->user($user['id']);
                        return true;
                    } else {
                        // Wrong Password
                        app('msg')->error('Wrong Password!');
                        return false;
                    }
                }else{
                    // Inactive account
                    app('msg')->error('Your account is disabled!');
                    return false;
                }
            } else {
                // Wrong Email
                app('msg')->error('Wrong Email!');
                return false;
            }
        }else{
            app('msg')->error('Email is invalid!');
            return false;
        }
    }

    // Log in guest user by username
    public function guest_authenticate($guest_name, $guest_username, $sex, $dob, $country, $timezone){
        $valid = true;
        $message = '<ul>';
        $validate_data = clean_and_validate('first_name', $guest_name);
        $value = $validate_data[0];

        if(!$validate_data[1][0]){
            $valid = false;
            foreach ($validate_data[1][1]['first_name'] as $each_error) {
                $message .= "<li>".$each_error."</li>";
            }
        }
        $message .= "</ul>";

        if($valid){
            $data['first_name'] = $guest_name;
  
            $data['email'] = "";
            $data['password'] = "";
            $data['user_name'] = $guest_username;
            $data['user_type'] = 3;
            $data['user_status'] = 1;
            $data['available_status'] = 1;
            $data['created_at'] = app('db')->now();

            if ($sex) {
                $data['sex'] = $sex;
            }

            if ($dob) {
                $data['dob'] = $dob;
            }

            if ($country) {
                $data['country'] = $country;
            }

            if ($timezone) {
                $data['timezone'] = $timezone;
            }

            $id = app('db')->insert ('users', $data);
            if($id){
                $_SESSION['user'] = $this->user($id);
                return true;
            }else {
                app('msg')->error('Something went wrong!');
                return false;
            }
        }else {
            app('msg')->error($message);
            return false;
        }
    }

    // Add new user to the system
    public function registerNewUser($user_name, $first_name,  $user_email, $password, $password_repeat, $sex, $dob, $country, $timezone)
    {
        // check provided data validity
        if (empty($user_name)) {
            app('msg')->error('Username is required!');
            return false;
        } elseif (preg_match('/[^a-z_\-0-9.]/i', $user_name)) {
            app('msg')->error(__('Username is invalid!'));
            return false;
        } elseif (empty($user_email)) {
            app('msg')->error(__('Email is required!'));
            return false;
        } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            app('msg')->error(__('Email is invalid!'));
            return false;
        } elseif (empty($password) || empty($password_repeat)) {
            app('msg')->error(__('Password is required!'));
            return false;
        } elseif ($password !== $password_repeat) {
            app('msg')->error(__('Password mismatch!'));
            return false;
        } else {
            app('db')->where('email', $user_email);
            $user_email_exist = app('db')->getOne('users');

            app('db')->where('user_name', $user_name);
            $user_name_exist = app('db')->getOne('users');

            if ($user_email_exist) {
                app('msg')->error(__('Email already exists!'));
                return false;
            } elseif ($user_name_exist) {
                app('msg')->error(__('Username is already taken!'));
                return false;
            } else {
                $data = array("user_name" => $user_name,
                               "first_name" => $first_name,
                             
                               "email" => $user_email,
                               "password" => $password,
                            );
                if ($sex) {
                    $data["sex"] = $sex;
                }
                if ($dob) {
                    $data["dob"] = $dob;
                }
                if ($country) {
                    $data["country"] = $country;
                }
                if ($timezone) {
                    $data["timezone"] = $timezone;
                }
                $valid = true;
                $message = '<ul>';
                foreach ($data as $key => $value) {
                    $validate_data = clean_and_validate($key, $value);
                    $value = $validate_data[0];
                    $data[$key] = $value;
                    if(!$validate_data[1][0]){
                        $valid = false;
                        foreach ($validate_data[1][1][$key] as $each_error) {
                            $message .= "<li>".$each_error."</li>";
                        }
                    }
                }
                $message .= "</ul>";
                if($valid){
                    $data['password'] = password_hash(trim($password), PASSWORD_DEFAULT);
                    $data['user_status'] = 1;
                    $data['available_status'] = 1;
                    $data['created_at'] = app('db')->now();
                    $id = app('db')->insert ('users', $data);
                    if($id){
                        return true;
                    }else {
                        app('msg')->error(__('Something went wrong!'));
                        return false;
                    }
                }else {
                    app('msg')->error($message);
                    return false;
                }
            }
        }
    }

    // Get user data
    public function user($id = false)
    {
        if ($id) {
            app('db')->where('id', $id);
            $user_data = app('db')->getOne('users');
            if ($user_data['avatar']) {
                $user_data['avatar_url'] = URL."media/avatars/".$user_data['avatar'];
            } else {
                $user_data['avatar_url'] = URL."static/img/user.jpg";
            }

            $user_data['user_status_class'] = "";
            if ($user_data['user_status'] == 1) {
                $user_data['user_status_class'] = "online";
            } elseif ($user_data['user_status'] == 2) {
                $user_data['user_status_class'] = "offline";
            } elseif ($user_data['user_status'] == 3) {
                $user_data['user_status_class'] = "busy";
            } elseif ($user_data['user_status'] == 4) {
                $user_data['user_status_class'] = "away";
            }
            return $user_data;
        } else {
            if (isset($_SESSION['user'])) {
                return $_SESSION['user'];
            } else {
                return false;
            }
        }
    }

    // Save user profile with provided data
    public function saveProfile($data, $admin_update)
    {
        app('db')->where('id', $data['id']);
        if (app('db')->update('users', $data)) {
            if(!$admin_update){
                $_SESSION['user'] = $this->user($data['id']);
            }
            return [true, __('Successfully saved!')];
        } else {
            app('msg')->error(__('Something went wrong!'));
            return [false, __('Something went wrong!')];
        }

    }

    // add new user profile with provided data
    public function addProfile($data)
    {
        if (app('db')->insert('users', $data)) {
            return [true, __('Successfully saved!')];
        } else {
            app('msg')->error(__('Something went wrong!'));
            return [false, __('Something went wrong!')];
        }

    }

    // Send password reset link with a reset key
    function sendResetPasswordLink($email){
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            app('db')->where('email', $email);
            $user_email_exist = app('db')->getOne('users');
            if ($user_email_exist) {
                $reset_key = uniqid('cn_',true);
                $data = array();
                $data['reset_key'] = $reset_key;
                app('db')->where('email', $email);
                app('db')->update('users', $data);
                $email_data['reset_link'] = route('reset-password').'?reset_key='.$reset_key;
                $body = app('twig')->render('emails/password_reset.html', $email_data);
                send_mail($email, SETTINGS['site_name'].' Password Reset', $body);
            }
            app('msg')->success(__('If the provided email is on our database, We have sent a password reset link.'));
            return [true, __('Email sent!')];
        }else{
            app('msg')->error(__('Email is invalid!'));
            return [false, ''];
        }
    }

    // Reset password if the reset key is valid
    function resetPassword($reset_key, $password){
        app('db')->where('reset_key', $reset_key);
        $user_exist = app('db')->getOne('users');
        if (empty($password)) {
            return [false, __('Empty Password')];
        } elseif (empty($reset_key)) {
            return [false, __('Empty Reset Key')];
        }elseif (!$user_exist) {
            return [false, __('Wrong Reset Key')];
        }else{
            $data = array();
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            $data['reset_key'] = '';
            app('db')->where('reset_key', $reset_key);
            app('db')->update('users', $data);
            return [true, __('Password Reseted Successfully')];
        }
    }

    function nextGuestUser(){
        app('db')->orderBy('id', 'desc');
        $next_id = app('db')->getOne('users')['id']+1001;
        $next_username = 'guest_'.$next_id;
        if($this->checkUserName($next_username)){
            $go_forward = true;
        }else{
            $go_forward = false;
            do {
                $next_id += 100 ;
                $next_username = 'guest_'.$next_id;
                $go_forward = $this->checkUserName($next_username);
                if($go_forward){
                    break;
                }
            } while (!$go_forward);
        }

        $next_guest_data =  array();
        if($go_forward){
            $next_guest_data['guest_username'] = $next_username;
            $next_guest_data['guest_userid'] = $next_id;
        }
        return $next_guest_data;
    }

    function checkUserName($user_name){
        app('db')->where('user_name', $user_name);
        if(app('db')->getOne('users')){
            return false;
        }else{
            return true;
        }
    }

}
