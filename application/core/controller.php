<?php

abstract class Controller {

    protected $load;

    public function __construct() {
        $this->load = Load::get_instance();
    }

}
