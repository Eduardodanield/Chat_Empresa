<?php
// ── CORRIGIDO: ob_start() evita saída acidental antes do JSON ──
ob_start();
require_once '../conexao.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

$eu     = usuario_logado();
$meu_id = $eu['id'];

// ── CORRIGIDO: JS envia { id: id, modo: modo }, não msg_id ──
$dados      = json_decode(file_get_contents('php://input'), true) ?? [];
$msg_id     = (int)($dados['msg_id'] ?? $dados['id'] ?? $_POST['msg_id'] ?? 0);
$modo       = $dados['modo']    ?? $_POST['modo']    ?? ''; // 'mim' ou 'todos'

if ($msg_id <= 0 || !in_array($modo, ['mim', 'todos'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

try {

    // ══════════════════════════════════════════
    // 1. BUSCA A MENSAGEM E VERIFICA SE � MINHA
    // ══════════════════════════════════════════
    $stmt = $pdo->prepare("
        SELECT id, remetente_id, arquivo_url, tipo_msg
        FROM chat_mensagens
        WHERE id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $msg_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        echo json_encode(['success' => false, 'error' => 'Mensagem não encontrada.']);
        exit;
    }

    // Só o remetente pode apagar
    if ((int)$msg['remetente_id'] !== $meu_id) {
        echo json_encode(['success' => false, 'error' => 'Você só pode apagar suas próprias mensagens.']);
        exit;
    }

    // ══════════════════════════════════════════
    // 2. APAGAR PARA MIM
    //    Insere na tabela chat_mensagens_apagadas
    // ══════════════════════════════════════════
    if ($modo === 'mim') {

        $insert = $pdo->prepare("
            INSERT IGNORE INTO chat_mensagens_apagadas (mensagem_id, usuario_id)
            VALUES (:msg_id, :usuario_id)
        ");
        $insert->execute([
            ':msg_id'     => $msg_id,
            ':usuario_id' => $meu_id,
        ]);

        echo json_encode([
            'success' => true,
            'modo'    => 'mim',
            'msg_id'  => $msg_id,
        ]);

    // ══════════════════════════════════════════
    // 3. APAGAR PARA TODOS
    //    Marca apagado_todos = 1 e deleta arquivo
    // ══════════════════════════════════════════
    } elseif ($modo === 'todos') {

        // Deleta arquivo físico do servidor se houver
        if (!empty($msg['arquivo_url'])) {
            $caminho = __DIR__ . '/../../' . ltrim($msg['arquivo_url'], '/');
            if (file_exists($caminho)) {
                unlink($caminho);
            }
        }

        // Marca como apagado para todos no banco
        $update = $pdo->prepare("
            UPDATE chat_mensagens
            SET apagado_todos = 1,
                conteudo      = NULL,
                arquivo_url   = NULL,
                arquivo_nome  = NULL
            WHERE id = :id
        ");
        $update->execute([':id' => $msg_id]);

        echo json_encode([
            'success' => true,
            'modo'    => 'todos',
            'msg_id'  => $msg_id,
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}