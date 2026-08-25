<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

?>
<!DOCTYPE html>
<html lang="ru">

<head>

<meta charset="UTF-8">

<title>Федеральный округ</title>

<!-- Новый официальный JS SDK Bitrix24 -->
<script src="https://unpkg.com/@bitrix24/b24jssdk@1/dist/umd/index.min.js"></script>

<!-- Наша логика округов -->
<script src="/district.js"></script>

<style>

html,
body {
    margin: 0;
    padding: 0;
    background: transparent;
    overflow: hidden;
    font-family: Arial, sans-serif;
}

.wrap {
    box-sizing: border-box;
    width: 100%;
    min-height: 132px;

    padding: 12px 14px;

    border-radius: 16px;
    border: 1px solid #d7e3f5;

    background:
        linear-gradient(
            135deg,
            #f4f8ff 0%,
            #ffffff 75%
        );

    box-shadow:
        0 4px 14px
        rgba(24, 91, 170, .08);
}

.label {
    margin-bottom: 6px;

    font-size: 12px;
    font-weight: 700;

    color: #7b8794;

    text-transform: uppercase;
    letter-spacing: .04em;
}

.main {
    margin-bottom: 8px;

    font-size: 22px;
    line-height: 1.15;

    font-weight: 800;

    color: #111827;
}

.manager {
    display: inline-flex;
    align-items: center;

    margin-bottom: 8px;

    padding: 5px 9px;

    border-radius: 999px;

    background: #e8f1ff;

    color: #1456a3;

    font-size: 13px;
    font-weight: 700;
}

.place {
    font-size: 13px;
    line-height: 1.35;

    color: #4b5563;

    font-weight: 600;
}

.small {
    margin-top: 6px;

    font-size: 12px;
    line-height: 1.35;

    color: #7b8794;
}

.warn {
    border-color: #f1d48a;

    background:
        linear-gradient(
            135deg,
            #fff8e6 0%,
            #ffffff 75%
        );
}

.warn .main {
    color: #9a6a00;
}

.bad {
    border-color: #efb7b3;

    background:
        linear-gradient(
            135deg,
            #fff1f0 0%,
            #ffffff 75%
        );
}

.bad .main {
    color: #d92d20;
}

</style>

</head>

<body>

<div
    class="wrap"
    id="wrap"
>

    <div class="label">
        Федеральный округ
    </div>

    <div
        class="main"
        id="main"
    >
        Загрузка...
    </div>

    <div
        class="manager"
        id="manager"
        style="display:none;"
    ></div>

    <div
        class="place"
        id="place"
    ></div>

    <div
        class="small"
        id="small"
    ></div>

</div>


<script>

const wrapEl =
    document.getElementById(
        'wrap'
    );

const mainEl =
    document.getElementById(
        'main'
    );

const managerEl =
    document.getElementById(
        'manager'
    );

const placeEl =
    document.getElementById(
        'place'
    );

const smallEl =
    document.getElementById(
        'small'
    );


const DEFAULT_MANAGERS = {

    central:
        'Людмила',

    northwest:
        'Виктория',

    south:
        'Вячеслав',

    northCaucasus:
        'Вячеслав',

    volga:
        'Виктория',

    ural:
        'Вячеслав',

    siberian:
        'Людмила',

    farEast:
        'Людмила'
};


/*
 * ============================================================
 * ВЫВОД ОШИБКИ
 * ============================================================
 */

function renderError(
    title,
    message
) {

    wrapEl.className =
        'wrap bad';

    mainEl.textContent =
        title;

    managerEl.style.display =
        'none';

    placeEl.textContent =
        '';

    smallEl.textContent =
        message || '';
}


/*
 * ============================================================
 * НОРМАЛЬНЫЙ ВЫВОД
 * ============================================================
 */

function renderOk(
    district,
    manager,
    city,
    region,
    source
) {

    wrapEl.className =
        'wrap';


    mainEl.textContent =
        manager
            ? district +
              ' (' +
              manager +
              ')'
            : district;


    if (manager) {

        managerEl.style.display =
            'inline-flex';

        managerEl.textContent =
            'Менеджер: ' +
            manager;

    } else {

        managerEl.style.display =
            'none';
    }


    placeEl.textContent =
        region
            ? city +
              ', ' +
              region
            : city;


    smallEl.textContent =
        source === 'region'
            ? 'Округ определён по области/региону'
            : 'Округ определён по городу';
}


/*
 * ============================================================
 * НЕОДНОЗНАЧНЫЙ ГОРОД
 * ============================================================
 */

function renderNeedRegion(
    city
) {

    wrapEl.className =
        'wrap warn';

    mainEl.textContent =
        'Нужна область';

    managerEl.style.display =
        'none';

    placeEl.textContent =
        city || '';

    smallEl.textContent =
        'Для этого города нужна область для точного определения округа.';
}


/*
 * ============================================================
 * МЕНЕДЖЕР
 * ============================================================
 */

function getManager(
    options,
    districtKey
) {

    const managers = {

        central:
            options.managerCentral ||
            DEFAULT_MANAGERS.central,

        northwest:
            options.managerNorthwest ||
            DEFAULT_MANAGERS.northwest,

        south:
            options.managerSouth ||
            DEFAULT_MANAGERS.south,

        northCaucasus:
            options.managerNorthCaucasus ||
            DEFAULT_MANAGERS
                .northCaucasus,

        volga:
            options.managerVolga ||
            DEFAULT_MANAGERS.volga,

        ural:
            options.managerUral ||
            DEFAULT_MANAGERS.ural,

        siberian:
            options.managerSiberian ||
            DEFAULT_MANAGERS.siberian,

        farEast:
            options.managerFarEast ||
            DEFAULT_MANAGERS.farEast
    };


    return (
        managers[districtKey] ||
        ''
    );
}


/*
 * ============================================================
 * BITRIX REST ЧЕРЕЗ НОВЫЙ SDK
 * ============================================================
 */

async function b24Call(
    $b24,
    method,
    params = {}
) {

    const requestId =
        'fed-' +
        Date.now() +
        '-' +
        Math.random()
            .toString(36)
            .slice(2);


    const response =
        await $b24.actions.v2.call.make({
            method:
                method,

            params:
                params,

            requestId:
                requestId
        });


    if (
        !response.isSuccess
    ) {

        const errors =
            response
                .getErrorMessages();


        throw new Error(
            errors &&
            errors.length
                ? errors.join('; ')
                : 'Ошибка REST ' +
                  method
        );
    }


    const data =
        response.getData();


    if (
        !data ||
        typeof data.result ===
            'undefined'
    ) {

        throw new Error(
            'Пустой result: ' +
            method
        );
    }


    return data.result;
}


/*
 * ============================================================
 * ОСНОВНАЯ ЛОГИКА
 * ============================================================
 */

async function start()
{

    /*
     * district.js
     */

    if (
        typeof FederalDistrict ===
        'undefined'
    ) {

        throw new Error(
            'Не загрузился /district.js'
        );
    }


    /*
     * Новый SDK.
     *
     * Старый BX24.init() больше
     * вообще не используем.
     */

    if (
        typeof B24Js ===
        'undefined'
    ) {

        throw new Error(
            'Не загрузился новый Bitrix24 SDK'
        );
    }


    const $b24 =
        await B24Js
            .initializeB24Frame();


    if (!$b24) {

        throw new Error(
            'Bitrix24 Frame не инициализирован'
        );
    }


    /*
     * USERFIELD_TYPE передаёт информацию
     * о текущей сделке именно сюда.
     */

    const placement =
        $b24.placement || {};


    const placementOptions =
        placement.options || {};


    const entityId =
        placementOptions
            .ENTITY_VALUE_ID ||

        (
            placementOptions
                .ENTITY_DATA &&
            placementOptions
                .ENTITY_DATA
                .entityId
        ) ||

        null;


    const entityType =
        String(
            placementOptions
                .ENTITY_ID ||
            ''
        );


    /*
     * Наше большое поле должно
     * работать именно в сделке.
     */

    if (
        entityType &&
        entityType !==
            'CRM_DEAL'
    ) {

        throw new Error(
            'Поле открыто не в сделке: ' +
            entityType
        );
    }


    const dealId =
        Number(
            entityId
        );


    if (
        !Number.isInteger(
            dealId
        ) ||
        dealId <= 0
    ) {

        throw new Error(
            'Не удалось определить ID сделки'
        );
    }


    /*
     * Сначала настройки приложения.
     */

    const options =
        await b24Call(
            $b24,
            'app.option.get',
            {}
        );


    const cityField =
        String(
            options
                .dealCityField ||
            ''
        );


    const regionField =
        String(
            options
                .dealRegionField ||
            ''
        );


    if (!cityField) {

        throw new Error(
            'В настройках не выбрано поле города сделки'
        );
    }


    /*
     * Затем сама сделка.
     */

    const deal =
        await b24Call(
            $b24,
            'crm.deal.get',
            {
                id:
                    dealId
            }
        );


    /*
     * И метаданные полей —
     * нужны для полей-списков.
     */

    const fields =
        await b24Call(
            $b24,
            'crm.deal.fields',
            {}
        );


    /*
     * Город
     */

    const city =
        FederalDistrict
            .parseFieldValue(
                deal[
                    cityField
                ],

                fields[
                    cityField
                ]
            );


    /*
     * Область
     */

    const region =
        regionField
            ? FederalDistrict
                .parseFieldValue(
                    deal[
                        regionField
                    ],

                    fields[
                        regionField
                    ]
                )
            : '';


    if (!city) {

        renderError(
            'Город не заполнен',
            'Заполните город в сделке.'
        );

        return;
    }


    /*
     * ВАЖНО:
     *
     * ФО считается только по:
     *
     * город + область сделки.
     *
     * Строковое поле сделки
     * тут НЕ используется.
     */

    const result =
        FederalDistrict
            .calcDistrictName(
                city,
                region
            );


    if (
        result.status ===
        'need_region'
    ) {

        renderNeedRegion(
            city
        );

        return;
    }


    if (
        result.status !==
            'ok' ||
        !result.districtName
    ) {

        wrapEl.className =
            'wrap bad';


        mainEl.textContent =
            'Округ не определён';


        managerEl.style.display =
            'none';


        placeEl.textContent =
            region
                ? city +
                  ', ' +
                  region
                : city;


        smallEl.textContent =
            'Проверьте город и область сделки.';


        return;
    }


    const manager =
        getManager(
            options,
            result.districtKey
        );


    renderOk(
        result.districtName,
        manager,
        city,
        region,
        result.source
    );
}


/*
 * ============================================================
 * СТАРТ
 * ============================================================
 */

start()
    .catch(
        function(error) {

            console.error(
                error
            );


            renderError(
                'Ошибка загрузки',
                String(
                    error &&
                    error.message
                        ? error.message
                        : error
                )
            );
        }
    );

</script>

</body>
</html>
