<?php

date_default_timezone_set('America/Mexico_City');

define('RELEASED', FALSE);
define('TRANSFER_PROTOCOL', 'http://');
define('WEB_PATH', '/CRUD-Servicios-REST-PHP-OO/');
define('WEB_SITE_DOMAIN', filter_input(INPUT_SERVER, 'SERVER_NAME'));
define('URI', filter_input(INPUT_SERVER, 'REQUEST_URI'));
