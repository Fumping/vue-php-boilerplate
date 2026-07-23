<?php

jsonResponse([
    'status' => 'ok',
    'driver' => defined('DB_DRIVER') ? DB_DRIVER : 'unknown',
    'time'   => date(DATE_ATOM),
]);
