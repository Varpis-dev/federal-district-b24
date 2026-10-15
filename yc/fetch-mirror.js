'use strict';

/*
 * Запускается при сборке образа (в GitHub Actions, вне РФ).
 * Находит в api/*.js ссылки на raw.githubusercontent.com (справочники CSV и т.п.)
 * и скачивает их внутрь образа. В работе server.js отдаёт эти файлы из локальной
 * копии, поэтому приложение не зависит от доступности GitHub из Яндекса
 * и не качает справочники на каждом холодном старте.
 *
 * Ошибка скачивания не ломает сборку: тогда приложение просто пойдёт в сеть, как раньше.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const APP_DIR = path.resolve(__dirname, '..');
const OUT_DIR = path.join(__dirname, 'mirror');

function findUrls() {
  const apiDir = path.join(APP_DIR, 'api');
  if (!fs.existsSync(apiDir)) return [];
  const urls = new Set();
  for (const f of fs.readdirSync(apiDir)) {
    if (!f.endsWith('.js')) continue;
    const src = fs.readFileSync(path.join(apiDir, f), 'utf8');
    for (const m of src.matchAll(/https:\/\/raw\.githubusercontent\.com\/[^\s'"`]+/g)) urls.add(m[0]);
  }
  return [...urls];
}

async function main() {
  const urls = findUrls();
  if (!urls.length) { console.log('mirror: внешних справочников нет'); return; }
  fs.mkdirSync(OUT_DIR, { recursive: true });

  for (const url of urls) {
    const name = crypto.createHash('sha1').update(url).digest('hex');
    try {
      const r = await fetch(url);
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const buf = Buffer.from(await r.arrayBuffer());
      fs.writeFileSync(path.join(OUT_DIR, name), buf);
      console.log(`mirror: ${url} → ${buf.length} байт`);
    } catch (e) {
      console.warn(`mirror: не удалось скачать ${url}: ${e.message}`);
    }
  }
}

main();
