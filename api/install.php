<?php
header('Content-Type: text/html; charset=utf-8');

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
$fieldUrl = $baseUrl . '/field';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Установка приложения</title>
  <script src="https://api.bitrix24.com/api/v1/"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 36px;
      color: #111;
    }

    h1 {
      margin-top: 0;
      font-size: 30px;
    }

    #status {
      margin-top: 18px;
      white-space: pre-wrap;
      color: #444;
      background: #f6f8fa;
      padding: 14px;
      border-radius: 12px;
      min-height: 70px;
    }
  </style>
</head>
<body>
  <h1>Установка приложения «Федеральный округ сделки»</h1>
  <p>Приложение регистрирует пользовательский тип поля для отображения федерального округа и менеджера в карточке сделки.</p>

  <div id="status">Ожидаю инициализацию Bitrix24...</div>

<script>
const statusEl = document.getElementById('status');

function setStatus(text) {
  statusEl.textContent = text;
}

BX24.init(function() {
  setStatus('BX24.init OK\nРегистрирую тип пользовательского поля...');

  BX24.callMethod('userfieldtype.add', {
    USER_TYPE_ID: 'fed_district_manager',
    HANDLER: <?php echo json_encode($fieldUrl, JSON_UNESCAPED_UNICODE); ?>,
    TITLE: 'Федеральный округ сделки',
    DESCRIPTION: 'Определение федерального округа и ответственного менеджера по городу и области сделки',
    OPTIONS: {
      height: 130
    }
  }, function(res) {
    if (res.error()) {
      const err = JSON.stringify(res.error(), null, 2);

      setStatus(
        'Тип поля уже мог быть зарегистрирован или вернулась ошибка регистрации:\n' +
        err +
        '\n\nЗавершаю установку приложения...'
      );

      setTimeout(function() {
        BX24.installFinish();
      }, 1200);

      return;
    }

    setStatus(
      'Тип поля зарегистрирован успешно.\n\n' +
      'Завершаю установку приложения...'
    );

    setTimeout(function() {
      BX24.installFinish();
    }, 1200);
  });
});
</script>
</body>
</html>
