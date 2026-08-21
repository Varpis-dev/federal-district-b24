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
    font-family: Arial, sans-serif;
    padding: 24px;
    color: #111;
}

h1 {
    margin-top: 0;
    font-size: 30px;
}

.section {
    margin-top: 20px;
    padding: 18px;
    max-width: 920px;
    background: #f6f8fa;
    border-radius: 14px;
}

.info {
    max-width: 920px;
    box-sizing: border-box;
    padding: 14px 16px;
    background: #edf7ff;
    border: 1px solid #cde5fb;
    border-radius: 12px;
    line-height: 1.45;
    color: #36536c;
}

label {
    display: block;
    margin: 14px 0 6px;
    font-weight: 700;
}

select,
input {
    box-sizing: border-box;
    width: 100%;
    max-width: 700px;
    padding: 9px 11px;
    font-size: 14px;
    border: 1px solid #c8d0da;
    border-radius: 8px;
    background: white;
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
    margin-top: 18px;
    max-width: 920px;
    min-height: 100px;
    box-sizing: border-box;
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

Теперь федеральный округ рассчитывается
<strong>на этапе лида</strong>.

<br><br>

При создании сделки приложение переносит
готовый ФО из исходного лида.
Если ФО в сделке уже заполнен —
приложение его не меняет.

</div>

<div class="section">

<h2>
Поля лида
</h2>

<label for="leadCityField">
Поле с городом в лиде
</label>

<select id="leadCityField"></select>

<label for="leadRegionField">
Поле с областью / регионом в лиде
</label>

<select id="leadRegionField"></select>

</div>

<div class="section">

<h2>
Менеджеры по федеральным округам
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

<button
    id="bindBtn"
>
Создать поля и подключить события
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
    document.getElementById('status');

const recalcBtn =
    document.getElementById('recalcBtn');

function setStatus(text) {
    statusEl.textContent = text;
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
                setTimeout(() => {

                    if (!finished) {
                        finished = true;

                        reject(
                            new Error(
                                'Таймаут ' + method
                            )
                        );
                    }

                }, timeoutMs);

            try {

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

            } catch (e) {

                if (!finished) {
                    finished = true;
                    clearTimeout(timer);
                    reject(e);
                }
            }
        }
    );
}

function getVal(id) {
    return (
        document
            .getElementById(id)
            .value || ''
    ).trim();
}

function setVal(id, value) {

    document
        .getElementById(id)
        .value =
        value || '';
}

function applyManagers(options) {

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
        'Загружаю поля лидов...'
    );

    const options =
        await bxCall(
            'app.option.get',
            {}
        );

    const fields =
        await bxCall(
            'crm.lead.fields',
            {}
        );

    const citySelect =
        document.getElementById(
            'leadCityField'
        );

    const regionSelect =
        document.getElementById(
            'leadRegionField'
        );

    citySelect.innerHTML = '';
    regionSelect.innerHTML = '';

    const cityEmpty =
        document.createElement(
            'option'
        );

    cityEmpty.value = '';
    cityEmpty.textContent =
        'Выберите поле города';

    citySelect.appendChild(
        cityEmpty
    );

    const regionEmpty =
        document.createElement(
            'option'
        );

    regionEmpty.value = '';
    regionEmpty.textContent =
        'Не использовать область';

    regionSelect.appendChild(
        regionEmpty
    );

    const savedCity =
        options.leadCityField || '';

    const savedRegion =
        options.leadRegionField || '';

    Object.keys(fields || {})
        .forEach(code => {

            const meta =
                fields[code] || {};

            const title =
                meta.formLabel ||
                meta.listLabel ||
                meta.title ||
                code;

            const cityOption =
                document.createElement(
                    'option'
                );

            cityOption.value =
                code;

            cityOption.textContent =
                code +
                ' — ' +
                title;

            if (
                code === savedCity
            ) {
                cityOption.selected =
                    true;
            }

            citySelect.appendChild(
                cityOption
            );

            const regionOption =
                document.createElement(
                    'option'
                );

            regionOption.value =
                code;

            regionOption.textContent =
                code +
                ' — ' +
                title;

            if (
                code === savedRegion
            ) {
                regionOption.selected =
                    true;
            }

            regionSelect.appendChild(
                regionOption
            );
        });

    applyManagers(options || {});

    setStatus(
        'Поля лидов загружены.'
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

    setStatus(
        'Сохраняю настройки...'
    );

    await bxCall(
        'app.option.set',
        {
            options: payload
        }
    );

    setStatus(
        'Настройки сохранены.\n\n' +

        'Город лида: ' +
        payload.leadCityField +
        '\n' +

        'Область лида: ' +
        (
            payload.leadRegionField ||
            'не используется'
        )
    );

    return payload;
}

async function createFieldsAndEvents() {

    const saved =
        await saveSettings();

    if (!saved) return;

    const auth =
        BX24.getAuth();

    if (
        !auth ||
        !auth.access_token ||
        !auth.domain
    ) {
        setStatus(
            'Не удалось получить AUTH.'
        );
        return;
    }

    setStatus(
        'Создаю поля и подключаю события...'
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

async function recalcOldLeads() {

    const saved =
        await saveSettings();

    if (!saved) return;

    const auth =
        BX24.getAuth();

    if (
        !auth ||
        !auth.access_token ||
        !auth.domain
    ) {
        setStatus(
            'Не удалось получить AUTH.'
        );
        return;
    }

    recalcBtn.disabled = true;

    let start = 0;

    const total = {
        processed: 0,
        updated: 0,
        already_actual: 0,
        unknown: 0,
        need_region: 0,
        no_city: 0,
        cleared: 0,
        errors: 0
    };

    try {

        while (true) {

            const response =
                await fetch(
                    '/recalc-leads',
                    {
                        method: 'POST',

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

            const stats =
                data.stats || {};

            Object.keys(total)
                .forEach(key => {

                    total[key] +=
                        Number(
                            stats[key] || 0
                        );
                });

            setStatus(
                'Пересчёт старых лидов...\n\n' +

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

                'Очищено: ' +
                total.cleared +
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

            /*
             * Немного разгружаем REST Б24.
             */
            await new Promise(
                resolve =>
                    setTimeout(
                        resolve,
                        350
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
                            await createFieldsAndEvents();
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
                    recalcOldLeads
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
