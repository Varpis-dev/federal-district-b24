<?php

require_once __DIR__ . '/common.php';

/*
 * Bitrix24 события обычно приходят
 * как application/x-www-form-urlencoded.
 *
 * Но на всякий случай поддерживаем JSON.
 */

$rawJson =
    file_get_contents('php://input');

$json =
    json_decode(
        $rawJson,
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

/*
 * ID сущности.
 * В событиях CRM Bitrix24 передаёт его
 * в data[FIELDS][ID].
 */
$entityId =
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

if (!$entityId) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_entity_id',
        'event' => $event
    ]);
}

/*
 * ============================================================
 * ЛИД СОЗДАН / ЛИД ИЗМЕНЁН
 * ============================================================
 */

if (
    $event === 'ONCRMLEADADD' ||
    $event === 'ONCRMLEADUPDATE'
) {

    $result =
        syncLeadDistrict(
            $entityId,
            $auth
        );

    jsonResponse([
        'success' => true,
        'handled' => 'lead',
        'event' => $event,
        'lead_id' => $entityId,
        'result' => $result
    ]);
}

/*
 * ============================================================
 * СДЕЛКА СОЗДАНА
 * ============================================================
 */

if (
    $event === 'ONCRMDEALADD'
) {

    $result =
        transferLeadDistrictToDeal(
            $entityId,
            $auth
        );

    jsonResponse([
        'success' => true,
        'handled' => 'deal_add',
        'event' => $event,
        'deal_id' => $entityId,
        'result' => $result
    ]);
}

/*
 * ============================================================
 * ВСЁ ОСТАЛЬНОЕ ИГНОРИРУЕМ
 *
 * В частности, если старая подписка
 * ONCRMDEALUPDATE вдруг осталась —
 * она ничего не сделает.
 * ============================================================
 */

jsonResponse([
    'success' => true,
    'handled' => false,
    'ignored_event' => $event,
    'entity_id' => $entityId
]);
