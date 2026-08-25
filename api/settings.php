<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="ru">

<head>

<meta charset="UTF-8">

<title>Федеральный округ — настройки</title>

<script src="https://api.bitrix24.com/api/v1/"></script>

<style>

body {
    margin: 0;
    padding: 24px;
    font-family: Arial, sans-serif;
    color: #111;
    background: #fff;
}

h1 {
    margin-top: 0;
    margin-bottom: 10px;
}

h2 {
    margin-top: 0;
}

.section {
    box-sizing: border-box;
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
    line-height: 1.5;
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

button:disabled {
    opacity: .55;
    cursor: not-allowed;
}

#status {
    box-sizing: border-box;
    max-width: 920px;
    min-height: 90px;
    margin-top: 18px;
    padding: 14px;
    border: 1px solid #e0e5eb;
    border-radius: 12px;
    background: white;
    white-space: pre-wrap;
    color: #444;
}

.error {
    border-color: #f0b4b4 !important;
    background: #fff4f4 !important;
    color: #b42318 !important;
}

</style>

</head>

<body>

<h1>Федеральный округ</h1>

<div class="info">

<strong>Лиды:</strong>
при создании или изменении лида приложение определяет
федеральный округ по городу + области и записывает его
в обычное строковое поле <strong>«Федеральный округ»</strong>.

<br><br>

<strong>Сделки:</strong>
строковое поле сделки приложение не изменяет.
Большое поле самостоятельно рассчитывает ФО
по городу + области сделки и показывает менеджера.

</div>


<div class="section">

<h2>Лид</h2>

<label for="leadCityField">
Поле города
</label>

<select id="leadCityField"></select>


<label for="leadRegionField">
Поле области / региона
</label>

<select id="leadRegionField"></select>

</div>


<div class="section">

<h2>Сделка — большое визуальное поле</h2>

<label for="dealCityField">
Поле города
</label>

<select id="dealCityField"></select>


<label for="dealRegionField">
Поле области / региона
</label>

<select id="dealRegionField"></select>

</div>


<div class="section">

<h2>Менеджеры по федеральным округам</h2>

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

</div>


<div id="status">
Инициализация Bitrix24...
</div>


<script>

const statusEl =
    document.getElementById(
        'status'
    );

const saveBtn =
    document.getElementById(
        'saveBtn'
    );

const bindBtn =
    document.getElementById(
        'bindBtn'
    );


function setStatus(
    text,
    isError = false
) {

    statusEl.textContent =
        text;

    statusEl.className =
        isError
            ? 'error'
            : '';
}


/*
 * Если будет реальная JS-ошибка,
 * она теперь не спрячется за белым экраном.
 */
window.addEventListener(
    'error',
    function(event) {

        setStatus(
            'Ошибка JavaScript:\n' +
            (
                event.message ||
                'Неизвестная ошибка'
            ),
            true
        );
    }
);


window.addEventListener(
    'unhandledrejection',
    function(event) {

        const reason =
            event.reason &&
            event.reason.message
                ? event.reason.message
                : String(
                    event.reason ||
                    'Неизвестная ошибка'
                );

        setStatus(
            'Ошибка Promise:\n' +
            reason,
            true
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
                                    'Таймаут Bitrix REST: ' +
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
                                    'Пустой ответ Bitrix: ' +
                                    method
                                )
                            );

                            return;
                        }


                        if (
                            typeof result.error ===
                                'function' &&
                            result.error()
                        ) {

                            let errorText =
                                '';

                            try {

                                errorText =
                                    JSON.stringify(
                                        result.error()
                                    );

                            } catch (e) {

                                errorText =
                                    String(
                                        result.error()
                                    );
                            }


                            reject(
                                new Error(
                                    method +
                                    ': ' +
                                    errorText
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


function getVal(id) {

    return String(
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


function fillSelect(
    element,
    fields,
    saved,
    emptyText
) {

    element.innerHTML =
        '';


    const empty =
        document.createElement(
            'option'
        );


    empty.value =
        '';

    empty.textContent =
        emptyText;


    element.appendChild(
        empty
    );


    Object.entries(
        fields || {}
    )
    .sort(
        function(a, b) {

            const titleA =
                (
                    a[1].formLabel ||
                    a[1].listLabel ||
                    a[1].title ||
                    a[0]
                ).toLowerCase();

            const titleB =
                (
                    b[1].formLabel ||
                    b[1].listLabel ||
                    b[1].title ||
                    b[0]
                ).toLowerCase();

            return titleA.localeCompare(
                titleB,
                'ru'
            );
        }
    )
    .forEach(
        function(entry) {

            const code =
                entry[0];

            const field =
                entry[1] || {};


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
                title +
                ' [' +
                code +
                ']';


            if (
                code === saved
            ) {

                option.selected =
                    true;
            }


            element.appendChild(
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


function getPayload() {

    return {

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
            ) ||
            'Людмила',

        managerNorthwest:
            getVal(
                'managerNorthwest'
            ) ||
            'Виктория',

        managerSouth:
            getVal(
                'managerSouth'
            ) ||
            'Вячеслав',

        managerNorthCaucasus:
            getVal(
                'managerNorthCaucasus'
            ) ||
            'Вячеслав',

        managerVolga:
            getVal(
                'managerVolga'
            ) ||
            'Виктория',

        managerUral:
            getVal(
                'managerUral'
            ) ||
            'Вячеслав',

        managerSiberian:
            getVal(
                'managerSiberian'
            ) ||
            'Людмила',

        managerFarEast:
            getVal(
                'managerFarEast'
            ) ||
            'Людмила'
    };
}


async function loadSettings() {

    setStatus(
        'Загружаю настройки приложения...'
    );


    /*
     * Сначала настройки.
     */
    const options =
        await bxCall(
            'app.option.get',
            {}
        );


    setStatus(
        'Загружаю поля лидов...'
    );


    /*
     * Потом лиды.
     * Не делаем 3 огромных запроса одновременно.
     */
    const leadFields =
        await bxCall(
            'crm.lead.fields',
            {}
        );


    setStatus(
        'Загружаю поля сделок...'
    );


    const dealFields =
        await bxCall(
            'crm.deal.fields',
            {}
        );


    fillSelect(
        document.getElementById(
            'leadCityField'
        ),
        leadFields,
        options.leadCityField ||
        '',
        'Выберите поле города'
    );


    fillSelect(
        document.getElementById(
            'leadRegionField'
        ),
        leadFields,
        options.leadRegionField ||
        '',
        'Не использовать область'
    );


    fillSelect(
        document.getElementById(
            'dealCityField'
        ),
        dealFields,
        options.dealCityField ||
        '',
        'Выберите поле города'
    );


    fillSelect(
        document.getElementById(
            'dealRegionField'
        ),
        dealFields,
        options.dealRegionField ||
        '',
        'Не использовать область'
    );


    applyManagers(
        options || {}
    );


    setStatus(
        'Настройки загружены ✅'
    );
}


async function saveSettings() {

    const payload =
        getPayload();


    if (
        !payload.leadCityField
    ) {

        throw new Error(
            'Не выбрано поле города лида.'
        );
    }


    if (
        !payload.dealCityField
    ) {

        throw new Error(
            'Не выбрано поле города сделки.'
        );
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
        'Настройки сохранены ✅'
    );


    return payload;
}


async function runBind() {

    await saveSettings();


    const auth =
        BX24.getAuth();


    if (
        !auth ||
        !auth.access_token ||
        !auth.domain
    ) {

        throw new Error(
            'Не удалось получить AUTH Bitrix24.'
        );
    }


    bindBtn.disabled =
        true;


    setStatus(
        'Переподключаю поля и события...'
    );


    try {

        const response =
            await fetch(
                '/bind',
                {
                    method:
                        'POST',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body:
                        JSON.stringify({
                            auth:
                                auth
                        })
                }
            );


        const text =
            await response.text();


        let data;


        try {

            data =
                JSON.parse(
                    text
                );

        } catch (e) {

            throw new Error(
                'Некорректный ответ /bind:\n' +
                text.slice(
                    0,
                    1500
                )
            );
        }


        if (!data.success) {

            throw new Error(
                JSON.stringify(
                    data,
                    null,
                    2
                )
            );
        }


        setStatus(
            'ГОТОВО ✅\n\n' +

            'Большое поле перепривязано к новому Vercel.\n' +

            'Старые события сделок удалены.\n' +

            'Подключены только события лидов:\n' +

            '• ONCRMLEADADD\n' +
            '• ONCRMLEADUPDATE\n\n' +

            'Handler большого поля:\n' +
            (
                data.visual_field_handler ||
                'не указан'
            ) +

            '\n\nHandler событий:\n' +
            (
                data.events &&
                data.events.new_handler
                    ? data.events.new_handler
                    : 'не указан'
            )
        );

    } finally {

        bindBtn.disabled =
            false;
    }
}


function startApplication() {

    if (
        typeof BX24 ===
        'undefined'
    ) {

        setStatus(
            'Не загрузилась библиотека Bitrix24 BX24.',
            true
        );

        return;
    }


    BX24.init(
        function() {

            loadSettings()
                .catch(
                    function(error) {

                        setStatus(
                            'Ошибка загрузки настроек:\n' +
                            String(
                                error.message ||
                                error
                            ),
                            true
                        );
                    }
                );


            saveBtn.addEventListener(
                'click',
                function() {

                    saveBtn.disabled =
                        true;


                    saveSettings()
                        .catch(
                            function(error) {

                                setStatus(
                                    'Ошибка:\n' +
                                    String(
                                        error.message ||
                                        error
                                    ),
                                    true
                                );
                            }
                        )
                        .finally(
                            function() {

                                saveBtn.disabled =
                                    false;
                            }
                        );
                }
            );


            bindBtn.addEventListener(
                'click',
                function() {

                    runBind()
                        .catch(
                            function(error) {

                                setStatus(
                                    'Ошибка:\n' +
                                    String(
                                        error.message ||
                                        error
                                    ),
                                    true
                                );
                            }
                        );
                }
            );


            if (
                typeof BX24.fitWindow ===
                'function'
            ) {

                BX24.fitWindow();
            }
        }
    );
}


startApplication();

</script>

</body>
</html>
