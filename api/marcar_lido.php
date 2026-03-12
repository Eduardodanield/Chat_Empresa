<?php
require_once '../conexao.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

$eu     = usuario_logado();
$meu_id = $eu['id'];

// Aceita POST normal ou JSON via fetch
$dados      = json_decode(file_get_contents('php://input'), true) ?? [];
$tipo_chat  = $dados['tipo']  ?? $_POST['tipo']  ?? '';
$destino_id = (int)($dados['id'] ?? $_POST['id'] ?? 0);

if (!in_array($tipo_chat, ['individual', 'grupo']) || $destino_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Par�metros inv�lidos.']);
    exit;
}

try {

    // ══════════════════════════════════════════
    // 1. BUSCA MENSAGENS AINDA N�O LIDAS POR MIM
    // ══════════════════════════════════════════
    if ($tipo_chat === 'individual') {

        // Mensagens que o outro enviou para mim e eu ainda n�o li
        $stmt = $pdo->prepare("
            SELECT m.id FROM chat_mensagens m
            LEFT JOIN chat_leituras l
                   ON l.mensagem_id = m.id AND l.usuario_id = :meu
            WHERE m.tipo_chat        = 'individual'
              AND m.remetente_id     = :outro
              AND m.destinatario_id  = :meu2
              AND m.apagado_todos    = 0
              AND l.id               IS NULL
        ");
        $stmt->execute([
            ':meu'   => $meu_id,
            ':outro' => $destino_id,
            ':meu2'  => $meu_id,
        ]);

    } elseif ($tipo_chat === 'grupo') {

        // Verifica se sou membro
        $check = $pdo->prepare("
            SELECT id FROM chat_grupo_membros
            WHERE grupo_id = :g AND usuario_id = :u LIMIT 1
        ");
        $check->execute([':g' => $destino_id, ':u' => $meu_id]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Voc� n�o � membro deste grupo.']);
            exit;
        }

        // Mensagens do grupo que eu ainda n�o li (exceto as minhas)
        $stmt = $pdo->prepare("
            SELECT m.id FROM chat_mensagens m
            LEFT JOIN chat_leituras l
                   ON l.mensagem_id = m.id AND l.usuario_id = :meu
            WHERE m.tipo_chat      = 'grupo'
              AND m.grupo_id       = :grupo
              AND m.remetente_id  != :meu2
              AND m.apagado_todos  = 0
              AND l.id             IS NULL
        ");
        $stmt->execute([
            ':meu'   => $meu_id,
            ':grupo' => $destino_id,
            ':meu2'  => $meu_id,
        ]);
    }

    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) {
        echo json_encode([
            'success'     => true,
            'atualizadas' => 0,
            'obs'         => 'Nenhuma mensagem nova para marcar.',
        ]);
        exit;
    }

    // ══════════════════════════════════════════
    // 2. INSERE NA TABELA chat_leituras
    //    INSERT IGNORE evita duplicatas
    // ══════════════════════════════════════════
    $placeholders = implode(',', array_fill(0, count($ids), '(?, ?, NOW())'));
    $valores      = [];

    foreach ($ids as $msg_id) {
        $valores[] = (int)$msg_id;
        $valores[] = $meu_id;
    }

    $insert = $pdo->prepare("
        INSERT IGNORE INTO chat_leituras (mensagem_id, usuario_id, lido_em)
        VALUES $placeholders
    ");
    $insert->execute($valores);

    echo json_encode([
        'success'     => true,
        'atualizadas' => count($ids),
        'ids_lidos'   => $ids, // JS usa isso para atualizar os ticks na tela
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}