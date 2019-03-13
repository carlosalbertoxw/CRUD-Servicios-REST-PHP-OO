<?php

class Controller_RTR {

    private $load;

    public function __construct() {
        $this->load = Load::get_instance();
        $method = $_SERVER['REQUEST_METHOD'];
        if (filter_input(INPUT_GET, 'one') === 'api') {
            if (filter_input(INPUT_GET, 'two') === 'notes' && $method === 'POST') {
                $ctl = $this->load->controller('note');
                $ctl->n_c_new();
            } else
            if (filter_input(INPUT_GET, 'two') === 'notes' && $method === 'GET' && strlen(filter_input(INPUT_GET, 'three')) > 0) {
                $ctl = $this->load->controller('note');
                $ctl->n_c_get();
            }
        }
    }

}
