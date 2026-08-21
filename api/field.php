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
Федеральный округ
</title>

<script src="https://api.bitrix24.com/api/v1/"></script>

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

    font-size: 24px;
    line-height: 1.1;
    font-weight: 800;

    color: #111827;
}

.manager {
    display: inline-flex;
    align-items: center;

    padding: 5px 9px;

    border-radius: 999px;

    background: #e8f1ff;
    color: #1456a3;

    font-size: 13px;
    font-weight: 700;
}

.small {
    margin-top: 7px;

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
            #fff 75%
        );
}

.warn .main {
    color: #9a6a00;
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
    class="small"
    id="small"
></div>

</div>

<script>

const DEAL_FED_FIELD =
    'UF_CRM_FEDERAL_DISTRICT_TEXT';

const wrapEl =
    document.getElementById('wrap');

const mainEl =
    document.getElementById('main');

const managerEl =
    document.getElementById('manager');

const smallEl =
    document.getElementById('small');

const DEFAULT_MANAGERS = {

    'Центральный':
        'Людмила',

    'Северо-Западный':
        'Виктория',

    'Южный':
        'Вячеслав',

    'Северо-Кавказский':
        'Вячеслав',

    'Приволжский':
        'Виктория',

    'Уральский':
        'Вячеслав',

    'Сибирский':
        'Людмила',

    'Дальневосточный':
        'Людмила'
};

function bxCall(
    method,
    params = {},
    timeout = 15000
) {

    return new Promise(
        (resolve, reject) => {

            let finished = false;

            const timer =
                setTimeout(() => {

                    if (!finished) {

                        finished = true;

                        reject(
                            new Error(
                                'Таймаут ' +
                                method
                            )
                        );
                    }

                }, timeout);

            BX24.callMethod(
                method,
                params,
                function(res) {

                    if (finished) {
                        return;
                    }

                    finished = true;

                    clearTimeout(timer);

                    if (!res) {

                        reject(
                            new Error(
                                'Пустой ответ'
                            )
                        );

                        return;
                    }

                    if (res.error()) {

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

function getManager(
    district,
    options
) {

    const custom = {

        'Центральный':
            options.managerCentral,

        'Северо-Западный':
            options.managerNorthwest,

        'Южный':
            options.managerSouth,

        'Северо-Кавказский':
            options.managerNorthCaucasus,

        'Приволжский':
            options.managerVolga,

        'Уральский':
            options.managerUral,

        'Сибирский':
            options.managerSiberian,

        'Дальневосточный':
            options.managerFarEast
    };

    return (
        custom[district] ||
        DEFAULT_MANAGERS[district] ||
        ''
    );
}

function renderEmpty() {

    wrapEl.className =
        'wrap warn';

    mainEl.textContent =
        'ФО не заполнен';

    managerEl.style.display =
        'none';

    smallEl.textContent =
        'Федеральный округ должен прийти из исходного лида.';
}

BX24.init(
    async function() {

        try {

            const info =
                BX24.placement.info();

            const options =
                info &&
                info.options
                    ? info.options
                    : {};

            const dealId =
                options.ENTITY_VALUE_ID ||
                options.ID ||
                options.id ||
                options.DEAL_ID ||
                (
                    options.ENTITY_DATA &&
                    (
                        options
                            .ENTITY_DATA
                            .entityId ||

                        options
                            .ENTITY_DATA
                            .id
                    )
                ) ||
                null;

            if (!dealId) {
                renderEmpty();
                return;
            }

            const [
                deal,
                appOptions
            ] =
                await Promise.all([
                    bxCall(
                        'crm.deal.get',
                        {
                            id: dealId
                        }
                    ),

                    bxCall(
                        'app.option.get',
                        {}
                    )
                ]);

            const district =
                String(
                    deal[
                        DEAL_FED_FIELD
                    ] || ''
                ).trim();

            if (!district) {
                renderEmpty();
                return;
            }

            const manager =
                getManager(
                    district,
                    appOptions || {}
                );

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

            smallEl.textContent =
                'Федеральный округ получен из лида';

            if (
                window.BX24 &&
                BX24.fitWindow
            ) {
                BX24.fitWindow();
            }

        } catch (e) {

            wrapEl.className =
                'wrap warn';

            mainEl.textContent =
                'Ошибка загрузки';

            managerEl.style.display =
                'none';

            smallEl.textContent =
                'Обновите карточку сделки.';
        }
    }
);

</script>

</body>
</html>
