<?php
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/helpers/functions.php';
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/db/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
