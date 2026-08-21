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

$auth =
    normalizeAuth($input);

if (
    empty($auth['domain']) ||
    empty($auth['access_token'])
) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_auth'
    ]);
}

$start =
    isset($input['start'])
        ? $input['start']
        : 0;

/*
 * Настройки приложения
 */
$optionsRes =
    callBitrix(
        'app.option.get',
        [],
        $auth
    );

if (!empty($optionsRes['error'])) {
    jsonResponse([
        'success' => false,
        'reason' => 'options_error',
        'error' => $optionsRes
    ]);
}

$appOptions =
    $optionsRes['result'] ?? [];

$cityField =
    $appOptions['leadCityField'] ?? '';

$regionField =
    $appOptions['leadRegionField'] ?? '';

if (!$cityField) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_lead_city_field',
        'message' =>
            'В настройках не выбрано поле города лида'
    ]);
}

/*
 * Получаем метаданные только двух нужных полей,
 * а не crm.lead.fields целиком.
 */
$cityMeta =
    getUserFieldMeta(
        'lead',
        $cityField,
        $auth
    );

$regionMeta =
    $regionField
        ? getUserFieldMeta(
            'lead',
            $regionField,
            $auth
        )
        : null;

/*
 * Выбираем только нужные поля.
 */
$select = [
    'ID',
    $cityField,
    LEAD_FED_FIELD
];

if ($regionField) {
    $select[] =
        $regionField;
}

$listRes =
    callBitrix(
        'crm.lead.list',
        [
            'order' => [
                'ID' => 'ASC'
            ],

            'filter' => [],

            'select' =>
                $select,

            'start' =>
                $start
        ],
        $auth
    );

if (!empty($listRes['error'])) {
    jsonResponse([
        'success' => false,
        'reason' => 'lead_list_error',
        'error' => $listRes
    ]);
}

$leads =
    $listRes['result'] ?? [];

$stats = [
    'processed' => 0,
    'updated' => 0,
    'already_actual' => 0,
    'unknown' => 0,
    'need_region' => 0,
    'no_city' => 0,
    'cleared' => 0,
    'errors' => 0
];

$sample = [];

foreach ($leads as $lead) {

    $stats['processed']++;

    $leadId =
        $lead['ID'] ?? null;

    if (!$leadId) {
        $stats['errors']++;
        continue;
    }

    $city =
        parseFieldValue(
            $lead[$cityField] ?? '',
            $cityMeta
        );

    $region =
        $regionField
            ? parseFieldValue(
                $lead[$regionField] ?? '',
                $regionMeta
            )
            : '';

    $current =
        trim(
            (string)(
                $lead[LEAD_FED_FIELD] ?? ''
            )
        );

    if (!$city) {

        $stats['no_city']++;

        /*
         * Если ФО раньше был записан,
         * а города теперь нет —
         * очищаем.
         */
        if ($current !== '') {

            $update =
                callBitrix(
                    'crm.lead.update',
                    [
                        'id' => $leadId,

                        'fields' => [
                            LEAD_FED_FIELD => ''
                        ],

                        'params' => [
                            'REGISTER_SONET_EVENT' => 'N',
                            'REGISTER_HISTORY_EVENT' => 'N'
                        ]
                    ],
                    $auth
                );

            if (empty($update['error'])) {
                $stats['cleared']++;
            } else {
                $stats['errors']++;
            }
        } else {
            $stats['already_actual']++;
        }

        continue;
    }

    $calc =
        calcDistrictName(
            $city,
            $region
        );

    if (
        $calc['status'] ===
        'need_region'
    ) {
        $stats['need_region']++;
    }

    if (
        $calc['status'] ===
        'unknown'
    ) {
        $stats['unknown']++;
    }

    $target =
        ($calc['status'] === 'ok')
            ? $calc['districtName']
            : '';

    if ($current === $target) {

        $stats['already_actual']++;

    } else {

        $update =
            callBitrix(
                'crm.lead.update',
                [
                    'id' => $leadId,

                    'fields' => [
                        LEAD_FED_FIELD =>
                            $target
                    ],

                    'params' => [
                        'REGISTER_SONET_EVENT' => 'N',
                        'REGISTER_HISTORY_EVENT' => 'N'
                    ]
                ],
                $auth
            );

        if (!empty($update['error'])) {

            $stats['errors']++;

        } elseif ($target !== '') {

            $stats['updated']++;

        } else {

            $stats['cleared']++;
        }
    }

    if (
        count($sample) < 15
    ) {
        $sample[] = [
            'lead_id' => $leadId,
            'city' => $city,
            'region' => $region,
            'district' => $target,
            'status' =>
                $calc['status']
        ];
    }
}

$next =
    $listRes['next'] ?? null;

jsonResponse([
    'success' => true,
    'start' => $start,
    'next' => $next,
    'stats' => $stats,
    'sample' => $sample
]);
