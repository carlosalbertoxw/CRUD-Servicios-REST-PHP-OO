<?php

class Note_CTL extends Controller {

    private $note_mdl;

    public function __construct() {
        parent::__construct();
        $this->note_mdl = $this->load->model('note');
    }

    public function n_prueba() {
        $a[0]='h';
        $a[1]='o';
        $a[2]='l';
        $a[3]='a';
        $a['object']=$a;
        print json_encode($a);
    }

}
