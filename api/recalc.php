<?php
require_once __DIR__ . '/common.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
  $input = $_REQUEST;
}

$auth = normalizeAuth($input);

if (empty($auth['domain']) || empty($auth['access_token'])) {
  jsonResponse([
    'success' => false,
    'reason' => 'no_auth'
  ]);
}

$start = isset($input['start']) && $input['start'] !== ''
  ? $input['start']
  : 0;

$optionsRes = callBitrix('app.option.get', [], $auth);

if (!empty($optionsRes['error'])) {
  jsonResponse([
    'success' => false,
    'reason' => 'options_error',
    'error' => $optionsRes
  ]);
}

$appOptions = $optionsRes['result'] ?? [];

$cityField = $appOptions['dealCityField'] ?? '';
$regionField = $appOptions['dealRegionField'] ?? '';

if (!$cityField) {
  jsonResponse([
    'success' => false,
    'reason' => 'no_city_field',
    'message' => 'В настройках приложения не выбрано поле города'
  ]);
}

$fieldsMeta = getSelectedDealFieldsMeta([
  $cityField,
  $regionField
], $auth);

$select = [
  'ID',
  $cityField,
  OUTPUT_TEXT_FIELD
];

if ($regionField) {
  $select[] = $regionField;
}

$listRes = callBitrix('crm.deal.list', [
  'order' => [
    'ID' => 'ASC'
  ],
  'filter' => [],
  'select' => $select,
  'start' => $start
], $auth);

if (!empty($listRes['error'])) {
  jsonResponse([
    'success' => false,
    'reason' => 'deal_list_error',
    'error' => $listRes
  ]);
}

$deals = $listRes['result'] ?? [];

$stats = [
  'processed' => 0,
  'updated' => 0,
  'already_actual' => 0,
  'cleared' => 0,
  'unknown' => 0,
  'need_region' => 0,
  'no_city' => 0,
  'errors' => 0
];

$details = [];

foreach ($deals as $deal) {
  $stats['processed']++;

  $dealId = $deal['ID'] ?? null;

  if (!$dealId) {
    $stats['errors']++;
    continue;
  }

  $city = parseFieldValue($deal[$cityField] ?? '', $cityField, $fieldsMeta);
  $region = $regionField ? parseFieldValue($deal[$regionField] ?? '', $regionField, $fieldsMeta) : '';

  if (!$city) {
    $sync = syncPlainDistrictField($dealId, $deal, '', $auth);
    $stats['no_city']++;

    if (!empty($sync['updated'])) {
      $stats['cleared']++;
    } elseif (($sync['reason'] ?? '') === 'already_actual') {
      $stats['already_actual']++;
    } else {
      $stats['errors']++;
    }

    continue;
  }

  $calc = calcDistrictName($city, $region);

  if ($calc['status'] === 'need_region') {
    $stats['need_region']++;
  }

  if ($calc['status'] === 'unknown') {
    $stats['unknown']++;
  }

  $districtName = ($calc['status'] === 'ok') ? $calc['districtName'] : '';

  $sync = syncPlainDistrictField($dealId, $deal, $districtName, $auth);

  if (!empty($sync['updated'])) {
    if ($districtName) {
      $stats['updated']++;
    } else {
      $stats['cleared']++;
    }
  } elseif (($sync['reason'] ?? '') === 'already_actual') {
    $stats['already_actual']++;
  } else {
    $stats['errors']++;
  }

  if (count($details) < 20) {
    $details[] = [
      'deal_id' => $dealId,
      'city' => $city,
      'region' => $region,
      'district' => $districtName,
      'status' => $calc['status'],
      'sync' => $sync['reason'] ?? null
    ];
  }
}

$next = $listRes['next'] ?? null;

jsonResponse([
  'success' => true,
  'start' => $start,
  'next' => $next,
  'stats' => $stats,
  'details_sample' => $details
]);
