<?php
require('../vendor/autoload.php');

$redis_url = getenv('REDIS_URL');
$redis_client = new Predis\Client($redis_url);

if ($argc !== 3) {
    echo "Usage: php scripts/fill_redis.php [key] [amount].\n";
    exit(1);
}

$key = $argv[1];
$amount = intval($argv[2]);

$current_time = time();
for ($i = 0; $i < amount; $i++) {
  $redis_client->rpush($key, $current_time);
}
