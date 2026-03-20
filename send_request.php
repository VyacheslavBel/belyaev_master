<?php
// Файл: send_request.php
// Обработчик заявок с отправкой в личные сообщения ВКонтакте

// ===== НАСТРОЙКИ =====
// Вставьте сюда свои данные (получить можно по инструкции выше)
define('VK_COMMUNITY_TOKEN', 'vk1.a.3E0ESwjqAcSWAaJU06yXvDDT1wLR1c1U7junxxwA40EtUn4oO9bv3_KrOCHMUqutiDk7wLQBmlCb76MM5mjvl9fzq-0_dSTTIeA-U_j-tXku5pQqsAH8MIlRFLfcC7-LQoRwXVabinCiaXsCokbdCo4v5N8k7_Oe6wFJsjNrmI_VXw-CP86xjVh_umamkCGKblaADMbJPOfyLUZCDEQoiQ'); // Токен сообщества с правом messages
define('YOUR_VK_USER_ID', '521132592');       // Ваш цифровой ID (например, 123456789)

// Настройки для редиректа после отправки
define('SUCCESS_PAGE', 'index.html?success=1'); // Куда перенаправить при успехе
define('ERROR_PAGE', 'index.html?error=1');     // Куда при ошибке
// =====================

// Функция для логирования ошибок
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, __DIR__ . '/vk_errors.log');
}

// Функция отправки сообщения в ВК
function sendVkMessage($userId, $messageText) {
    $token = VK_COMMUNITY_TOKEN;
    $randomId = time() . rand(1, 1000); // Уникальный ID
    $version = '5.199';
    
    $params = [
        'user_id' => $userId,
        'random_id' => $randomId,
        'message' => $messageText,
        'access_token' => $token,
        'v' => $version
    ];
    
    $url = 'https://api.vk.com/method/messages.send?' . http_build_query($params);
    
    // Используем file_get_contents с контекстом для таймаута
    $context = stream_context_create([
        'http' => [
            'timeout' => 10 // Таймаут 10 секунд
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        logError('Ошибка соединения с VK API');
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        logError('VK API Error: ' . print_r($data['error'], true));
        return false;
    }
    
    return true;
}

// ===== ОСНОВНАЯ ЛОГИКА =====
// Получаем данные из POST-запроса
$name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
$phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$comment = isset($_POST['comment']) ? trim(strip_tags($_POST['comment'])) : '';
$source = isset($_POST['source']) ? trim(strip_tags($_POST['source'])) : 'неизвестно';

// Проверяем обязательные поля
if (empty($phone)) {
    // Телефон обязателен
    header('Location: ' . ERROR_PAGE . '&reason=empty_phone');
    exit;
}

// Формируем текст сообщения
$message = "🔔 НОВАЯ ЗАЯВКА С САЙТА\n";
$message .= "📌 Источник: " . $source . "\n";
$message .= "───────────────────\n";
$message .= "👤 Имя: " . ($name ?: 'не указано') . "\n";
$message .= "📞 Телефон: " . $phone . "\n";
$message .= "💬 Комментарий: " . ($comment ?: 'не указан') . "\n";
$message .= "🕐 Время: " . date('d.m.Y H:i');

// Отправляем в ВК
$sent = sendVkMessage(YOUR_VK_USER_ID, $message);

// Дополнительно сохраняем заявку локально (в localStorage мы сохраняли через JS)
$local_backup = date('Y-m-d H:i:s') . " | {$source} | {$name} | {$phone} | {$comment}" . PHP_EOL;
file_put_contents(__DIR__ . '/requests_backup.txt', $local_backup, FILE_APPEND | LOCK_EX);

// Перенаправляем пользователя
if ($sent) {
    header('Location: ' . SUCCESS_PAGE);
} else {
    header('Location: ' . ERROR_PAGE . '&reason=vk_failed');
}
exit;
?>