<?php

defined('APP_PATH') || define('APP_PATH', realpath('.'));

$connection = [];
$logPath = [];


$host = gethostname();



if ($host == '192.168.1.5') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'

    );  
    $logPath = array('location' => "/var/log/envirofit/");
} else if ($host == 'philo') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );
    $logPath = array('location' => "/var/www/logs/envirofit/");
} else if ($host == 'Jamess-MacBook-Air.local') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );
    $logPath = array('location' => "../logs/envirofit/");
} else if ($host == 'jamess-air') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );

    $logPath = array('location' => "../logs/envirofit/");
} else if ($host == '192.168.1.14') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );

    $logPath = array('location' => "../logs/envirofit/");
} else if ($host == '192.168.1.3') {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'localhost',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );

    $logPath = array('location' => "../logs/envirofit/");
} else {
    $connection = array(
        'adapter' => 'mysql',
        'host' => 'ke-pr-sdb1',
        'username' => 'envirofit',
        'password' => 'envirofit',
        'dbname' => 'envirofit',
        'charset' => 'utf8'
    );

    $logPath = array('location' => "/var/www/logs/envirofit/");
}

return new \Phalcon\Config([
    'database' => $connection,
    'application' => [
        'controllersDir' => APP_PATH . '/app/controllers/',
        'modelsDir' => APP_PATH . '/app/models/',
        'utilsDir' => APP_PATH . '/app/utils/',
        'pluginsDir' => APP_PATH . '/app/plugins/',
        'libraryDir' => APP_PATH . '/app/library/',
        'cacheDir' => APP_PATH . '/app/cache/',
        'vendorDir' => APP_PATH . '/vendor/',
        'baseUri' => '/envirofit/',
    ],
    'logPath' => $logPath
        ]);

/*CREATE USER 'envirofit'@'localhost' IDENTIFIED WITH mysql_native_password;GRANT USAGE ON *.* TO 'envirofit'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;SET PASSWORD FOR 'envirofit'@'localhost' = '***';GRANT ALL PRIVILEGES ON `envirofit`.* TO 'envirofit'@'localhost';*/
