<?php

require_once __DIR__ . '/common.php';

$input =
    json_decode(
        file_get_contents('php://input'),
        true
    );

if (!is_array($input)) {
    $input =
        $_REQUEST;
}

$auth =
    normalizeAuth(
        $input
    );

if (
    empty($auth['domain']) ||
    empty($auth['access_token'])
) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_auth'
    ]);
}


$baseUrl =
    getBaseUrl();

$fieldUrl =
    $baseUrl .
    '/field';

$eventsBase =
    $baseUrl .
    '/events';


$optionsRes =
    callBitrix(
        'app.option.get',
        [],
        $auth
    );

if (
    !empty(
        $optionsRes['error']
    )
) {
    jsonResponse([
        'success' => false,
        'reason' =>
            'options_error',
        'error' =>
            $optionsRes
    ]);
}


$options =
    $optionsRes['result'] ??
    [];

$leadCityField =
    $options['leadCityField'] ??
    '';

$leadRegionField =
    $options['leadRegionField'] ??
    '';


if (!$leadCityField) {

    jsonResponse([
        'success' => false,
        'reason' =>
            'no_lead_city_field',
        'message' =>
            'Сначала выберите поле города лида и сохраните настройки.'
    ]);
}


function getExistingField(
    $entity,
    $fieldName,
    $auth
) {

    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.list'
            : 'crm.deal.userfield.list';

    $result =
        callBitrix(
            $method,
            [
                'filter' => [
                    'FIELD_NAME' =>
                        $fieldName
                ]
            ],
            $auth
        );

    if (
        !empty(
            $result['error']
        )
    ) {
        return [];
    }

    return
        $result['result'] ??
        [];
}


function createStringField(
    $entity,
    $fieldName,
    $label,
    $auth
) {

    $existing =
        getExistingField(
            $entity,
            $fieldName,
            $auth
        );

    if (!empty($existing)) {

        return [
            'success' => true,
            'already_exists' =>
                true
        ];
    }


    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.add'
            : 'crm.deal.userfield.add';


    $result =
        callBitrix(
            $method,
            [
                'fields' => [

                    'FIELD_NAME' =>
                        $fieldName,

                    'EDIT_FORM_LABEL' =>
                        $label,

                    'LIST_COLUMN_LABEL' =>
                        $label,

                    'LIST_FILTER_LABEL' =>
                        $label,

                    'USER_TYPE_ID' =>
                        'string',

                    'MULTIPLE' =>
                        'N',

                    'MANDATORY' =>
                        'N',

                    'SHOW_FILTER' =>
                        'Y',

                    'SORT' =>
                        101,

                    'SETTINGS' => [
                        'SIZE' => 30,
                        'ROWS' => 1
                    ]
                ]
            ],
            $auth
        );


    return [
        'success' =>
            empty(
                $result['error']
            ),

        'already_exists' =>
            false,

        'result' =>
            $result
    ];
}


/*
 * ==================================================
 * СТРОКОВОЕ ФО ЛИДА
 * ==================================================
 */

$leadString =
    createStringField(
        'lead',
        LEAD_FED_FIELD,
        'Федеральный округ',
        $auth
    );


/*
 * ==================================================
 * СТРОКОВОЕ ФО СДЕЛКИ
 *
 * Только создаём.
 * Приложение его НЕ ЗАПОЛНЯЕТ.
 * ==================================================
 */

$dealString =
    createStringField(
        'deal',
        DEAL_FED_FIELD,
        'Федеральный округ (строка)',
        $auth
    );


/*
 * ==================================================
 * БОЛЬШОЕ ВИЗУАЛЬНОЕ ПОЛЕ
 * ==================================================
 */

$visualTypeId =
    'fed_district_manager';

$visualFieldName =
    'UF_CRM_FEDERAL_DISTRICT_MANAGER';


$typeResult =
    callBitrix(
        'userfieldtype.add',
        [
            'USER_TYPE_ID' =>
                $visualTypeId,

            'HANDLER' =>
                $fieldUrl,

            'TITLE' =>
                'Федеральный округ сделки',

            'DESCRIPTION' =>
                'Федеральный округ и менеджер',

            'OPTIONS' => [
                'height' => 150
            ]
        ],
        $auth
    );


$appInfo =
    callBitrix(
        'app.info',
        [],
        $auth
    );


$appId =
    $appInfo['result']['ID'] ??
    $appInfo['result']['APP_ID'] ??
    null;


$visualExisting =
    getExistingField(
        'deal',
        $visualFieldName,
        $auth
    );


$visualResult = [
    'already_exists' =>
        !empty($visualExisting)
];


if (empty($visualExisting)) {

    $possibleTypes = [];

    if ($appId) {

        $possibleTypes[] =
            'rest_' .
            $appId .
            '_' .
            $visualTypeId;
    }

    $possibleTypes[] =
        $visualTypeId;


    foreach (
        $possibleTypes
        as $actualType
    ) {

        $add =
            callBitrix(
                'crm.deal.userfield.add',
                [
                    'fields' => [

                        'FIELD_NAME' =>
                            $visualFieldName,

                        'EDIT_FORM_LABEL' =>
                            'Федеральный округ',

                        'LIST_COLUMN_LABEL' =>
                            'Федеральный округ',

                        'LIST_FILTER_LABEL' =>
                            'Федеральный округ',

                        'USER_TYPE_ID' =>
                            $actualType,

                        'MULTIPLE' =>
                            'N',

                        'MANDATORY' =>
                            'N',

                        'SHOW_FILTER' =>
                            'N',

                        'SORT' =>
                            100
                    ]
                ],
                $auth
            );


        $visualResult = [
            'already_exists' =>
                false,

            'type' =>
                $actualType,

            'result' =>
                $add
        ];


        if (
            empty($add['error']) &&
            isset($add['result'])
        ) {
            break;
        }
    }
}


/*
 * ==================================================
 * УДАЛЯЕМ ВСЕ СТАРЫЕ НАШИ EVENTS
 * ==================================================
 */

$eventGet =
    callBitrix(
        'event.get',
        [],
        $auth
    );


$removedEvents = [];


if (
    empty(
        $eventGet['error']
    )
) {

    foreach (
        $eventGet['result'] ?? []
        as $handler
    ) {

        $eventName =
            strtoupper(
                $handler['event'] ??
                $handler['EVENT'] ??
                ''
            );

        $handlerUrl =
            $handler['handler'] ??
            $handler['HANDLER'] ??
            '';


        $isOurHandler =
            strpos(
                $handlerUrl,
                $eventsBase
            ) === 0;


        $isRelevantEvent =
            in_array(
                $eventName,
                [
                    'ONCRMLEADADD',
                    'ONCRMLEADUPDATE',
                    'ONCRMDEALADD',
                    'ONCRMDEALUPDATE'
                ],
                true
            );


        if (
            !$isOurHandler ||
            !$isRelevantEvent
        ) {
            continue;
        }


        $unbind =
            callBitrix(
                'event.unbind',
                [
                    'event' =>
                        $eventName,

                    'handler' =>
                        $handlerUrl
                ],
                $auth
            );


        $removedEvents[] = [
            'event' =>
                $eventName,

            'handler' =>
                $handlerUrl,

            'result' =>
                $unbind
        ];
    }
}


/*
 * ==================================================
 * НОВЫЙ ОПТИМИЗИРОВАННЫЙ HANDLER
 *
 * В URL передаём коды полей.
 * events.js больше не делает app.option.get
 * на каждый лид.
 * ==================================================
 */

$handlerUrl =
    $eventsBase .
    '?city=' .
    rawurlencode(
        $leadCityField
    );


if ($leadRegionField) {

    $handlerUrl .=
        '&region=' .
        rawurlencode(
            $leadRegionField
        );
}


$bindLeadAdd =
    callBitrix(
        'event.bind',
        [
            'event' =>
                'ONCRMLEADADD',

            'handler' =>
                $handlerUrl
        ],
        $auth
    );


$bindLeadUpdate =
    callBitrix(
        'event.bind',
        [
            'event' =>
                'ONCRMLEADUPDATE',

            'handler' =>
                $handlerUrl
        ],
        $auth
    );


jsonResponse([
    'success' => true,

    'message' =>
        'Оптимизированная версия подключена.',

    'handler' =>
        $handlerUrl,

    'fields' => [
        'lead_string' =>
            $leadString,

        'deal_string' =>
            $dealString,

        'visual' =>
            $visualResult
    ],

    'events' => [
        'removed_old' =>
            $removedEvents,

        'lead_add' =>
            $bindLeadAdd,

        'lead_update' =>
            $bindLeadUpdate
    ],

    'important' =>
        'События сделок отключены. Приложение автоматически изменяет только строковый ФО лида.'
]);
