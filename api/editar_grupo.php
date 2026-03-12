<?php
ob_start();
require_once '../conexao.php';
exigir_login();
exigir_admin();
header('Content-Type: application/json; charset=utf-8');

$d        = json_decode(file_get_contents('php://input'), true) ?: [];
$grupo_id = intval($d['grupo_id'] ?? 0);
$nome     = trim($d['nome']  ?? '');
$icone    = trim($d['icone'] ?? '👥');

if (!$grupo_id || !$nome) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit;
}

try {
    $pdo->prepare("UPDATE chat_grupos SET nome = :nome, icone = :icone WHERE id = :id")
        ->execute([':nome' => $nome, ':icone' => $icone, ':id' => $grupo_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
