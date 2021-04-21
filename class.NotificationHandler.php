<?php



class NotificationHandler
{
    public const ERROR_MYSQLI_QUERY_MSG = 'Error in mysqli query';
    public const ERROR_MYSQLI_QUERY_CODE = 964;
    public const ERROR_MYSQLI_CONNECT_MSG = 'Error in mysqli connection';
    public const ERROR_MYSQLI_CONNECT_CODE = 458;


    function __construct(mysqli $db, string $restApiKey, string $appID, array $defaultParams = null)
    {
        $this->auth_header = $auth_header = array(
            'Content-Type: application/json; charset=utf-8',
            "authorization: Basic " . $restApiKey,
        );
        $this->APP_ID = $appID;
        $this->defaultParams = $defaultParams;


        $createNotificationTable = "CREATE TABLE IF NOT EXISTS `notifications_data` (
            `notificationID` VARCHAR(255) NOT NULL ,
            `notification` JSON NOT NULL ,
            `aggregation` VARCHAR(255) NOT NULL ,
            `createdAt` DATETIME NOT NULL ,
            PRIMARY KEY (`notificationID`)
            );";

        if ($db->connect_errno) {
            throw new Exception(self::ERROR_MYSQLI_CONNECT_MSG, self::ERROR_MYSQLI_CONNECT_CODE);
        }
        $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, TRUE);

        $res = $db->query($createNotificationTable);
        $this->db = $db;
    }

    public function sendNotification(array $fields, string $aggregation = 'all')
    {
        if (!isset($fields['included_segments'])) {
            $fields['included_segments'] = ['Subscribed Users'];
        }
        if ($this->defaultParams != null) {
            $newFields = array_merge($this->defaultParams, $fields);
        } else {
            $newFields = $fields;
        }

        $newFields['app_id'] = $this->APP_ID;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://onesignal.com/api/v1/notifications",
            CURLOPT_HTTPHEADER => $this->auth_header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => json_encode($newFields),
        ));
        $response = (array) json_decode(curl_exec($curl));
        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl), curl_errno($curl)); 
        }       
        curl_close($curl);

        if ($aggregation == null || $aggregation == '') {
            $aggregation = 'all';
        }

        $notificationID = $response['id'];
        $notification = json_encode($fields);
        $createdAt = $this->_getCurrentTimeForMySQL();


        $q = "INSERT INTO `notifications_data` (`notificationID`, `notification`, `aggregation`, `createdAt`)
        VALUES ('$notificationID', '$notification', '$aggregation', '$createdAt')";

        if (!$this->db->query($q)) {
            throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_MYSQLI_QUERY_CODE);
        }
        
        return $response;
    }

    public function getNotificationList(array $aggregations = null, int $page = 1, int $notificationsPerPage = 20)
    {
        $offset = ($page - 1) * $notificationsPerPage;

        $whereStatement = $this->_getWhereAggregation($aggregations);

        $q = "SELECT * FROM `notifications_data` $whereStatement";
        $q .= " ORDER BY createdAt DESC LIMIT $offset, $notificationsPerPage";

        $res = $this->db->query($q);
        if (!$res) {
            throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_MYSQLI_QUERY_CODE);
        }
        $returnArray = [];
        while ($row = $res->fetch_assoc()) {
            $row['notification'] = (array) json_decode($row['notification']);
            $returnArray[] = $row;
        }
        return $returnArray;
    }

    public function getNotificationsCount(array $aggregations = null)
    {
        $whereStatement = $this->_getWhereAggregation($aggregations);

        $query = "SELECT count(*) FROM `notifications_data` $whereStatement";
        $res = $this->db->query($query);
        if (!$res) {
            throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_MYSQLI_QUERY_CODE);
        }
        if ($row = $res->fetch_assoc()) {
            return $row['count(*)'];
        } else {
            throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_MYSQLI_QUERY_CODE);
        }
    }
    public function getPagesCount($notificationsCount, int $notificationsPerPage = 20)
    {
        return ceil($notificationsCount / $notificationsPerPage);
    }

    private function _getWhereAggregation(array $aggregations = null)
    {
        if ($aggregations == null || $aggregations == []) {
            return '';
        }
        $whereStatement = "WHERE ";
        $first = true;
        foreach ($aggregations as $value) {
            if (!$first) {
                $whereStatement .= "OR ";
            }
            $first = false;
            $whereStatement .= "aggregation = '$value' ";
        }
        return $whereStatement;
    }

    private function _getCurrentTimeForMySQL()
    {
        return date('Y-m-d H:i:s', time());
    }
}
