<?php

use Phalcon\Loader;

$loader = new Loader();

$loader->registerDirs(
    [
    "app/controllers/",
     "app/models/",
     "app/utils/",
     "vendor/"
    ]
);
$loader->register();
