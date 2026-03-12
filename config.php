<?php
// ══════════════════════════════════════════════════════
//  CONFIGURAÇÃO GERAL DO SISTEMA
//  Ajuste este arquivo antes de subir para produção.
// ══════════════════════════════════════════════════════

// Ambiente: 'development' exibe erros detalhados
//           'production'  oculta erros e usa credenciais reais
define('APP_ENV', 'development');

// ── Credenciais do banco de dados ──────────────────────
// Desenvolvimento (XAMPP local)
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_megaaxnen');
define('DB_USER', 'root');
define('DB_PASS', '');

// Para produção na KingHost, troque pelos valores reais:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'seu_banco_kinghost');
// define('DB_USER', 'seu_usuario_kinghost');
// define('DB_PASS', 'sua_senha_segura');
