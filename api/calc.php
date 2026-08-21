<?php

require_once __DIR__ . '/common.php';

$input =
    json_decode(
        file_get_contents('php://input'),
        true
    );

if (!is_array($input)) {
    $input = $_REQUEST;
}

$city =
    $input['city'] ?? '';

$region =
    $input['region'] ?? '';

$result =
    calcDistrictName(
        $city,
        $region
    );

jsonResponse([
    'success' => true,
    'city' => $city,
    'region' => $region,
    'result' => $result
]);
