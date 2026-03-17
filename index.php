<?php
// Redirecionar acessos à raiz da API para o frontend público caso acionado por navegador
header('Location: /public/index.html');
exit;
