<?php
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$meu_id   = $_SESSION['usuario_id'];
$meu_role = $_SESSION['role'] ?? 'funcionario';
$d      = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$tarefa_id = intval($d['tarefa_id'] ?? 0);
$acao      = $d['acao'] ?? '';

if (!$tarefa_id || !in_array($acao, ['concluir', 'confirmar', 'adiar', 'desmarcar', 'cancelar'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit;
}

try {
    // Prepared statement — sem concatenação de variáveis na query
    $stmt = $pdo->prepare("SELECT * FROM chat_tarefas WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $tarefa_id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        echo json_encode(['success' => false, 'error' => 'Tarefa não encontrada.']); exit;
    }

    // Apenas o responsável pode concluir, confirmar ou desmarcar
    if (in_array($acao, ['concluir', 'confirmar', 'desmarcar'])) {
        if (intval($tarefa['usuario_id']) !== intval($meu_id)) {
            echo json_encode(['success' => false, 'error' => 'Sem permissão para alterar esta tarefa.']); exit;
        }
    }

    if ($acao === 'concluir' || $acao === 'confirmar') {
        $temHoraConf = array_key_exists('hora_confirmacao', $tarefa);
        $temCheck    = array_key_exists('horario_check', $tarefa);

        if ($temHoraConf && $temCheck) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'confirmada', alerta_enviado = 1,
                    hora_confirmacao = NOW(), horario_check = NOW()
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } elseif ($temHoraConf) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'confirmada', alerta_enviado = 1, hora_confirmacao = NOW()
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } elseif ($temCheck) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'confirmada', alerta_enviado = 1, horario_check = NOW()
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } else {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'confirmada', alerta_enviado = 1
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        }

    } elseif ($acao === 'adiar') {
        $pdo->prepare("
            UPDATE chat_tarefas SET alerta_enviado = 1 WHERE id = :id
        ")->execute([':id' => $tarefa_id]);

    } elseif ($acao === 'desmarcar') {
        $temHoraConf = array_key_exists('hora_confirmacao', $tarefa);
        $temCheck    = array_key_exists('horario_check', $tarefa);

        if ($temHoraConf && $temCheck) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'pendente', alerta_enviado = 0,
                    hora_confirmacao = NULL, horario_check = NULL
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } elseif ($temHoraConf) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'pendente', alerta_enviado = 0, hora_confirmacao = NULL
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } elseif ($temCheck) {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'pendente', alerta_enviado = 0, horario_check = NULL
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        } else {
            $pdo->prepare("
                UPDATE chat_tarefas
                SET status = 'pendente', alerta_enviado = 0
                WHERE id = :id
            ")->execute([':id' => $tarefa_id]);
        }

    } elseif ($acao === 'cancelar') {
        // Admin ou dono podem excluir
        if ($meu_role !== 'admin' && intval($tarefa['usuario_id']) !== intval($meu_id)) {
            echo json_encode(['success' => false, 'error' => 'Sem permissão.']); exit;
        }
        $pdo->prepare("DELETE FROM chat_tarefas WHERE id = :id")->execute([':id' => $tarefa_id]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Erro interno.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
