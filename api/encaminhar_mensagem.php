<?php
require_once '../conexao.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

$eu     = usuario_logado();
$meu_id = $eu['id'];

// Aceita JSON (fetch) ou POST normal
$dados         = json_decode(file_get_contents('php://input'), true) ?? [];
$mensagem_id   = (int)($dados['mensagem_id'] ?? $_POST['mensagem_id'] ?? 0);
$destinatarios = $dados['destinatarios']     ?? []; // Ex: ['individual-2', 'grupo-1']

if ($mensagem_id <= 0 || empty($destinatarios)) {
    echo json_encode(['success' => false, 'error' => 'Mensagem ou destinat�rios n�o informados.']);
    exit;
}

try {

    // ══════════════════════════════════════════
    // 1. BUSCA MENSAGEM ORIGINAL
    //    N�o encaminha msg apagada
    // ══════════════════════════════════════════
    $stmt = $pdo->prepare("
        SELECT tipo_msg, conteudo, arquivo_url, arquivo_nome, apagado_todos
        FROM chat_mensagens
        WHERE id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $mensagem_id]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original) {
        echo json_encode(['success' => false, 'error' => 'Mensagem original n�o encontrada.']);
        exit;
    }

    if ($original['apagado_todos']) {
        echo json_encode(['success' => false, 'error' => 'N�o � poss�vel encaminhar uma mensagem apagada.']);
        exit;
    }

    // Verifica se foi apagada para mim
    $apag = $pdo->prepare("
        SELECT id FROM chat_mensagens_apagadas
        WHERE mensagem_id = :mid AND usuario_id = :uid LIMIT 1
    ");
    $apag->execute([':mid' => $mensagem_id, ':uid' => $meu_id]);
    if ($apag->fetch()) {
        echo json_encode(['success' => false, 'error' => 'N�o � poss�vel encaminhar uma mensagem apagada.']);
        exit;
    }

    // ══════════════════════════════════════════
    // 2. PREPARA QUERIES
    // ══════════════════════════════════════════
    $insertDM = $pdo->prepare("
        INSERT INTO chat_mensagens
            (tipo_chat, remetente_id, destinatario_id, tipo_msg, conteudo, arquivo_url, arquivo_nome)
        VALUES
            ('individual', :remetente, :destinatario, :tipo_msg, :conteudo, :arquivo_url, :arquivo_nome)
    ");

    $insertGrupo = $pdo->prepare("
        INSERT INTO chat_mensagens
            (tipo_chat, grupo_id, remetente_id, tipo_msg, conteudo, arquivo_url, arquivo_nome)
        VALUES
            ('grupo', :grupo, :remetente, :tipo_msg, :conteudo, :arquivo_url, :arquivo_nome)
    ");

    $enviados = 0;
    $erros    = [];

    // ══════════════════════════════════════════
    // 3. ENCAMINHA PARA CADA DESTINAT�RIO
    // ══════════════════════════════════════════
    foreach ($destinatarios as $dest) {

        $partes = explode('-', $dest, 2);
        if (count($partes) !== 2) continue;

        $tipo_dest = $partes[0];
        $id_alvo   = (int)$partes[1];

        if ($id_alvo <= 0) continue;

        if ($tipo_dest === 'individual') {

            // Verifica se destinat�rio existe
            $check = $pdo->prepare("SELECT id FROM chat_users WHERE id = :id LIMIT 1");
            $check->execute([':id' => $id_alvo]);
            if (!$check->fetch()) {
                $erros[] = "Usu�rio $id_alvo n�o encontrado.";
                continue;
            }

            // N�o encaminha para si mesmo
            if ($id_alvo === $meu_id) {
                $erros[] = "N�o � poss�vel encaminhar para si mesmo.";
                continue;
            }

            $insertDM->execute([
                ':remetente'    => $meu_id,
                ':destinatario' => $id_alvo,
                ':tipo_msg'     => $original['tipo_msg'],
                ':conteudo'     => $original['conteudo'],
                ':arquivo_url'  => $original['arquivo_url'],
                ':arquivo_nome' => $original['arquivo_nome'],
            ]);
            $enviados++;

        } elseif ($tipo_dest === 'grupo') {

            // Verifica se � membro do grupo
            $check = $pdo->prepare("
                SELECT id FROM chat_grupo_membros
                WHERE grupo_id = :g AND usuario_id = :u LIMIT 1
            ");
            $check->execute([':g' => $id_alvo, ':u' => $meu_id]);
            if (!$check->fetch()) {
                $erros[] = "Voc� n�o � membro do grupo $id_alvo.";
                continue;
            }

            $insertGrupo->execute([
                ':grupo'        => $id_alvo,
                ':remetente'    => $meu_id,
                ':tipo_msg'     => $original['tipo_msg'],
                ':conteudo'     => $original['conteudo'],
                ':arquivo_url'  => $original['arquivo_url'],
                ':arquivo_nome' => $original['arquivo_nome'],
            ]);
            $enviados++;
        }
    }

    // ══════════════════════════════════════════
    // 4. RETORNO
    // ══════════════════════════════════════════
    if ($enviados === 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'Nenhuma mensagem foi encaminhada.',
            'erros'   => $erros,
        ]);
    } else {
        echo json_encode([
            'success'  => true,
            'enviados' => $enviados,
            'erros'    => $erros, // avisa se algum destino falhou
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}