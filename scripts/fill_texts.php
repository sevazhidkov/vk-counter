<?php
require('vendor/autoload.php');

$redis_url = getenv('REDIS_URL');
$redis_client = new Predis\Client($redis_url);

if ($argc !== 3) {
    echo "Usage: php scripts/fill_redis.php [keys] [amount-per-key].\n";
    exit(1);
}

$keys = $argv[1];
$amount = intval($argv[2]);
$stats_sum = 0.0; // Store sum of times of execution for each push.

$current_time = time();
for ($i = 0; $i < $keys; $i++) {
  $start = microtime(true);
  $redis_client->rpush($key, $current_time);
  $stats_sum += microtime(true) - $start;
}

$current_time = time();
for ($i = 0; $i < $amount; $i++) {
  $start = microtime(true);
  $redis_client->rpush($key, $current_time);
  $stats_sum += microtime(true) - $start;
}

echo "Average execution time per RPUSH:\n" + strval($stats_sum / $amount);
