<?php
require_once '../conexao.php';
exigir_login();

header('Content-Type: application/json; charset=utf-8');

$eu     = usuario_logado();
$meu_id = $eu['id'];

// ══════════════════════════════════════════
// MODO ADMIN: retorna todos os usuários com dados completos
// ══════════════════════════════════════════
if (($_GET['modo'] ?? '') === 'admin') {
    if ($eu['role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Acesso negado.']); exit;
    }
    $stmt = $pdo->prepare("
        SELECT id, nome_completo, username, avatar, role, cargo
        FROM chat_users
        ORDER BY role DESC, nome_completo ASC
    ");
    $stmt->execute();
    $contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'contatos' => $contatos]);
    exit;
}

try {

    // ══════════════════════════════════════════
    // 1. CONTATOS (DMs)
    // ══════════════════════════════════════════
    $stmt = $pdo->prepare("
        SELECT id, nome_completo, username, avatar, online
        FROM chat_users
        WHERE id != :id
        ORDER BY online DESC, nome_completo ASC
    ");
    $stmt->execute([':id' => $meu_id]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dms = [];

    foreach ($usuarios as $u) {
        $outro_id = (int)$u['id'];

        // Última mensagem entre os dois (preview)
        $stmtLast = $pdo->prepare("
            SELECT m.tipo_msg, m.conteudo, m.arquivo_nome, m.remetente_id, m.apagado_todos, m.enviado_em
            FROM chat_mensagens m
            WHERE m.tipo_chat = 'individual'
              AND m.apagado_todos = 0
              AND (
                    (m.remetente_id = :meu  AND m.destinatario_id = :outro)
                 OR (m.remetente_id = :outro2 AND m.destinatario_id = :meu2)
              )
            ORDER BY m.enviado_em DESC
            LIMIT 1
        ");
        $stmtLast->execute([
            ':meu'   => $meu_id,
            ':outro' => $outro_id,
            ':outro2'=> $outro_id,
            ':meu2'  => $meu_id,
        ]);
        $ultima = $stmtLast->fetch(PDO::FETCH_ASSOC);

        // Monta preview
        $preview = '...';
        if ($ultima) {
            $prefixo = ((int)$ultima['remetente_id'] === $meu_id) ? 'Você: ' : '';
            if ($ultima['tipo_msg'] === 'foto')      $preview = $prefixo . '📷 Foto';
            elseif ($ultima['tipo_msg'] === 'video') $preview = $prefixo . '🎥 Vídeo';
            elseif ($ultima['tipo_msg'] === 'documento') {
                $preview = $prefixo . '📄 ' . ($ultima['arquivo_nome'] ?? 'Documento');
            } else {
                $texto   = mb_substr($ultima['conteudo'] ?? '', 0, 28);
                $preview = $prefixo . $texto . (mb_strlen($ultima['conteudo'] ?? '') > 28 ? '...' : '');
            }
        }

        // Não lidas: msgs do outro que não estão em chat_leituras para mim
        $stmtUnread = $pdo->prepare("
            SELECT COUNT(*) FROM chat_mensagens m
            LEFT JOIN chat_leituras l ON l.mensagem_id = m.id AND l.usuario_id = :meu
            WHERE m.tipo_chat       = 'individual'
              AND m.remetente_id    = :outro
              AND m.destinatario_id = :meu2
              AND m.apagado_todos   = 0
              AND l.id              IS NULL
        ");
        $stmtUnread->execute([
            ':meu'   => $meu_id,
            ':outro' => $outro_id,
            ':meu2'  => $meu_id,
        ]);
        $nao_lidas = (int)$stmtUnread->fetchColumn();

        $dms[] = [
            'id'        => $outro_id,
            'nome'      => $u['nome_completo'] ?? $u['username'],
            'avatar'    => $u['avatar'],
            'online'    => (bool)$u['online'],
            'preview'   => $preview,
            'nao_lidas' => $nao_lidas,
            '_ts'       => $ultima['enviado_em'] ?? '',
        ];
    }

    // Ordena por mensagem mais recente (igual WhatsApp)
    usort($dms, fn($a, $b) => strcmp($b['_ts'], $a['_ts']));
    // Remove campo interno de ordenação
    foreach ($dms as &$d) unset($d['_ts']);
    unset($d);

    // ══════════════════════════════════════════
    // 2. GRUPOS
    // ══════════════════════════════════════════
    $stmtGrupos = $pdo->prepare("
        SELECT g.id, g.nome, g.icone, g.fixo
        FROM chat_grupos g
        INNER JOIN chat_grupo_membros m ON m.grupo_id = g.id
        WHERE m.usuario_id = :uid
        ORDER BY g.fixo DESC, g.nome ASC
    ");
    $stmtGrupos->execute([':uid' => $meu_id]);
    $grupos_db = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    $grupos = [];

    foreach ($grupos_db as $g) {
        $grupo_id = (int)$g['id'];

        // Última mensagem do grupo (preview)
        $stmtLastG = $pdo->prepare("
            SELECT m.tipo_msg, m.conteudo, m.arquivo_nome, m.remetente_id,
                   u.nome_completo, u.username
            FROM chat_mensagens m
            LEFT JOIN chat_users u ON u.id = m.remetente_id
            WHERE m.tipo_chat     = 'grupo'
              AND m.grupo_id      = :grupo
              AND m.apagado_todos = 0
            ORDER BY m.enviado_em DESC
            LIMIT 1
        ");
        $stmtLastG->execute([':grupo' => $grupo_id]);
        $ultimaG = $stmtLastG->fetch(PDO::FETCH_ASSOC);

        // Preview do grupo
        $previewG = '...';
        if ($ultimaG) {
            $nome_rem = ((int)$ultimaG['remetente_id'] === $meu_id)
                ? 'Você'
                : ($ultimaG['nome_completo'] ?? $ultimaG['username'] ?? '');
            $prefixoG = $nome_rem . ': ';

            if ($ultimaG['tipo_msg'] === 'foto')          $previewG = $prefixoG . '📷 Foto';
            elseif ($ultimaG['tipo_msg'] === 'video')     $previewG = $prefixoG . '🎥 Vídeo';
            elseif ($ultimaG['tipo_msg'] === 'documento') $previewG = $prefixoG . '📄 Documento';
            else {
                $texto    = mb_substr($ultimaG['conteudo'] ?? '', 0, 28);
                $previewG = $prefixoG . $texto . (mb_strlen($ultimaG['conteudo'] ?? '') > 28 ? '...' : '');
            }
        }

        // Não lidas no grupo: msgs que não estou em chat_leituras
        $stmtUnreadG = $pdo->prepare("
            SELECT COUNT(*) FROM chat_mensagens m
            LEFT JOIN chat_leituras l ON l.mensagem_id = m.id AND l.usuario_id = :meu
            WHERE m.tipo_chat      = 'grupo'
              AND m.grupo_id       = :grupo
              AND m.remetente_id  != :meu2
              AND m.apagado_todos  = 0
              AND l.id             IS NULL
        ");
        $stmtUnreadG->execute([
            ':meu'   => $meu_id,
            ':grupo' => $grupo_id,
            ':meu2'  => $meu_id,
        ]);
        $nao_lidas_g = (int)$stmtUnreadG->fetchColumn();

        $grupos[] = [
            'id'        => $grupo_id,
            'nome'      => $g['nome'],
            'icone'     => $g['icone'],
            'fixo'      => (bool)$g['fixo'],
            'preview'   => $previewG,
            'nao_lidas' => $nao_lidas_g,
        ];
    }

    echo json_encode([
        'success' => true,
        'dms'     => $dms,
        'grupos'  => $grupos,
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
