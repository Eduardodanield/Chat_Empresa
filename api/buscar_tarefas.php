<?php
// ── CORRIGIDO: UTF-8 + NOVO: usa intervalo_alerta por tarefa ──
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$meu_id = $_SESSION['usuario_id'];
$role   = $_SESSION['role'] ?? 'funcionario';
$status = $_GET['status'] ?? 'todas';
$data   = $_GET['data']   ?? '';          // sem default: mostra todas as datas

try {
    // ── NOVO: garante que a coluna intervalo_alerta existe antes de usá-la ──
    try {
        $pdo->exec("ALTER TABLE chat_tarefas ADD COLUMN IF NOT EXISTS intervalo_alerta INT DEFAULT 30");
    } catch (Exception $e) {}

    // Auto-atualiza atrasadas
    $pdo->exec("
        UPDATE chat_tarefas
        SET status = 'atrasada'
        WHERE status = 'pendente'
          AND CONCAT(COALESCE(data_tarefa, data_criacao), ' ', horario_limite) < NOW()
    ");

    $where  = ["1=1"];
    $params = [];

    // Admin vê todos, funcionário só os seus
    if ($role !== 'admin') {
        $where[] = "t.usuario_id = :uid";
        $params[':uid'] = $meu_id;
    } elseif ($status === 'equipe') {
        $where[] = "t.usuario_id != :uid";
        $params[':uid'] = $meu_id;
    }

    if ($data) {
        $where[] = "(COALESCE(t.data_tarefa, t.data_criacao) = :data OR :data2 = '')";
        $params[':data']  = $data;
        $params[':data2'] = $data;
    }

    if ($status === 'pendente') {
        // Pendente inclui atrasadas
        $where[] = "t.status IN ('pendente','atrasada')";
    } elseif (!in_array($status, ['todas', 'equipe', ''])) {
        $where[] = "t.status = :status";
        $params[':status'] = $status;
    }

    // Tarefas confirmadas: exibir apenas as das últimas 24h
    $where[] = "(t.status != 'confirmada' OR COALESCE(t.horario_check, t.hora_confirmacao, t.data_criacao) > DATE_SUB(NOW(), INTERVAL 24 HOUR))";

    $sql = "
        SELECT t.*, u.nome_completo AS usuario_nome,
               IF(t.usuario_id = :eu, 1, 0) AS e_minha,
               DATE_FORMAT(COALESCE(t.data_tarefa, t.data_criacao), '%d/%m/%Y') AS data_fmt,
               -- alerta_proximo só é verdadeiro para o próprio dono da tarefa
               IF(
                 t.usuario_id = :eu2
                 AND CONCAT(COALESCE(t.data_tarefa, t.data_criacao), ' ', t.horario_limite)
                       BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL COALESCE(t.intervalo_alerta, 30) MINUTE)
                 AND t.status = 'pendente',
                 1, 0
               ) AS alerta_proximo,
               IF(t.horario_check IS NOT NULL,
                  TIME_FORMAT(t.horario_check, '%H:%i'), NULL) AS horario_check
        FROM chat_tarefas t
        LEFT JOIN chat_users u ON u.id = t.usuario_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY CONCAT(COALESCE(t.data_tarefa, t.data_criacao), ' ', t.horario_limite) ASC
    ";
    $params[':eu']  = $meu_id;
    $params[':eu2'] = $meu_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais por status — mesmos filtros de usuário e data da query principal
    $totWhere  = ["1=1"];
    $totParams = [];
    if ($role !== 'admin') {
        $totWhere[]          = "usuario_id = :uid";
        $totParams[':uid']   = $meu_id;
    }
    if ($data) {
        $totWhere[]            = "(COALESCE(data_tarefa, data_criacao) = :tdata OR :tdata2 = '')";
        $totParams[':tdata']   = $data;
        $totParams[':tdata2']  = $data;
    }
    $totSQL = $pdo->prepare(
        "SELECT status, COUNT(*) as n FROM chat_tarefas WHERE " . implode(' AND ', $totWhere) . " GROUP BY status"
    );
    $totSQL->execute($totParams);
    $totRows = $totSQL->fetchAll(PDO::FETCH_ASSOC);
    $totais  = ['todas' => 0, 'pendente' => 0, 'confirmada' => 0, 'atrasada' => 0, 'cancelada' => 0];
    foreach ($totRows as $r) {
        $totais[$r['status']] = (int)$r['n'];
        $totais['todas'] += (int)$r['n'];
    }

    echo json_encode(['success' => true, 'tarefas' => $tarefas, 'totais' => $totais]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
