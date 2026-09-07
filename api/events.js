const FederalDistrict = require('../public/district.js');

const LEAD_FED_FIELD = 'UF_CRM_FEDERAL_DISTRICT_TEXT';
const REST_TIMEOUT_MS = 10000;
const RETRY_DELAYS = [0, 500, 1500];
const ENUM_CACHE_TTL_MS = 60 * 60 * 1000;
const enumCache = new Map();

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function setBracketValue(target, key, value) {
  const parts = String(key).replace(/\]/g, '').split('[').filter(Boolean);
  let cur = target;

  for (let i = 0; i < parts.length; i++) {
    const part = parts[i];

    if (i === parts.length - 1) {
      cur[part] = value;
    } else {
      if (!cur[part] || typeof cur[part] !== 'object') cur[part] = {};
      cur = cur[part];
    }
  }
}

function parseBody(req) {
  const body = req?.body;

  if (body && typeof body === 'object' && !Buffer.isBuffer(body)) {
    const out = {};
    for (const [key, value] of Object.entries(body)) {
      if (key.includes('[')) setBracketValue(out, key, value);
      else out[key] = value;
    }
    return out;
  }

  const raw = Buffer.isBuffer(body) ? body.toString('utf8') : String(body || '');
  if (!raw) return {};

  try {
    return JSON.parse(raw);
  } catch (_) {}

  const params = new URLSearchParams(raw);
  const out = {};
  for (const [key, value] of params.entries()) setBracketValue(out, key, value);
  return out;
}

function extractEvent(body) {
  const auth = body?.auth || body?.AUTH || {};

  return {
    event: String(body?.event || body?.EVENT || '').toUpperCase(),
    leadId: String(
      body?.data?.FIELDS?.ID ||
      body?.data?.fields?.ID ||
      body?.DATA?.FIELDS?.ID ||
      ''
    ),
    domain: String(auth.domain || auth.DOMAIN || '')
      .replace(/^https?:\/\//i, '')
      .replace(/\/+$/, ''),
    accessToken: String(auth.access_token || auth.ACCESS_TOKEN || '')
  };
}

function appendForm(form, key, value) {
  if (value === undefined || value === null) return;

  if (Array.isArray(value)) {
    value.forEach((v, i) => appendForm(form, `${key}[${i}]`, v));
    return;
  }

  if (typeof value === 'object') {
    for (const [k, v] of Object.entries(value)) {
      appendForm(form, `${key}[${k}]`, v);
    }
    return;
  }

  form.append(key, String(value));
}

function isTransient(error) {
  const m = String(error?.message || error || '').toLowerCase();

  return (
    m.includes('abort') ||
    m.includes('timeout') ||
    m.includes('fetch failed') ||
    m.includes('network') ||
    m.includes('пустой ответ') ||
    m.includes('empty response') ||
    m.includes('http 429') ||
    m.includes('http 500') ||
    m.includes('http 502') ||
    m.includes('http 503') ||
    m.includes('http 504') ||
    m.includes('query_limit_exceeded') ||
    m.includes('operation time limit')
  );
}

async function callBitrixOnce(domain, accessToken, method, params = {}) {
  const form = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) appendForm(form, key, value);
  form.append('auth', accessToken);

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), REST_TIMEOUT_MS);

  try {
    const response = await fetch(`https://${domain}/rest/${method}.json`, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: form.toString(),
      signal: controller.signal
    });

    const raw = await response.text();

    if (!response.ok) {
      throw new Error(`Bitrix HTTP ${response.status} ${method}: ${raw.slice(0, 300)}`);
    }

    if (!raw.trim()) {
      throw new Error(`Пустой ответ Bitrix24: ${method}`);
    }

    let data;
    try {
      data = JSON.parse(raw);
    } catch (_) {
      throw new Error(`Некорректный JSON Bitrix24: ${method}: ${raw.slice(0, 300)}`);
    }

    if (data.error) {
      throw new Error(`${data.error}: ${data.error_description || method}`);
    }

    return data.result;
  } finally {
    clearTimeout(timer);
  }
}

async function callBitrix(domain, accessToken, method, params = {}) {
  let lastError;

  for (let i = 0; i < RETRY_DELAYS.length; i++) {
    if (RETRY_DELAYS[i]) await sleep(RETRY_DELAYS[i]);

    try {
      const result = await callBitrixOnce(domain, accessToken, method, params);
      if (i > 0) {
        console.log(`[fed] ${method} recovered on attempt=${i + 1}`);
      }
      return result;
    } catch (error) {
      lastError = error;
      console.warn(
        `[fed] REST error method=${method} attempt=${i + 1} error=${error?.message || error}`
      );

      if (!isTransient(error) || i === RETRY_DELAYS.length - 1) throw error;
    }
  }

  throw lastError;
}

function scalar(value) {
  if (Array.isArray(value)) return value.length ? scalar(value[0]) : '';
  if (value && typeof value === 'object') {
    if ('ID' in value) return String(value.ID ?? '');
    if ('VALUE' in value) return String(value.VALUE ?? '');
  }
  return value == null ? '' : String(value);
}

function looksLikeEnumId(value) {
  return /^\d+$/.test(String(value || '').trim());
}

async function getEnumMap(domain, accessToken, fieldCode) {
  const key = `${domain}::${fieldCode}`;
  const cached = enumCache.get(key);
  const now = Date.now();

  if (cached && now < cached.expiresAt) return cached.map;

  const list = await callBitrix(
    domain,
    accessToken,
    'crm.lead.userfield.list',
    {filter: {FIELD_NAME: fieldCode}}
  );

  const field = Array.isArray(list) ? list[0] : null;
  const map = new Map();

  for (const item of (field?.LIST || [])) {
    const id = String(item.ID ?? '');
    const value = String(item.VALUE ?? '').trim();
    if (id && value) map.set(id, value);
  }

  enumCache.set(key, {
    map,
    expiresAt: now + ENUM_CACHE_TTL_MS
  });

  console.log(`[fed] cached enum ${fieldCode}: ${map.size}`);
  return map;
}

async function resolveField(domain, accessToken, fieldCode, rawValue) {
  const raw = scalar(rawValue).trim();

  if (!raw) return '';
  if (!looksLikeEnumId(raw)) return raw;
  if (!String(fieldCode || '').startsWith('UF_')) return raw;

  const map = await getEnumMap(domain, accessToken, fieldCode);
  return map.get(raw) || raw;
}

function queryValue(req, key) {
  const value = req?.query?.[key];
  if (Array.isArray(value)) return String(value[0] || '').trim();
  return String(value || '').trim();
}

async function getFieldCodes(req, domain, accessToken) {
  let cityField = queryValue(req, 'city');
  let regionField = queryValue(req, 'region');

  if (cityField) return {cityField, regionField};

  const options = await callBitrix(domain, accessToken, 'app.option.get', {});
  return {
    cityField: String(options?.leadCityField || '').trim(),
    regionField: String(options?.leadRegionField || '').trim()
  };
}

function calcDistrict(city, region) {
  return String(
    FederalDistrict.calcDistrictName(city || '', region || '') || ''
  ).trim();
}

module.exports = async function handler(req, res) {
  try {
    if (req.method !== 'POST') {
      res.status(200).json({ok: true, skipped: 'not_post'});
      return;
    }

    const body = parseBody(req);
    const {event, leadId, domain, accessToken} = extractEvent(body);

    if (event !== 'ONCRMLEADADD' && event !== 'ONCRMLEADUPDATE') {
      res.status(200).json({ok: true, skipped: 'unsupported_event', event});
      return;
    }

    if (!leadId) {
      console.log('[fed] no_lead_id');
      res.status(200).json({ok: true, skipped: 'no_lead_id'});
      return;
    }

    if (!domain || !accessToken) {
      console.log(`[fed] lead=${leadId} no_event_auth`);
      res.status(200).json({ok: false, skipped: 'no_event_auth', leadId});
      return;
    }

    const {cityField, regionField} = await getFieldCodes(
      req,
      domain,
      accessToken
    );

    if (!cityField) throw new Error('Не настроено поле города лида');

    const lead = await callBitrix(
      domain,
      accessToken,
      'crm.lead.get',
      {id: leadId}
    );

    const currentFed = scalar(lead?.[LEAD_FED_FIELD]).trim();

    let region = '';
    if (regionField) {
      region = await resolveField(
        domain,
        accessToken,
        regionField,
        lead?.[regionField]
      );
    }

    // Регион имеет приоритет. Для Карсуна + Ульяновской области
    // город вообще не нужен для определения ФО.
    let district = region ? calcDistrict('', region) : '';
    let city = '';

    if (!district) {
      city = await resolveField(
        domain,
        accessToken,
        cityField,
        lead?.[cityField]
      );

      district = calcDistrict(city, region);
    }

    if (!district) {
      console.log(
        `[fed] lead=${leadId} no_district city="${city}" region="${region}"`
      );
      res.status(200).json({
        ok: true,
        updated: false,
        reason: 'district_not_found',
        leadId,
        city,
        region
      });
      return;
    }

    if (currentFed === district) {
      res.status(200).json({
        ok: true,
        updated: false,
        reason: 'already_actual',
        leadId,
        district
      });
      return;
    }

    await callBitrix(
      domain,
      accessToken,
      'crm.lead.update',
      {
        id: leadId,
        fields: {[LEAD_FED_FIELD]: district}
      }
    );

    console.log(
      `[fed] lead=${leadId} updated="${district}" city="${city}" region="${region}"`
    );

    res.status(200).json({
      ok: true,
      updated: true,
      leadId,
      district,
      city,
      region
    });
  } catch (error) {
    console.error(
      'Federal district event error:',
      error?.message || error
    );

    res.status(200).json({
      ok: false,
      error: error?.message || String(error)
    });
  }
};
