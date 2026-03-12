<?php
ob_start();
require_once '../conexao.php';
exigir_login();
exigir_admin();
header('Content-Type: application/json; charset=utf-8');

$d          = json_decode(file_get_contents('php://input'), true) ?: [];
$usuario_id = intval($d['usuario_id'] ?? 0);
$meu_id     = $_SESSION['usuario_id'];

if (!$usuario_id) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}
if ($usuario_id === $meu_id) {
    echo json_encode(['success' => false, 'error' => 'Não é possível excluir a própria conta.']); exit;
}

try {
    // Anonimiza o usuário (mantém mensagens, mas substitui o nome)
    $pdo->prepare("
        UPDATE chat_users
        SET nome_completo = 'Usuário removido',
            username      = CONCAT('removido_', id),
            avatar        = NULL
        WHERE id = :id
    ")->execute([':id' => $usuario_id]);

    // Remove de todos os grupos
    $pdo->prepare("DELETE FROM chat_grupo_membros WHERE usuario_id = :id")
        ->execute([':id' => $usuario_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
