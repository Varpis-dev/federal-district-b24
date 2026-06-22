<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Федеральный округ сделки — настройки</title>
  <script src="https://api.bitrix24.com/api/v1/"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 24px;
      color: #111;
    }

    h1 {
      margin-top: 0;
      font-size: 32px;
    }

    .section {
      margin-top: 22px;
      padding: 18px;
      border-radius: 14px;
      background: #f6f8fa;
      max-width: 920px;
    }

    label {
      display: block;
      margin: 14px 0 6px;
      font-weight: 700;
      font-size: 14px;
    }

    select,
    input {
      box-sizing: border-box;
      width: 100%;
      max-width: 680px;
      padding: 9px 11px;
      font-size: 14px;
      border: 1px solid #c8d0da;
      border-radius: 8px;
      background: #fff;
    }

    .grid {
      display: grid;
      grid-template-columns: minmax(220px, 320px) minmax(220px, 360px);
      gap: 12px 18px;
      align-items: center;
      max-width: 760px;
    }

    .district-label {
      font-weight: 700;
      color: #333;
    }

    .buttons {
      margin-top: 18px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    button {
      padding: 10px 14px;
      font-size: 14px;
      border-radius: 9px;
      border: 1px solid #b8c2cc;
      background: #fff;
      cursor: pointer;
      font-weight: 700;
    }

    button.primary {
      background: #0b7cff;
      border-color: #0b7cff;
      color: #fff;
    }

    #status {
      margin-top: 18px;
      white-space: pre-wrap;
      color: #444;
      background: #fff;
      padding: 14px;
      border-radius: 12px;
      min-height: 76px;
      border: 1px solid #e0e5eb;
      max-width: 920px;
    }
  </style>
</head>
<body>
  <h1>Федеральный округ сделки</h1>

  <div class="section">
    <h2>Поля сделки</h2>

    <label for="cityField">Поле с городом в сделке</label>
    <select id="cityField"></select>

    <label for="regionField">Поле с областью / регионом в сделке</label>
    <select id="regionField"></select>
  </div>

  <div class="section">
    <h2>Менеджеры по федеральным округам</h2>

    <div class="grid">
      <div class="district-label">Центральный</div>
      <input id="managerCentral" type="text" value="Людмила">

      <div class="district-label">Северо-Западный</div>
      <input id="managerNorthwest" type="text" value="Виктория">

      <div class="district-label">Южный</div>
      <input id="managerSouth" type="text" value="Вячеслав">

      <div class="district-label">Северо-Кавказский</div>
      <input id="managerNorthCaucasus" type="text" value="Вячеслав">

      <div class="district-label">Приволжский</div>
      <input id="managerVolga" type="text" value="Виктория">

      <div class="district-label">Уральский</div>
      <input id="managerUral" type="text" value="Вячеслав">

      <div class="district-label">Сибирский</div>
      <input id="managerSiberian" type="text" value="Людмила">

      <div class="district-label">Дальневосточный</div>
      <input id="managerFarEast" type="text" value="Людмила">
    </div>
  </div>

  <div class="buttons">
    <button class="primary" id="saveBtn">Сохранить настройки</button>
    <button id="bindBtn">Создать поле в сделке и привязать</button>
  </div>

  <div id="status">Страница загружена.</div>

<script>
const statusEl = document.getElementById('status');

function setStatus(text) {
  statusEl.textContent = text;
}

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

function getInputValue(id) {
  return document.getElementById(id).value.trim();
}

function setInputValue(id, value) {
  document.getElementById(id).value = value || '';
}

function getSettingsPayload(current = {}) {
  return {
    appName: 'Федеральный округ сделки',

    dealCityField: document.getElementById('cityField').value || '',
    dealRegionField: document.getElementById('regionField').value || '',

    managerCentral: getInputValue('managerCentral') || 'Людмила',
    managerNorthwest: getInputValue('managerNorthwest') || 'Виктория',
    managerSouth: getInputValue('managerSouth') || 'Вячеслав',
    managerNorthCaucasus: getInputValue('managerNorthCaucasus') || 'Вячеслав',
    managerVolga: getInputValue('managerVolga') || 'Виктория',
    managerUral: getInputValue('managerUral') || 'Вячеслав',
    managerSiberian: getInputValue('managerSiberian') || 'Людмила',
    managerFarEast: getInputValue('managerFarEast') || 'Людмила'
  };
}

function applyManagerDefaults(options) {
  setInputValue('managerCentral', options.managerCentral || 'Людмила');
  setInputValue('managerNorthwest', options.managerNorthwest || 'Виктория');
  setInputValue('managerSouth', options.managerSouth || 'Вячеслав');
  setInputValue('managerNorthCaucasus', options.managerNorthCaucasus || 'Вячеслав');
  setInputValue('managerVolga', options.managerVolga || 'Виктория');
  setInputValue('managerUral', options.managerUral || 'Вячеслав');
  setInputValue('managerSiberian', options.managerSiberian || 'Людмила');
  setInputValue('managerFarEast', options.managerFarEast || 'Людмила');
}

async function loadFields() {
  setStatus('Загружаю поля сделки...');

  const options = await bxCall('app.option.get', {});
  const fields = await bxCall('crm.deal.fields', {});

  const citySelect = document.getElementById('cityField');
  const regionSelect = document.getElementById('regionField');

  citySelect.innerHTML = '';
  regionSelect.innerHTML = '';

  const emptyCity = document.createElement('option');
  emptyCity.value = '';
  emptyCity.textContent = 'Выберите поле с городом';
  citySelect.appendChild(emptyCity);

  const emptyRegion = document.createElement('option');
  emptyRegion.value = '';
  emptyRegion.textContent = 'Не использовать область';
  regionSelect.appendChild(emptyRegion);

  const savedCity = options.dealCityField || '';
  const savedRegion = options.dealRegionField || '';

  Object.keys(fields || {}).forEach(code => {
    const title = fields[code].title || fields[code].formLabel || fields[code].listLabel || code;

    const cityOpt = document.createElement('option');
    cityOpt.value = code;
    cityOpt.textContent = code + ' — ' + title;
    if (code === savedCity) cityOpt.selected = true;
    citySelect.appendChild(cityOpt);

    const regionOpt = document.createElement('option');
    regionOpt.value = code;
    regionOpt.textContent = code + ' — ' + title;
    if (code === savedRegion) regionOpt.selected = true;
    regionSelect.appendChild(regionOpt);
  });

  applyManagerDefaults(options || {});

  setStatus('Поля сделки загружены. Выберите город, область и сохраните настройки.');
}

async function saveOptions() {
  const cityField = document.getElementById('cityField').value || '';

  if (!cityField) {
    setStatus('Не выбрано поле с городом.');
    return null;
  }

  setStatus('Сохраняю настройки...');

  const current = await bxCall('app.option.get', {});
  const payload = getSettingsPayload(current);

  await bxCall('app.option.set', { options: payload });

  setStatus(
    'Настройки сохранены.\n\n' +
    'Поле города: ' + payload.dealCityField + '\n' +
    'Поле области: ' + (payload.dealRegionField || 'не используется') + '\n\n' +
    'Центральный: ' + payload.managerCentral + '\n' +
    'Северо-Западный: ' + payload.managerNorthwest + '\n' +
    'Южный: ' + payload.managerSouth + '\n' +
    'Северо-Кавказский: ' + payload.managerNorthCaucasus + '\n' +
    'Приволжский: ' + payload.managerVolga + '\n' +
    'Уральский: ' + payload.managerUral + '\n' +
    'Сибирский: ' + payload.managerSiberian + '\n' +
    'Дальневосточный: ' + payload.managerFarEast
  );

  return payload;
}

BX24.init(async function() {
  try {
    await loadFields();

    document.getElementById('saveBtn').addEventListener('click', async function() {
      try {
        await saveOptions();
      } catch (e) {
        setStatus('Ошибка сохранения:\n' + String(e));
      }
    });

    document.getElementById('bindBtn').addEventListener('click', async function() {
      try {
        const saved = await saveOptions();
        if (!saved) return;

        const auth = BX24.getAuth();

        if (!auth || !auth.access_token || !auth.domain) {
          setStatus('Не удалось получить авторизацию Б24');
          return;
        }

        setStatus('Создаю пользовательское поле в сделке...');

        const r = await fetch('/bind', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            auth
          })
        });

        const text = await r.text();

        setStatus(
          'Ответ /bind:\n' +
          'HTTP ' + r.status + '\n\n' +
          text
        );
      } catch (e) {
        setStatus('Ошибка создания поля:\n' + String(e));
      }
    });

  } catch (e) {
    setStatus('Ошибка инициализации:\n' + String(e));
  }
});
</script>
</body>
</html>
