<?php
ob_start();
require_once '../conexao.php';
exigir_login();
exigir_admin();
header('Content-Type: application/json; charset=utf-8');

$d          = json_decode(file_get_contents('php://input'), true) ?: [];
$usuario_id = intval($d['usuario_id'] ?? 0);
$novo_nome  = trim($d['nome_completo'] ?? '');

if (!$usuario_id || !$novo_nome) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit;
}

try {
    $pdo->prepare("UPDATE chat_users SET nome_completo = :nome WHERE id = :id")
        ->execute([':nome' => $novo_nome, ':id' => $usuario_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
