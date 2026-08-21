<?php

require_once __DIR__ . '/common.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

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

$visualUserTypeId =
    'fed_district_manager';

$visualFieldName =
    'UF_CRM_FEDERAL_DISTRICT_MANAGER';

$dealTextFieldName =
    DEAL_FED_FIELD;

$leadTextFieldName =
    LEAD_FED_FIELD;

/*
 * ============================================================
 * ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
 * ============================================================
 */

function getExistingUserField(
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

    if (!empty($result['error'])) {
        return [];
    }

    return $result['result'] ?? [];
}

function createStringUserField(
    $entity,
    $fieldName,
    $label,
    $auth
) {

    $existing =
        getExistingUserField(
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

                    'USER_TYPE_ID' =>
                        'string',

                    'MULTIPLE' => 'N',
                    'MANDATORY' => 'N',
                    'SHOW_FILTER' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
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
 * ============================================================
 * 1. РЕГИСТРАЦИЯ КРАСИВОГО ТИПА ПОЛЯ
 * ============================================================
 */

$typeResult =
    callBitrix(
        'userfieldtype.add',
        [
            'USER_TYPE_ID' =>
                $visualUserTypeId,

            'HANDLER' =>
                $fieldUrl,

            'TITLE' =>
                'Федеральный округ сделки',

            'DESCRIPTION' =>
                'Федеральный округ и менеджер',

            'OPTIONS' => [
                'height' => 130
            ]
        ],
        $auth
    );

/*
 * ============================================================
 * 2. КРАСИВОЕ ПОЛЕ В СДЕЛКЕ
 * ============================================================
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
        $visualUserTypeId;
}

$possibleTypes[] =
    $visualUserTypeId;

$visualExisting =
    getExistingUserField(
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
            'type' => $actualType,
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
 * ============================================================
 * 3. СТРОКОВОЕ ФО В ЛИДЕ
 * ============================================================
 */

$leadTextResult =
    createStringUserField(
        'lead',
        $leadTextFieldName,
        'Федеральный округ',
        $auth
    );

/*
 * ============================================================
 * 4. СТРОКОВОЕ ФО В СДЕЛКЕ
 * ============================================================
 */

$dealTextResult =
    createStringUserField(
        'deal',
        $dealTextFieldName,
        'Федеральный округ (строка)',
        $auth
    );

/*
 * ============================================================
 * 5. СОБЫТИЯ
 * ============================================================
 */

/*
 * На всякий случай снимаем старую подписку
 * ONCRMDEALUPDATE.
 *
 * Даже если unbind вернёт ошибку —
 * новый events.php всё равно её игнорирует.
 */
$unbindOldDealUpdate =
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
 * Лид создан
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

/*
 * Лид изменился
 */
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

/*
 * Сделка создана.
 * Нужен только перенос ФО из лида.
 */
$bindDealAdd =
    callBitrix(
        'event.bind',
        [
            'event' =>
                'ONCRMDEALADD',

            'handler' =>
                $eventsUrl
        ],
        $auth
    );

jsonResponse([
    'success' => true,

    'message' =>
        'Поля созданы/проверены, события подключены.',

    'fields' => [
        'lead_text' =>
            $leadTextResult,

        'deal_text' =>
            $dealTextResult,

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

        'deal_add' =>
            $bindDealAdd,

        'old_deal_update_unbind' =>
            $unbindOldDealUpdate
    ],

    'codes' => [
        'lead' =>
            $leadTextFieldName,

        'deal' =>
            $dealTextFieldName
    ]
]);
