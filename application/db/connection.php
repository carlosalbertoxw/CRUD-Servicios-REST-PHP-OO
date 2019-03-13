<?php

function write_log($cadena, $tipo) {
    $file = fopen(realpath('.') . "/application/db/dbl/log_" . date("Y-m-d") . ".txt", "a+");
    fwrite($file, "[" . date("Y-m-d H:i:s") . "] - " . $tipo . " - " . $cadena . "\n");
    fclose($file);
}

class Connection {

    public function open_connection() {
        $conn = new mysqli('localhost', 'root', 'qwerty', 'notes');
        if ($conn->connect_errno == 0) {
            $conn->set_charset('UTF8');
            //write_log('----conexión abierta----', 'info');
            return $conn;
        } else {
            write_log($conn->connect_errno . ' - Error al abrir la conexión a la BD - ' . $conn->connect_error, 'Error');
        }
    }

    public function close_connection($conn) {
        if ($conn !== FALSE) {
            $conn->close();
            $conn = FALSE;
            //write_log('----conexión cerrada----', 'info');
        }
    }

    public function execute_query($conn, $query) {
        $conn->query($query);
        $result = $conn->errno;
        //write_log($query, 'info');
        if ($conn->errno != 0) {
            write_log($conn->errno . ' - Error al ejecutar la query - ' . $conn->error . ' Query ' . $query, 'Error');
        }
        return $result;
    }

    public function get_result($conn, $query) {
        $row = NULL;
        $result = $conn->query($query);
        //write_log($query, 'info');
        if ($conn->errno != 0) {
            $row = $conn->errno;
            write_log($conn->errno . ' - Error al ejecutar la query - ' . $conn->error . ' Query ' . $query, 'Error');
        } else {
            $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
        }
        return $row;
    }

    public function get_results($conn, $query) {
        $rows = NULL;
        $result = $conn->query($query);
        //write_log($query, 'info');
        if ($conn->errno != 0) {
            $rows = $conn->errno;
            write_log($conn->errno . ' - Error al ejecutar la query - ' . $conn->error . ' Query ' . $query, 'Error');
        } else {
            while ($rows[] = mysqli_fetch_array($result,MYSQLI_ASSOC));
            array_pop($rows);
        }
        return $rows;
    }

}
