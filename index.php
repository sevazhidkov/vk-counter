<?php
require('vendor/autoload.php');

$MESSAGE_COUNT_LIMIT = 5000;
$MESSAGE_LENGTH_LIMIT = 1000;
$USER_REQUESTS_PER_HOUR = 120;

if (!isset($_REQUEST)) {
  return;
}

// Get tokens and access data from environment
$confirmation_token = getenv('CONFIRMATION_TOKEN');
$secret_key = getenv('SECRET_KEY');
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
    if ($data->secret != $secret_key) {
      echo "bad secret token";
      break;
    }

    $current_time = time();
    $user_id = $data->object->user_id;
    $text = $data->object->body;

    if (mb_strlen($text) > $MESSAGE_LENGTH_LIMIT) {
      echo('ok');
      break;
    }

    $user_count = $redis_client->incr(strval($user_id));
    if ($user_count > $USER_REQUESTS_PER_HOUR and $user_id != -1 and $user_id != -2) {
      echo('ok');
      break;
    } elseif ($user_count == 1) {
      // If it's the first user message in last hour, set a timeout for Redis key
      $redis_client->expire(strval($user_id), $current_time + 60*60);
    }

    if (is_null($text) or $text == '') {
      $request_params = array(
        'message' => 'Я понимаю только текстовые сообщения :)',
        'user_id' => $user_id,
        'access_token' => $token,
        'v' => '5.0'
      );
      $get_params = http_build_query($request_params);
      file_get_contents('https://api.vk.com/method/messages.send?'. $get_params);
      echo('ok');
      break;
    }

    $text = 'message#' . $text;

    $cache_interval = 24 * 60 * 60;
    // For stress-testing
    if ($user_id == -2) {
      $cache_interval = 60;
    }

    $message_frequency = intval($redis_client->llen($text));
    if ($message_frequency == 0) {
      $result_len = 0;
    } elseif ($message_frequency > $MESSAGE_COUNT_LIMIT) { // Flood detection
      echo 'ok';
      break;
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
        if ($current_timestamp > $current_time - $cache_interval) {
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

    // For stress testing we shouldn't send message to vk servers
    if ($user_id == -1 or $user_id == -2) {
      break;
    }

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
