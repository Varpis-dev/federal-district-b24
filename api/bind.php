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

function getExistingDealField($fieldName, $auth) {
  $result = callBitrix('crm.deal.userfield.list', [
    'filter' => [
      'FIELD_NAME' => $fieldName
    ]
  ], $auth);

  if (!empty($result['result']) && is_array($result['result'])) {
    return $result['result'];
  }

  return [];
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
  jsonResponse([
    'success' => false,
    'error' => 'BAD_INPUT',
    'message' => 'Некорректный JSON'
  ]);
}

$auth = $input['auth'] ?? null;

if (!$auth) {
  jsonResponse([
    'success' => false,
    'error' => 'NO_AUTH',
    'message' => 'Не передана авторизация'
  ]);
}

$baseUrl = getBaseUrl();
$fieldUrl = $baseUrl . '/field';

$visualUserTypeId = 'fed_district_manager';

$visualFieldName = 'UF_CRM_FEDERAL_DISTRICT_MANAGER';
$textFieldName = 'UF_CRM_FEDERAL_DISTRICT_TEXT';

$typeResult = callBitrix('userfieldtype.add', [
  'USER_TYPE_ID' => $visualUserTypeId,
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
  $possibleUserTypeIds[] = 'rest_' . $appId . '_' . $visualUserTypeId;
}

$possibleUserTypeIds[] = $visualUserTypeId;

$visualExisting = getExistingDealField($visualFieldName, $auth);
$textExisting = getExistingDealField($textFieldName, $auth);

$visualResult = [
  'already_exists' => !empty($visualExisting),
  'field' => $visualExisting,
  'create_result' => null,
  'used_user_type_id' => null
];

$textResult = [
  'already_exists' => !empty($textExisting),
  'field' => $textExisting,
  'create_result' => null
];

if (empty($visualExisting)) {
  foreach ($possibleUserTypeIds as $actualUserTypeId) {
    $addVisualResult = callBitrix('crm.deal.userfield.add', [
      'fields' => [
        'FIELD_NAME' => $visualFieldName,
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

    $visualResult['create_result'] = $addVisualResult;
    $visualResult['used_user_type_id'] = $actualUserTypeId;

    if (isset($addVisualResult['result']) && !isset($addVisualResult['error'])) {
      break;
    }
  }
}

if (empty($textExisting)) {
  $addTextResult = callBitrix('crm.deal.userfield.add', [
    'fields' => [
      'FIELD_NAME' => $textFieldName,
      'EDIT_FORM_LABEL' => 'Федеральный округ (строка)',
      'LIST_COLUMN_LABEL' => 'Федеральный округ (строка)',
      'LIST_FILTER_LABEL' => 'Федеральный округ (строка)',
      'ERROR_MESSAGE' => '',
      'HELP_MESSAGE' => 'Обычное строковое поле для фильтров, роботов и бизнес-процессов',
      'USER_TYPE_ID' => 'string',
      'XML_ID' => 'FEDERAL_DISTRICT_TEXT',
      'MULTIPLE' => 'N',
      'MANDATORY' => 'N',
      'SHOW_FILTER' => 'Y',
      'SORT' => 101
    ]
  ], $auth);

  $textResult['create_result'] = $addTextResult;
}

jsonResponse([
  'success' => true,
  'message' => 'Проверка и создание полей завершены.',
  'visual_field' => $visualResult,
  'text_field' => $textResult,
  'type_register_result' => $typeResult,
  'app_info' => $appInfo,
  'field_url' => $fieldUrl,
  'important' => [
    'visual_field_name' => $visualFieldName,
    'text_field_name' => $textFieldName,
    'text_field_value_example' => 'Приволжский',
    'text_field_description' => 'Это обычное строковое поле. Его можно использовать в фильтрах, роботах и бизнес-процессах.'
  ]
]);
