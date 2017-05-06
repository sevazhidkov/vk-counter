<?php
require('vendor/autoload.php');

if (!isset($_REQUEST)) {
  return;
}

$CACHE_INTERVAL = 24 * 60 * 60 // in seconds

// Get tokens and access data from environment
$confirmation_token = getenv('CONFIRMATION_TOKEN');
$token = getenv('TOKEN');
$redis_url = getenv('REDIS_URL');

// Connect to Redis
$redis_client = new Predis\Client($redis_url);

// Get POST data in JSON
$data = json_decode(file_get_contents('php://input'));

// Request typy
switch ($data->type) {
  // Confirmation request from VK
  case 'confirmation':
    echo $confirmation_token;
    break;

  // New incoming message
  case 'message_new':
    $current_time = time();
    $user_id = $data->object->user_id;
    $text = $data->object->body;

    $message_frequency = intval($redis_client->llen($text));
    if ($message_frequency == 0) {
      $result_len = 0;
    } else {
      $checked = false; // Turn to true, when we'll pass all expired messages
      $expired = 0;
      while (!$checked) {
        $current_timestamp = $redis_client->lpop($text);
        // If there's no timestamps left, we should stop and return 0
        if (is_null($current_timestamp)) {
          break;
        }
        // If message have been sent less than 24 hours ago, stop checking
        if ($current_timestamp > $current_time - $CACHE_INTERVAL) {
          $checked = true;
          $redis_client->lpush($text, $current_timestamp);
        } else {
          $expired += 1;
        }
      }
      $result_len = $message_frequency - $expired;
    }

    // Save current time for future use
    $redis_client->rpush($text, $current_time);

    // Compose and send result message
    $request_params = array(
      'message' => $result_len,
      'user_id' => $user_id,
      'access_token' => $token,
      'v' => '5.0'
    );
    $get_params = http_build_query($request_params);
    file_get_contents('https://api.vk.com/method/messages.send?'. $get_params);

    // VK requires to return 'ok' string
    echo('ok');
    break;
}
