<?php
require('vendor/autoload.php');

if (!isset($_REQUEST)) {
  return;
}

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
    $user_id = $data->object->user_id;
    $text = $data->object->body;

    $current_len = $redis_client->llen($text);

    // Compose and send result message
    $request_params = array(
      'message' => $current_len,
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
