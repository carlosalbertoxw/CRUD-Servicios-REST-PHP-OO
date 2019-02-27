<?php

include_once 'application/config/constants.php';
include_once 'application/core/load.php';
include_once 'application/core/controller.php';
include_once 'application/core/functions.php';

class Index {

    private $load;

    public function __construct() {
        header('Content-Type: application/json');
        $this->load = Load::get_instance();
        $this->load->router('controller');
    }

}
