<?php

header(
    'Content-Type: text/html; charset=utf-8'
);

?>
<!DOCTYPE html>
<html lang="ru">

<head>

<meta charset="UTF-8">

<title>
Федеральный округ сделки
</title>

<script src="https://api.bitrix24.com/api/v1/"></script>

<style>

html,
body {
    margin: 0;
    padding: 0;

    background: transparent;

    overflow: hidden;

    font-family:
        Arial,
        sans-serif;
}

.wrap {
    box-sizing: border-box;

    width: 100%;

    padding:
        12px
        14px;

    border-radius: 16px;

    border:
        1px solid
        #d7e3f5;

    background:
        linear-gradient(
            135deg,
            #f4f8ff 0%,
            #ffffff 75%
        );

    box-shadow:
        0 4px 14px
        rgba(
            24,
            91,
            170,
            .08
        );
}

.label {
    margin-bottom: 6px;

    font-size: 12px;

    color: #7b8794;

    font-weight: 700;

    text-transform:
        uppercase;

    letter-spacing:
        .04em;
}

.main {
    margin-bottom: 8px;

    font-size: 24px;

    line-height: 1.1;

    font-weight: 800;

    color: #111827;
}

.manager {
    display: inline-flex;

    align-items: center;

    padding:
        5px
        9px;

    border-radius:
        999px;

    background:
        #e8f1ff;

    color:
        #1456a3;

    font-size:
        13px;

    font-weight:
        700;

    margin-bottom:
        8px;
}

.place {
    font-size:
        13px;

    color:
        #4b5563;

    line-height:
        1.3;

    font-weight:
        600;
}

.small {
    margin-top:
        6px;

    font-size:
        12px;

    color:
        #7b8794;

    line-height:
        1.35;
}

.warn {
    border-color:
        #f1d48a;

    background:
        linear-gradient(
            135deg,
            #fff8e6 0%,
            #ffffff 75%
        );
}

.warn .main {
    color:
        #9a6a00;
}

.bad {
    border-color:
        #efb7b3;

    background:
        linear-gradient(
            135deg,
            #fff1f0 0%,
            #ffffff 75%
        );
}

.bad .main {
    color:
        #d92d20;
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

function bxCall(
    method,
    params = {},
    timeout = 15000
) {
    return new Promise(
        (resolve, reject) => {

            let finished =
                false;

            const timer =
                setTimeout(
                    () => {

                        if (!finished) {

                            finished =
                                true;

                            reject(
                                new Error(
                                    'Таймаут ' +
                                    method
                                )
                            );
                        }

                    },
                    timeout
                );

            BX24.callMethod(
                method,
                params,
                function(res) {

                    if (finished) {
                        return;
                    }

                    finished =
                        true;

                    clearTimeout(
                        timer
                    );

                    if (!res) {

                        reject(
                            new Error(
                                'Пустой ответ'
                            )
                        );

                        return;
                    }

                    if (
                        res.error()
                    ) {

                        reject(
                            new Error(
                                JSON.stringify(
                                    res.error()
                                )
                            )
                        );

                        return;
                    }

                    resolve(
                        res.data()
                    );
                }
            );
        }
    );
}

function getFieldEnumItems(
    fieldMeta
) {
    if (!fieldMeta) {
        return [];
    }

    return (
        fieldMeta.items ||
        fieldMeta.ITEMS ||
        fieldMeta.list ||
        fieldMeta.LIST ||
        fieldMeta.values ||
        fieldMeta.VALUES ||
        []
    );
}

function parseFieldValue(
    rawValue,
    fieldCode,
    fieldsMeta
) {
    if (
        Array.isArray(
            rawValue
        )
    ) {
        rawValue =
            rawValue[0] ||
            '';
    }

    if (
        typeof rawValue ===
            'object' &&
        rawValue !== null
    ) {
        rawValue =
            rawValue.VALUE ||
            rawValue.value ||
            rawValue.ID ||
            rawValue.id ||
            '';
    }

    let value =
        rawValue ||
        '';

    const fieldMeta =
        fieldsMeta
            ? fieldsMeta[fieldCode]
            : null;

    const items =
        getFieldEnumItems(
            fieldMeta
        );

    if (
        items &&
        items.length
    ) {
        const found =
            items.find(
                item => {

                    const id =
                        String(
                            item.ID ||
                            item.id ||
                            item.VALUE_ID ||
                            item.valueId ||
                            ''
                        );

                    const val =
                        String(
                            item.VALUE ||
                            item.value ||
                            item.NAME ||
                            item.name ||
                            ''
                        );

                    return (
                        id ===
                            String(value) ||
                        val ===
                            String(value)
                    );
                }
            );

        if (found) {
            return (
                found.VALUE ||
                found.value ||
                found.NAME ||
                found.name ||
                value
            );
        }
    }

    return value;
}

function getManager(
    options,
    key
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
        managers[key] ||
        ''
    );
}

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

function renderUnknown(
    city,
    region
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
}

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

BX24.init(
    async function() {

        try {

            const info =
                BX24.placement.info();

            const placement =
                info &&
                info.options
                    ? info.options
                    : {};

            const dealId =
                placement
                    .ENTITY_VALUE_ID ||

                placement.ID ||

                placement.id ||

                placement.DEAL_ID ||

                (
                    placement
                        .ENTITY_DATA &&
                    (
                        placement
                            .ENTITY_DATA
                            .entityId ||

                        placement
                            .ENTITY_DATA
                            .id
                    )
                ) ||

                null;

            if (!dealId) {

                renderError(
                    'Нет ID сделки',
                    'Не удалось определить ID сделки.'
                );

                return;
            }

            const [
                options,
                deal,
                fields
            ] =
                await Promise.all([
                    bxCall(
                        'app.option.get',
                        {}
                    ),

                    bxCall(
                        'crm.deal.get',
                        {
                            id: dealId
                        }
                    ),

                    bxCall(
                        'crm.deal.fields',
                        {}
                    )
                ]);

            const cityField =
                options.dealCityField ||
                '';

            const regionField =
                options.dealRegionField ||
                '';

            if (!cityField) {

                renderError(
                    'Не выбрано поле города',
                    'Откройте настройки приложения.'
                );

                return;
            }

            const city =
                parseFieldValue(
                    deal[cityField],
                    cityField,
                    fields
                );

            const region =
                regionField
                    ? parseFieldValue(
                        deal[
                            regionField
                        ],
                        regionField,
                        fields
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
             * большое поле НЕ ЧИТАЕТ
             * строковое поле ФО.
             *
             * Оно каждый раз считает
             * ФО по город + область.
             */

            const response =
                await fetch(
                    '/calc',
                    {
                        method:
                            'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                city,
                                region
                            })
                    }
                );

            const data =
                await response.json();

            const result =
                data.result ||
                {};

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
                result.status !== 'ok' ||
                !result.districtName
            ) {

                renderUnknown(
                    city,
                    region
                );

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

            if (
                window.BX24 &&
                BX24.fitWindow
            ) {
                BX24.fitWindow();
            }

        } catch (e) {

            renderError(
                'Ошибка загрузки',
                'Обновите карточку сделки.'
            );
        }
    }
);

</script>

</body>

</html>
