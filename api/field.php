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

const OUTPUT_TEXT_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';

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

async function syncPlainDistrictField(entityId, deal, districtName) {
  try {
    if (!entityId) return;

    const targetValue = districtName || '';
    const currentValue = typeof deal[OUTPUT_TEXT_FIELD] !== 'undefined'
      ? String(deal[OUTPUT_TEXT_FIELD] || '')
      : null;

    if (currentValue !== null && currentValue === targetValue) {
      return;
    }

    const fields = {};
    fields[OUTPUT_TEXT_FIELD] = targetValue;

    await bxCall('crm.deal.update', {
      id: entityId,
      fields,
      params: {
        REGISTER_SONET_EVENT: 'N',
        REGISTER_HISTORY_EVENT: 'N'
      }
    }, 15000);
  } catch (e) {
    console.log('Не удалось записать строковое поле федерального округа:', e);
  }
}

function renderOk(districtName, manager, city, region, source) {
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
    ? 'Округ определён по области/региону · строковое поле обновляется автоматически'
    : 'Округ определён по городу · строковое поле обновляется автоматически';
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
  smallEl.textContent = 'Проверьте заполнение города и области в сделке. Строковое поле будет очищено.';
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
      await syncPlainDistrictField(entityId, deal, '');
      renderError('Город не заполнен', 'Заполните город в карточке сделки. Строковое поле федерального округа очищено.');
      return;
    }

    const calcResponse = await fetch('/calc', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ city, region })
    });

    const calcData = await calcResponse.json();
    const calc = calcData.result || {};

    if (calc.status === 'need_region') {
      await syncPlainDistrictField(entityId, deal, '');
      renderNeedRegion(city);
      return;
    }

    if (calc.status === 'unknown' || !calc.districtName) {
      await syncPlainDistrictField(entityId, deal, '');
      renderUnknown(city, region);
      return;
    }

    const manager = getManagerByDistrict(appOptions, calc.districtKey);

    await syncPlainDistrictField(entityId, deal, calc.districtName);

    renderOk(calc.districtName, manager, city, region, calc.source);

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
