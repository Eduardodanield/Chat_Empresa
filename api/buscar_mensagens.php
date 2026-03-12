<?php
// ── CORRIGIDO: ob_start() + UTF-8 ──
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

// ── NOVO: limpa mensagens de sistema com encoding garbled (executa uma vez, no-op depois) ──
try {
    $pdo->exec("DELETE FROM chat_mensagens WHERE tipo_msg = 'sistema' AND (conteudo LIKE '%&#12%' OR conteudo LIKE '%atribu?%' OR conteudo LIKE '% ? %')");
} catch (Exception $e) {}

$meu_id    = $_SESSION['usuario_id'];
$tipo      = $_GET['tipo']     ?? '';
$id        = intval($_GET['id']       ?? 0);
$ultimo_id = intval($_GET['ultimo_id'] ?? 0);

if (!$id || !in_array($tipo, ['individual','grupo'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']); exit;
}

try {
    if ($tipo === 'individual') {
        $stmt = $pdo->prepare("
            SELECT m.*,
                   u.nome_completo AS nome_remetente,
                   u.avatar AS remetente_avatar,
                   IF(m.remetente_id = :eu, 1, 0) AS sou_eu,
                   IF(l.id IS NOT NULL, 1, 0) AS apagada,
                   IF(leit.id IS NOT NULL, 1, 0) AS lido_confirmado
            FROM chat_mensagens m
            LEFT JOIN chat_users u ON u.id = m.remetente_id
            LEFT JOIN chat_mensagens_apagadas l ON l.mensagem_id = m.id AND l.usuario_id = :eu2
            LEFT JOIN chat_leituras leit ON leit.mensagem_id = m.id AND leit.usuario_id = :eu3
            WHERE m.apagado_todos = 0
              AND m.id > :ultimo
              AND (
                (m.tipo_chat = 'individual' AND (
                  (m.remetente_id = :eu4 AND m.destinatario_id = :dest)
                  OR (m.remetente_id = :dest2 AND m.destinatario_id = :eu5)
                ))
              )
            ORDER BY m.id ASC
            LIMIT 80
        ");
        $stmt->execute([
            ':eu'=>$meu_id, ':eu2'=>$meu_id, ':eu3'=>$meu_id, ':eu4'=>$meu_id, ':eu5'=>$meu_id,
            ':dest'=>$id, ':dest2'=>$id,
            ':ultimo'=>$ultimo_id,
        ]);
    } else {
        // Verifica se � membro
        $mem = $pdo->prepare("SELECT 1 FROM chat_grupo_membros WHERE grupo_id=? AND usuario_id=?");
        $mem->execute([$id, $meu_id]);
        if (!$mem->rowCount()) {
            echo json_encode(['success'=>false,'error'=>'N�o � membro do grupo.']); exit;
        }
        $stmt = $pdo->prepare("
            SELECT m.*,
                   u.nome_completo AS nome_remetente,
                   u.avatar AS remetente_avatar,
                   IF(m.remetente_id = :eu, 1, 0) AS sou_eu,
                   IF(l.id IS NOT NULL, 1, 0) AS apagada,
                   0 AS lido_confirmado
            FROM chat_mensagens m
            LEFT JOIN chat_users u ON u.id = m.remetente_id
            LEFT JOIN chat_mensagens_apagadas l ON l.mensagem_id = m.id AND l.usuario_id = :eu2
            WHERE m.apagado_todos = 0
              AND m.id > :ultimo
              AND m.grupo_id = :grp
            ORDER BY m.id ASC
            LIMIT 80
        ");
        $stmt->execute([':eu'=>$meu_id,':eu2'=>$meu_id,':grp'=>$id,':ultimo'=>$ultimo_id]);
    }

    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marca como lido no banco (chat_leituras) — individual E grupo
    if (!empty($msgs)) {
        foreach ($msgs as $m) {
            if (!$m['sou_eu']) {
                try {
                    $pdo->prepare("INSERT IGNORE INTO chat_leituras (mensagem_id, usuario_id) VALUES (?,?)")
                        ->execute([$m['id'], $meu_id]);
                } catch (Exception $e) {}
            }
        }
    }

    // Formata para o JS
    $resultado = [];
    foreach ($msgs as $m) {
        // Compatibilidade: conteudo OU mensagem
        $texto = $m['conteudo'] ?? $m['mensagem'] ?? '';
        // Hora formatada
        $hora = '';
        if (!empty($m['data_envio'])) {
            $hora = date('H:i', strtotime($m['data_envio']));
        } elseif (!empty($m['enviado_em'])) {
            $hora = date('H:i', strtotime($m['enviado_em']));
        }

        // Tick: lido = algu�m leu
        $tick = 'entregue';
        if ($m['sou_eu']) {
            $leu = $pdo->prepare("SELECT 1 FROM chat_leituras WHERE mensagem_id=? AND usuario_id!=?");
            $leu->execute([$m['id'], $meu_id]);
            if ($leu->rowCount()) $tick = 'lido';
        }

        $resultado[] = [
            'id'            => $m['id'],
            'sou_eu'        => (int)$m['sou_eu'],
            'nome_remetente'    => $m['nome_remetente'] ?? '',
            'remetente_avatar'  => $m['remetente_avatar'] ?? null,
            'tipo_msg'      => $m['tipo_msg'] ?? 'texto',
            'conteudo'      => $texto,
            'mensagem'      => $texto,
            'arquivo_url'   => $m['arquivo_url'] ?? $m['arquivo'] ?? null,
            'arquivo_nome'  => $m['arquivo_nome'] ?? null,
            'hora'          => $hora,
            'tick'          => $tick,
            'apagada'       => (int)($m['apagada'] ?? 0),
            'apagado_todos' => (int)($m['apagado_todos'] ?? 0),
        ];
    }

    $ultimo = !empty($resultado) ? end($resultado)['id'] : $ultimo_id;

    echo json_encode([
        'success'   => true,
        'mensagens' => $resultado,
        'ultimo_id' => $ultimo,
        'append'    => $ultimo_id > 0,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}