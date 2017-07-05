<?php

use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use \Firebase\JWT\JWT;

/**
 * generates/issues and validates tokens
 */
class JwtManager {

    public function verifyToken($token, $action = 0) {
        $key = "IMw2c3W5KWLFN1sBH1befeFocdWUs0Sd";
        $config = include APP_PATH . "/app/config/config.php";
        $logPathLocation = $config->logPath->location;

        $decoded;

        try {
            $decoded = JWT::decode($token, $key, array('HS256'));
        } catch (Exception $e) {
            $logger = new FileAdapter(logPathLocation.'error_logs.log');
            $logger->log("token errors " . $e->getMessage());
            $res = new SystemResponses();
            $res->composePushLog("Exeption", $e->getMessage(), "Token decode error");
            return $decoded;
        }
        if ($decoded->action == $action) {
            return $decoded;
        }
        if ($decoded->name && $decoded->userId) {
            return $decoded;
        }

        return;
    }

    public function issueToken($user) {
        $key = "IMw2c3W5KWLFN1sBH1befeFocdWUs0Sd";

        $tokenId = base64_encode(mcrypt_create_iv(32));
        $issuedAt = time();
        $notBefore = $issuedAt + 10;             //Adding 10 seconds
        $expire = $notBefore + 60;            // Adding 60 seconds
        $serverName = "www.southwell.io"; // Retrieve the server name from config file

        $token = array(
            "iss" => $serverName,
            "iat" => $issuedAt,
            "nbf" => $notBefore,
            "name" => $user->username,
            "userId" => $user->userID
        );
        $jwt = JWT::encode($token, $key);
        return $jwt;
    }

}
