<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Массовое заполнение ФО</title>
<script src="https://api.bitrix24.com/api/v1/"></script>
<script src="/district.js"></script>
<style>
body{font-family:Arial,sans-serif;background:#f3f6f9;margin:0;padding:24px;color:#1f2937}
.wrap{max-width:1050px;margin:auto}.card{background:#fff;border-radius:14px;padding:22px;margin-bottom:18px;box-shadow:0 5px 24px rgba(0,0,0,.07)}
h1{margin-top:0}.info,.warn,.status{padding:13px;border-radius:10px;margin-top:12px;line-height:1.45}
.info{background:#eef8f3;border:1px solid #a9dcc7}.warn{background:#fff8e6;border:1px solid #f5d06f}.status{background:#eef6ff}
code{background:#eef2f7;padding:2px 6px;border-radius:5px}.buttons{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
button{border:0;border-radius:9px;padding:12px 17px;font-weight:700;color:#fff;background:#1976d2;cursor:pointer}
button.safe{background:#148a67}button.danger{background:#c95050}button:disabled{opacity:.55;cursor:not-allowed}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}
.stat{border:1px solid #dfe7ef;border-radius:10px;padding:13px}.stat span{font-size:13px;color:#64748b;display:block}.stat b{font-size:25px;display:block;margin-top:5px}
.progress{height:10px;background:#e5ebf2;border-radius:99px;overflow:hidden;margin-top:16px}.progress div{height:100%;width:0;background:#1976d2}
pre{max-height:360px;overflow:auto;background:#111827;color:#d1fae5;border-radius:10px;padding:14px;white-space:pre-wrap;font-size:12px}
@media(max-width:800px){.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h1>Массовое заполнение ФО в сделках</h1>
<div class="info">
<b>Область (Тех поле)</b> — <code>UF_CRM_1788263875</code><br>
<b>Город (Список)</b> — <code>UF_CRM_69CCC683A9C14</code><br>
<b>Федеральный округ (строка)</b> — <code>UF_CRM_FEDERAL_DISTRICT_TEXT</code>
</div>
<div class="warn">
Сначала запускай <b>«Анализ без изменений»</b>. Уже нормальный ФО не перезаписывается.
Значение <b>[object Object]</b> считается сломанным и будет исправлено.
</div>
<div class="buttons">
<button id="analyze" disabled>Анализ без изменений</button>
<button id="fill" class="safe" disabled>Заполнить ФО во всех сделках</button>
<button id="stop" class="danger" disabled>Остановить</button>
</div>
<div id="status" class="status">Инициализация Bitrix24…</div>
</div>

<div class="card">
<div class="stats">
<div class="stat"><span>Обработано</span><b id="processed">0</b></div>
<div class="stat"><span>Всего</span><b id="total">0</b></div>
<div class="stat"><span>Уже заполнен ФО</span><b id="already">0</b></div>
<div class="stat"><span>К заполнению / заполнено</span><b id="changed">0</b></div>
<div class="stat"><span>По области</span><b id="byRegion">0</b></div>
<div class="stat"><span>По городу</span><b id="byCity">0</b></div>
<div class="stat"><span>Нет области и города</span><b id="noSource">0</b></div>
<div class="stat"><span>ФО не определён</span><b id="notFound">0</b></div>
<div class="stat"><span>Исправлено [object Object]</span><b id="objectFixed">0</b></div>
<div class="stat"><span>Ошибки REST</span><b id="errors">0</b></div>
</div>
<div class="progress"><div id="bar"></div></div>
<p id="progressText">Ожидание запуска…</p>
<pre id="log">Лог появится здесь.</pre>
</div>
</div>

<script>
const CITY='UF_CRM_69CCC683A9C14';
const REGION='UF_CRM_1788263875';
const FED='UF_CRM_FEDERAL_DISTRICT_TEXT';
const VALID=new Set(['Центральный','Северо-Западный','Южный','Северо-Кавказский','Приволжский','Уральский','Сибирский','Дальневосточный']);
const BATCH=20, BATCH_DELAY=650, PAGE_DELAY=180;
let ready=false,stopRequested=false,cityMap=new Map(),lines=[];

const el=id=>document.getElementById(id);
const sleep=ms=>new Promise(r=>setTimeout(r,ms));

function scalar(v){
  if(Array.isArray(v)) return v.length?scalar(v[0]):'';
  if(v&&typeof v==='object'){if('ID'in v)return String(v.ID??'');if('VALUE'in v)return String(v.VALUE??'')}
  return v==null?'':String(v)
}
function text(v){return scalar(v).trim()}
function normalizeDistrict(r){
  if(r==null)return '';
  if(typeof r==='string'||typeof r==='number'){const v=String(r).trim();return VALID.has(v)?v:''}
  if(typeof r==='object'){
    for(const x of [r.name,r.district,r.districtName,r.federalDistrict,r.value,r.title,r.result]){
      if(typeof x==='string'||typeof x==='number'){const v=String(x).trim();if(VALID.has(v))return v}
    }
    for(const k of ['district','federalDistrict','result']){
      const n=r[k];
      if(n&&typeof n==='object'){
        for(const x of [n.name,n.value,n.title,n.districtName]){
          if(typeof x==='string'||typeof x==='number'){const v=String(x).trim();if(VALID.has(v))return v}
        }
      }
    }
  }
  return ''
}
function calc(city,region){
  if(!window.FederalDistrict||typeof window.FederalDistrict.calcDistrictName!=='function')throw new Error('Не загружен district.js');
  return normalizeDistrict(window.FederalDistrict.calcDistrictName(city||'',region||''))
}
function log(m){
  lines.push('['+new Date().toLocaleTimeString('ru-RU')+'] '+m);
  if(lines.length>180)lines=lines.slice(-180);
  el('log').textContent=lines.join('\n'); el('log').scrollTop=el('log').scrollHeight
}
function call(method,params={}){
  return new Promise((resolve,reject)=>BX24.callMethod(method,params,r=>{
    if(r.error()){const e=r.error();reject(new Error(e?.ex?.error_description||e?.ex?.error||String(e)));return}
    resolve(r)
  }))
}
async function retry(method,params={}){
  let last;
  for(const d of [0,500,1500]){
    if(d)await sleep(d);
    try{return await call(method,params)}catch(e){last=e;log('REST '+method+': '+e.message)}
  }
  throw last
}
function blank(){return{processed:0,total:0,already:0,changed:0,byRegion:0,byCity:0,noSource:0,notFound:0,objectFixed:0,errors:0}}
function render(s){
  for(const k of ['processed','total','already','changed','byRegion','byCity','noSource','notFound','objectFixed','errors'])el(k).textContent=s[k];
  const p=s.total?Math.min(100,s.processed/s.total*100):0;
  el('bar').style.width=p.toFixed(1)+'%';el('progressText').textContent=`Обработано ${s.processed} из ${s.total||'…'} (${p.toFixed(1)}%)`
}
function busy(v){el('analyze').disabled=v||!ready;el('fill').disabled=v||!ready;el('stop').disabled=!v}

async function loadCityMap(){
  const r=await retry('crm.deal.userfield.list',{filter:{FIELD_NAME:CITY}});
  let f=(r.data()||[])[0];
  if(!f)throw new Error('Не найдено поле города '+CITY);
  if(f.USER_TYPE_ID==='enumeration'&&!Array.isArray(f.LIST)){
    const g=await retry('crm.deal.userfield.get',{id:f.ID});f=g.data()
  }
  cityMap=new Map();
  for(const i of (f.LIST||[])){const id=String(i.ID??''),v=String(i.VALUE??'').trim();if(id&&v)cityMap.set(id,v)}
  log('Загружено вариантов города: '+cityMap.size)
}
function cityValue(v){const raw=text(v);return raw?(cityMap.get(raw)||raw):''}
function decide(deal){
  const cur=text(deal[FED]), broken=cur==='[object Object]';
  if(cur&&!broken)return{reason:'already'};
  const region=text(deal[REGION]);
  if(region){const d=calc('',region);if(d)return{reason:'region',district:d,broken}}
  const city=cityValue(deal[CITY]);
  if(city){const d=calc(city,region);if(d)return{reason:'city',district:d,broken,city,region}}
  if(!region&&!city)return{reason:'noSource'};
  return{reason:'notFound',city,region}
}
function nextPage(r){
  return new Promise((resolve,reject)=>{
    if(!r.more()){resolve(null);return}
    r.next(n=>{if(n.error()){const e=n.error();reject(new Error(e?.ex?.error_description||e?.ex?.error||String(e)));return}resolve(n)})
  })
}
async function updateChunk(chunk,s){
  const cmds={};
  chunk.forEach((x,i)=>cmds['u'+i]=['crm.deal.update',{id:x.id,fields:{[FED]:x.district}}]);
  await new Promise(resolve=>BX24.callBatch(cmds,res=>{
    chunk.forEach((x,i)=>{
      const r=res['u'+i];
      if(!r||r.error()){s.errors++;log('Ошибка сделки #'+x.id);return}
      s.changed++; if(x.source==='region')s.byRegion++;else s.byCity++; if(x.broken)s.objectFixed++
    });
    resolve()
  },false))
}

async function run(writeMode){
  stopRequested=false;busy(true);lines=[];el('log').textContent='';
  const s=blank();
  try{
    el('status').textContent=writeMode?'Массовое заполнение запущено…':'Анализ запущен…';
    await loadCityMap();
    let page=await retry('crm.deal.list',{order:{ID:'ASC'},select:['ID',CITY,REGION,FED]});
    s.total=Number(page.total()||0);render(s);

    while(page&&!stopRequested){
      const updates=[];
      for(const deal of (page.data()||[])){
        s.processed++;
        const d=decide(deal);
        if(d.reason==='already'){s.already++;continue}
        if(d.reason==='region'||d.reason==='city'){
          if(writeMode)updates.push({id:deal.ID,district:d.district,source:d.reason,broken:d.broken});
          else{s.changed++;if(d.reason==='region')s.byRegion++;else s.byCity++;if(d.broken)s.objectFixed++}
          continue
        }
        if(d.reason==='noSource'){s.noSource++;if(s.noSource<=40)log('Нет области и города: #'+deal.ID);continue}
        s.notFound++;if(s.notFound<=80)log(`ФО не определён #${deal.ID}: город="${d.city||''}", область="${d.region||''}"`)
      }

      if(writeMode){
        for(let i=0;i<updates.length&&!stopRequested;i+=BATCH){
          await updateChunk(updates.slice(i,i+BATCH),s);render(s);await sleep(BATCH_DELAY)
        }
      }

      render(s);
      if(stopRequested||!page.more())break;
      await sleep(PAGE_DELAY);
      page=await nextPage(page)
    }

    el('status').textContent=stopRequested?'Остановлено. Уже выполненные изменения сохранены.':(writeMode?'Готово. Массовое заполнение завершено.':'Готово. Анализ завершён, сделки не изменялись.');
    log(stopRequested?'Остановлено пользователем.':'Готово.')
  }catch(e){
    s.errors++;render(s);el('status').textContent='Ошибка: '+(e.message||e);log('КРИТИЧЕСКАЯ ОШИБКА: '+(e.message||e))
  }finally{busy(false)}
}

el('analyze').onclick=()=>run(false);
el('fill').onclick=()=>{if(confirm('Запустить массовое заполнение ФО во всех сделках?\n\nНормально заполненные значения не перезаписываются.'))run(true)};
el('stop').onclick=()=>{stopRequested=true;el('status').textContent='Останавливаю после текущей пачки…'};

BX24.init(function(){
  ready=true;busy(false);
  el('status').textContent='Bitrix24 подключён. Сначала нажми «Анализ без изменений».';
  BX24.resizeWindow(1100,900)
});
</script>
</body>
</html>
