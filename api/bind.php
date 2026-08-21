<?php

require_once __DIR__ . '/common.php';

$input =
    json_decode(
        file_get_contents('php://input'),
        true
    );

if (!is_array($input)) {
    jsonResponse([
        'success' => false,
        'reason' => 'bad_input'
    ]);
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

$baseUrl =
    getBaseUrl();

$fieldUrl =
    $baseUrl . '/field';

$eventsUrl =
    $baseUrl . '/events';

$visualTypeId =
    'fed_district_manager';

$visualFieldName =
    'UF_CRM_FEDERAL_DISTRICT_MANAGER';

function getExistingField(
    $entity,
    $fieldName,
    $auth
) {
    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.list'
            : 'crm.deal.userfield.list';

    $res =
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
            $res['error']
        )
    ) {
        return [];
    }

    return
        $res['result'] ??
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
            'already_exists' => true,
            'field' => $existing
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

                    'HELP_MESSAGE' =>
                        'Федеральный округ',

                    'USER_TYPE_ID' =>
                        'string',

                    'XML_ID' =>
                        $entity === 'lead'
                            ? 'LEAD_FEDERAL_DISTRICT_TEXT'
                            : 'DEAL_FEDERAL_DISTRICT_TEXT',

                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'SORT' => 101,

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
            empty($result['error']),

        'already_exists' => false,
        'result' => $result
    ];
}

/*
 * =====================================================
 * РЕГИСТРИРУЕМ БОЛЬШОЙ ТИП ПОЛЯ
 * =====================================================
 */

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
                'Определение федерального округа и менеджера по городу и области',

            'OPTIONS' => [
                'height' => 130
            ]
        ],
        $auth
    );

/*
 * =====================================================
 * БОЛЬШОЕ ПОЛЕ СДЕЛКИ
 * =====================================================
 */

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

                        'XML_ID' =>
                            'FEDERAL_DISTRICT_MANAGER',

                        'MULTIPLE' => 'N',
                        'MANDATORY' => 'N',
                        'SHOW_FILTER' => 'N',
                        'SORT' => 100
                    ]
                ],
                $auth
            );

        $visualResult = [
            'already_exists' => false,
            'used_type' =>
                $actualType,
            'result' => $add
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
 * =====================================================
 * СТРОКОВОЕ ПОЛЕ ЛИДА
 * =====================================================
 */

$leadString =
    createStringField(
        'lead',
        LEAD_FED_FIELD,
        'Федеральный округ',
        $auth
    );

/*
 * =====================================================
 * СТРОКОВОЕ ПОЛЕ СДЕЛКИ
 *
 * Только создаём.
 * Приложение его НЕ заполняет.
 * Потом используешь БП.
 * =====================================================
 */

$dealString =
    createStringField(
        'deal',
        DEAL_FED_FIELD,
        'Федеральный округ (строка)',
        $auth
    );

/*
 * =====================================================
 * УБИРАЕМ СТАРЫЕ СОБЫТИЯ СДЕЛОК
 * =====================================================
 */

$unbindDealAdd =
    callBitrix(
        'event.unbind',
        [
            'event' =>
                'ONCRMDEALADD',

            'handler' =>
                $eventsUrl
        ],
        $auth
    );

$unbindDealUpdate =
    callBitrix(
        'event.unbind',
        [
            'event' =>
                'ONCRMDEALUPDATE',

            'handler' =>
                $eventsUrl
        ],
        $auth
    );

/*
 * Чтобы повторное нажатие кнопки
 * не плодило подписки — сначала
 * снимаем подписки лидов.
 */

$unbindLeadAdd =
    callBitrix(
        'event.unbind',
        [
            'event' =>
                'ONCRMLEADADD',

            'handler' =>
                $eventsUrl
        ],
        $auth
    );

$unbindLeadUpdate =
    callBitrix(
        'event.unbind',
        [
            'event' =>
                'ONCRMLEADUPDATE',

            'handler' =>
                $eventsUrl
        ],
        $auth
    );

/*
 * =====================================================
 * ПОДКЛЮЧАЕМ ТОЛЬКО ЛИДЫ
 * =====================================================
 */

$bindLeadAdd =
    callBitrix(
        'event.bind',
        [
            'event' =>
                'ONCRMLEADADD',

            'handler' =>
                $eventsUrl
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
                $eventsUrl
        ],
        $auth
    );

jsonResponse([
    'success' => true,

    'message' =>
        'Поля проверены/созданы. Подключены только события лидов.',

    'fields' => [
        'lead_string' =>
            $leadString,

        'deal_string' =>
            $dealString,

        'deal_visual' =>
            $visualResult
    ],

    'events' => [
        'handler' =>
            $eventsUrl,

        'lead_add' =>
            $bindLeadAdd,

        'lead_update' =>
            $bindLeadUpdate,

        'deal_add_removed' =>
            $unbindDealAdd,

        'deal_update_removed' =>
            $unbindDealUpdate
    ],

    'field_codes' => [
        'lead_string' =>
            LEAD_FED_FIELD,

        'deal_string' =>
            DEAL_FED_FIELD,

        'deal_visual' =>
            $visualFieldName
    ],

    'important' =>
        'Приложение НЕ переносит и НЕ меняет строковый ФО сделки.'
]);
