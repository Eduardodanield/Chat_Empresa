<?php
require_once __DIR__ . '/config.php';

session_start();

// ── Exibição de erros por ambiente ──────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── Headers de segurança HTTP ───────────────────────────
// Proteção contra clickjacking
header('X-Frame-Options: DENY');
// Proteção contra MIME sniffing
header('X-Content-Type-Options: nosniff');
// Proteção XSS nos navegadores antigos
header('X-XSS-Protection: 1; mode=block');
// Referrer Policy (não vaza URL interna em links externos)
header('Referrer-Policy: strict-origin-when-cross-origin');
// Permissions Policy (bloqueia acesso à câmera, microfone, etc.)
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Content Security Policy básica
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self';");

// ── Conexão PDO ────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    $msg = (APP_ENV === 'development')
        ? 'Erro na conexão: ' . $e->getMessage()
        : 'Erro interno. Tente novamente mais tarde.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Funções auxiliares ─────────────────────────────────
function exigir_login() {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /chat_megaaxnen/login.php');
        exit;
    }
}

function exigir_admin() {
    exigir_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }
}

function usuario_logado(): array {
    $nome = $_SESSION['usuario_nome'] ?? $_SESSION['nome_completo'] ?? '';
    return [
        'id'            => $_SESSION['usuario_id']  ?? 0,
        'nome'          => $nome,
        'nome_completo' => $nome,
        'username'      => $nome,
        'role'          => $_SESSION['role']   ?? 'funcionario',
        'avatar'        => $_SESSION['avatar'] ?? null,
    ];
}
