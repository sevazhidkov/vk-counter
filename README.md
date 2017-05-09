# VK Counter
Бот должен на любое сообщение пользователя отвечать числом – сколько раз уже получал такое сообщение за эти сутки (суммарно от всех пользователей).

## Используемые технологии

Тестировался на PHP 7.0, не должно возникнуть проблем на других версиях;
зависимости через Composer

Redis и коннектор для PHP Predis (использует ООП, возможно переписать на голый TCP/использование функциональной библиотеки).

Прототип развернут на Heroku.

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
мы дошли до него, возвращаем его обратно с помощью операции LPUSH.
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
$redis_client->rpush($text, $current_time);
```

## Оценка эффективности

Ожидается, что количество пользователей и, соотвественно, различных текстов,
будет больше, чем количество повторений каждого текста, поэтому я решил,
что хранить для каждого текста список вхождений - приемлимо.

Кроме того, много вхождений может появиться для популярного текста, а если он
популярен, то и пересчитывается он чаще, поэтому для обычного пользователя
запрос будет считаться за 2 запроса к Redis (LPOP, LPUSH), а лишь для некоторых
будет необходимость делать несколько LPOP - и не слишком много, за счет частоты.

Для редких же текстов, список может дольше пересчитываться, но за счет редкости
в списке не будет много значений. Win win.

### Теоретическая

Получить количество и нормализовать список: О(N), N - количество сообщений с
данным текстом за 24 часа от момента последнего запроса перед новым

Лучший случай: O(1), первый элемент в списке сделан в пределах 24 часов от текущего времени

Худший случай: O(N), все N элементов в списке сделаны раньше, чем 24 часа назад от текущего времени

LPOP, LPUSH, RPUSH - работают за O(1)

### Стресс-тест

Я написал несколько скриптов для проверки скорости работы приложения и Redis.
Их все можно посмотреть в _/scripts_.

_clean_redis.php_ - сервисный скрипт, очищает Redis. Можно использовать между запусками
других скриптов.

_fill_redis.php [key] [amount]_ - заполняет key текущим временем amount раз. Можно использовать,
для того, чтобы тестировать лучшие случаи для алгоритма.

_fill_redis_expired.php [key] [amount]_ заполняет key временем 24 часа назад amount раз.
Можно использовать для того, чтобы тестировать худшие случаи для алгоритма.

_fill_redis_keys.php [keys]_ заполняет keys ключей по одному сообщению. Проверка на работу
Redis с большим количеством ключей.

_integration_test.php [keys] [amount-per-key]_ - это интеграционный тест,
он отправляет запрос к серверу, симуляруя VK Callback. Он заполняет keys ключей
(различных текстов), сохраняя amount-per-key timestamp'ов. Затем он запрашивает
по разу каждый из ключей, проверяя возможность timestamp'ов удаляться - для
интеграционного теста время хранения снижается с 24 часов до 60 секунд
(за минуту все ключи должны добавиться).


__Результаты__:

Heroku Hobby Dyno c 512 МБ RAM, часть общего CPU, который и стал верхним лимитом -
использование было на 100% +
базовый Redis с 52 МБ оперативной памяти. При стресс-тестировании сообщения не отправлялись.

Интеграционные тесты с имитацией запросов VK Callback API показали, что сервер
*за минуту* принимает 3000 различных запросов-сообщений на добавление в любой пропорции, тестировалось на:
1) 300 различных текстов по 10 вхождений - 60 секунд, 20 мс на каждый запрос
2) 3000 различных текстов по 1 вхождению - 56 секунд, 18 мс на каждый запрос

Для expired запросов, запросов на удаление, тратится:
1) 71 секунда на удаление 3000 ключей с
одним вхождением, по 23 мс на ключ
2) 9 секунд на удаление 300 ключей с 10 вхождениями, по 30 мс на ключ

Это работает в разы лучше поставленных условий, с оставленными полями для оптимизаций.

Кроме того, Redis хорошо работает на большом количестве ключей.

## Возможные улучшения

В случаях, где N большое, значительная часть работы алгоритма уходит на
передачу данных между Redis и сервером. Для того, чтобы ускорить этот процесс,
можно переписать основной цикл на Lua, сделав его хранимой процедурой в Redis.

Кроме того, возможно подойти к этой проблеме с другой стороны и постараться избежать
большого N: дополнительно к основной проверке сделать воркер,
который постоянно обходит ключи и удаляет сделанные более, чем 24 назад.
