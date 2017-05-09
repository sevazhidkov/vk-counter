<?php
require('vendor/autoload.php');

$redis_url = getenv('REDIS_URL');
$redis_client = new Predis\Client($redis_url);

if ($argc !== 2) {
    echo "Usage: php scripts/fill_redis_keys.php [keys].\n";
    exit(1);
}

$keys = $argv[1];
$stats_sum = 0.0; // Store sum of times of execution for each push.

$current_time = time();
for ($i = 0; $i < $keys; $i++) {
  $start = microtime(true);
  $key = 'key' . $i;
  $redis_client->rpush($key, $current_time);
  $stats_sum += microtime(true) - $start;
}

echo "Total execution time: \n" . time() - $current_time;
echo "Average execution time per RPUSH: \n" . $stats_sum / $keys;
