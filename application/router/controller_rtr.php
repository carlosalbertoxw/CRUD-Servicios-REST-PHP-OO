<?php

class Controller_RTR {

    private $load;

    public function __construct() {
        $this->load = Load::get_instance();
        $method = $_SERVER['REQUEST_METHOD'];
        if (filter_input(INPUT_GET, 'one') === 'prueba' && $method === 'GET') {
            $ctl = $this->load->controller('note');
            $ctl->n_prueba();
        }
    }

}
