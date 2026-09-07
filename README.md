Federal District B24 — retry fix

Заменить:
- api/events.js
- vercel.json

Не менять:
- public/district.js
- api/bind.php
- api/field-v2.php
- api/settings.php

Фикс:
- retry AbortError/timeout/пустых ответов/429/5xx;
- 3 попытки REST;
- кэш списочных UF;
- регион обрабатывается раньше города;
- maxDuration /events = 45 секунд;
- защита от рекурсии ONCRMLEADUPDATE сохранена.
