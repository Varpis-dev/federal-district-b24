<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$file = __DIR__ . '/../public/field.html';

if (!is_file($file)) {
    http_response_code(500);

    echo '<h1>Ошибка</h1>';
    echo '<p>Не найден public/field.html</p>';

    exit;
}

readfile($file);
