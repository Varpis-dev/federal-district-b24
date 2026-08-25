const FederalDistrict =
    require('../public/district.js');


const LEAD_FED_FIELD =
    'UF_CRM_FEDERAL_DISTRICT_TEXT';


function cleanDomain(value) {

    return String(value || '')
        .replace(
            /[^a-zA-Z0-9.\-]/g,
            ''
        );
}


function addFormValue(
    form,
    key,
    value
) {

    if (
        value === null ||
        typeof value === 'undefined'
    ) {
        return;
    }

    if (Array.isArray(value)) {

        value.forEach(
            (item, index) => {

                addFormValue(
                    form,
                    `${key}[${index}]`,
                    item
                );
            }
        );

        return;
    }

    if (
        typeof value === 'object'
    ) {

        Object.entries(value)
            .forEach(
                ([childKey, childValue]) => {

                    addFormValue(
                        form,
                        `${key}[${childKey}]`,
                        childValue
                    );
                }
            );

        return;
    }

    form.append(
        key,
        String(value)
    );
}


async function callBitrix(
    domain,
    accessToken,
    method,
    params = {}
) {

    const safeDomain =
        cleanDomain(domain);

    if (
        !safeDomain ||
        !accessToken
    ) {
        throw new Error(
            'NO_AUTH'
        );
    }

    const form =
        new URLSearchParams();

    Object.entries(params)
        .forEach(
            ([key, value]) => {

                addFormValue(
                    form,
                    key,
                    value
                );
            }
        );

    form.append(
        'auth',
        accessToken
    );

    const controller =
        new AbortController();

    const timeout =
        setTimeout(
            () =>
                controller.abort(),

            9000
        );

    try {

        const response =
            await fetch(
                `https://${safeDomain}/rest/${method}.json`,
                {
                    method:
                        'POST',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded'
                    },

                    body:
                        form.toString(),

                    signal:
                        controller.signal
                }
            );

        const text =
            await response.text();

        let data;

        try {
            data =
                JSON.parse(text);
        } catch {
            throw new Error(
                `BAD_JSON ${method}: ` +
                text.slice(0, 300)
            );
        }

        if (data.error) {

            throw new Error(
                `${method}: ` +
                data.error +
                ' ' +
                (
                    data.error_description ||
                    ''
                )
            );
        }

        return data;

    } finally {

        clearTimeout(
            timeout
        );
    }
}


function getFlatValue(
    body,
    key
) {

    if (
        body &&
        typeof body === 'object' &&
        Object.prototype
            .hasOwnProperty
            .call(
                body,
                key
            )
    ) {
        return body[key];
    }

    return undefined;
}


function getNestedValue(
    object,
    path
) {

    let current =
        object;

    for (const key of path) {

        if (
            !current ||
            typeof current !== 'object' ||
            !Object.prototype
                .hasOwnProperty
                .call(
                    current,
                    key
                )
        ) {
            return undefined;
        }

        current =
            current[key];
    }

    return current;
}


async function getRequestBody(req) {

    if (
        req.body !== undefined &&
        req.body !== null
    ) {
        return req.body;
    }

    let raw = '';

    for await (
        const chunk of req
    ) {
        raw +=
            chunk.toString();
    }

    if (!raw) {
        return {};
    }

    const contentType =
        String(
            req.headers[
                'content-type'
            ] || ''
        );

    if (
        contentType.includes(
            'application/json'
        )
    ) {

        try {
            return JSON.parse(raw);
        } catch {
            return {};
        }
    }

    const params =
        new URLSearchParams(raw);

    const result = {};

    for (
        const [key, value]
        of params.entries()
    ) {
        result[key] =
            value;
    }

    return result;
}


function findEvent(
    body
) {

    return String(
        getNestedValue(
            body,
            ['event']
        ) ||

        getNestedValue(
            body,
            ['EVENT']
        ) ||

        getFlatValue(
            body,
            'event'
        ) ||

        getFlatValue(
            body,
            'EVENT'
        ) ||

        ''
    ).toUpperCase();
}


function findLeadId(
    body
) {

    return (
        getNestedValue(
            body,
            [
                'data',
                'FIELDS',
                'ID'
            ]
        ) ||

        getNestedValue(
            body,
            [
                'DATA',
                'FIELDS',
                'ID'
            ]
        ) ||

        getFlatValue(
            body,
            'data[FIELDS][ID]'
        ) ||

        getFlatValue(
            body,
            'DATA[FIELDS][ID]'
        ) ||

        getFlatValue(
            body,
            'ID'
        ) ||

        null
    );
}


function findAuth(
    body
) {

    const nested =
        getNestedValue(
            body,
            ['auth']
        ) || {};

    const domain =
        nested.domain ||
        nested.DOMAIN ||

        getFlatValue(
            body,
            'auth[domain]'
        ) ||

        getFlatValue(
            body,
            'auth[DOMAIN]'
        ) ||

        getFlatValue(
            body,
            'DOMAIN'
        ) ||

        '';

    const accessToken =
        nested.access_token ||
        nested.ACCESS_TOKEN ||

        getFlatValue(
            body,
            'auth[access_token]'
        ) ||

        getFlatValue(
            body,
            'auth[ACCESS_TOKEN]'
        ) ||

        getFlatValue(
            body,
            'AUTH_ID'
        ) ||

        '';

    return {
        domain,
        accessToken
    };
}


function rawScalar(value) {

    if (
        Array.isArray(value)
    ) {
        value =
            value[0] || '';
    }

    if (
        value &&
        typeof value === 'object'
    ) {
        value =
            value.VALUE ||
            value.value ||
            value.ID ||
            value.id ||
            '';
    }

    return String(
        value || ''
    );
}


async function resolveFieldValue(
    domain,
    accessToken,
    fieldCode,
    rawValue
) {

    const value =
        rawScalar(
            rawValue
        );

    if (!value) {
        return '';
    }

    /*
     * Если пришёл обычный текст —
     * вообще никаких дополнительных
     * запросов к Bitrix.
     */
    if (
        !/^\d+$/.test(value)
    ) {
        return value;
    }

    if (
        !String(fieldCode)
            .startsWith(
                'UF_CRM_'
            )
    ) {
        return value;
    }

    /*
     * Если поле является списком
     * и Bitrix вернул ID элемента —
     * запрашиваем только конкретное
     * пользовательское поле.
     *
     * Не используем crm.lead.fields.
     */
    const metaResponse =
        await callBitrix(
            domain,
            accessToken,
            'crm.lead.userfield.list',
            {
                filter: {
                    FIELD_NAME:
                        fieldCode
                }
            }
        );

    const fields =
        metaResponse.result || [];

    const meta =
        fields.find(
            field =>
                field.FIELD_NAME ===
                fieldCode
        );

    if (!meta) {
        return value;
    }

    const list =
        meta.LIST || [];

    const found =
        list.find(
            item =>
                String(item.ID) ===
                value
        );

    if (found) {
        return String(
            found.VALUE || value
        );
    }

    return value;
}


module.exports =
async function handler(
    req,
    res
) {

    res.setHeader(
        'Cache-Control',
        'no-store'
    );

    if (
        req.method !== 'POST'
    ) {

        res.status(200).json({
            success: true,
            status: 'alive'
        });

        return;
    }

    try {

        const body =
            await getRequestBody(
                req
            );

        const event =
            findEvent(body);

        if (
            event !==
                'ONCRMLEADADD' &&
            event !==
                'ONCRMLEADUPDATE'
        ) {

            res.status(200).json({
                success: true,
                ignored: true,
                event
            });

            return;
        }

        const leadId =
            findLeadId(body);

        const {
            domain,
            accessToken
        } =
            findAuth(body);

        if (
            !leadId ||
            !domain ||
            !accessToken
        ) {

            res.status(200).json({
                success: false,
                reason: 'missing_event_data',
                event,
                leadId:
                    leadId || null
            });

            return;
        }

        /*
         * bind.php передаёт коды полей
         * прямо в URL обработчика.
         *
         * Поэтому на КАЖДОЕ событие
         * больше не нужен app.option.get.
         */
        let cityField =
            String(
                req.query.city || ''
            );

        let regionField =
            String(
                req.query.region || ''
            );

        /*
         * Защита от странных значений.
         */
        if (
            cityField &&
            !/^[A-Z0-9_]+$/i
                .test(cityField)
        ) {
            cityField = '';
        }

        if (
            regionField &&
            !/^[A-Z0-9_]+$/i
                .test(regionField)
        ) {
            regionField = '';
        }

        /*
         * Миграционный fallback:
         * если вдруг пришёл старый handler
         * без ?city=..., один раз читаем
         * настройки приложения.
         */
        if (!cityField) {

            const optionsResponse =
                await callBitrix(
                    domain,
                    accessToken,
                    'app.option.get',
                    {}
                );

            const options =
                optionsResponse.result ||
                {};

            cityField =
                String(
                    options
                        .leadCityField ||
                    ''
                );

            regionField =
                String(
                    options
                        .leadRegionField ||
                    ''
                );
        }

        if (!cityField) {

            res.status(200).json({
                success: false,
                reason:
                    'no_city_field'
            });

            return;
        }

        /*
         * ОДИН основной REST-запрос.
         */
        const leadResponse =
            await callBitrix(
                domain,
                accessToken,
                'crm.lead.get',
                {
                    id:
                        leadId
                }
            );

        const lead =
            leadResponse.result || {};

        /*
         * Разрешение города и области
         * выполняем ПАРАЛЛЕЛЬНО.
         *
         * Для обычных строковых полей
         * дополнительных REST-вызовов
         * здесь вообще не будет.
         */
        const [
            city,
            region
        ] =
            await Promise.all([
                resolveFieldValue(
                    domain,
                    accessToken,
                    cityField,
                    lead[cityField]
                ),

                regionField
                    ? resolveFieldValue(
                        domain,
                        accessToken,
                        regionField,
                        lead[
                            regionField
                        ]
                    )
                    : Promise.resolve('')
            ]);

        const calculated =
            city
                ? FederalDistrict
                    .calcDistrictName(
                        city,
                        region
                    )
                : {
                    status:
                        'no_city',
                    districtName: ''
                };

        const target =
            calculated.status === 'ok'
                ? calculated
                    .districtName
                : '';

        const current =
            String(
                lead[
                    LEAD_FED_FIELD
                ] || ''
            ).trim();

        /*
         * ВАЖНЕЙШАЯ ЗАЩИТА.
         *
         * Наш crm.lead.update сам
         * вызовет ONCRMLEADUPDATE.
         *
         * Повторное событие зайдёт сюда,
         * увидит что ФО уже правильный,
         * и НЕМЕДЛЕННО закончит работу
         * без нового update.
         */
        if (
            current === target
        ) {

            res.status(200).json({
                success: true,
                leadId,
                event,
                updated: false,
                reason:
                    'already_actual',
                city,
                region,
                district:
                    target
            });

            return;
        }

        /*
         * Единственный update.
         */
        await callBitrix(
            domain,
            accessToken,
            'crm.lead.update',
            {
                id:
                    leadId,

                fields: {
                    [LEAD_FED_FIELD]:
                        target
                },

                params: {
                    REGISTER_SONET_EVENT:
                        'N',

                    REGISTER_HISTORY_EVENT:
                        'N'
                }
            }
        );

        res.status(200).json({
            success: true,
            leadId,
            event,
            updated: true,
            city,
            region,
            district:
                target,
            status:
                calculated.status
        });

    } catch (error) {

        /*
         * Bitrix online events не делает
         * retry, поэтому отдаём нормальный
         * HTTP-ответ, а ошибку оставляем
         * в логах.
         */

        console.error(
            'Federal district event error:',
            error
        );

        res.status(200).json({
            success: false,
            reason:
                'handler_error',

            message:
                String(
                    error &&
                    error.message
                        ? error.message
                        : error
                )
        });
    }
};
