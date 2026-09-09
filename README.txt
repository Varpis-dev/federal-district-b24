Добавить api/deals-mass.php
Заменить vercel.json

НЕ ТРОГАТЬ:
api/events.js
api/bind.php
api/settings.php
api/field-v2.php
public/district.js

После деплоя открыть:
https://federal-district-b24-xi.vercel.app/deals-mass

Сначала Анализ без изменений.
После проверки цифр — Заполнить ФО во всех сделках.

Массовые crm.deal.update идут из браузера через BX24.callBatch.
Vercel только отдаёт страницу и НЕ обрабатывает каждую сделку серверной функцией.
