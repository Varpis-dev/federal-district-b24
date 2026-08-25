<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="ru">

<head>

<meta charset="UTF-8">

<title>Федеральный округ</title>

<script src="https://api.bitrix24.com/api/v1/"></script>
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
    min-height: 120px;
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


window.addEventListener(
    'error',
    function(event) {

        renderError(
            'Ошибка JavaScript',
            event.message ||
            'Неизвестная ошибка'
        );
    }
);


function bxCall(
    method,
    params = {},
    timeoutMs = 20000
) {

    return new Promise(
        (resolve, reject) => {

            let finished =
                false;


            const timer =
                setTimeout(
                    function() {

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
                    timeoutMs
                );


            try {

                BX24.callMethod(
                    method,
                    params,
                    function(result) {

                        if (finished) {
                            return;
                        }


                        finished =
                            true;


                        clearTimeout(
                            timer
                        );


                        if (!result) {

                            reject(
                                new Error(
                                    'Пустой ответ ' +
                                    method
                                )
                            );

                            return;
                        }


                        if (
                            result.error()
                        ) {

                            reject(
                                new Error(
                                    method +
                                    ': ' +
                                    JSON.stringify(
                                        result.error()
                                    )
                                )
                            );

                            return;
                        }


                        resolve(
                            result.data()
                        );
                    }
                );

            } catch (error) {

                clearTimeout(
                    timer
                );

                reject(
                    error
                );
            }
        }
    );
}


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


    if (
        typeof BX24 !==
            'undefined' &&
        typeof BX24.fitWindow ===
            'function'
    ) {

        BX24.fitWindow();
    }
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


async function loadField() {

    if (
        typeof FederalDistrict ===
        'undefined'
    ) {

        throw new Error(
            'Не загрузился /district.js'
        );
    }


    const info =
        BX24.placement.info();


    const placement =
        info &&
        info.options
            ? info.options
            : {};


    const dealId =
        placement.ENTITY_VALUE_ID ||

        placement.ID ||

        placement.id ||

        placement.DEAL_ID ||

        (
            placement.ENTITY_DATA &&
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

        throw new Error(
            'Не удалось определить ID сделки'
        );
    }


    /*
     * Все REST-запросы выполняет браузер
     * напрямую в Bitrix24.
     *
     * Vercel Function их НЕ ждёт.
     */
    const options =
        await bxCall(
            'app.option.get',
            {}
        );


    const deal =
        await bxCall(
            'crm.deal.get',
            {
                id:
                    dealId
            }
        );


    const fields =
        await bxCall(
            'crm.deal.fields',
            {}
        );


    const cityField =
        options.dealCityField ||
        '';


    const regionField =
        options.dealRegionField ||
        '';


    if (!cityField) {

        throw new Error(
            'В настройках приложения не выбрано поле города сделки'
        );
    }


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
     * Главное:
     * строковое поле сделки тут
     * вообще не используется.
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


if (
    typeof BX24 ===
    'undefined'
) {

    renderError(
        'BX24 не загрузился',
        'Не загрузилась библиотека Bitrix24.'
    );

} else {

    BX24.init(
        function() {

            loadField()
                .catch(
                    function(error) {

                        renderError(
                            'Ошибка загрузки',
                            String(
                                error.message ||
                                error
                            )
                        );
                    }
                );
        }
    );
}

</script>

</body>
</html>
