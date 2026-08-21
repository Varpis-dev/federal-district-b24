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
Федеральный округ — настройки
</title>

<script src="https://api.bitrix24.com/api/v1/"></script>

<style>

body {
    margin: 0;
    padding: 24px;
    font-family: Arial, sans-serif;
    color: #111;
}

h1 {
    margin-top: 0;
}

.section {
    max-width: 920px;
    margin-top: 20px;
    padding: 18px;

    background: #f6f8fa;

    border-radius: 14px;
}

.info {
    box-sizing: border-box;

    max-width: 920px;

    padding: 14px 16px;

    background: #edf7ff;
    border: 1px solid #cde5fb;
    border-radius: 12px;

    color: #36536c;

    line-height: 1.45;
}

label {
    display: block;

    margin:
        14px
        0
        6px;

    font-weight: 700;
}

select,
input {
    box-sizing: border-box;

    width: 100%;
    max-width: 700px;

    padding: 9px 11px;

    font-size: 14px;

    background: white;

    border: 1px solid #c8d0da;
    border-radius: 8px;
}

.grid {
    display: grid;

    grid-template-columns:
        minmax(220px, 320px)
        minmax(220px, 360px);

    gap: 12px 18px;

    align-items: center;

    max-width: 760px;
}

.district-label {
    font-weight: 700;
}

.buttons {
    display: flex;
    flex-wrap: wrap;

    gap: 10px;

    margin-top: 20px;
}

button {
    padding: 11px 15px;

    border-radius: 9px;
    border: 1px solid #b8c2cc;

    background: white;

    font-weight: 700;

    cursor: pointer;
}

button.primary {
    background: #0b7cff;
    border-color: #0b7cff;
    color: white;
}

button.green {
    background: #18a35b;
    border-color: #18a35b;
    color: white;
}

button:disabled {
    opacity: .55;
    cursor: not-allowed;
}

#status {
    box-sizing: border-box;

    max-width: 920px;
    min-height: 110px;

    margin-top: 18px;
    padding: 14px;

    background: white;

    border: 1px solid #e0e5eb;
    border-radius: 12px;

    white-space: pre-wrap;

    color: #444;
}

</style>

</head>

<body>

<h1>
Федеральный округ
</h1>

<div class="info">

<strong>Лиды:</strong>
приложение автоматически определяет ФО
по городу + области и записывает его
в обычное строковое поле лида.

<br><br>

<strong>Сделки:</strong>
приложение НЕ заполняет строковое поле сделки.
Большое визуальное поле независимо
рассчитывает ФО по городу + области сделки.

</div>

<div class="section">

<h2>
Лид — источник строкового ФО
</h2>

<label for="leadCityField">
Поле города в лиде
</label>

<select id="leadCityField"></select>

<label for="leadRegionField">
Поле области / региона в лиде
</label>

<select id="leadRegionField"></select>

</div>

<div class="section">

<h2>
Сделка — большое визуальное поле
</h2>

<label for="dealCityField">
Поле города в сделке
</label>

<select id="dealCityField"></select>

<label for="dealRegionField">
Поле области / региона в сделке
</label>

<select id="dealRegionField"></select>

</div>

<div class="section">

<h2>
Менеджеры по округам
</h2>

<div class="grid">

<div class="district-label">
Центральный
</div>

<input
    id="managerCentral"
    value="Людмила"
>

<div class="district-label">
Северо-Западный
</div>

<input
    id="managerNorthwest"
    value="Виктория"
>

<div class="district-label">
Южный
</div>

<input
    id="managerSouth"
    value="Вячеслав"
>

<div class="district-label">
Северо-Кавказский
</div>

<input
    id="managerNorthCaucasus"
    value="Вячеслав"
>

<div class="district-label">
Приволжский
</div>

<input
    id="managerVolga"
    value="Виктория"
>

<div class="district-label">
Уральский
</div>

<input
    id="managerUral"
    value="Вячеслав"
>

<div class="district-label">
Сибирский
</div>

<input
    id="managerSiberian"
    value="Людмила"
>

<div class="district-label">
Дальневосточный
</div>

<input
    id="managerFarEast"
    value="Людмила"
>

</div>

</div>

<div class="buttons">

<button
    class="primary"
    id="saveBtn"
>
Сохранить настройки
</button>

<button id="bindBtn">
Создать поля и подключить лиды
</button>

<button
    class="green"
    id="recalcBtn"
>
Пересчитать старые лиды
</button>

</div>

<div id="status">
Загрузка...
</div>

<script>

const statusEl =
    document.getElementById(
        'status'
    );

const recalcBtn =
    document.getElementById(
        'recalcBtn'
    );

function setStatus(text) {
    statusEl.textContent =
        text;
}

function bxCall(
    method,
    params = {},
    timeoutMs = 15000
) {
    return new Promise(
        (resolve, reject) => {

            let finished = false;

            const timer =
                setTimeout(
                    () => {

                        if (!finished) {
                            finished = true;

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

            BX24.callMethod(
                method,
                params,
                function(res) {

                    if (finished) {
                        return;
                    }

                    finished = true;

                    clearTimeout(
                        timer
                    );

                    if (!res) {
                        reject(
                            new Error(
                                'Пустой ответ ' +
                                method
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

function getVal(id) {
    return (
        document
            .getElementById(id)
            .value ||
        ''
    ).trim();
}

function setVal(
    id,
    value
) {
    document
        .getElementById(id)
        .value =
        value || '';
}

function makeEmptyOption(
    select,
    text
) {
    const option =
        document.createElement(
            'option'
        );

    option.value = '';
    option.textContent =
        text;

    select.appendChild(
        option
    );
}

function fillSelect(
    select,
    fields,
    savedValue
) {
    Object.keys(
        fields || {}
    ).forEach(
        code => {

            const field =
                fields[code] ||
                {};

            const title =
                field.formLabel ||
                field.listLabel ||
                field.title ||
                code;

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                code;

            option.textContent =
                code +
                ' — ' +
                title;

            if (
                code ===
                savedValue
            ) {
                option.selected =
                    true;
            }

            select.appendChild(
                option
            );
        }
    );
}

function applyManagers(
    options
) {
    setVal(
        'managerCentral',
        options.managerCentral ||
        'Людмила'
    );

    setVal(
        'managerNorthwest',
        options.managerNorthwest ||
        'Виктория'
    );

    setVal(
        'managerSouth',
        options.managerSouth ||
        'Вячеслав'
    );

    setVal(
        'managerNorthCaucasus',
        options.managerNorthCaucasus ||
        'Вячеслав'
    );

    setVal(
        'managerVolga',
        options.managerVolga ||
        'Виктория'
    );

    setVal(
        'managerUral',
        options.managerUral ||
        'Вячеслав'
    );

    setVal(
        'managerSiberian',
        options.managerSiberian ||
        'Людмила'
    );

    setVal(
        'managerFarEast',
        options.managerFarEast ||
        'Людмила'
    );
}

async function loadFields() {

    setStatus(
        'Загружаю настройки и поля...'
    );

    const [
        options,
        leadFields,
        dealFields
    ] =
        await Promise.all([
            bxCall(
                'app.option.get',
                {}
            ),

            bxCall(
                'crm.lead.fields',
                {}
            ),

            bxCall(
                'crm.deal.fields',
                {}
            )
        ]);

    const leadCity =
        document.getElementById(
            'leadCityField'
        );

    const leadRegion =
        document.getElementById(
            'leadRegionField'
        );

    const dealCity =
        document.getElementById(
            'dealCityField'
        );

    const dealRegion =
        document.getElementById(
            'dealRegionField'
        );

    leadCity.innerHTML = '';
    leadRegion.innerHTML = '';
    dealCity.innerHTML = '';
    dealRegion.innerHTML = '';

    makeEmptyOption(
        leadCity,
        'Выберите поле города'
    );

    makeEmptyOption(
        leadRegion,
        'Не использовать область'
    );

    makeEmptyOption(
        dealCity,
        'Выберите поле города'
    );

    makeEmptyOption(
        dealRegion,
        'Не использовать область'
    );

    fillSelect(
        leadCity,
        leadFields,
        options.leadCityField ||
        ''
    );

    fillSelect(
        leadRegion,
        leadFields,
        options.leadRegionField ||
        ''
    );

    fillSelect(
        dealCity,
        dealFields,
        options.dealCityField ||
        ''
    );

    fillSelect(
        dealRegion,
        dealFields,
        options.dealRegionField ||
        ''
    );

    applyManagers(
        options || {}
    );

    setStatus(
        'Настройки загружены.'
    );
}

function getPayload() {
    return {
        appName:
            'Федеральный округ',

        leadCityField:
            getVal(
                'leadCityField'
            ),

        leadRegionField:
            getVal(
                'leadRegionField'
            ),

        dealCityField:
            getVal(
                'dealCityField'
            ),

        dealRegionField:
            getVal(
                'dealRegionField'
            ),

        managerCentral:
            getVal(
                'managerCentral'
            ) || 'Людмила',

        managerNorthwest:
            getVal(
                'managerNorthwest'
            ) || 'Виктория',

        managerSouth:
            getVal(
                'managerSouth'
            ) || 'Вячеслав',

        managerNorthCaucasus:
            getVal(
                'managerNorthCaucasus'
            ) || 'Вячеслав',

        managerVolga:
            getVal(
                'managerVolga'
            ) || 'Виктория',

        managerUral:
            getVal(
                'managerUral'
            ) || 'Вячеслав',

        managerSiberian:
            getVal(
                'managerSiberian'
            ) || 'Людмила',

        managerFarEast:
            getVal(
                'managerFarEast'
            ) || 'Людмила'
    };
}

async function saveSettings() {

    const payload =
        getPayload();

    if (
        !payload.leadCityField
    ) {
        setStatus(
            'Не выбрано поле города лида.'
        );

        return null;
    }

    if (
        !payload.dealCityField
    ) {
        setStatus(
            'Не выбрано поле города сделки.'
        );

        return null;
    }

    setStatus(
        'Сохраняю настройки...'
    );

    await bxCall(
        'app.option.set',
        {
            options:
                payload
        }
    );

    setStatus(
        'Настройки сохранены ✅\n\n' +

        'ЛИД\n' +
        'Город: ' +
        payload.leadCityField +
        '\n' +

        'Область: ' +
        (
            payload.leadRegionField ||
            'не используется'
        ) +

        '\n\nСДЕЛКА\n' +

        'Город: ' +
        payload.dealCityField +
        '\n' +

        'Область: ' +
        (
            payload.dealRegionField ||
            'не используется'
        )
    );

    return payload;
}

async function bind() {

    const saved =
        await saveSettings();

    if (!saved) return;

    const auth =
        BX24.getAuth();

    setStatus(
        'Создаю поля и подключаю события лидов...'
    );

    const response =
        await fetch(
            '/bind',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json'
                },

                body:
                    JSON.stringify({
                        auth
                    })
            }
        );

    const data =
        await response.json();

    setStatus(
        JSON.stringify(
            data,
            null,
            2
        )
    );
}

async function recalc() {

    const saved =
        await saveSettings();

    if (!saved) return;

    const auth =
        BX24.getAuth();

    recalcBtn.disabled =
        true;

    let start = 0;

    const total = {
        processed: 0,
        updated: 0,
        already_actual: 0,
        no_city: 0,
        need_region: 0,
        unknown: 0,
        cleared: 0,
        errors: 0
    };

    try {

        while (true) {

            const response =
                await fetch(
                    '/recalc-leads',
                    {
                        method:
                            'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                auth,
                                start
                            })
                    }
                );

            const data =
                await response.json();

            if (!data.success) {

                setStatus(
                    'Ошибка пересчёта:\n' +
                    JSON.stringify(
                        data,
                        null,
                        2
                    )
                );

                break;
            }

            Object
                .keys(total)
                .forEach(
                    key => {

                        total[key] +=
                            Number(
                                (
                                    data.stats ||
                                    {}
                                )[key] ||
                                0
                            );
                    }
                );

            setStatus(
                'Пересчёт лидов...\n\n' +

                'Обработано: ' +
                total.processed +
                '\n' +

                'Обновлено: ' +
                total.updated +
                '\n' +

                'Уже актуально: ' +
                total.already_actual +
                '\n' +

                'Нет города: ' +
                total.no_city +
                '\n' +

                'Нужна область: ' +
                total.need_region +
                '\n' +

                'Не определено: ' +
                total.unknown +
                '\n' +

                'Ошибки: ' +
                total.errors
            );

            if (
                data.next === null ||
                typeof data.next ===
                'undefined'
            ) {

                setStatus(
                    'ПЕРЕСЧЁТ ЗАВЕРШЁН ✅\n\n' +

                    'Обработано: ' +
                    total.processed +
                    '\n' +

                    'Обновлено: ' +
                    total.updated +
                    '\n' +

                    'Уже актуально: ' +
                    total.already_actual +
                    '\n' +

                    'Нет города: ' +
                    total.no_city +
                    '\n' +

                    'Нужна область: ' +
                    total.need_region +
                    '\n' +

                    'Не определено: ' +
                    total.unknown +
                    '\n' +

                    'Ошибки: ' +
                    total.errors
                );

                break;
            }

            start =
                data.next;

            await new Promise(
                resolve =>
                    setTimeout(
                        resolve,
                        400
                    )
            );
        }

    } catch (e) {

        setStatus(
            'Ошибка пересчёта:\n' +
            String(e)
        );

    } finally {

        recalcBtn.disabled =
            false;
    }
}

BX24.init(
    async function() {

        try {

            await loadFields();

            document
                .getElementById(
                    'saveBtn'
                )
                .addEventListener(
                    'click',
                    async () => {

                        try {
                            await saveSettings();
                        } catch (e) {
                            setStatus(
                                String(e)
                            );
                        }
                    }
                );

            document
                .getElementById(
                    'bindBtn'
                )
                .addEventListener(
                    'click',
                    async () => {

                        try {
                            await bind();
                        } catch (e) {
                            setStatus(
                                String(e)
                            );
                        }
                    }
                );

            document
                .getElementById(
                    'recalcBtn'
                )
                .addEventListener(
                    'click',
                    recalc
                );

        } catch (e) {

            setStatus(
                'Ошибка загрузки:\n' +
                String(e)
            );
        }
    }
);

</script>

</body>
</html>
