<?php

const LEAD_FED_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';
const DEAL_FED_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';

function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_PRETTY_PRINT |
        JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function getBaseUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return 'https://' . $host;
}

function callBitrix($method, $params, $auth) {

    if (
        empty($auth['domain']) ||
        empty($auth['access_token'])
    ) {
        return [
            'error' => 'NO_AUTH',
            'error_description' => 'Нет domain или access_token'
        ];
    }

    $domain = preg_replace(
        '/[^a-zA-Z0-9\.\-]/',
        '',
        $auth['domain']
    );

    $url =
        'https://' .
        $domain .
        '/rest/' .
        $method .
        '.json';

    $params['auth'] = $auth['access_token'];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' =>
                "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params),
            'timeout' => 25,
            'ignore_errors' => true
        ]
    ]);

    $raw = @file_get_contents(
        $url,
        false,
        $context
    );

    if ($raw === false) {
        return [
            'error' => 'HTTP_REQUEST_FAILED',
            'error_description' =>
                'Не удалось выполнить запрос к Bitrix24',
            'url' => $url
        ];
    }

    $decoded = json_decode(
        $raw,
        true
    );

    if (!is_array($decoded)) {
        return [
            'error' => 'BAD_JSON',
            'json_error' => json_last_error_msg(),
            'raw_length' => strlen($raw),
            'raw_start' => substr($raw, 0, 1000)
        ];
    }

    return $decoded;
}

function normalizeAuth($input) {

    $auth = [];

    if (
        isset($input['auth']) &&
        is_array($input['auth'])
    ) {
        $auth = $input['auth'];
    }

    if (
        isset($input['AUTH']) &&
        is_array($input['AUTH'])
    ) {
        $auth = array_merge(
            $auth,
            $input['AUTH']
        );
    }

    if (empty($auth['access_token'])) {

        if (!empty($input['AUTH_ID'])) {
            $auth['access_token'] =
                $input['AUTH_ID'];

        } elseif (!empty($input['auth']['AUTH_ID'])) {
            $auth['access_token'] =
                $input['auth']['AUTH_ID'];

        } elseif (!empty($input['AUTH']['ACCESS_TOKEN'])) {
            $auth['access_token'] =
                $input['AUTH']['ACCESS_TOKEN'];

        } elseif (!empty($input['access_token'])) {
            $auth['access_token'] =
                $input['access_token'];
        }
    }

    if (empty($auth['domain'])) {

        if (!empty($input['DOMAIN'])) {
            $auth['domain'] =
                $input['DOMAIN'];

        } elseif (!empty($input['domain'])) {
            $auth['domain'] =
                $input['domain'];

        } elseif (!empty($input['auth']['domain'])) {
            $auth['domain'] =
                $input['auth']['domain'];

        } elseif (!empty($input['auth']['DOMAIN'])) {
            $auth['domain'] =
                $input['auth']['DOMAIN'];

        } elseif (!empty($input['AUTH']['DOMAIN'])) {
            $auth['domain'] =
                $input['AUTH']['DOMAIN'];
        }
    }

    return $auth;
}

function normalizeText($value) {

    $value = mb_strtolower(
        (string)$value,
        'UTF-8'
    );

    $value = str_replace(
        'ё',
        'е',
        $value
    );

    $value = preg_replace(
        '/[()"«»]/u',
        '',
        $value
    );

    $value = preg_replace(
        '/(^|\s)г\.(?=\s|$)/iu',
        ' ',
        $value
    );

    /*
     * ВАЖНО:
     * слово "город" удаляем только как отдельное слово.
     * Поэтому "Нижегородская" НЕ ломается.
     */
    $value = preg_replace(
        '/(^|\s)(область|обл\.?|республика|респ\.?|край|ао|автономный округ|город)(?=\s|$)/iu',
        ' ',
        $value
    );

    $value = preg_replace(
        '/\s+/u',
        ' ',
        $value
    );

    return trim($value);
}

function normalizeCity($value) {

    $value = mb_strtolower(
        (string)$value,
        'UTF-8'
    );

    $value = str_replace(
        'ё',
        'е',
        $value
    );

    $value = preg_replace(
        '/[()"«»]/u',
        '',
        $value
    );

    $value = preg_replace(
        '/\s+/u',
        ' ',
        $value
    );

    return trim($value);
}

function getDistrictNameMap() {

    return [
        'central' => 'Центральный',
        'northwest' => 'Северо-Западный',
        'south' => 'Южный',
        'northCaucasus' => 'Северо-Кавказский',
        'volga' => 'Приволжский',
        'ural' => 'Уральский',
        'siberian' => 'Сибирский',
        'farEast' => 'Дальневосточный'
    ];
}

function getRegionDistrictExact() {

    return [

        // ЦФО
        'белгородская' => 'central',
        'брянская' => 'central',
        'владимирская' => 'central',
        'воронежская' => 'central',
        'ивановская' => 'central',
        'калужская' => 'central',
        'костромская' => 'central',
        'курская' => 'central',
        'липецкая' => 'central',
        'московская' => 'central',
        'москва' => 'central',
        'орловская' => 'central',
        'рязанская' => 'central',
        'смоленская' => 'central',
        'тамбовская' => 'central',
        'тверская' => 'central',
        'тульская' => 'central',
        'ярославская' => 'central',

        // СЗФО
        'карелия' => 'northwest',
        'коми' => 'northwest',
        'архангельская' => 'northwest',
        'ненецкий' => 'northwest',
        'вологодская' => 'northwest',
        'калининградская' => 'northwest',
        'ленинградская' => 'northwest',
        'мурманская' => 'northwest',
        'новгородская' => 'northwest',
        'псковская' => 'northwest',
        'санкт-петербург' => 'northwest',
        'петербург' => 'northwest',
        'спб' => 'northwest',

        // ЮФО
        'адыгея' => 'south',
        'калмыкия' => 'south',
        'крым' => 'south',
        'краснодарский' => 'south',
        'астраханская' => 'south',
        'волгоградская' => 'south',
        'ростовская' => 'south',
        'севастополь' => 'south',

        // СКФО
        'дагестан' => 'northCaucasus',
        'ингушетия' => 'northCaucasus',
        'кабардино-балкарская' => 'northCaucasus',
        'карачаево-черкесская' => 'northCaucasus',
        'северная осетия' => 'northCaucasus',
        'северная осетия алания' => 'northCaucasus',
        'алания' => 'northCaucasus',
        'чеченская' => 'northCaucasus',
        'ставропольский' => 'northCaucasus',

        // ПФО
        'башкортостан' => 'volga',
        'башкирия' => 'volga',
        'марий эл' => 'volga',
        'мордовия' => 'volga',
        'татарстан' => 'volga',
        'удмуртская' => 'volga',
        'чувашская' => 'volga',
        'пермский' => 'volga',
        'кировская' => 'volga',
        'нижегородская' => 'volga',
        'оренбургская' => 'volga',
        'пензенская' => 'volga',
        'самарская' => 'volga',
        'саратовская' => 'volga',
        'ульяновская' => 'volga',

        // УрФО
        'курганская' => 'ural',
        'свердловская' => 'ural',
        'тюменская' => 'ural',
        'ханты-мансийский' => 'ural',
        'ханты-мансийский югра' => 'ural',
        'югра' => 'ural',
        'ямало-ненецкий' => 'ural',
        'челябинская' => 'ural',

        // СФО
        'алтайский' => 'siberian',
        'алтай' => 'siberian',
        'тыва' => 'siberian',
        'тува' => 'siberian',
        'хакасия' => 'siberian',
        'красноярский' => 'siberian',
        'иркутская' => 'siberian',
        'кемеровская' => 'siberian',
        'кузбасс' => 'siberian',
        'новосибирская' => 'siberian',
        'омская' => 'siberian',
        'томская' => 'siberian',

        // ДФО
        'бурятия' => 'farEast',
        'саха' => 'farEast',
        'якутия' => 'farEast',
        'забайкальский' => 'farEast',
        'камчатский' => 'farEast',
        'приморский' => 'farEast',
        'хабаровский' => 'farEast',
        'амурская' => 'farEast',
        'магаданская' => 'farEast',
        'сахалинская' => 'farEast',
        'еврейская' => 'farEast',
        'чукотский' => 'farEast'
    ];
}

function getRegionDistrictStems() {

    return [

        // ЦФО
        'белгород' => 'central',
        'брянск' => 'central',
        'владимир' => 'central',
        'воронеж' => 'central',
        'иванов' => 'central',
        'калуж' => 'central',
        'костром' => 'central',
        'курск' => 'central',
        'липецк' => 'central',
        'московск' => 'central',
        'орлов' => 'central',
        'рязан' => 'central',
        'смоленск' => 'central',
        'тамбов' => 'central',
        'твер' => 'central',
        'тульск' => 'central',
        'ярослав' => 'central',

        // СЗФО
        'карел' => 'northwest',
        'коми' => 'northwest',
        'архангел' => 'northwest',
        'ненец' => 'northwest',
        'вологод' => 'northwest',
        'калининград' => 'northwest',
        'ленинград' => 'northwest',
        'мурман' => 'northwest',
        'новгород' => 'northwest',
        'псков' => 'northwest',
        'петербург' => 'northwest',

        // ЮФО
        'адыге' => 'south',
        'калмык' => 'south',
        'крым' => 'south',
        'краснодар' => 'south',
        'астрахан' => 'south',
        'волгоград' => 'south',
        'ростов' => 'south',
        'севастопол' => 'south',

        // СКФО
        'дагестан' => 'northCaucasus',
        'ингуш' => 'northCaucasus',
        'кабардино' => 'northCaucasus',
        'балкар' => 'northCaucasus',
        'карачаево' => 'northCaucasus',
        'черкес' => 'northCaucasus',
        'осет' => 'northCaucasus',
        'алания' => 'northCaucasus',
        'чечен' => 'northCaucasus',
        'ставропол' => 'northCaucasus',

        // ПФО
        'башкортостан' => 'volga',
        'башкир' => 'volga',
        'марий' => 'volga',
        'мордов' => 'volga',
        'татарстан' => 'volga',
        'удмурт' => 'volga',
        'чуваш' => 'volga',
        'перм' => 'volga',
        'киров' => 'volga',
        'нижегород' => 'volga',
        'оренбург' => 'volga',
        'пенз' => 'volga',
        'самар' => 'volga',
        'саратов' => 'volga',
        'ульянов' => 'volga',

        // УрФО
        'курган' => 'ural',
        'свердлов' => 'ural',
        'тюмен' => 'ural',
        'ханты' => 'ural',
        'югра' => 'ural',
        'ямало' => 'ural',
        'челябин' => 'ural',

        // СФО
        'алтай' => 'siberian',
        'тыва' => 'siberian',
        'тува' => 'siberian',
        'хакас' => 'siberian',
        'краснояр' => 'siberian',
        'иркут' => 'siberian',
        'кемеров' => 'siberian',
        'кузбасс' => 'siberian',
        'новосибир' => 'siberian',
        'омск' => 'siberian',
        'томск' => 'siberian',

        // ДФО
        'бурят' => 'farEast',
        'саха' => 'farEast',
        'якут' => 'farEast',
        'забайкал' => 'farEast',
        'камчат' => 'farEast',
        'примор' => 'farEast',
        'хабаров' => 'farEast',
        'амур' => 'farEast',
        'магадан' => 'farEast',
        'сахалин' => 'farEast',
        'еврей' => 'farEast',
        'чукот' => 'farEast'
    ];
}

function getCityDistrictMap() {

    return [

        // ЦФО
        'москва' => 'central',
        'балашиха' => 'central',
        'химки' => 'central',
        'мытищи' => 'central',
        'подольск' => 'central',
        'королев' => 'central',
        'королёв' => 'central',
        'люберцы' => 'central',
        'красногорск' => 'central',
        'одинцово' => 'central',
        'воронеж' => 'central',
        'липецк' => 'central',
        'тамбов' => 'central',
        'белгород' => 'central',
        'курск' => 'central',
        'орел' => 'central',
        'орёл' => 'central',
        'тула' => 'central',
        'рязань' => 'central',
        'калуга' => 'central',
        'брянск' => 'central',
        'смоленск' => 'central',
        'тверь' => 'central',
        'ярославль' => 'central',
        'владимир' => 'central',
        'иваново' => 'central',
        'кострома' => 'central',

        // СЗФО
        'санкт-петербург' => 'northwest',
        'петербург' => 'northwest',
        'калининград' => 'northwest',
        'мурманск' => 'northwest',
        'архангельск' => 'northwest',
        'северодвинск' => 'northwest',
        'вологда' => 'northwest',
        'череповец' => 'northwest',
        'псков' => 'northwest',
        'великий новгород' => 'northwest',
        'петрозаводск' => 'northwest',
        'сыктывкар' => 'northwest',

        // ЮФО
        'краснодар' => 'south',
        'сочи' => 'south',
        'новороссийск' => 'south',
        'анапа' => 'south',
        'геленджик' => 'south',
        'ростов-на-дону' => 'south',
        'ростов на дону' => 'south',
        'таганрог' => 'south',
        'шахты' => 'south',
        'волгоград' => 'south',
        'волжский' => 'south',
        'астрахань' => 'south',
        'элиста' => 'south',
        'майкоп' => 'south',
        'симферополь' => 'south',
        'севастополь' => 'south',
        'ялта' => 'south',

        // СКФО
        'махачкала' => 'northCaucasus',
        'каспийск' => 'northCaucasus',
        'дербент' => 'northCaucasus',
        'грозный' => 'northCaucasus',
        'ставрополь' => 'northCaucasus',
        'пятигорск' => 'northCaucasus',
        'кисловодск' => 'northCaucasus',
        'ессентуки' => 'northCaucasus',
        'невинномысск' => 'northCaucasus',
        'нальчик' => 'northCaucasus',
        'владикавказ' => 'northCaucasus',
        'назрань' => 'northCaucasus',
        'черкесск' => 'northCaucasus',

        // ПФО
        'нижний новгород' => 'volga',
        'дзержинск' => 'volga',
        'богородск' => 'volga',
        'шаранга' => 'volga',
        'казань' => 'volga',
        'набережные челны' => 'volga',
        'альметьевск' => 'volga',
        'нижнекамск' => 'volga',
        'йошкар-ола' => 'volga',
        'чебоксары' => 'volga',
        'саранск' => 'volga',
        'пенза' => 'volga',
        'кузнецк' => 'volga',
        'самара' => 'volga',
        'тольятти' => 'volga',
        'сызрань' => 'volga',
        'саратов' => 'volga',
        'энгельс' => 'volga',
        'балаково' => 'volga',
        'балашов' => 'volga',
        'ульяновск' => 'volga',
        'димитровград' => 'volga',
        'киров' => 'volga',
        'пермь' => 'volga',
        'березники' => 'volga',
        'соликамск' => 'volga',
        'уфа' => 'volga',
        'стерлитамак' => 'volga',
        'мелеуз' => 'volga',
        'салават' => 'volga',
        'нефтекамск' => 'volga',
        'оренбург' => 'volga',
        'орск' => 'volga',
        'бузулук' => 'volga',
        'ижевск' => 'volga',
        'сарапул' => 'volga',

        // УрФО
        'екатеринбург' => 'ural',
        'нижний тагил' => 'ural',
        'каменск-уральский' => 'ural',
        'первоуральск' => 'ural',
        'челябинск' => 'ural',
        'магнитогорск' => 'ural',
        'миасс' => 'ural',
        'златоуст' => 'ural',
        'копейск' => 'ural',
        'курган' => 'ural',
        'тюмень' => 'ural',
        'тобольск' => 'ural',
        'сургут' => 'ural',
        'нижневартовск' => 'ural',
        'ханты-мансийск' => 'ural',
        'нефтеюганск' => 'ural',
        'новый уренгой' => 'ural',
        'ноябрьск' => 'ural',
        'надым' => 'ural',
        'салехард' => 'ural',

        // СФО
        'новосибирск' => 'siberian',
        'бердск' => 'siberian',
        'омск' => 'siberian',
        'томск' => 'siberian',
        'северск' => 'siberian',
        'красноярск' => 'siberian',
        'ачинск' => 'siberian',
        'норильск' => 'siberian',
        'иркутск' => 'siberian',
        'ангарск' => 'siberian',
        'братск' => 'siberian',
        'кемерово' => 'siberian',
        'новокузнецк' => 'siberian',
        'прокопьевск' => 'siberian',
        'киселевск' => 'siberian',
        'киселёвск' => 'siberian',
        'междуреченск' => 'siberian',
        'барнаул' => 'siberian',
        'бийск' => 'siberian',
        'рубцовск' => 'siberian',
        'горно-алтайск' => 'siberian',
        'абакан' => 'siberian',
        'кызыл' => 'siberian',

        // ДФО
        'улан-удэ' => 'farEast',
        'чита' => 'farEast',
        'якутск' => 'farEast',
        'благовещенск' => 'farEast',
        'владивосток' => 'farEast',
        'артем' => 'farEast',
        'артём' => 'farEast',
        'уссурийск' => 'farEast',
        'находка' => 'farEast',
        'хабаровск' => 'farEast',
        'комсомольск-на-амуре' => 'farEast',
        'южно-сахалинск' => 'farEast',
        'магадан' => 'farEast',
        'петропавловск-камчатский' => 'farEast'
    ];
}

function getAmbiguousCities() {

    return [
        'советск',
        'троицк',
        'заречный',
        'мирный',
        'приморск',
        'красный яр',
        'никольск',
        'красноармейск',
        'лесной',
        'октябрьский',
        'первомайский',
        'радужный',
        'светлый',
        'северный',
        'центральный'
    ];
}

function getDistrictByRegion($region) {

    $normalized = normalizeText($region);

    if (!$normalized) {
        return null;
    }

    $exact = getRegionDistrictExact();

    if (isset($exact[$normalized])) {
        return $exact[$normalized];
    }

    foreach (
        getRegionDistrictStems()
        as $stem => $district
    ) {

        if (
            mb_strpos(
                $normalized,
                $stem,
                0,
                'UTF-8'
            ) !== false
        ) {
            return $district;
        }
    }

    return null;
}

function getDistrictByCity($city) {

    $normalized = normalizeCity($city);

    if (!$normalized) {
        return null;
    }

    if (
        in_array(
            $normalized,
            getAmbiguousCities(),
            true
        )
    ) {
        return [
            'needRegion' => true
        ];
    }

    $map = getCityDistrictMap();

    return $map[$normalized] ?? null;
}

function detectDistrict($city, $region) {

    // ОБЛАСТЬ ВСЕГДА ИМЕЕТ ПРИОРИТЕТ
    $byRegion = getDistrictByRegion($region);

    if ($byRegion) {
        return [
            'status' => 'ok',
            'districtKey' => $byRegion,
            'source' => 'region'
        ];
    }

    // Если области нет или она не распознана —
    // пробуем город.
    $byCity = getDistrictByCity($city);

    if (
        is_array($byCity) &&
        !empty($byCity['needRegion'])
    ) {
        return [
            'status' => 'need_region',
            'districtKey' => null,
            'source' => 'city'
        ];
    }

    if ($byCity) {
        return [
            'status' => 'ok',
            'districtKey' => $byCity,
            'source' => 'city'
        ];
    }

    return [
        'status' => 'unknown',
        'districtKey' => null,
        'source' => null
    ];
}

function getDistrictNameByKey($key) {

    $map = getDistrictNameMap();

    return $map[$key] ?? '';
}

function calcDistrictName($city, $region) {

    $result = detectDistrict(
        $city,
        $region
    );

    if (
        ($result['status'] ?? '') !== 'ok' ||
        empty($result['districtKey'])
    ) {
        return [
            'status' =>
                $result['status'] ?? 'unknown',

            'districtKey' => null,
            'districtName' => '',

            'source' =>
                $result['source'] ?? null
        ];
    }

    return [
        'status' => 'ok',

        'districtKey' =>
            $result['districtKey'],

        'districtName' =>
            getDistrictNameByKey(
                $result['districtKey']
            ),

        'source' =>
            $result['source']
    ];
}

function getUserFieldMeta(
    $entity,
    $fieldCode,
    $auth
) {

    if (!$fieldCode) {
        return null;
    }

    if (
        stripos(
            $fieldCode,
            'UF_CRM_'
        ) !== 0
    ) {
        return null;
    }

    $method =
        $entity === 'lead'
            ? 'crm.lead.userfield.list'
            : 'crm.deal.userfield.list';

    $result = callBitrix(
        $method,
        [
            'filter' => [
                'FIELD_NAME' => $fieldCode
            ]
        ],
        $auth
    );

    if (!empty($result['error'])) {
        return null;
    }

    foreach (
        $result['result'] ?? []
        as $item
    ) {

        if (
            ($item['FIELD_NAME'] ?? '') ===
            $fieldCode
        ) {
            return $item;
        }
    }

    return null;
}

function getEnumItemsFromMeta($meta) {

    if (
        !$meta ||
        !is_array($meta)
    ) {
        return [];
    }

    foreach (
        [
            'LIST',
            'list',
            'ITEMS',
            'items',
            'VALUES',
            'values'
        ]
        as $key
    ) {

        if (
            !empty($meta[$key]) &&
            is_array($meta[$key])
        ) {
            return $meta[$key];
        }
    }

    return [];
}

function parseFieldValue(
    $rawValue,
    $fieldMeta = null
) {

    if (is_array($rawValue)) {

        if (
            array_key_exists(
                'VALUE',
                $rawValue
            )
        ) {
            $rawValue =
                $rawValue['VALUE'];

        } elseif (
            array_key_exists(
                'value',
                $rawValue
            )
        ) {
            $rawValue =
                $rawValue['value'];

        } elseif (
            array_key_exists(
                'ID',
                $rawValue
            )
        ) {
            $rawValue =
                $rawValue['ID'];

        } elseif (
            array_key_exists(
                'id',
                $rawValue
            )
        ) {
            $rawValue =
                $rawValue['id'];

        } else {
            $rawValue =
                reset($rawValue);
        }
    }

    $value =
        (string)($rawValue ?? '');

    $items =
        getEnumItemsFromMeta(
            $fieldMeta
        );

    foreach ($items as $item) {

        if (!is_array($item)) {
            continue;
        }

        $id = (string)(
            $item['ID'] ??
            $item['id'] ??
            $item['VALUE_ID'] ??
            ''
        );

        $text = (string)(
            $item['VALUE'] ??
            $item['value'] ??
            $item['NAME'] ??
            $item['name'] ??
            ''
        );

        if (
            $id === $value ||
            $text === $value
        ) {
            return $text ?: $value;
        }
    }

    return $value;
}

/*
 * ============================================================
 * ЛИД
 * ============================================================
 */

function calculateLeadDistrict(
    $lead,
    $appOptions,
    $auth
) {

    $cityField =
        $appOptions['leadCityField'] ?? '';

    $regionField =
        $appOptions['leadRegionField'] ?? '';

    if (!$cityField) {
        return [
            'success' => false,
            'reason' => 'no_lead_city_field'
        ];
    }

    $cityMeta =
        getUserFieldMeta(
            'lead',
            $cityField,
            $auth
        );

    $regionMeta =
        $regionField
            ? getUserFieldMeta(
                'lead',
                $regionField,
                $auth
            )
            : null;

    $city =
        parseFieldValue(
            $lead[$cityField] ?? '',
            $cityMeta
        );

    $region =
        $regionField
            ? parseFieldValue(
                $lead[$regionField] ?? '',
                $regionMeta
            )
            : '';

    if (!$city) {
        return [
            'success' => true,
            'status' => 'no_city',
            'city' => '',
            'region' => $region,
            'districtName' => ''
        ];
    }

    $calc =
        calcDistrictName(
            $city,
            $region
        );

    return [
        'success' => true,
        'status' => $calc['status'],
        'city' => $city,
        'region' => $region,
        'districtKey' =>
            $calc['districtKey'],
        'districtName' =>
            $calc['districtName'],
        'source' =>
            $calc['source']
    ];
}

function syncLeadDistrict(
    $leadId,
    $auth
) {

    $optionsRes =
        callBitrix(
            'app.option.get',
            [],
            $auth
        );

    if (!empty($optionsRes['error'])) {
        return [
            'success' => false,
            'reason' => 'options_error',
            'error' => $optionsRes
        ];
    }

    $appOptions =
        $optionsRes['result'] ?? [];

    $leadRes =
        callBitrix(
            'crm.lead.get',
            [
                'id' => $leadId
            ],
            $auth
        );

    if (!empty($leadRes['error'])) {
        return [
            'success' => false,
            'reason' => 'lead_get_error',
            'error' => $leadRes
        ];
    }

    $lead =
        $leadRes['result'] ?? [];

    $calc =
        calculateLeadDistrict(
            $lead,
            $appOptions,
            $auth
        );

    if (
        empty($calc['success'])
    ) {
        return $calc;
    }

    $target =
        ($calc['status'] === 'ok')
            ? $calc['districtName']
            : '';

    $current =
        (string)(
            $lead[LEAD_FED_FIELD] ?? ''
        );

    /*
     * Ничего не обновляем,
     * если значение уже правильное.
     *
     * Это защищает ONCRMLEADUPDATE
     * от бесконечного цикла.
     */
    if ($current === $target) {

        return array_merge(
            $calc,
            [
                'success' => true,
                'updated' => false,
                'reason' => 'already_actual'
            ]
        );
    }

    $update =
        callBitrix(
            'crm.lead.update',
            [
                'id' => $leadId,
                'fields' => [
                    LEAD_FED_FIELD =>
                        $target
                ],
                'params' => [
                    'REGISTER_SONET_EVENT' => 'N',
                    'REGISTER_HISTORY_EVENT' => 'N'
                ]
            ],
            $auth
        );

    if (!empty($update['error'])) {
        return array_merge(
            $calc,
            [
                'success' => false,
                'updated' => false,
                'reason' => 'lead_update_error',
                'error' => $update
            ]
        );
    }

    return array_merge(
        $calc,
        [
            'success' => true,
            'updated' => true,
            'reason' => 'updated'
        ]
    );
}

/*
 * ============================================================
 * ПЕРЕНОС ЛИД → СДЕЛКА
 * ============================================================
 */

function transferLeadDistrictToDeal(
    $dealId,
    $auth
) {

    $dealRes =
        callBitrix(
            'crm.deal.get',
            [
                'id' => $dealId
            ],
            $auth
        );

    if (!empty($dealRes['error'])) {
        return [
            'success' => false,
            'reason' => 'deal_get_error',
            'error' => $dealRes
        ];
    }

    $deal =
        $dealRes['result'] ?? [];

    /*
     * Главное правило:
     * если в сделке ФО уже заполнен —
     * вообще ничего не трогаем.
     */
    $currentDealDistrict =
        trim(
            (string)(
                $deal[DEAL_FED_FIELD] ?? ''
            )
        );

    if ($currentDealDistrict !== '') {
        return [
            'success' => true,
            'updated' => false,
            'reason' => 'deal_already_has_district',
            'districtName' =>
                $currentDealDistrict
        ];
    }

    $leadId =
        $deal['LEAD_ID'] ?? null;

    if (!$leadId) {
        return [
            'success' => true,
            'updated' => false,
            'reason' => 'deal_has_no_lead'
        ];
    }

    $leadRes =
        callBitrix(
            'crm.lead.get',
            [
                'id' => $leadId
            ],
            $auth
        );

    if (!empty($leadRes['error'])) {
        return [
            'success' => false,
            'updated' => false,
            'reason' => 'source_lead_get_error',
            'lead_id' => $leadId,
            'error' => $leadRes
        ];
    }

    $lead =
        $leadRes['result'] ?? [];

    $leadDistrict =
        trim(
            (string)(
                $lead[LEAD_FED_FIELD] ?? ''
            )
        );

    /*
     * Если по какой-то причине лид ещё не успел
     * получить ФО — пробуем рассчитать его прямо сейчас.
     */
    if ($leadDistrict === '') {

        $leadSync =
            syncLeadDistrict(
                $leadId,
                $auth
            );

        if (
            ($leadSync['status'] ?? '') === 'ok'
        ) {
            $leadDistrict =
                trim(
                    (string)(
                        $leadSync['districtName'] ?? ''
                    )
                );
        }
    }

    if ($leadDistrict === '') {
        return [
            'success' => true,
            'updated' => false,
            'reason' => 'lead_has_no_district',
            'lead_id' => $leadId
        ];
    }

    $update =
        callBitrix(
            'crm.deal.update',
            [
                'id' => $dealId,

                'fields' => [
                    DEAL_FED_FIELD =>
                        $leadDistrict
                ],

                'params' => [
                    'REGISTER_SONET_EVENT' => 'N',
                    'REGISTER_HISTORY_EVENT' => 'N'
                ]
            ],
            $auth
        );

    if (!empty($update['error'])) {
        return [
            'success' => false,
            'updated' => false,
            'reason' => 'deal_update_error',
            'error' => $update
        ];
    }

    return [
        'success' => true,
        'updated' => true,
        'reason' =>
            'district_copied_from_lead',
        'lead_id' => $leadId,
        'districtName' =>
            $leadDistrict
    ];
}
