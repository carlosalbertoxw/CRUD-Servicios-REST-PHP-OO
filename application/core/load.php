<?php

class Load {

    private static $instance = NULL;

    private function __construct() {
        
    }

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new Load();
        }
        return self::$instance;
    }

    public function controller($controller_name) {
        if (include_once 'application/controller/' . $controller_name . '_ctl.php') {
            $classname = ucfirst($controller_name) . '_CTL';
            return new $classname;
        } else {
            throw new Exception('File not found: ' . $controller_name . '_ctl');
        }
    }

    public function model($model_name) {
        if (include_once 'application/model/' . $model_name . '_mdl.php') {
            $classname = ucfirst($model_name) . '_MDL';
            return new $classname;
        } else {
            throw new Exception('File not found: ' . $model_name . '_mdl');
        }
    }

    public function router($router_name) {
        if (include_once 'application/router/' . $router_name . '_rtr.php') {
            $classname = ucfirst($router_name) . '_RTR';
            return new $classname;
        } else {
            throw new Exception('File not found: ' . $router_name . '_rtr');
        }
    }

    public function view($view_name, $a = NULL) {
        if (!include_once 'application/view/' . $view_name . '.php') {
            throw new Exception('File not found: ' . $view_name);
        }
    }

    public function helper($helper_name) {
        if (!include_once 'application/helper/' . $helper_name . '.php') {
            throw new Exception('File not found: ' . $helper_name);
        }
    }

}
