<?php
use Phalcon\Http\Response;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use Phalcon\Mvc\Controller;

 /**
 * 
 */
 class SystemResponses extends Controller
 {
     public $url = "http://api.southwell.io/java360_api_v1/";
    public $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJsb2NhbGhvc3QiLCJpYXQiOjE0ODM5NTYwNDYsImFwcCI6ImphdmEzNjAiLCJvd25lciI6ImFub255bW91cyIsImFjdGlvbiI6Im9wZW5SZXF1ZXN0In0.eLHZjnFduufVspUz7E2QfTzKFfPqNWYBoENJbmIeZtA";

    //private $logPathLocation = $this->config->logPath->location;


 	private function getLogFile($action="")
    {

          //define('APP_PATH', realpath(''));

          /**
           * Read the configuration
           */
          $config = include APP_PATH . "/app/config/config.php";

        $logPathLocation = $config->logPath->location;
        switch ($action) {
        case 'success':
          return $logPathLocation.'response_logs.log';
          break;
        case 'error':
          return $logPathLocation.'error_logs.log';
          break;
        default:
          return $logPathLocation.'apicalls_logs.log';
          break;
      }
        
    }  

    public function rawSelect($statement)
       { 
          $connection = $this->di->getShared("db"); 
          $success = $connection->query($statement);
           $success->setFetchMode(Phalcon\Db::FETCH_ASSOC); 
          $success = $success->fetchAll($success); 
          return $success;
       }
    public function calculateTotalPages($total,$per_page){
       $totalPages = (int)($total/$per_page);
       if(($total % $per_page) > 0){
          $totalPages = $totalPages + 1; 
       }

       return $totalPages;
    }

    public function composePushLog($type,$description,$resolution){//($data,$title,$body,$userID){
         $data = array();
         $data["origin"] = "Envirofit apis";
         $data["description"] = $description;
         $data["resolution"] = $resolution;
         $data["alertTime"] = date("d-m-Y H:i:s");
         $data["status"] = 0;
         $data["type"] = $type;
         $title = "Envirofit api notification";
         $body = $type." notification";

         $userID = array();
         $id["userId"] = 111;
         array_push($userID, $id); 
         $appName="com.james.southwelservicemonitor";
         $this->sendAndroidPushNotification($data,$title,$body,$userID,$appName);

       //   {"appName":"com.james.southwelservicemonitor","body":"body","title":"title","data":{"origin":"Payments", "description":"Connection timout", "resolution" :"Service stopped. Piga nduru ",  "alertTime":"12-13-2017 13:24:12","status":0,  "type":"Error"}, "users":[{"userId":"111"}]}
    }




 	
 	public function success($message,$data){
        $file = $this->config->senderIds->mediamax;
       
    	
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");
       /* $success = array();
        $sucess["code"] = 201;
        $success["success"]=$message;
        $success["data"]=$data;
        $response->setStatusCode(201, "SUCCESS");*/
        $success["success"]=$message;
        $success["data"]=$data;
        $success["code"] = 201;

        $response->setContent(json_encode($success));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log($file.' ty '.$message.' '.json_encode($data));
        $this->composePushLog("success  ".$message,$data);
       
        return $response;
    }

  public function successFromData($data){
      
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");

        $response->setContent(json_encode($data));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log($message.' '.json_encode($data));
        $this->composePushLog("success","from data".$this->config->logPath->location,$data);
       
        return $response;
    }

    public function getSalesSuccess($data){
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");
        $success = array();
        $response->setContent(json_encode($data));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log(' '.json_encode($data));
        $this->composePushLog("success","get order success",json_encode($data));
        return $response;
    }



     /* formats page not found response messages */    
	public function notFound($message,$data){
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
         $error = array();
        $error["error"]=$message;
        $error["data"]=$data;
        $error["code"] = 404;
        $response->setStatusCode(404, "NOT FOUND");
        $response->setContent(json_encode($error));
        
         $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log($message.' '.json_encode($data).$this->config->logPath->location);

        $this->composePushLog("error","NOT FOUND ".$message," ".json_encode($data));
        return $response;
    }




    /* formats validation error response messages */   
    public function unProcessable($message,$data){
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $error = array();
        $error["error"]=$message;
        $error["data"]=$data;
        $response->setStatusCode(422, "UNPROCESSABLE ENTITY");
        $response->setContent(json_encode($error));

         $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log($message.' '.json_encode($data));
        $this->composePushLog("error","UNPROCESSABLE ".$message," ".json_encode($data)); 

        return $response;
    }

    /* formats data error response messages */   
    public function dataError($message,$data){
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $error["error"]=$message;
        $error["data"]=$data;
        $error["code"] = 421;
        $response->setStatusCode(421, "DATA ERROR");
        $response->setContent(json_encode($error));

        $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log($message.' '.json_encode($data));
        $this->composePushLog("error","DATA ERROR ".$message," ".$data);

        return $response;
    }

    

   public function sendMessage($msisdn,$message) 
      {
                $postData = array(
                "sender" => "EnvirofitKE",
                "recipient" => $msisdn,
                "message" => $message
              );
               
                $channelAPIURL = "api.southwell.io/fastSMS/public/api/v1/messages";
                $username = "faith.wanjiku@envirofit.org";
                $password = "envirofit1234";


                $httpRequest = curl_init($channelAPIURL);
                curl_setopt($httpRequest, CURLOPT_NOBODY, true);
                curl_setopt($httpRequest, CURLOPT_POST, true);
                curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
                curl_setopt($httpRequest, CURLOPT_TIMEOUT, 10);
                curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
                        'Content-Length: ' . strlen(json_encode($postData))));
                    curl_setopt($httpRequest, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    curl_setopt($httpRequest, CURLOPT_USERPWD, "$username:$password");
                    $postresponse = curl_exec($httpRequest);
                    $httpStatusCode = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE); //get status code
                    curl_close($httpRequest);

                    
                       
                     $response = array(
                        'httpStatus' => $httpStatusCode,
                        'response' => json_decode($postresponse)
                    );

                      $logger = new FileAdapter($this->getLogFile());
                    $logger->log($message.' '.json_encode($response));

                    return $response;

        }

    private function sendAndroidPushNotification($data,$title,$body,$userID,$appName){
        $logger = new FileAdapter($this->getLogFile());

    
        $jsonPayload = array(); 
        $url;
        if(!$userID){
            $url = "http://api.southwell.io/mobile_devices_v1/push/broadcast/$appName";
         
           $jsonPayload = array("appName"=>$appName,
                              "body"=>$body,
                               "title"=>$title,
                               "data"=>$data);
        }

        else{
            $url= "http://api.southwell.io/mobile_devices_v1/push/broadcast/$appName";

            $jsonPayload = array("appName"=>$appName,
                              "body"=>$body,
                               "title"=>$title,
                               "data"=>$data,
                               "users"=>$userID);
        }
       
         
        $headers = array(
         'Content-Type:application/json'
        );
            
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_POST, 1);
         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonPayload));
         $result = curl_exec($ch);

         curl_close ($ch);

         if($result === true){
             $logger->log("Push notification sent SUCCESS ".$result);
             return $result;
         }
         else{
             $logger->log("Push notification sent FAILED ".$result);
             return $result;
         }
    }

    public function sendPushNotification($data,$title,$body,$userID){
       
        $appName = "com.southwell.envirofitsalesapp"; //for test
       return $this->sendAndroidPushNotification($data,$title,$body,$userID,$appName);
                 
    }


  public  function formatMobileNumber($mobile) 
   { 
      $mobile = preg_replace('/\s+/','',$mobile); 
      $input = substr($mobile, 0, -strlen($mobile)+1); 
      $number = ''; 
         if ($input == '0') 
            {
              $number = substr_replace($mobile, '254', 0, 1); 

              return $number; 
              }
              elseif ($input == '+') 
              {
               $number = substr_replace($mobile, '', 0, 1);
              } 
              elseif ($input == '7') 
              {
               $number = substr_replace($mobile, '2547', 0, 1); 
               } 
              else{ 
              $number = $mobile; 
              } 
            return $number; 
     }

     protected function mobile($number) { 
         $regex = '/^(?:\+?(?:[1-9]{3})|0)?7([0-9]{8})$/'; 
         if (preg_match_all($regex, $number, $capture)) { 
          $msisdn = '2547' . $capture[1][0]; 
        } 
        else{ 
          $msisdn = false;
           } 
           return $msisdn; }

 }        
