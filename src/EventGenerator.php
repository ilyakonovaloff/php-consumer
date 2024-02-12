<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

$redisHost = getenv('REDIS_HOST');
if ($redisHost === false) {
    echo "REDIS_HOST environment variable is not set\n";
    exit(1);
}

$redis = new Client([
    'host' => $redisHost
]);

$usersCount = (int)getenv('USERS_COUNT');
$eventsPerUserCount = (int)getenv('EVENTS_PER_USER_COUNT');

function generateEvents($userId, $redis, $eventsPerUserCount): void
{
    $queueName = "user_events_queue_$userId";

    for($i = 1; $i <= $eventsPerUserCount; $i++) {
        $eventData = [
            'user_id' => $userId,
            'event_id' => $i,
        ];

        $redis->rpush($queueName, json_encode($eventData));
    }
}

for ($i = 1; $i <= $usersCount; $i++) {
    $userId = uniqid('user_');

    $pid = pcntl_fork();

    if ($pid == -1) {
        die("Error forking process.");
    } elseif ($pid == 0) {
        generateEvents($userId, $redis, $eventsPerUserCount);
        exit();
    }
}

while (pcntl_waitpid(0, $status) != -1) {
    $status = pcntl_wexitstatus($status);
    echo "Child process completed.\n";
}
