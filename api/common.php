<?php

const OUTPUT_TEXT_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';

function jsonResponse($data) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

function getBaseUrl() {
  return 'https://' . $_SERVER['HTTP_HOST'];
}

function callBitrix($method, $params, $auth) {
  if (empty($auth['domain']) || empty($auth['access_token'])) {
    return [
      'error' => 'NO_AUTH',
      'error_description' => 'Нет domain или access_token'
    ];
  }

  $domain = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $auth['domain']);
  $url = 'https://' . $domain . '/rest/' . $method . '.json';

  $params['auth'] = $auth['access_token'];

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query($params),
      'timeout' => 25
    ]
  ]);

  $raw = @file_get_contents($url, false, $context);

  if ($raw === false) {
    return [
      'error' => 'HTTP_REQUEST_FAILED',
      'error_description' => 'Не удалось выполнить запрос к Bitrix24',
      'url' => $url
    ];
  }

  $decoded = json_decode($raw, true);

  if (!is_array($decoded)) {
    return [
      'error' => 'BAD_JSON',
      'json_error' => json_last_error_msg(),
      'raw_length' => strlen($raw),
      'raw_start' => substr($raw, 0, 1200)
    ];
  }

  return $decoded;
}

function normalizeAuth($input) {
  $auth = [];

  if (isset($input['auth']) && is_array($input['auth'])) {
    $auth = $input['auth'];
  }

  if (empty($auth['access_token'])) {
    if (!empty($input['AUTH_ID'])) {
      $auth['access_token'] = $input['AUTH_ID'];
    } elseif (!empty($input['auth']['AUTH_ID'])) {
      $auth['access_token'] = $input['auth']['AUTH_ID'];
    } elseif (!empty($input['access_token'])) {
      $auth['access_token'] = $input['access_token'];
    }
  }

  if (empty($auth['domain'])) {
    if (!empty($input['DOMAIN'])) {
      $auth['domain'] = $input['DOMAIN'];
    } elseif (!empty($input['domain'])) {
      $auth['domain'] = $input['domain'];
    } elseif (!empty($input['auth']['DOMAIN'])) {
      $auth['domain'] = $input['auth']['DOMAIN'];
    } elseif (!empty($input['auth']['domain'])) {
      $auth['domain'] = $input['auth']['domain'];
    }
  }

  return $auth;
}

function normalizeText($value) {
  $value = mb_strtolower((string)$value, 'UTF-8');
  $value = str_replace('ё', 'е', $value);
  $value = preg_replace('/\s+/u', ' ', $value);
  $value = preg_replace('/[()"«»]/u', '', $value);
  $value = preg_replace('/(^|\s)г\.(?=\s|$)/iu', ' ', $value);
  $value = preg_replace('/(^|\s)(область|обл\.?|республика|респ\.?|край|ао|автономный округ|город)(?=\s|$)/iu', ' ', $value);
  $value = preg_replace('/\s+/u', ' ', $value);
  return trim($value);
}

function normalizeCity($value) {
  $value = mb_strtolower((string)$value, 'UTF-8');
  $value = str_replace('ё', 'е', $value);
  $value = preg_replace('/\s+/u', ' ', $value);
  $value = preg_replace('/[()"«»]/u', '', $value);
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

    'адыгея' => 'south',
    'калмыкия' => 'south',
    'крым' => 'south',
    'краснодарский' => 'south',
    'астраханская' => 'south',
    'волгоградская' => 'south',
    'ростовская' => 'south',
    'севастополь' => 'south',

    'дагестан' => 'northCaucasus',
    'ингушетия' => 'northCaucasus',
    'кабардино-балкарская' => 'northCaucasus',
    'карачаево-черкесская' => 'northCaucasus',
    'северная осетия' => 'northCaucasus',
    'северная осетия алания' => 'northCaucasus',
    'алания' => 'northCaucasus',
    'чеченская' => 'northCaucasus',
    'ставропольский' => 'northCaucasus',

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

    'курганская' => 'ural',
    'свердловская' => 'ural',
    'тюменская' => 'ural',
    'ханты-мансийский' => 'ural',
    'ханты-мансийский югра' => 'ural',
    'югра' => 'ural',
    'ямало-ненецкий' => 'ural',
    'челябинская' => 'ural',

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

    'адыге' => 'south',
    'калмык' => 'south',
    'крым' => 'south',
    'краснодар' => 'south',
    'астрахан' => 'south',
    'волгоград' => 'south',
    'ростов' => 'south',
    'севастопол' => 'south',

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

    'курган' => 'ural',
    'свердлов' => 'ural',
    'тюмен' => 'ural',
    'ханты' => 'ural',
    'югра' => 'ural',
    'ямало' => 'ural',
    'челябин' => 'ural',

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
    'москва' => 'central',
    'балашиха' => 'central',
    'химки' => 'central',
    'мытищи' => 'central',
    'подольск' => 'central',
    'королев' => 'central',
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
    'советск' => 'northwest',

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

function getFieldEnumItems($fieldMeta) {
  if (!$fieldMeta || !is_array($fieldMeta)) {
    return [];
  }

  foreach (['items', 'ITEMS', 'list', 'LIST', 'values', 'VALUES'] as $key) {
    if (!empty($fieldMeta[$key]) && is_array($fieldMeta[$key])) {
      return $fieldMeta[$key];
    }
  }

  return [];
}

function getSelectedDealFieldsMeta($fieldCodes, $auth) {
  $meta = [];
  $fieldCodes = array_values(array_unique(array_filter($fieldCodes)));

  foreach ($fieldCodes as $fieldCode) {
    if (stripos($fieldCode, 'UF_CRM_') !== 0) {
      continue;
    }

    $res = callBitrix('crm.deal.userfield.list', [
      'filter' => [
        'FIELD_NAME' => $fieldCode
      ]
    ], $auth);

    if (!empty($res['error'])) {
      continue;
    }

    $items = $res['result'] ?? [];

    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }

      $code = $item['FIELD_NAME'] ?? '';

      if ($code === $fieldCode) {
        $meta[$fieldCode] = $item;
        break;
      }
    }
  }

  return $meta;
}

function parseFieldValue($rawValue, $fieldCode, $fieldsMeta) {
  if (is_array($rawValue)) {
    if (array_key_exists('VALUE', $rawValue)) {
      $rawValue = $rawValue['VALUE'];
    } elseif (array_key_exists('value', $rawValue)) {
      $rawValue = $rawValue['value'];
    } elseif (array_key_exists('ID', $rawValue)) {
      $rawValue = $rawValue['ID'];
    } elseif (array_key_exists('id', $rawValue)) {
      $rawValue = $rawValue['id'];
    } else {
      $rawValue = reset($rawValue);
    }
  }

  $value = (string)($rawValue ?? '');

  $fieldMeta = $fieldsMeta[$fieldCode] ?? null;
  $items = getFieldEnumItems($fieldMeta);

  foreach ($items as $item) {
    if (!is_array($item)) {
      continue;
    }

    $id = (string)($item['ID'] ?? $item['id'] ?? $item['VALUE_ID'] ?? $item['valueId'] ?? '');
    $val = (string)($item['VALUE'] ?? $item['value'] ?? $item['NAME'] ?? $item['name'] ?? '');

    if ($id === $value || $val === $value) {
      return $val ?: $value;
    }
  }

  return $value;
}

function getDistrictByRegion($region) {
  $normalized = normalizeText($region);

  if (!$normalized) {
    return null;
  }

  $exact = getRegionDistrictExact();

  if (!empty($exact[$normalized])) {
    return $exact[$normalized];
  }

  $stems = getRegionDistrictStems();

  foreach ($stems as $key => $districtKey) {
    if (
      mb_strpos($normalized, $key, 0, 'UTF-8') !== false ||
      mb_strpos($key, $normalized, 0, 'UTF-8') !== false
    ) {
      return $districtKey;
    }
  }

  return null;
}

function getDistrictByCity($city) {
  $normalized = normalizeCity($city);

  if (!$normalized) {
    return null;
  }

  if (in_array($normalized, getAmbiguousCities(), true)) {
    return [
      'needRegion' => true
    ];
  }

  $map = getCityDistrictMap();

  return $map[$normalized] ?? null;
}

function detectDistrict($city, $region) {
  $byRegion = getDistrictByRegion($region);

  if ($byRegion) {
    return [
      'status' => 'ok',
      'districtKey' => $byRegion,
      'source' => 'region'
    ];
  }

  $byCity = getDistrictByCity($city);

  if (is_array($byCity) && !empty($byCity['needRegion'])) {
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

function getDistrictNameByKey($districtKey) {
  $map = getDistrictNameMap();
  return $map[$districtKey] ?? '';
}

function calcDistrictName($city, $region) {
  $result = detectDistrict($city, $region);

  if (($result['status'] ?? '') !== 'ok' || empty($result['districtKey'])) {
    return [
      'status' => $result['status'] ?? 'unknown',
      'districtKey' => null,
      'districtName' => '',
      'source' => $result['source'] ?? null
    ];
  }

  return [
    'status' => 'ok',
    'districtKey' => $result['districtKey'],
    'districtName' => getDistrictNameByKey($result['districtKey']),
    'source' => $result['source'] ?? null
  ];
}

function syncPlainDistrictField($entityId, $deal, $districtName, $auth) {
  $targetValue = (string)($districtName ?? '');
  $currentValue = array_key_exists(OUTPUT_TEXT_FIELD, $deal)
    ? (string)($deal[OUTPUT_TEXT_FIELD] ?? '')
    : null;

  if ($currentValue !== null && $currentValue === $targetValue) {
    return [
      'updated' => false,
      'reason' => 'already_actual',
      'value' => $targetValue
    ];
  }

  $fields = [
    OUTPUT_TEXT_FIELD => $targetValue
  ];

  $update = callBitrix('crm.deal.update', [
    'id' => $entityId,
    'fields' => $fields,
    'params' => [
      'REGISTER_SONET_EVENT' => 'N',
      'REGISTER_HISTORY_EVENT' => 'N'
    ]
  ], $auth);

  if (!empty($update['error'])) {
    return [
      'updated' => false,
      'reason' => 'update_error',
      'error' => $update
    ];
  }

  return [
    'updated' => true,
    'reason' => 'updated',
    'value' => $targetValue
  ];
}

function calculateAndSyncDeal($dealId, $auth) {
  $optionsRes = callBitrix('app.option.get', [], $auth);

  if (!empty($optionsRes['error'])) {
    return [
      'success' => false,
      'reason' => 'options_error',
      'error' => $optionsRes
    ];
  }

  $appOptions = $optionsRes['result'] ?? [];

  $cityField = $appOptions['dealCityField'] ?? '';
  $regionField = $appOptions['dealRegionField'] ?? '';

  if (!$cityField) {
    return [
      'success' => false,
      'reason' => 'no_city_field'
    ];
  }

  $dealRes = callBitrix('crm.deal.get', [
    'id' => $dealId
  ], $auth);

  if (!empty($dealRes['error'])) {
    return [
      'success' => false,
      'reason' => 'deal_get_error',
      'error' => $dealRes
    ];
  }

  $deal = $dealRes['result'] ?? [];

  $fieldsMeta = getSelectedDealFieldsMeta([
    $cityField,
    $regionField
  ], $auth);

  $city = parseFieldValue($deal[$cityField] ?? '', $cityField, $fieldsMeta);
  $region = $regionField ? parseFieldValue($deal[$regionField] ?? '', $regionField, $fieldsMeta) : '';

  if (!$city) {
    $sync = syncPlainDistrictField($dealId, $deal, '', $auth);

    return [
      'success' => true,
      'status' => 'no_city',
      'districtName' => '',
      'sync' => $sync
    ];
  }

  $calc = calcDistrictName($city, $region);
  $districtName = ($calc['status'] === 'ok') ? $calc['districtName'] : '';

  $sync = syncPlainDistrictField($dealId, $deal, $districtName, $auth);

  return [
    'success' => true,
    'status' => $calc['status'],
    'city' => $city,
    'region' => $region,
    'districtKey' => $calc['districtKey'],
    'districtName' => $districtName,
    'source' => $calc['source'],
    'sync' => $sync
  ];
}
