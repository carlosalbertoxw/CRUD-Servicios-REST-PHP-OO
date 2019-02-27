<?php

function random_string($length = 10, $lc = TRUE, $uc = TRUE, $n = TRUE, $ch = FALSE) {
    $rstr = '';
    $source = '';
    if ($lc) {
        $source .= 'abcdefghijklmnopqrstuvwxyz';
    }
    if ($uc) {
        $source .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if ($n) {
        $source .= '1234567890';
    }
    if ($ch) {
        $source .= '|@#~$%()=^*+[]{}-_';
    }
    if ($length > 0) {
        $source = str_split($source, 1);
        for ($i = 1; $i <= $length; $i++) {
            $num = rand(1, count($source));
            $rstr .= $source[$num - 1];
        }
    }
    return $rstr;
}
