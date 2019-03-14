<?php

class Note_CTL extends Controller {

    private $note_mdl;
    private $token;

    public function __construct() {
        parent::__construct();
        $this->note_mdl = $this->load->model('note');
        $this->token = "qwerty";
    }

    public function n_c_delete() {
        try {
            $headers = apache_request_headers();
            if ($headers["Authentication"] === $this->token) {
                $r = $this->note_mdl->n_m_delete(intval(get("three"), 10));
                if ($r === 1) {
                    print success_to_json(SUCCESSFUL_DELETE);
                } else {
                    print error_to_json(ERROR_DELETE);
                }
            } else {
                print error_to_json(ERROR_SESSION_VALIDATION);
            }
        } catch (Exception $ex) {
            print error_to_json(ERROR_PROCESSING_DATA);
        }
    }

    public function n_c_update() {
        try {
            $headers = apache_request_headers();
            if ($headers["Authentication"] === $this->token) {
                $a = json_decode(file_get_contents('php://input'), true);
                $a["id"] = intval(get("three"), 10);
                $r = $this->note_mdl->n_m_update($a);
                if ($r === 1) {
                    print success_to_json(SUCCESSFUL_UPDATE);
                } else {
                    print error_to_json(ERROR_UPDATE);
                }
            } else {
                print error_to_json(ERROR_SESSION_VALIDATION);
            }
        } catch (Exception $ex) {
            print error_to_json(ERROR_PROCESSING_DATA);
        }
    }

    public function n_c_get() {
        try {
            $headers = apache_request_headers();
            if ($headers["Authentication"] === $this->token) {
                $r = $this->note_mdl->n_m_get(intval(get("three"), 10));
                if (is_array($r)) {
                    print success_to_json($r);
                } else {
                    print error_to_json(ERROR_CONSULT);
                }
            } else {
                print error_to_json(ERROR_SESSION_VALIDATION);
            }
        } catch (Exception $ex) {
            print error_to_json(ERROR_PROCESSING_DATA);
        }
    }

    public function n_c_list() {
        try {
            $headers = apache_request_headers();
            if ($headers["Authentication"] === $this->token) {
                $r = $this->note_mdl->n_m_list();
                if (is_array($r)) {
                    print success_to_json($r);
                } else {
                    print error_to_json(ERROR_CONSULT);
                }
            } else {
                print error_to_json(ERROR_SESSION_VALIDATION);
            }
        } catch (Exception $ex) {
            print error_to_json(ERROR_PROCESSING_DATA);
        }
    }

    public function n_c_new() {
        try {
            $headers = apache_request_headers();
            if ($headers["Authentication"] === $this->token) {
                $a = json_decode(file_get_contents('php://input'), true);
                $r = $this->note_mdl->n_m_new($a);
                if ($r === 1) {
                    print success_to_json(SUCCESSFUL_SAVE);
                } else {
                    print error_to_json(ERROR_SAVE);
                }
            } else {
                print error_to_json(ERROR_SESSION_VALIDATION);
            }
        } catch (Exception $ex) {
            print error_to_json(ERROR_PROCESSING_DATA);
        }
    }

}
