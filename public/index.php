<?php

declare(strict_types=1);

use App\Bootstrap\App;

require dirname(__DIR__) . '/vendor/autoload.php';

// Punto de entrada único: construye la aplicación y despacha la petición.
(new App(dirname(__DIR__)))->run();
