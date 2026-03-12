<?php
// ── CORRIGIDO: ob_start() + UTF-8 + NOVO: intervalo_alerta ──
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$meu_id     = $_SESSION['usuario_id'];
$minha_role = $_SESSION['role'] ?? 'funcionario';

$d                = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$titulo           = trim($d['titulo']           ?? '');
$horario_limite   = trim($d['horario_limite']   ?? '');
$data_tarefa      = trim($d['data_tarefa']      ?? date('Y-m-d'));
$data_inicio      = trim($d['data_inicio']      ?? '') ?: null;
$descricao        = trim($d['descricao']        ?? '');
$usuario_alvo     = intval($d['usuario_alvo']   ?? $meu_id);
// ── NOVO: intervalo de alerta em minutos (padrão 30) ──
$intervalo_alerta = intval($d['intervalo_alerta'] ?? 30);
if (!in_array($intervalo_alerta, [10, 15, 20, 30, 45, 60, 90, 120])) {
    $intervalo_alerta = 30;
}

if (!$titulo || !$horario_limite) {
    echo json_encode(['success' => false, 'error' => 'Título e horário são obrigatórios.']); exit;
}
if ($usuario_alvo !== $meu_id && $minha_role !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Apenas o admin pode criar tarefas para outros.']); exit;
}

try {
    // Garante colunas extras
    try {
        $pdo->exec("ALTER TABLE chat_tarefas ADD COLUMN IF NOT EXISTS intervalo_alerta INT DEFAULT 30");
        $pdo->exec("ALTER TABLE chat_tarefas ADD COLUMN IF NOT EXISTS data_inicio DATE NULL");
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("
        INSERT INTO chat_tarefas
          (usuario_id, criado_por, titulo, descricao, horario_limite, data_tarefa, data_inicio, intervalo_alerta, status, data_criacao)
        VALUES (:uid, :criador, :titulo, :desc, :hora, :data, :inicio, :intervalo, 'pendente', CURDATE())
    ");
    $stmt->execute([
        ':uid'       => $usuario_alvo,
        ':criador'   => $meu_id,
        ':titulo'    => $titulo,
        ':desc'      => $descricao ?: null,
        ':hora'      => $horario_limite,
        ':data'      => $data_tarefa,
        ':inicio'    => $data_inicio,
        ':intervalo' => $intervalo_alerta,
    ]);
    $tid = $pdo->lastInsertId();

    // Notifica no chat se admin atribuiu a outro
    if ($usuario_alvo !== $meu_id) {
        $aviso = "🔔 Nova tarefa atribuída: *{$titulo}* — prazo: {$horario_limite}";
        try {
            $n = $pdo->prepare("
                INSERT INTO chat_mensagens (tipo_chat, remetente_id, destinatario_id, tipo_msg, conteudo, mensagem, data_envio, lido)
                VALUES ('individual', :adm, :func, 'sistema', :aviso, :aviso2, NOW(), 0)
            ");
            $n->execute([':adm' => $meu_id, ':func' => $usuario_alvo, ':aviso' => $aviso, ':aviso2' => $aviso]);
        } catch (Exception $e) {} // notificação é opcional
    }

    echo json_encode(['success' => true, 'tarefa_id' => $tid]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
