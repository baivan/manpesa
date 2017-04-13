<?php

defined('APP_PATH') || define('APP_PATH', realpath('.'));

$connection = [];
$logPath = [];


$host = gethostname();



if ($host == 'philo') 
    {
        $connection = array(
            'adapter'     => 'mysql',
            'host'        => 'localhost',
            'username'    => 'envirofit',
            'password'    => 'envirofit',
            'dbname'      => 'envirofit',
            'charset'     => 'utf8'
            // 'unix_socket'   => '/Applications/MAMP/tmp/mysql/mysql.sock',
        );
        $logPath =  array('location'  =>  "/var/www/logs/envirofit/");
    }
else if ($host=='Jamess-MacBook-Air.local' ) 
    {
        $connection = array(
            'adapter'     => 'mysql',
            'host'        => 'localhost',
            'username'    => 'envirofit',
            'password'    => 'envirofit',
            'dbname'      => 'envirofit',
            'charset'     => 'utf8'
            // 'unix_socket'   => '/Applications/MAMP/tmp/mysql/mysql.sock',
        );
        $logPath = array('location'  => "../logs/envirofit/");
    }
else if ($host=='jamess-air' ) 
    {
        $connection = array(
            'adapter'     => 'mysql',
            'host'        => 'localhost',
            'username'    => 'envirofit',
            'password'    => 'envirofit',
            'dbname'      => 'envirofit',
            'charset'     => 'utf8'
            // 'unix_socket'   => '/Applications/MAMP/tmp/mysql/mysql.sock',
        );

       $logPath = array('location'  => "../logs/envirofit/");
    }
    else if($host=='192.168.1.11' ){
        $connection = array(
            'adapter'     => 'mysql',
            'host'        => 'localhost',
            'username'    => 'envirofit',
            'password'    => 'envirofit',
            'dbname'      => 'envirofit',
            'charset'     => 'utf8'
            // 'unix_socket'   => '/Applications/MAMP/tmp/mysql/mysql.sock',
        );

        $logPath = array('location'  => "../logs/msupport/");
    }
    
else
    {
        $connection = array(
                'adapter'     => 'mysql',
                'host'        => 'ke-pr-db-1',
                'username'    => 'fast_sms',
                'password'    => 'fast_sms',
                'dbname'      => 'envirofit',
                'charset'     => 'utf8'
            );

        $logPath =  array('location'  =>  "/var/www/logs/envirofit/");
    }

return new \Phalcon\Config([
    'database' => $connection,
    'application' => [
        'controllersDir' => APP_PATH . '/app/controllers/',
        'modelsDir'      => APP_PATH . '/app/models/',
        'utilsDir'       => APP_PATH . '/app/utils/',
        'pluginsDir'     => APP_PATH . '/app/plugins/',
        'libraryDir'     => APP_PATH . '/app/library/',
        'cacheDir'       => APP_PATH . '/app/cache/',
        'vendorDir'      => APP_PATH . '/vendor/',
        'baseUri'        => '/envirofit/',
    ],
    'logPath' => $logPath
]);

/*CREATE USER 'envirofit'@'localhost' IDENTIFIED WITH mysql_native_password;GRANT USAGE ON *.* TO 'envirofit'@'localhost' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;SET PASSWORD FOR 'envirofit'@'localhost' = '***';GRANT ALL PRIVILEGES ON `envirofit`.* TO 'envirofit'@'localhost';*/
