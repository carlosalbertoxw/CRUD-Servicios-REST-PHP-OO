<?php

function encrypt_password($string) {
    return sha1($string);
}
