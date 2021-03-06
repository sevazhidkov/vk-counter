<?php
if ($argc !== 3) {
    echo "Usage: php scripts/integration_test.php [keys] [amount-per-key].\n";
    exit(1);
}

$keys = $argv[1];
$amount = intval($argv[2]);

$stats_sum = 0.0;
for ($i = 0; $i < $keys; $i++) {
  $key = 'unexpired_key' . $i;
  for ($j = 0; $j < $amount; $j++) {
    $start = microtime(true);
    $postData = array(
        'type' => 'message_new',
        'object' => array(
          'user_id' => -1,
          'body' => $key
        ),
    );
    $ch = curl_init('https://vk-counter.herokuapp.com/');
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POSTFIELDS => json_encode($postData)
    ));
    $response = curl_exec($ch);
    if($response === FALSE){
        die(curl_error($ch));
    }
    $stats_sum += microtime(true) - $start;
  }
}
echo "Total execution time for adding new texts: \n" . strval($stats_sum) . "\n";
echo "Average execution time per add: \n" . strval($stats_sum / ($keys * $amount)) . "\n";

sleep(10);


$stats_sum = 0.0;
for ($i = 0; $i < $keys; $i++) {
  $key = 'unexpired_key' . $i;
  $start = microtime(true);
  $postData = array(
      'type' => 'message_new',
      'object' => array(
        'user_id' => -2, // change interval from 24 hours to 1 minute
        'body' => $key
      ),
  );
  $ch = curl_init('https://vk-counter.herokuapp.com/');
  curl_setopt_array($ch, array(
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POSTFIELDS => json_encode($postData)
  ));
  $response = curl_exec($ch);
  if($response === FALSE){
      die(curl_error($ch));
  }
  $stats_sum += microtime(true) - $start;
}
echo "Total execution time for deleting texts: \n" . strval($stats_sum) . "\n";
echo "Average execution time per delete: \n" . strval($stats_sum / $keys) . "\n";
