<?php
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$usuario_id = intval($_SESSION['usuario_id'] ?? 0);
if (!$usuario_id) {
    echo json_encode(['success' => false, 'error' => 'Sessão inválida.']); exit;
}

$d      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$emoji  = mb_substr(trim($d['emoji']  ?? ''), 0, 10);
$status = mb_substr(trim($d['status'] ?? ''), 0, 100);

if ($status === '') {
    echo json_encode(['success' => false, 'error' => 'Status inválido.']); exit;
}

try {
    $pdo->prepare("UPDATE chat_users SET status_emoji = :emoji, status_atual = :status WHERE id = :id")
        ->execute([':emoji' => $emoji, ':status' => $status, ':id' => $usuario_id]);

    $_SESSION['status_emoji'] = $emoji;
    $_SESSION['status_atual'] = $status;

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Erro interno.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
