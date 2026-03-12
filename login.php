<?php
require_once 'conexao.php';

// Se já está logado vai direto pro chat
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// ── Tabela de controle de tentativas de login ──────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `chat_login_tentativas` (
        `id`               INT(11) NOT NULL AUTO_INCREMENT,
        `ip`               VARCHAR(45) NOT NULL,
        `tentativas`       INT(11) NOT NULL DEFAULT 1,
        `ultima_tentativa` DATETIME NOT NULL DEFAULT current_timestamp(),
        `bloqueado_ate`    DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ip` (`ip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

const MAX_TENTATIVAS   = 5;
const BLOQUEIO_MINUTOS = 15;

function get_ip(): string {
    return !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function verificar_bloqueio(PDO $pdo): ?string {
    $ip   = get_ip();
    $stmt = $pdo->prepare("SELECT tentativas, bloqueado_ate FROM chat_login_tentativas WHERE ip = :ip LIMIT 1");
    $stmt->execute([':ip' => $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ($row['bloqueado_ate'] && strtotime($row['bloqueado_ate']) > time()) {
        $falta = ceil((strtotime($row['bloqueado_ate']) - time()) / 60);
        return "Muitas tentativas. Tente novamente em {$falta} minuto(s).";
    }
    return null;
}

function registrar_falha(PDO $pdo): void {
    $ip = get_ip();
    $pdo->prepare("
        INSERT INTO chat_login_tentativas (ip, tentativas, ultima_tentativa)
        VALUES (:ip, 1, NOW())
        ON DUPLICATE KEY UPDATE
            tentativas       = tentativas + 1,
            ultima_tentativa = NOW(),
            bloqueado_ate    = IF(tentativas + 1 >= :max,
                                  DATE_ADD(NOW(), INTERVAL :min MINUTE),
                                  NULL)
    ")->execute([':ip' => $ip, ':max' => MAX_TENTATIVAS, ':min' => BLOQUEIO_MINUTOS]);
}

function limpar_tentativas(PDO $pdo): void {
    $pdo->prepare("DELETE FROM chat_login_tentativas WHERE ip = :ip")
        ->execute([':ip' => get_ip()]);
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $erro = 'Preencha todos os campos.';
    } else {
        // Verifica bloqueio por IP
        $bloqueio = verificar_bloqueio($pdo);
        if ($bloqueio) {
            $erro = $bloqueio;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM chat_users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $autenticado = false;
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $autenticado = true;
                } elseif ($user['password'] === $password) {
                    // Compatibilidade: senha em texto puro (CPF de funcionário criado pelo admin)
                    $autenticado = true;
                }
            }

            if (!$autenticado) {
                registrar_falha($pdo);
                $erro = 'Usuario ou senha incorretos.';
            } else {
                limpar_tentativas($pdo);

                $pdo->prepare("UPDATE chat_users SET online = 1, ultimo_acesso = NOW() WHERE id = :id")
                    ->execute([':id' => $user['id']]);

                $_SESSION['usuario_id']   = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome_completo'] ?? $user['username'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['role']         = $user['role'];
                $_SESSION['avatar']       = $user['avatar'] ?? null;

                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Chat Mega Axnen � Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --bg:        #19253F;
      --surface:   #1e2d45;
      --surface2:  #243552;
      --verde:     #059669;
      --verde-dark:#047857;
      --borda:     #2d4060;
      --texto:     #e9edef;
      --muted:     #8696a0;
      --erro:      #ef4444;
      --radius:    14px;
    }

    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

    body {
      font-family:'DM Sans', sans-serif;
      background: var(--bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    /* Fundo animado */
    body::before {
      content:'';
      position:absolute;
      inset:0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(5,150,105,0.12) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(25,37,63,0.8) 0%, transparent 60%);
      pointer-events:none;
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      animation: fadeUp .5s ease both;
      position: relative;
      z-index: 1;
    }

    @keyframes fadeUp {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* Logo */
    .brand {
      text-align: center;
      margin-bottom: 32px;
    }
    .brand-logo {
      height: 42px;
      object-fit: contain;
      margin-bottom: 10px;
    }
    .brand-name {
      font-family: 'Syne', sans-serif;
      font-size: 24px;
      font-weight: 800;
      color: var(--verde);
      letter-spacing: .04em;
    }
    .brand-sub {
      font-size: 13px;
      color: var(--muted);
      margin-top: 2px;
    }

    /* Card */
    .card {
      background: var(--surface);
      border: 1.5px solid var(--borda);
      border-radius: 24px;
      padding: 40px 36px;
      box-shadow: 0 24px 64px rgba(0,0,0,0.4);
    }

    .card-title {
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--texto);
      margin-bottom: 6px;
    }
    .card-sub {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 28px;
    }

    /* Form */
    .form-group {
      margin-bottom: 18px;
    }
    .form-label {
      display: block;
      font-family: 'Syne', sans-serif;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .input-wrap {
      position: relative;
    }
    .input-wrap svg {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
    }
    .form-input {
      width: 100%;
      padding: 13px 16px 13px 44px;
      background: var(--surface2);
      border: 1.5px solid var(--borda);
      border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      color: var(--texto);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-input:focus {
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(5,150,105,0.15);
    }
    .form-input::placeholder { color: var(--muted); }

    /* Toggle senha */
    .toggle-senha {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 2px;
      display: flex;
      align-items: center;
    }
    .toggle-senha:hover { color: var(--texto); }

    /* Erro */
    .alert-erro {
      background: rgba(239,68,68,0.12);
      border: 1.5px solid rgba(239,68,68,0.3);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      color: #fca5a5;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Bot�o */
    .btn-login {
      width: 100%;
      padding: 14px;
      background: var(--verde);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: .04em;
      cursor: pointer;
      transition: background .2s, transform .15s, box-shadow .2s;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-login:hover {
      background: var(--verde-dark);
      transform: translateY(-1px);
      box-shadow: 0 6px 24px rgba(5,150,105,0.35);
    }
    .btn-login:active { transform: translateY(0); }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 24px;
      font-size: 12px;
      color: var(--muted);
    }

    /* Loading no bot�o */
    .spinner {
      width: 18px; height: 18px;
      border: 2.5px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<div class="login-wrap">

  <!-- LOGO -->
  <div class="brand">
    <img src="assets/logo.png" alt="Mega Axnen" class="brand-logo"
         onerror="this.style.display='none';document.getElementById('bn').style.display='block'"/>
    <div class="brand-name" id="bn" style="display:none;">MEGA AXNEN</div>
    <div class="brand-sub">Sistema interno de comunicacao</div>
  </div>

  <!-- CARD -->
  <div class="card">
    <div class="card-title">Bem-vindo de volta</div>
    <div class="card-sub">Entre com suas credenciais de acesso</div>

    <?php if ($erro !== ''): ?>
    <div class="alert-erro">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <?= htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="formLogin" onsubmit="submeter(event)">

      <div class="form-group">
        <label class="form-label">Usuario</label>
        <div class="input-wrap">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <input
            type="text"
            name="username"
            class="form-input"
            placeholder="Seu usuario"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            autocomplete="username"
            autofocus
            required
          />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Senha</label>
        <div class="input-wrap">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input
            type="password"
            name="password"
            id="inputSenha"
            class="form-input"
            placeholder="Sua senha"
            autocomplete="current-password"
            required
          />
          <button type="button" class="toggle-senha" onclick="toggleSenha()" title="Mostrar/ocultar senha">
            <svg id="olhoIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login" id="btnLogin">
        <div class="spinner" id="spinner"></div>
        <span id="btnTexto">Entrar no Chat</span>
      </button>

    </form>
  </div>

  <div class="login-footer">
    &copy; <?= date('Y') ?> Mega Axnen &mdash; Uso interno
  </div>

</div>

<script>
function toggleSenha() {
  var input = document.getElementById('inputSenha');
  input.type = input.type === 'password' ? 'text' : 'password';
}

function submeter(e) {
  var btn     = document.getElementById('btnLogin');
  var spinner = document.getElementById('spinner');
  var texto   = document.getElementById('btnTexto');
  btn.disabled    = true;
  spinner.style.display = 'block';
  texto.textContent     = 'Entrando...';
  // Deixa o form submeter normalmente
}
</script>

</body>
</html>