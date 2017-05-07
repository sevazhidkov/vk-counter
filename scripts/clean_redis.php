<?php
require('vendor/autoload.php');

use Predis\Collection\Iterator;

$redis_url = getenv('REDIS_URL');
$redis_client = new Predis\Client($redis_url);

$delete_pattern = '*';

foreach (new Iterator\Keyspace($redis_client, $delete_pattern) as $key) {
  $redis_client.del($key);
}
