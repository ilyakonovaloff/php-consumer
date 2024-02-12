<?php
declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/../vendor/autoload.php';

use Predis\Client;

const USER_QUEUE_NAME = 'user_events_queue_';

$redisHost = getenv('REDIS_HOST');
if ($redisHost === false) {
    echo "REDIS_HOST environment variable is not set\n";
    exit(1);
}

$redis = new Client([
    'host' => $redisHost,
    'read_write_timeout' => -1,
]);

function isQueueEmpty(string $queueName): bool
{
    global $redis;

    return $redis->llen($queueName) === 0;
}

/**
 * @param string[] $userIdsWithEvents
 */
function updateQueues(array &$userIdsWithEvents, $chunkSize): void
{
    global $redis;

    $userIdsWithEvents = [];
    $userEventsQueues  = $redis->keys(USER_QUEUE_NAME . '*');

    if (empty($userEventsQueues)) {
        return;
    }

    foreach ($userEventsQueues as $userEventsQueue) {
        if (!isQueueEmpty($userEventsQueue)) {
            $userIdsWithEvents[] = substr($userEventsQueue, strlen(USER_QUEUE_NAME));
        }
        if(count($userIdsWithEvents) >= $chunkSize) {
            break;
        }
    }
}

/**
 * @param string[] $userIdsWithEvents
 */
function runConsumers(array $userIdsWithEvents): void
{
    foreach ($userIdsWithEvents as $userId) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            echo "Error forking process\n";
            exit(1);
        } elseif ($pid === 0) {
            $path =  __DIR__ . '/consumer.php';
            exec(sprintf("(php %s %s) > /dev/null 2>&1", $path, $userId));
            exit(0);
        }
    }

    while (pcntl_waitpid(-1, $status, WNOHANG) > 0) {
        continue;
    }
}

$userIdsWithEvents = [];
$chunkSize = (int)getenv('CHUNK_SIZE') === 0 ? 100 : (int)getenv('CHUNK_SIZE');
while (true) {
    updateQueues($userIdsWithEvents, $chunkSize);
    runConsumers($userIdsWithEvents);
    sleep(10);
}
