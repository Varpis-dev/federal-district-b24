<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Федеральный округ сделки</title>
  <script src="https://api.bitrix24.com/api/v1/"></script>

  <style>
    html, body {
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
      background: linear-gradient(135deg, #f4f8ff 0%, #ffffff 75%);
      box-shadow: 0 4px 14px rgba(24, 91, 170, 0.08);
    }

    .label {
      font-size: 12px;
      color: #7b8794;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 6px;
    }

    .main {
      font-size: 24px;
      line-height: 1.1;
      font-weight: 800;
      color: #111827;
      margin-bottom: 8px;
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
      margin-bottom: 8px;
    }

    .place {
      font-size: 13px;
      color: #4b5563;
      line-height: 1.3;
      font-weight: 600;
    }

    .warn {
      border-color: #f1d48a;
      background: linear-gradient(135deg, #fff8e6 0%, #ffffff 75%);
      box-shadow: 0 4px 14px rgba(154, 106, 0, 0.10);
    }

    .warn .main {
      color: #9a6a00;
    }

    .bad {
      border-color: #efb7b3;
      background: linear-gradient(135deg, #fff1f0 0%, #ffffff 75%);
      box-shadow: 0 4px 14px rgba(217, 45, 32, 0.08);
    }

    .bad .main {
      color: #d92d20;
    }

    .small {
      margin-top: 6px;
      font-size: 12px;
      color: #7b8794;
      line-height: 1.35;
    }
  </style>
</head>

<body>
<div class="wrap" id="wrap">
  <div class="label">Федеральный округ</div>
  <div class="main" id="main">Загрузка...</div>
  <div class="manager" id="manager" style="display:none;"></div>
  <div class="place" id="place"></div>
  <div class="small" id="small"></div>
</div>

<script>
const wrapEl = document.getElementById('wrap');
const mainEl = document.getElementById('main');
const managerEl = document.getElementById('manager');
const placeEl = document.getElementById('place');
const smallEl = document.getElementById('small');

const DISTRICTS = {
  central: 'Центральный',
  northwest: 'Северо-Западный',
  south: 'Южный',
  northCaucasus: 'Северо-Кавказский',
  volga: 'Приволжский',
  ural: 'Уральский',
  siberian: 'Сибирский',
  farEast: 'Дальневосточный'
};

const DEFAULT_MANAGERS = {
  central: 'Людмила',
  northwest: 'Виктория',
  south: 'Вячеслав',
  northCaucasus: 'Вячеслав',
  volga: 'Виктория',
  ural: 'Вячеслав',
  siberian: 'Людмила',
  farEast: 'Людмила'
};

const REGION_DISTRICT_EXACT = {
  // Центральный
  'белгородская': 'central',
  'брянская': 'central',
  'владимирская': 'central',
  'воронежская': 'central',
  'ивановская': 'central',
  'калужская': 'central',
  'костромская': 'central',
  'курская': 'central',
  'липецкая': 'central',
  'московская': 'central',
  'москва': 'central',
  'орловская': 'central',
  'рязанская': 'central',
  'смоленская': 'central',
  'тамбовская': 'central',
  'тверская': 'central',
  'тульская': 'central',
  'ярославская': 'central',

  // Северо-Западный
  'карелия': 'northwest',
  'коми': 'northwest',
  'архангельская': 'northwest',
  'ненецкий': 'northwest',
  'вологодская': 'northwest',
  'калининградская': 'northwest',
  'ленинградская': 'northwest',
  'мурманская': 'northwest',
  'новгородская': 'northwest',
  'псковская': 'northwest',
  'санкт-петербург': 'northwest',
  'петербург': 'northwest',
  'спб': 'northwest',

  // Южный
  'адыгея': 'south',
  'калмыкия': 'south',
  'крым': 'south',
  'краснодарский': 'south',
  'астраханская': 'south',
  'волгоградская': 'south',
  'ростовская': 'south',
  'севастополь': 'south',

  // Северо-Кавказский
  'дагестан': 'northCaucasus',
  'ингушетия': 'northCaucasus',
  'кабардино-балкарская': 'northCaucasus',
  'карачаево-черкесская': 'northCaucasus',
  'северная осетия': 'northCaucasus',
  'северная осетия алания': 'northCaucasus',
  'алания': 'northCaucasus',
  'чеченская': 'northCaucasus',
  'ставропольский': 'northCaucasus',

  // Приволжский
  'башкортостан': 'volga',
  'башкирия': 'volga',
  'марий эл': 'volga',
  'мордовия': 'volga',
  'татарстан': 'volga',
  'удмуртская': 'volga',
  'чувашская': 'volga',
  'пермский': 'volga',
  'кировская': 'volga',
  'нижегородская': 'volga',
  'оренбургская': 'volga',
  'пензенская': 'volga',
  'самарская': 'volga',
  'саратовская': 'volga',
  'ульяновская': 'volga',

  // Уральский
  'курганская': 'ural',
  'свердловская': 'ural',
  'тюменская': 'ural',
  'ханты-мансийский': 'ural',
  'ханты-мансийский югра': 'ural',
  'югра': 'ural',
  'ямало-ненецкий': 'ural',
  'челябинская': 'ural',

  // Сибирский
  'алтайский': 'siberian',
  'алтай': 'siberian',
  'тыва': 'siberian',
  'тува': 'siberian',
  'хакасия': 'siberian',
  'красноярский': 'siberian',
  'иркутская': 'siberian',
  'кемеровская': 'siberian',
  'кузбасс': 'siberian',
  'новосибирская': 'siberian',
  'омская': 'siberian',
  'томская': 'siberian',

  // Дальневосточный
  'бурятия': 'farEast',
  'саха': 'farEast',
  'якутия': 'farEast',
  'забайкальский': 'farEast',
  'камчатский': 'farEast',
  'приморский': 'farEast',
  'хабаровский': 'farEast',
  'амурская': 'farEast',
  'магаданская': 'farEast',
  'сахалинская': 'farEast',
  'еврейская': 'farEast',
  'чукотский': 'farEast'
};

const REGION_DISTRICT_STEMS = {
  // Центральный
  'белгород': 'central',
  'брянск': 'central',
  'владимир': 'central',
  'воронеж': 'central',
  'иванов': 'central',
  'калуж': 'central',
  'костром': 'central',
  'курск': 'central',
  'липецк': 'central',
  'московск': 'central',
  'орлов': 'central',
  'рязан': 'central',
  'смоленск': 'central',
  'тамбов': 'central',
  'твер': 'central',
  'тульск': 'central',
  'ярослав': 'central',

  // Северо-Западный
  'карел': 'northwest',
  'коми': 'northwest',
  'архангел': 'northwest',
  'ненец': 'northwest',
  'вологод': 'northwest',
  'калининград': 'northwest',
  'ленинград': 'northwest',
  'мурман': 'northwest',
  'новгород': 'northwest',
  'псков': 'northwest',
  'петербург': 'northwest',

  // Южный
  'адыге': 'south',
  'калмык': 'south',
  'крым': 'south',
  'краснодар': 'south',
  'астрахан': 'south',
  'волгоград': 'south',
  'ростов': 'south',
  'севастопол': 'south',

  // Северо-Кавказский
  'дагестан': 'northCaucasus',
  'ингуш': 'northCaucasus',
  'кабардино': 'northCaucasus',
  'балкар': 'northCaucasus',
  'карачаево': 'northCaucasus',
  'черкес': 'northCaucasus',
  'осет': 'northCaucasus',
  'алания': 'northCaucasus',
  'чечен': 'northCaucasus',
  'ставропол': 'northCaucasus',

  // Приволжский
  'башкортостан': 'volga',
  'башкир': 'volga',
  'марий': 'volga',
  'мордов': 'volga',
  'татарстан': 'volga',
  'удмурт': 'volga',
  'чуваш': 'volga',
  'перм': 'volga',
  'киров': 'volga',
  'нижегород': 'volga',
  'оренбург': 'volga',
  'пенз': 'volga',
  'самар': 'volga',
  'саратов': 'volga',
  'ульянов': 'volga',

  // Уральский
  'курган': 'ural',
  'свердлов': 'ural',
  'тюмен': 'ural',
  'ханты': 'ural',
  'югра': 'ural',
  'ямало': 'ural',
  'челябин': 'ural',

  // Сибирский
  'алтай': 'siberian',
  'тыва': 'siberian',
  'тува': 'siberian',
  'хакас': 'siberian',
  'краснояр': 'siberian',
  'иркут': 'siberian',
  'кемеров': 'siberian',
  'кузбасс': 'siberian',
  'новосибир': 'siberian',
  'омск': 'siberian',
  'томск': 'siberian',

  // Дальневосточный
  'бурят': 'farEast',
  'саха': 'farEast',
  'якут': 'farEast',
  'забайкал': 'farEast',
  'камчат': 'farEast',
  'примор': 'farEast',
  'хабаров': 'farEast',
  'амур': 'farEast',
  'магадан': 'farEast',
  'сахалин': 'farEast',
  'еврей': 'farEast',
  'чукот': 'farEast'
};

const CITY_DISTRICT = {
  // Центральный
  'москва': 'central',
  'балашиха': 'central',
  'химки': 'central',
  'мытищи': 'central',
  'подольск': 'central',
  'королев': 'central',
  'люберцы': 'central',
  'красногорск': 'central',
  'одинцово': 'central',
  'воронеж': 'central',
  'липецк': 'central',
  'тамбов': 'central',
  'белгород': 'central',
  'курск': 'central',
  'орел': 'central',
  'орёл': 'central',
  'тула': 'central',
  'рязань': 'central',
  'калуга': 'central',
  'брянск': 'central',
  'смоленск': 'central',
  'тверь': 'central',
  'ярославль': 'central',
  'владимир': 'central',
  'иваново': 'central',
  'кострома': 'central',

  // Северо-Западный
  'санкт-петербург': 'northwest',
  'петербург': 'northwest',
  'калининград': 'northwest',
  'мурманск': 'northwest',
  'архангельск': 'northwest',
  'северодвинск': 'northwest',
  'вологда': 'northwest',
  'череповец': 'northwest',
  'псков': 'northwest',
  'великий новгород': 'northwest',
  'петрозаводск': 'northwest',
  'сыктывкар': 'northwest',
  'советск': 'northwest',

  // Южный
  'краснодар': 'south',
  'сочи': 'south',
  'новороссийск': 'south',
  'анапа': 'south',
  'геленджик': 'south',
  'ростов-на-дону': 'south',
  'ростов на дону': 'south',
  'таганрог': 'south',
  'шахты': 'south',
  'волгоград': 'south',
  'волжский': 'south',
  'астрахань': 'south',
  'элиста': 'south',
  'майкоп': 'south',
  'симферополь': 'south',
  'севастополь': 'south',
  'ялта': 'south',

  // Северо-Кавказский
  'махачкала': 'northCaucasus',
  'каспийск': 'northCaucasus',
  'дербент': 'northCaucasus',
  'грозный': 'northCaucasus',
  'ставрополь': 'northCaucasus',
  'пятигорск': 'northCaucasus',
  'кисловодск': 'northCaucasus',
  'ессентуки': 'northCaucasus',
  'невинномысск': 'northCaucasus',
  'наличк': 'northCaucasus',
  'нальчик': 'northCaucasus',
  'владикавказ': 'northCaucasus',
  'назрань': 'northCaucasus',
  'черкесск': 'northCaucasus',

  // Приволжский
  'нижний новгород': 'volga',
  'дзержинск': 'volga',
  'богородск': 'volga',
  'шаранга': 'volga',
  'казань': 'volga',
  'набережные челны': 'volga',
  'альметьевск': 'volga',
  'нижнекамск': 'volga',
  'йошкар-ола': 'volga',
  'чебоксары': 'volga',
  'саранск': 'volga',
  'пенза': 'volga',
  'кузнецк': 'volga',
  'самара': 'volga',
  'тольятти': 'volga',
  'сызрань': 'volga',
  'саратов': 'volga',
  'энгельс': 'volga',
  'балаково': 'volga',
  'балашов': 'volga',
  'ульяновск': 'volga',
  'димитровград': 'volga',
  'киров': 'volga',
  'пермь': 'volga',
  'березники': 'volga',
  'соликамск': 'volga',
  'уфа': 'volga',
  'стерлитамак': 'volga',
  'мелеуз': 'volga',
  'салават': 'volga',
  'нефтекамск': 'volga',
  'оренбург': 'volga',
  'орск': 'volga',
  'бузулук': 'volga',
  'ижевск': 'volga',
  'сарапул': 'volga',

  // Уральский
  'екатеринбург': 'ural',
  'нижний тагил': 'ural',
  'каменск-уральский': 'ural',
  'первоуральск': 'ural',
  'челябинск': 'ural',
  'магнитогорск': 'ural',
  'миасс': 'ural',
  'златоуст': 'ural',
  'копейск': 'ural',
  'курган': 'ural',
  'тюмень': 'ural',
  'тобольск': 'ural',
  'сургут': 'ural',
  'нижневартовск': 'ural',
  'ханты-мансийск': 'ural',
  'нефтеюганск': 'ural',
  'новый уренгой': 'ural',
  'ноябрьск': 'ural',
  'надым': 'ural',
  'салехард': 'ural',

  // Сибирский
  'новосибирск': 'siberian',
  'бердск': 'siberian',
  'омск': 'siberian',
  'томск': 'siberian',
  'северск': 'siberian',
  'красноярск': 'siberian',
  'ачинск': 'siberian',
  'норильск': 'siberian',
  'иркутск': 'siberian',
  'ангарск': 'siberian',
  'братск': 'siberian',
  'кемерово': 'siberian',
  'новокузнецк': 'siberian',
  'прокопьевск': 'siberian',
  'киселевск': 'siberian',
  'киселёвск': 'siberian',
  'междуреченск': 'siberian',
  'барнаул': 'siberian',
  'бийск': 'siberian',
  'рубцовск': 'siberian',
  'горно-алтайск': 'siberian',
  'абакан': 'siberian',
  'кызыл': 'siberian',

  // Дальневосточный
  'улан-удэ': 'farEast',
  'чита': 'farEast',
  'якутск': 'farEast',
  'благовещенск': 'farEast',
  'владивосток': 'farEast',
  'артем': 'farEast',
  'артём': 'farEast',
  'уссурийск': 'farEast',
  'находка': 'farEast',
  'хабаровск': 'farEast',
  'комсомольск-на-амуре': 'farEast',
  'южно-сахалинск': 'farEast',
  'магадан': 'farEast',
  'петропавловск-камчатский': 'farEast'
};

const AMBIGUOUS_CITIES = [
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

function bxCall(method, params = {}, timeoutMs = 15000) {
  return new Promise((resolve, reject) => {
    let finished = false;

    const timer = setTimeout(() => {
      if (!finished) {
        finished = true;
        reject(new Error('Таймаут вызова ' + method));
      }
    }, timeoutMs);

    try {
      BX24.callMethod(method, params, function(res) {
        if (finished) return;

        finished = true;
        clearTimeout(timer);

        if (!res) {
          reject(new Error('Пустой ответ от ' + method));
          return;
        }

        if (res.error()) {
          reject(new Error(JSON.stringify(res.error(), null, 2)));
          return;
        }

        resolve(res.data());
      });
    } catch (e) {
      if (!finished) {
        finished = true;
        clearTimeout(timer);
        reject(e);
      }
    }
  });
}

function normalizeText(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[ё]/g, 'е')
    .replace(/\s+/g, ' ')
    .replace(/[()"«»]/g, '')
    .replace(/(^|\s)г\.(?=\s|$)/gi, ' ')
    .replace(/(^|\s)(область|обл\.?|республика|респ\.?|край|ао|автономный округ|город)(?=\s|$)/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizeCity(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[ё]/g, 'е')
    .replace(/\s+/g, ' ')
    .replace(/[()"«»]/g, '')
    .trim();
}

function getFieldEnumItems(fieldMeta) {
  if (!fieldMeta) return [];

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

function parseFieldValue(rawValue, fieldCode, fieldsMeta) {
  if (Array.isArray(rawValue)) {
    rawValue = rawValue[0] || '';
  }

  if (typeof rawValue === 'object' && rawValue !== null) {
    rawValue = rawValue.VALUE || rawValue.value || rawValue.ID || rawValue.id || '';
  }

  let value = rawValue || '';

  const fieldMeta = fieldsMeta ? fieldsMeta[fieldCode] : null;
  const items = getFieldEnumItems(fieldMeta);

  if (items && items.length) {
    const found = items.find(item => {
      const id = String(item.ID || item.id || item.VALUE_ID || item.valueId || '');
      const val = String(item.VALUE || item.value || item.NAME || item.name || '');
      return id === String(value) || val === String(value);
    });

    if (found) {
      return found.VALUE || found.value || found.NAME || found.name || value;
    }
  }

  return value;
}

function getDistrictByRegion(region) {
  const normalized = normalizeText(region);

  if (!normalized) return null;

  if (REGION_DISTRICT_EXACT[normalized]) {
    return REGION_DISTRICT_EXACT[normalized];
  }

  for (const key in REGION_DISTRICT_STEMS) {
    if (normalized.includes(key) || key.includes(normalized)) {
      return REGION_DISTRICT_STEMS[key];
    }
  }

  return null;
}

function getDistrictByCity(city) {
  const normalized = normalizeCity(city);

  if (!normalized) return null;

  if (AMBIGUOUS_CITIES.includes(normalized)) {
    return {
      needRegion: true
    };
  }

  return CITY_DISTRICT[normalized] || null;
}

function getManagerByDistrict(appOptions, districtKey) {
  const map = {
    central: appOptions.managerCentral || DEFAULT_MANAGERS.central,
    northwest: appOptions.managerNorthwest || DEFAULT_MANAGERS.northwest,
    south: appOptions.managerSouth || DEFAULT_MANAGERS.south,
    northCaucasus: appOptions.managerNorthCaucasus || DEFAULT_MANAGERS.northCaucasus,
    volga: appOptions.managerVolga || DEFAULT_MANAGERS.volga,
    ural: appOptions.managerUral || DEFAULT_MANAGERS.ural,
    siberian: appOptions.managerSiberian || DEFAULT_MANAGERS.siberian,
    farEast: appOptions.managerFarEast || DEFAULT_MANAGERS.farEast
  };

  return map[districtKey] || '';
}

function detectDistrict(city, region) {
  const byRegion = getDistrictByRegion(region);

  if (byRegion) {
    return {
      status: 'ok',
      districtKey: byRegion,
      source: 'region'
    };
  }

  const byCity = getDistrictByCity(city);

  if (byCity && byCity.needRegion) {
    return {
      status: 'need_region',
      districtKey: null,
      source: 'city'
    };
  }

  if (byCity) {
    return {
      status: 'ok',
      districtKey: byCity,
      source: 'city'
    };
  }

  return {
    status: 'unknown',
    districtKey: null,
    source: null
  };
}

function renderOk(districtKey, manager, city, region, source) {
  const districtName = DISTRICTS[districtKey] || 'Не определено';

  wrapEl.className = 'wrap';

  mainEl.textContent = manager
    ? districtName + ' (' + manager + ')'
    : districtName;

  managerEl.style.display = manager ? 'inline-flex' : 'none';
  managerEl.textContent = manager ? 'Менеджер: ' + manager : '';

  placeEl.textContent = region
    ? city + ', ' + region
    : city;

  smallEl.textContent = source === 'region'
    ? 'Округ определён по области/региону'
    : 'Округ определён по городу';
}

function renderNeedRegion(city) {
  wrapEl.className = 'wrap warn';

  mainEl.textContent = 'Нужна область';
  managerEl.style.display = 'none';
  placeEl.textContent = city || '';
  smallEl.textContent = 'Город может относиться к разным регионам. Заполните область для точного определения федерального округа.';
}

function renderUnknown(city, region) {
  wrapEl.className = 'wrap bad';

  mainEl.textContent = 'Округ не определён';
  managerEl.style.display = 'none';
  placeEl.textContent = region ? city + ', ' + region : city;
  smallEl.textContent = 'Проверьте заполнение города и области в сделке.';
}

function renderError(title, message) {
  wrapEl.className = 'wrap bad';

  mainEl.textContent = title;
  managerEl.style.display = 'none';
  placeEl.textContent = '';
  smallEl.textContent = message || '';
}

BX24.init(async function() {
  try {
    const info = BX24.placement.info();
    const options = info && info.options ? info.options : {};

    const entityId =
      options.ENTITY_VALUE_ID ||
      options.ID ||
      options.id ||
      options.DEAL_ID ||
      (options.ENTITY_DATA && (options.ENTITY_DATA.entityId || options.ENTITY_DATA.id)) ||
      null;

    if (!entityId) {
      renderError('Нет ID сделки', 'Не удалось определить ID сделки из Bitrix24.');
      return;
    }

    const appOptions = await bxCall('app.option.get', {});

    const cityField = appOptions.dealCityField || '';
    const regionField = appOptions.dealRegionField || '';

    if (!cityField) {
      renderError('Не выбрано поле города', 'Откройте настройки приложения и выберите поле с городом сделки.');
      return;
    }

    const deal = await bxCall('crm.deal.get', { id: entityId });
    const fieldsMeta = await bxCall('crm.deal.fields', {});

    const rawCity = deal[cityField];
    const rawRegion = regionField ? deal[regionField] : '';

    const city = parseFieldValue(rawCity, cityField, fieldsMeta);
    const region = regionField ? parseFieldValue(rawRegion, regionField, fieldsMeta) : '';

    if (!city) {
      renderError('Город не заполнен', 'Заполните город в карточке сделки.');
      return;
    }

    const result = detectDistrict(city, region);

    if (result.status === 'need_region') {
      renderNeedRegion(city);
      return;
    }

    if (result.status === 'unknown' || !result.districtKey) {
      renderUnknown(city, region);
      return;
    }

    const manager = getManagerByDistrict(appOptions, result.districtKey);
    renderOk(result.districtKey, manager, city, region, result.source);

    if (window.BX24 && BX24.fitWindow) {
      BX24.fitWindow();
    }

  } catch (e) {
    renderError('Ошибка загрузки', 'Обновите карточку или проверьте настройки приложения.');
  }
});
</script>
</body>
</html>
