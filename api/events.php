<?php

require_once __DIR__ . '/common.php';

$raw =
    file_get_contents(
        'php://input'
    );

$json =
    json_decode(
        $raw,
        true
    );

if (
    is_array($json) &&
    !empty($json)
) {
    $input =
        array_replace_recursive(
            $_REQUEST,
            $json
        );
} else {
    $input =
        $_REQUEST;
}

$auth =
    normalizeAuth($input);

$event =
    strtoupper(
        (string)(
            $input['event'] ??
            $input['EVENT'] ??
            ''
        )
    );

$leadId =
    $input['data']['FIELDS']['ID'] ??
    $input['DATA']['FIELDS']['ID'] ??
    $input['data']['fields']['ID'] ??
    $input['FIELDS']['ID'] ??
    $input['ID'] ??
    null;

if (
    empty($auth['domain']) ||
    empty($auth['access_token'])
) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_auth',
        'event' => $event
    ]);
}

if (
    $event !== 'ONCRMLEADADD' &&
    $event !== 'ONCRMLEADUPDATE'
) {
    jsonResponse([
        'success' => true,
        'handled' => false,
        'ignored_event' => $event
    ]);
}

if (!$leadId) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_lead_id',
        'event' => $event
    ]);
}

$result =
    syncLeadDistrict(
        $leadId,
        $auth
    );

jsonResponse([
    'success' => true,
    'handled' => true,
    'event' => $event,
    'lead_id' => $leadId,
    'result' => $result
]);
