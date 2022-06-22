<?php
namespace App;

/* This class is handling all the requests in the installer*/

class installController{

    // load not installed page if script is not installed
    function index() {
        include(BASE_PATH.'installer/not_installed.php');
    }

    // load installer index page
    function install() {
        include(BASE_PATH.'installer/index.php');
    }

    // load already installed page if script is installed
    function installed() {
        include(BASE_PATH.'installer/installed.php');
    }

    // handles installer ajax requests
    function ajax(){
        include(BASE_PATH.'installer/ajax.php');
    }

    // load updater
    function update() {
        include(BASE_PATH.'installer/update.php');
    }
}
