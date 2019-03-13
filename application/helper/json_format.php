<?php

function a_json($status, $object) {
    $a["result"] = $object;
    $a["status"] = $status;
    print json_encode($a);
}

function error_to_json($mensaje) {
    a_json(FALSE, $mensaje);
}

function success_to_json($mensaje) {
    a_json(TRUE, $mensaje);
}
