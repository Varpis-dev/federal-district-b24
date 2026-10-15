'use strict';

/*
 * Универсальный запускатель Vercel-проекта в Yandex Serverless Containers.
 *
 * - читает vercel.json (routes / rewrites) — маршруты остаются те же, что были на Vercel;
 * - api/*.js запускает как Vercel-функции (req.body, req.query, res.status().json() …);
 * - api/*.php проксирует во встроенный PHP-сервер (php -S), который стартует рядом;
 * - отдаёт статику из public/.
 *
 * Переменные окружения:
 *   PORT         — порт, его задаёт Яндекс (по умолчанию 8080);
 *   PUBLIC_HOST  — публичный домен приложения (например xxx.containers.yandexcloud.net).
 *                  Если задан, подставляется в заголовок Host, чтобы приложения
 *                  правильно строили ссылки на /events, /field и т.д.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const querystring = require('querystring');
const { spawn } = require('child_process');

const APP_DIR = path.resolve(__dirname, '..');
const PORT = Number(process.env.PORT || 8080);
const PHP_PORT = Number(process.env.PHP_PORT || 8081);
const PUBLIC_HOST = (process.env.PUBLIC_HOST || '').replace(/^https?:\/\//, '').replace(/\/+$/, '');
const MAX_BODY = 10 * 1024 * 1024;

process.chdir(APP_DIR); // Vercel-функции читают файлы через process.cwd()

// ---------------------------------------------------------------- локальная копия справочников

// yc/fetch-mirror.js при сборке образа скачивает CSV с GitHub. Здесь подменяем fetch:
// если для URL есть локальная копия — отдаём её, иначе идём в сеть как обычно.
(function installMirror() {
  const dir = path.join(__dirname, 'mirror');
  if (!fs.existsSync(dir) || typeof globalThis.fetch !== 'function') return;
  const crypto = require('crypto');
  const realFetch = globalThis.fetch;
  globalThis.fetch = async function mirroredFetch(input, init) {
    const url = typeof input === 'string' ? input : (input && input.url) || String(input);
    if (url.startsWith('https://raw.githubusercontent.com/')) {
      const file = path.join(dir, crypto.createHash('sha1').update(url).digest('hex'));
      if (fs.existsSync(file)) {
        return new Response(fs.readFileSync(file), {
          status: 200,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        });
      }
    }
    return realFetch(input, init);
  };
  console.log('Локальные копии справочников:', fs.readdirSync(dir).length);
})();

// ---------------------------------------------------------------- маршруты

function loadRoutes() {
  const file = path.join(APP_DIR, 'vercel.json');
  if (!fs.existsSync(file)) return [];
  const cfg = JSON.parse(fs.readFileSync(file, 'utf8'));
  const list = [];

  for (const r of cfg.routes || []) {
    if (!r.src || !r.dest) continue;
    let src = r.src;
    if (!src.startsWith('^')) src = '^' + src;
    if (!src.endsWith('$')) src = src + '$';
    list.push({ re: new RegExp(src), dest: r.dest });
  }

  for (const r of cfg.rewrites || []) {
    if (!r.source || !r.destination) continue;
    const pattern = r.source
      .replace(/[.+?^${}()|[\]\\]/g, '\\$&')
      .replace(/\/:[a-zA-Z_]+\*/g, '(?:/.*)?')
      .replace(/:[a-zA-Z_]+/g, '[^/]+');
    list.push({ re: new RegExp('^' + pattern + '/?$'), dest: r.destination });
  }

  return list;
}

const ROUTES = loadRoutes();

function resolveTarget(pathname) {
  for (const r of ROUTES) {
    if (r.re.test(pathname)) return r.dest.split('?')[0];
  }
  // Прямое обращение к функции, как на Vercel: /api/events или /api/events.js
  const m = pathname.match(/^\/api\/([a-zA-Z0-9_\-]+)(\.js|\.php)?\/?$/);
  if (m) {
    for (const ext of m[2] ? [m[2]] : ['.js', '.php']) {
      const rel = `/api/${m[1]}${ext}`;
      if (fs.existsSync(path.join(APP_DIR, rel))) return rel;
    }
  }
  return null;
}

// ---------------------------------------------------------------- статика

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.ico': 'image/x-icon',
  '.webp': 'image/webp',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
  '.csv': 'text/csv; charset=utf-8'
};

function findStatic(pathname) {
  const base = path.join(APP_DIR, 'public');
  let rel;
  try { rel = decodeURIComponent(pathname); } catch { return null; }
  const candidates = [rel, rel.endsWith('/') ? rel + 'index.html' : rel + '.html'];
  for (const c of candidates) {
    const full = path.normalize(path.join(base, c));
    if (!full.startsWith(base + path.sep)) continue;
    try {
      if (fs.statSync(full).isFile()) return full;
    } catch { /* нет файла */ }
  }
  return null;
}

function serveStatic(res, file) {
  res.writeHead(200, {
    'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream',
    'Cache-Control': 'public, max-age=300'
  });
  fs.createReadStream(file).pipe(res);
}

// ---------------------------------------------------------------- JS-функции в стиле Vercel

const handlerCache = new Map();
const responseCache = new Map(); // замена CDN-кэша Vercel для ответов с s-maxage

function loadHandler(rel) {
  if (!handlerCache.has(rel)) {
    const mod = require(path.join(APP_DIR, rel));
    handlerCache.set(rel, typeof mod === 'function' ? mod : (mod.default || mod.handler));
  }
  return handlerCache.get(rel);
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on('data', c => {
      size += c.length;
      if (size > MAX_BODY) { reject(new Error('Body too large')); req.destroy(); return; }
      chunks.push(c);
    });
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

// Тот же разбор тела, что у Vercel: JSON → объект, form-urlencoded → плоский объект
// (ключи вида data[FIELDS][ID] остаются как есть), text → строка, иное → Buffer.
function parseBody(raw, contentType) {
  if (!raw.length) return undefined;
  const type = String(contentType || '').split(';')[0].trim().toLowerCase();
  const text = raw.toString('utf8');
  if (type === 'application/json') {
    try { return JSON.parse(text); } catch { return text; }
  }
  if (type === 'application/x-www-form-urlencoded') return querystring.parse(text);
  if (type.startsWith('text/')) return text;
  return raw;
}

function parseCookies(header) {
  const out = {};
  for (const part of String(header || '').split(';')) {
    const i = part.indexOf('=');
    if (i > 0) out[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
  }
  return out;
}

function sMaxAge(cacheControl) {
  const m = String(cacheControl || '').match(/s-maxage=(\d+)/);
  return m ? Number(m[1]) : 0;
}

async function runJs(rel, req, res, url) {
  const cacheKey = req.method === 'GET' ? rel + url.search : null;
  if (cacheKey) {
    const hit = responseCache.get(cacheKey);
    if (hit && hit.expires > Date.now()) {
      res.writeHead(hit.status, { ...hit.headers, 'X-Cache': 'HIT' });
      res.end(hit.body);
      return;
    }
  }

  const raw = await readBody(req);
  req.body = parseBody(raw, req.headers['content-type']);
  req.query = querystring.parse(url.search.slice(1));
  req.cookies = parseCookies(req.headers.cookie);

  // Собираем ответ в буфер: так проще поддержать кэш и не зависеть от деталей Node.
  const headers = {};
  const chunks = [];
  let finished;
  const done = new Promise(r => { finished = r; });
  let ended = false;

  const vres = {
    statusCode: 200,
    get headersSent() { return ended; },
    setHeader(k, v) { headers[k.toLowerCase()] = v; return vres; },
    getHeader(k) { return headers[k.toLowerCase()]; },
    getHeaders() { return { ...headers }; },
    hasHeader(k) { return k.toLowerCase() in headers; },
    removeHeader(k) { delete headers[k.toLowerCase()]; },
    writeHead(code, hdrs) {
      vres.statusCode = code;
      if (hdrs && typeof hdrs === 'object') for (const [k, v] of Object.entries(hdrs)) vres.setHeader(k, v);
      return vres;
    },
    status(code) { vres.statusCode = code; return vres; },
    write(chunk) { if (chunk != null) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(String(chunk))); return true; },
    end(chunk) {
      if (ended) return vres;
      if (chunk != null && typeof chunk !== 'function') vres.write(chunk);
      ended = true;
      finished();
      return vres;
    },
    json(obj) {
      if (!vres.hasHeader('content-type')) vres.setHeader('Content-Type', 'application/json; charset=utf-8');
      return vres.end(JSON.stringify(obj));
    },
    send(body) {
      if (body === undefined || body === null) return vres.end();
      if (Buffer.isBuffer(body)) {
        if (!vres.hasHeader('content-type')) vres.setHeader('Content-Type', 'application/octet-stream');
        return vres.end(body);
      }
      if (typeof body === 'object') return vres.json(body);
      if (!vres.hasHeader('content-type')) vres.setHeader('Content-Type', 'text/html; charset=utf-8');
      return vres.end(String(body));
    },
    redirect(a, b) {
      const [code, loc] = typeof a === 'number' ? [a, b] : [307, a];
      vres.statusCode = code;
      vres.setHeader('Location', loc);
      return vres.end();
    },
    on() { return vres; },
    once() { return vres; }
  };

  const handler = loadHandler(rel);
  try {
    await Promise.race([Promise.resolve(handler(req, vres)).then(() => {
      if (!ended) return done; // функция могла ответить позже через колбэк
    }), done]);
  } catch (err) {
    console.error(`[${rel}]`, err);
    if (!ended) {
      vres.statusCode = 500;
      headers['content-type'] = 'text/plain; charset=utf-8';
      chunks.length = 0;
      chunks.push(Buffer.from('Internal error: ' + (err && err.message || String(err))));
      ended = true;
    }
  }

  const body = Buffer.concat(chunks);
  res.writeHead(vres.statusCode, headers);
  res.end(body);

  const ttl = sMaxAge(headers['cache-control']);
  if (cacheKey && ttl > 0 && vres.statusCode === 200) {
    responseCache.set(cacheKey, { status: 200, headers, body, expires: Date.now() + ttl * 1000 });
  }
}

// ---------------------------------------------------------------- PHP

let phpProc = null;

function startPhp() {
  if (!fs.existsSync(path.join(APP_DIR, 'api'))) return;
  const hasPhp = fs.readdirSync(path.join(APP_DIR, 'api')).some(f => f.endsWith('.php'));
  if (!hasPhp) return;

  phpProc = spawn('php', [
    '-q',
    '-d', 'display_errors=0',
    '-d', 'log_errors=1',
    '-d', 'error_log=/dev/stderr',
    '-d', 'max_execution_time=120',
    '-d', 'default_socket_timeout=60',
    '-S', `127.0.0.1:${PHP_PORT}`,
    '-t', APP_DIR
  ], {
    cwd: APP_DIR,
    env: { ...process.env, PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS || '4' },
    stdio: ['ignore', 'inherit', 'inherit']
  });
  phpProc.on('exit', code => {
    console.error('php -S завершился с кодом', code, '— перезапуск');
    setTimeout(startPhp, 500);
  });
}

function proxyToPhp(rel, req, res, url, attempt = 0) {
  const headers = { ...req.headers };
  delete headers.connection;

  const preq = http.request({
    host: '127.0.0.1',
    port: PHP_PORT,
    method: req.method,
    path: rel + url.search,
    headers,
    timeout: 120000
  }, pres => {
    const out = { ...pres.headers };
    delete out['x-powered-by'];
    delete out.connection;
    res.writeHead(pres.statusCode || 502, out);
    pres.pipe(res);
  });

  preq.on('error', err => {
    // PHP ещё не успел подняться после холодного старта
    if (err.code === 'ECONNREFUSED' && attempt < 30 && !res.headersSent) {
      setTimeout(() => proxyToPhp(rel, req, res, url, attempt + 1), 100);
      return;
    }
    console.error('PHP proxy error', err);
    if (!res.headersSent) res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('PHP недоступен: ' + err.message);
  });

  if (attempt === 0) {
    // Тело читаем один раз, чтобы при повторе после ECONNREFUSED отправить его снова
    readBody(req).then(buf => {
      req._rawBody = buf;
      preq.end(buf);
    }).catch(err => { preq.destroy(err); });
  } else {
    preq.end(req._rawBody);
  }
}

// ---------------------------------------------------------------- сервер

const server = http.createServer(async (req, res) => {
  try {
    if (PUBLIC_HOST) req.headers.host = PUBLIC_HOST;
    if (!req.headers['x-forwarded-proto']) req.headers['x-forwarded-proto'] = 'https';

    const url = new URL(req.url, 'http://localhost');
    const pathname = url.pathname.replace(/\/{2,}/g, '/');

    if (pathname === '/__health') {
      res.writeHead(200, { 'Content-Type': 'text/plain' });
      res.end('ok');
      return;
    }

    const target = resolveTarget(pathname);
    if (target) {
      if (target.endsWith('.php')) {
        if (!fs.existsSync(path.join(APP_DIR, target))) return notFound(res);
        return proxyToPhp(target, req, res, url);
      }
      if (target.endsWith('.js') && target.startsWith('/api/')) {
        return await runJs(target, req, res, url);
      }
      const file = findStatic(target);
      if (file) return serveStatic(res, file);
      return notFound(res);
    }

    const file = findStatic(pathname);
    if (file) return serveStatic(res, file);

    notFound(res);
  } catch (err) {
    console.error(err);
    if (!res.headersSent) res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('Internal error');
  }
});

function notFound(res) {
  res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('Not found');
}

server.keepAliveTimeout = 65000;
server.requestTimeout = 0;

startPhp();
server.listen(PORT, () => {
  console.log(`Listening on :${PORT}, app: ${APP_DIR}, routes: ${ROUTES.length}` +
    (PUBLIC_HOST ? `, public host: ${PUBLIC_HOST}` : ''));
});

for (const sig of ['SIGTERM', 'SIGINT']) {
  process.on(sig, () => {
    if (phpProc) { phpProc.removeAllListeners('exit'); phpProc.kill(); }
    server.close(() => process.exit(0));
    setTimeout(() => process.exit(0), 2000).unref();
  });
}
