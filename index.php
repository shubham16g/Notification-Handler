<?php
header('Content-Type: application/json');

const ONE_SIGNAL_REST_API_KEY = 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz';
const APP_ID = 'zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz';

$db = new mysqli('localhost', 'root', '', 'smart');

include './class.NotificationHandler.php';

$notifHandler = new NotificationHandler($db, ONE_SIGNAL_REST_API_KEY, APP_ID);

try {
    // $res = $notifHandler->sendNotification([
        // 'headings' => ['en' => 'This is the title'],
        // 'contents' => ['en' => 'This is the body']
    // ], 'uid:kji9ejlwkalksufjkewsj');

    $res = $notifHandler->getNotificationList(['all', 'uid:kji9ejlwkalksufjkewsj']);

    $notificatonsCount =  $notifHandler->getNotificationsCount(['all', 'uid:kji9ejlwkalksufjkewsj']);
    echo json_encode([
        'pagesCount' => $notifHandler->getPagesCount($notificatonsCount),
        'notificationsCount' => $notificatonsCount,
        'notifications' => $res,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo $e->getMessage();
}
