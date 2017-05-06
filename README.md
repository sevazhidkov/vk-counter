# VK Counter
Бот должен на любое сообщение пользователя отвечать числом – сколько раз уже получал такое сообщение за эти сутки (суммарно от всех пользователей).

## Используемые технологии

Тестировался на PHP 7.0, не должно возникнуть проблем на других версиях;
зависимости через Composer

Redis и коннектор Predis (использует ООП, возможно переписать на голый TCP/использование функциональной библиотеки)

## Алгоритм

Для каждого текста сообщения будем хранить список int, каждое из которых
\- timestamp, в который было отправлено сообщение с таким текстом. Мы должны будем
*стремиться*, чтобы там были timestamp'ы только за прошедшие 24 часа.

При получении нового сообщения, мы проводим нормализацию
текущего списка timestamp'ов для данного текста, выводим количество элементов
в получившемся списке и добавляем в него новый timestamp.

Последовательность действий:
1) Смотрим длину списка timestamp'ов для данного текста, если она равна нулю,
возвращаем 0, переходим к п. 4
2) Иначе делаем операцию LPOP (получить и удалить крайний левый элемент из списка)
до тех пор, пока получаемый элемент не станет больше, чем текущее время. Как только
мы дошли до него, возвращаем его с помощью операции LPUSH.
3) Возвращаем новую длину списка, которую можно посчитать как l1 - l2,
где l1 - длина изначального списка, l2 - количество элементов, которые мы удалили
и не вернули с помощью LPUSH
4) Добавляем новый timestamp в список для данного текста с помощью операции LPUSH

```php
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
    if ($current_timestamp > $current_time - 60 * 60 * 24) {
      $checked = true;
      $redis_client->lpush($text, $current_timestamp);
    } else {
      $expired += 1;
    }
  }
  $result_len = $message_frequency - $expired;
}

// Save current time for future use
$redis_client->rpush($text, $current_time);```

## Оценка эффективности

### Теоретическая

### Стресс-тест

## Узкие места

### Время

### Память
