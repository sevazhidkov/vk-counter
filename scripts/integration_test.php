<?php
if ($argc !== 3) {
    echo "Usage: php scripts/integration_test.php [keys] [amount-per-key].\n";
    exit(1);
}

$keys = $argv[1];
$amount = intval($argv[2]);

$stats_sum = 0.0;
$current_time = time();
for ($i = 0; $i < $keys; $i++) {
  $key = 'unexpired_key' . $i;
  for ($j = 0; $i < $amount; $j++) {
    $start = microtime(true);
    $postData = array(
        'type' => 'message_new',
        'object' => array(
          'user_id' => -1,
          'body' => $key
        ),
    );
    $ch = curl_init('https://www.googleapis.com/blogger/v3/blogs/'.$blogID.'/posts/');
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POSTFIELDS => json_encode($postData)
    ));

    $stats_sum += microtime(true) - $start;
  }
}
echo "Total execution time for adding new texts: \n" . strval($stats_sum);
echo "Average execution time per add :\n" . strval($stats_sum / $amount);