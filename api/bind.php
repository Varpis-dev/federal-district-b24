<?php

require_once __DIR__ . '/common.php';

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    $input = $_REQUEST;
}

$auth = normalizeAuth($input);

if (
    empty($auth['domain']) ||
    empty($auth['access_token'])
) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_auth'
    ]);
}

$baseUrl = getBaseUrl();

$fieldUrl =
    $baseUrl . '/field-v2';

$eventsBase =
    $baseUrl . '/events';


/*
 * ============================================================
 * НАСТРОЙКИ ПРИЛОЖЕНИЯ
 * ============================================================
 */

$optionsRes = callBitrix(
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

$options =
    $optionsRes['result'] ?? [];

$leadCityField =
    $options['leadCityField'] ?? '';

$leadRegionField =
    $options['leadRegionField'] ?? '';

if (!$leadCityField) {
    jsonResponse([
        'success' => false,
        'reason' => 'no_lead_city_field',
        'message' =>
            'Сначала выберите поле города лида и сохраните настройки.'
    ]);
}


/*
 * ============================================================
 * ПОИСК ПОЛЯ
 * ============================================================
 */

function getExistingField(
    $entity,
    $fieldName,
    $auth
) {
    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.list'
            : 'crm.deal.userfield.list';

    $result = callBitrix(
        $method,
        [
            'filter' => [
                'FIELD_NAME' =>
                    $fieldName
            ]
        ],
        $auth
    );

    if (!empty($result['error'])) {
        return [];
    }

    return
        $result['result'] ?? [];
}


/*
 * ============================================================
 * СОЗДАНИЕ СТРОКОВОГО ПОЛЯ
 * ============================================================
 */

function createStringField(
    $entity,
    $fieldName,
    $label,
    $auth
) {
    $existing = getExistingField(
        $entity,
        $fieldName,
        $auth
    );

    if (!empty($existing)) {
        return [
            'success' => true,
            'already_exists' => true
        ];
    }

    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.add'
            : 'crm.deal.userfield.add';

    $result = callBitrix(
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

                'EDIT_IN_LIST' =>
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
            empty($result['error']),

        'already_exists' =>
            false,

        'result' =>
            $result
    ];
}


/*
 * ============================================================
 * СТРОКОВОЕ ПОЛЕ ЛИДА
 * ============================================================
 */

$leadString = createStringField(
    'lead',
    LEAD_FED_FIELD,
    'Федеральный округ',
    $auth
);


/*
 * ============================================================
 * СТРОКОВОЕ ПОЛЕ СДЕЛКИ
 *
 * Только создаём.
 * Автоматически его приложение не заполняет.
 * ============================================================
 */

$dealString = createStringField(
    'deal',
    DEAL_FED_FIELD,
    'Федеральный округ (строка)',
    $auth
);


/*
 * ============================================================
 * БОЛЬШОЕ ПОЛЕ
 * ============================================================
 */

$visualTypeId =
    'fed_district_manager';

$visualFieldName =
    'UF_CRM_FEDERAL_DISTRICT_MANAGER';


/*
 * Сначала пытаемся обновить уже существующий
 * пользовательский тип на новый Vercel handler.
 */

$typeUpdate = callBitrix(
    'userfieldtype.update',
    [
        'USER_TYPE_ID' =>
            $visualTypeId,

        'HANDLER' =>
            $fieldUrl,

        'TITLE' =>
            'Федеральный округ сделки',

        'DESCRIPTION' =>
            'Федеральный округ и менеджер по городу и области сделки',

        'OPTIONS' => [
            'height' => 150
        ]
    ],
    $auth
);

$typeAction =
    'updated';

$typeResult =
    $typeUpdate;


/*
 * Если типа ещё нет — регистрируем.
 */

if (!empty($typeUpdate['error'])) {

    $typeAdd = callBitrix(
        'userfieldtype.add',
        [
            'USER_TYPE_ID' =>
                $visualTypeId,

            'HANDLER' =>
                $fieldUrl,

            'TITLE' =>
                'Федеральный округ сделки',

            'DESCRIPTION' =>
                'Федеральный округ и менеджер по городу и области сделки',

            'OPTIONS' => [
                'height' => 150
            ]
        ],
        $auth
    );

    if (empty($typeAdd['error'])) {

        $typeAction =
            'created';

        $typeResult =
            $typeAdd;

    } else {

        jsonResponse([
            'success' => false,
            'reason' =>
                'visual_type_error',
            'field_handler' =>
                $fieldUrl,
            'update_error' =>
                $typeUpdate,
            'add_error' =>
                $typeAdd
        ]);
    }
}


/*
 * ============================================================
 * APP ID
 * ============================================================
 */

$appInfo = callBitrix(
    'app.info',
    [],
    $auth
);

if (!empty($appInfo['error'])) {
    jsonResponse([
        'success' => false,
        'reason' => 'app_info_error',
        'error' => $appInfo
    ]);
}

$appId =
    $appInfo['result']['ID'] ??
    $appInfo['result']['APP_ID'] ??
    null;


/*
 * ============================================================
 * ПРОВЕРЯЕМ БОЛЬШОЕ ПОЛЕ
 * ============================================================
 */

$visualExisting = getExistingField(
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

        $add = callBitrix(
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
 * ============================================================
 * УДАЛЯЕМ СТАРЫЕ ПОДПИСКИ
 * ============================================================
 */

$eventGet = callBitrix(
    'event.get',
    [],
    $auth
);

$removedEvents = [];

$relevantEvents = [
    'ONCRMLEADADD',
    'ONCRMLEADUPDATE',
    'ONCRMDEALADD',
    'ONCRMDEALUPDATE'
];

if (empty($eventGet['error'])) {

    foreach (
        $eventGet['result'] ?? []
        as $handler
    ) {

        $eventName = strtoupper(
            (string)(
                $handler['event'] ??
                $handler['EVENT'] ??
                ''
            )
        );

        $handlerUrl = (string)(
            $handler['handler'] ??
            $handler['HANDLER'] ??
            ''
        );

        if (
            !in_array(
                $eventName,
                $relevantEvents,
                true
            )
        ) {
            continue;
        }

        if (!$handlerUrl) {
            continue;
        }

        $unbind = callBitrix(
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
 * ============================================================
 * НОВЫЙ HANDLER ЛИДОВ
 * ============================================================
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


/*
 * ============================================================
 * ПОДПИСКИ
 * ============================================================
 */

$bindLeadAdd = callBitrix(
    'event.bind',
    [
        'event' =>
            'ONCRMLEADADD',

        'handler' =>
            $handlerUrl
    ],
    $auth
);

$bindLeadUpdate = callBitrix(
    'event.bind',
    [
        'event' =>
            'ONCRMLEADUPDATE',

        'handler' =>
            $handlerUrl
    ],
    $auth
);


/*
 * ============================================================
 * ОТВЕТ
 * ============================================================
 */

jsonResponse([
    'success' => true,

    'message' =>
        'Федеральные округа успешно подключены.',

    'domain' =>
        $baseUrl,

    'visual_field_handler' =>
        $fieldUrl,

    'visual_type' => [
        'action' =>
            $typeAction,

        'result' =>
            $typeResult
    ],

    'fields' => [
        'lead_string' =>
            $leadString,

        'deal_string' =>
            $dealString,

        'deal_visual' =>
            $visualResult
    ],

    'events' => [
        'removed_old' =>
            $removedEvents,

        'new_handler' =>
            $handlerUrl,

        'lead_add' =>
            $bindLeadAdd,

        'lead_update' =>
            $bindLeadUpdate
    ]
]);
