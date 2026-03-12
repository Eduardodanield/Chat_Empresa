<?php
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$meu_id   = intval($_SESSION['usuario_id'] ?? 0);
$meu_role = $_SESSION['role'] ?? 'funcionario';

$de         = $_GET['de']          ?? '';
$ate        = $_GET['ate']         ?? '';
$usuario_id = intval($_GET['usuario_id'] ?? 0);

if (!$de || !$ate) {
    echo json_encode(['success' => false, 'error' => 'Período obrigatório.']); exit;
}

// Funcionário só pode ver o próprio histórico
if ($meu_role !== 'admin') {
    $usuario_id = $meu_id;
}

try {
    $where  = ["1=1"];
    $params = [];

    // Filtro de usuário
    if ($usuario_id > 0) {
        $where[]              = "t.usuario_id = :uid";
        $params[':uid']       = $usuario_id;
    } elseif ($meu_role !== 'admin') {
        $where[]              = "t.usuario_id = :uid";
        $params[':uid']       = $meu_id;
    }

    // Filtro de data: usa data_tarefa ou data_criacao
    $where[] = "COALESCE(t.data_tarefa, DATE(t.data_criacao)) BETWEEN :de AND :ate";
    $params[':de']  = $de;
    $params[':ate'] = $ate;

    $sql = "
        SELECT t.id, t.titulo, t.descricao, t.status,
               DATE_FORMAT(COALESCE(t.data_tarefa, t.data_criacao), '%d/%m/%Y') AS data_fmt,
               u.nome_completo AS usuario_nome,
               IF(t.horario_check IS NOT NULL, TIME_FORMAT(t.horario_check, '%H:%i'), NULL) AS horario_check
        FROM chat_tarefas t
        LEFT JOIN chat_users u ON u.id = t.usuario_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(t.data_tarefa, DATE(t.data_criacao)) DESC, t.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tarefas' => $tarefas]);

} catch (PDOException $e) {
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Erro interno.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
