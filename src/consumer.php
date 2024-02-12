<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2) {
    echo "Usage: php consumer.php <userId>";
    exit(1);
}

use Predis\Client;

$redisHost = getenv('REDIS_HOST');
$debugMode = (bool)getenv('DEBUG_MODE');
$writeToFile = (bool)getenv('WRITE_TO_FILE');

if ($redisHost === false) {
    logger("REDIS_HOST environment variable is not set");
    exit(1);
}

$redis = new Client([
    'host' => $redisHost,
    'read_write_timeout' => -1,
]);

$userId = $argv[1];

logger("Consumer for User ID $userId started.");

$lockKey      = "queue_lock_$userId";
$lockAcquired = $redis->set($lockKey, 1, 'EX', 1000, 'NX');
if ($lockAcquired === null) {
    logger("Another consumer is processing events for User ID $userId. Skipping.");
    exit(0);
}

$queueName = "user_events_queue_$userId";
$eventsConsumed = 0;
$fairConsuming = (int)getenv('FAIR_CONSUMING');
$emulateEventProcess = (bool)getenv('EMULATE_EVENT_PROCESS');
while (true) {
    logger("Consumer for User ID $userId processing.");
    $userEvent = $redis->blpop([$queueName], 1);
    if (!is_array($userEvent) || !isset($userEvent[1])) {
        logger("No message received within the timeout. Terminating consumer for User ID $userId.");
        break;
    }
    $userEvent = $userEvent[1];

    $data    = json_decode($userEvent, true);
    $eventId = $data['event_id'];

    logger("Processing event for User ID $userId, Event ID $eventId");
    if ($emulateEventProcess) {
        sleep(1);
    }
    writeEventId($eventId);
    logger("Event processed for User ID $userId, Event ID $eventId");

    if ($fairConsuming !== 0 && $fairConsuming <= ++$eventsConsumed) {
        logger("Consumer reached the limit of consumed events. Terminating consumer for User ID $userId.");
        break;
    }
}

$redis->del($lockKey);
logger("Consumer for User ID $userId finished.");
exit(0);

function logger($message): void
{
    global $userId;
    global $debugMode;

    if (!$debugMode) {
        return;
    }

    $pid = getmypid();
    $message = (new DateTimeImmutable())->format('Y-m-d H:i:s.u') . " - $message - $pid" . PHP_EOL;
    $name    = getenv('SINGLE_LOG_FILE') ? '' : '_' . $userId;
    file_put_contents(__DIR__ . "/../data/user_log$name.log", $message, FILE_APPEND);
}

function writeEventId($eventId): void
{
    global $userId;
    global $writeToFile;

    if (!$writeToFile) {
        return;
    }

    file_put_contents(__DIR__ . "/../data/user_events_$userId.log", "$eventId" . PHP_EOL, FILE_APPEND);
}
