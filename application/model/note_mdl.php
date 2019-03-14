<?php

include_once 'application/db/connection.php';

class Note_MDL {

    private $db;

    public function __construct() {
        $this->db = new Connection();
    }

    public function n_m_list() {
        $conn = $this->db->open_connection();
        $res = $this->db->get_results($conn, "SELECT * FROM notes");
        $this->db->close_connection($conn);
        return $res;
    }

    public function n_m_delete($id) {
        $conn = $this->db->open_connection();
        $conn->begin_transaction();
        $r = $this->db->execute_query($conn, "DELETE FROM notes WHERE id=" . $conn->escape_string($id));
        if ($r === 0) {
            $conn->commit();
            $r = 1;
        } else {
            $conn->rollback();
            $r = 0;
        }
        $this->db->close_connection($conn);
        return $r;
    }

    public function n_m_update($a) {
        $conn = $this->db->open_connection();
        $conn->begin_transaction();
        $r = $this->db->execute_query($conn, "UPDATE notes SET title='" . $conn->escape_string($a['title']) . "', text='" . $conn->escape_string($a['text']) . "' WHERE id=" . $conn->escape_string($a['id']));
        if ($r === 0) {
            $conn->commit();
            $r = 1;
        } else {
            $conn->rollback();
            $r = 0;
        }
        $this->db->close_connection($conn);
        return $r;
    }

    public function n_m_get($id) {
        $conn = $this->db->open_connection();
        $r = $this->db->get_result($conn, "SELECT * FROM notes WHERE id=" . $conn->escape_string($id));
        $this->db->close_connection($conn);
        return $r;
    }

    public function n_m_new($a) {
        $conn = $this->db->open_connection();
        $conn->begin_transaction();
        $r = $this->db->execute_query($conn, "INSERT INTO notes(title,text) VALUES('" . $conn->escape_string($a['title']) . "','" . $conn->escape_string($a['text']) . "')");
        if ($r === 0) {
            $conn->commit();
            $r = 1;
        } else {
            $conn->rollback();
            $r = 0;
        }
        $this->db->close_connection($conn);
        return $r;
    }

}
