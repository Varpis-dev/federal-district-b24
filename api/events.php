<?php
require_once __DIR__ . '/common.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
  $input = $_REQUEST;
}

$auth = normalizeAuth($input);

$dealId =
  $input['data']['FIELDS']['ID'] ??
  $input['data']['fields']['ID'] ??
  $input['DATA']['FIELDS']['ID'] ??
  $input['FIELDS']['ID'] ??
  $input['ID'] ??
  null;

if (!$dealId && !empty($input['data']['FIELDS']) && is_array($input['data']['FIELDS'])) {
  $fields = $input['data']['FIELDS'];
  $dealId = $fields['ID'] ?? $fields['id'] ?? null;
}

if (!$dealId) {
  jsonResponse([
    'success' => false,
    'reason' => 'no_deal_id',
    'input_keys' => array_keys($input)
  ]);
}

if (empty($auth['domain']) || empty($auth['access_token'])) {
  jsonResponse([
    'success' => false,
    'reason' => 'no_auth'
  ]);
}

$result = calculateAndSyncDeal($dealId, $auth);

jsonResponse([
  'success' => true,
  'event_processed' => true,
  'deal_id' => $dealId,
  'result' => $result
]);
