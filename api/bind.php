<?php
header('Content-Type: text/plain; charset=utf-8');

function jsonResponse($data) {
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function getBaseUrl() {
  return 'https://' . $_SERVER['HTTP_HOST'];
}

function callBitrix($method, $params, $auth) {
  if (empty($auth['domain']) || empty($auth['access_token'])) {
    return [
      'error' => 'NO_AUTH',
      'error_description' => 'Нет domain или access_token'
    ];
  }

  $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $auth['domain']);
  $url = 'https://' . $domain . '/rest/' . $method . '.json';

  $params['auth'] = $auth['access_token'];

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query($params),
      'timeout' => 20
    ]
  ]);

  $raw = @file_get_contents($url, false, $context);

  if ($raw === false) {
    return [
      'error' => 'HTTP_REQUEST_FAILED',
      'error_description' => 'Не удалось выполнить запрос к Bitrix24',
      'url' => $url
    ];
  }

  $decoded = json_decode($raw, true);

  if (!is_array($decoded)) {
    return [
      'error' => 'BAD_JSON',
      'raw' => $raw
    ];
  }

  return $decoded;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
  jsonResponse([
    'error' => 'BAD_INPUT',
    'message' => 'Некорректный JSON'
  ]);
}

$auth = $input['auth'] ?? null;

if (!$auth) {
  jsonResponse([
    'error' => 'NO_AUTH',
    'message' => 'Не передана авторизация'
  ]);
}

$baseUrl = getBaseUrl();
$fieldUrl = $baseUrl . '/field';

$userTypeId = 'fed_district_manager';

$typeResult = callBitrix('userfieldtype.add', [
  'USER_TYPE_ID' => $userTypeId,
  'HANDLER' => $fieldUrl,
  'TITLE' => 'Федеральный округ сделки',
  'DESCRIPTION' => 'Определение федерального округа и ответственного менеджера по городу и области сделки',
  'OPTIONS' => [
    'height' => 130
  ]
], $auth);

$appInfo = callBitrix('app.info', [], $auth);
$appId = null;

if (isset($appInfo['result']['ID'])) {
  $appId = $appInfo['result']['ID'];
} elseif (isset($appInfo['result']['APP_ID'])) {
  $appId = $appInfo['result']['APP_ID'];
}

$possibleUserTypeIds = [];

if ($appId) {
  $possibleUserTypeIds[] = 'rest_' . $appId . '_' . $userTypeId;
}

$possibleUserTypeIds[] = $userTypeId;

$existingFields = callBitrix('crm.deal.userfield.list', [
  'filter' => [
    'FIELD_NAME' => 'UF_CRM_FEDERAL_DISTRICT_MANAGER'
  ]
], $auth);

if (!empty($existingFields['result']) && is_array($existingFields['result'])) {
  jsonResponse([
    'success' => true,
    'message' => 'Поле уже существует. Повторно создавать не нужно.',
    'field' => $existingFields['result'],
    'type_register_result' => $typeResult,
    'app_info' => $appInfo,
    'field_url' => $fieldUrl
  ]);
}

$lastAddResult = null;

foreach ($possibleUserTypeIds as $actualUserTypeId) {
  $addResult = callBitrix('crm.deal.userfield.add', [
    'fields' => [
      'FIELD_NAME' => 'UF_CRM_FEDERAL_DISTRICT_MANAGER',
      'EDIT_FORM_LABEL' => 'Федеральный округ',
      'LIST_COLUMN_LABEL' => 'Федеральный округ',
      'LIST_FILTER_LABEL' => 'Федеральный округ',
      'ERROR_MESSAGE' => '',
      'HELP_MESSAGE' => 'Федеральный округ и менеджер по городу и области сделки',
      'USER_TYPE_ID' => $actualUserTypeId,
      'XML_ID' => 'FEDERAL_DISTRICT_MANAGER',
      'MULTIPLE' => 'N',
      'MANDATORY' => 'N',
      'SHOW_FILTER' => 'N',
      'SORT' => 100
    ]
  ], $auth);

  $lastAddResult = [
    'tried_user_type_id' => $actualUserTypeId,
    'result' => $addResult
  ];

  if (isset($addResult['result']) && !isset($addResult['error'])) {
    jsonResponse([
      'success' => true,
      'message' => 'Поле создано успешно. Теперь добавьте его в карточку сделки.',
      'field_add_result' => $addResult,
      'type_register_result' => $typeResult,
      'app_info' => $appInfo,
      'used_user_type_id' => $actualUserTypeId,
      'field_url' => $fieldUrl
    ]);
  }
}

jsonResponse([
  'success' => false,
  'message' => 'Не удалось создать поле.',
  'last_add_result' => $lastAddResult,
  'type_register_result' => $typeResult,
  'app_info' => $appInfo,
  'possible_user_type_ids' => $possibleUserTypeIds,
  'field_url' => $fieldUrl
]);
