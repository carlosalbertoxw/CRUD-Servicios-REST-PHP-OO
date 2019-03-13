<?php

abstract class Controller {

    protected $load;

    public function __construct() {
        $this->load = Load::get_instance();
        $this->load->helper('json_format');
        $this->load->helper('mensajes');
        $this->load->helper('inputs_validation');
    }

}
