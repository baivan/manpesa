<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use \Firebase\JWT\JWT;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Logger\Adapter\File as FileAdapter;

class ActivityLogsController extends Controller
{

	 /*
    Raw query select function to work in any version of phalcon
    */
    protected function rawSelect($statement) {
        $connection = $this->di->getShared("db");
        $success = $connection->query($statement);
        $success->setFetchMode(Phalcon\Db::FETCH_ASSOC);
        $success = $success->fetchAll($success);
        return $success;
        
    }



    public function create($userID,$activity,$longitude=0,$latitude=0)
    {
    	$res = new SystemResponses();
     
        $res->success("Creating log userID $userID $activity");

        $activityLogs = new ActivityLogs();
        $activityLogs->userID = $userID;
        $activityLogs->longitude = $longitude;
        $activityLogs->latitude = $latitude;
        $activityLogs->action = $activity;
        $activityLogs->createdAt = date("Y-m-d H:i:s");
        
        if ($activityLogs->save() === false) {
	                $errors = array();
	                $messages = $activityLogs->getMessages();
	                foreach ($messages as $message) {
	                    $e["message"] = $message->getMessage();
	                    $e["field"] = $message->getField();
	                    $errors[] = $e;
	                }
	           $res->dataError("Error adding activity logs ".json_encode($errors));
	              //  $dbTransaction->rollback('activityLogs create failed' . json_encode($errors));
            }
           // $dbTransaction->commit();
           return true;
    }

}

