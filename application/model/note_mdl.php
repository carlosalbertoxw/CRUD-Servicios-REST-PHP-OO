<?php

include_once 'application/db/connection.php';

class Note_MDL {

    private $db;

    public function __construct() {
        $this->db = new Connection();
    }

}
