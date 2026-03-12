<?php
// ── CORRIGIDO: ob_start() evita saída acidental antes do JSON ──
ob_start();
require_once '../conexao.php';
exigir_login();
exigir_admin();

header('Content-Type: application/json; charset=utf-8');

$eu     = usuario_logado();
$meu_id = $eu['id'];

$dados   = json_decode(file_get_contents('php://input'), true) ?? [];
$nome    = trim($dados['nome']    ?? $_POST['nome']    ?? '');
$icone   = trim($dados['icone']   ?? $_POST['icone']   ?? '');
$membros = $dados['membros']      ?? $_POST['membros'] ?? [];

// ── CORRIGIDO: strings agora em UTF-8 correto (eram Latin-1, quebravam json_encode) ──
if ($nome === '') {
    echo json_encode(['success' => false, 'error' => 'Nome do grupo é obrigatório.']);
    exit;
}

if (mb_strlen($nome) > 100) {
    echo json_encode(['success' => false, 'error' => 'Nome muito longo. Máximo 100 caracteres.']);
    exit;
}

// ── CORRIGIDO: membros externos não são mais obrigatórios ──
// O admin criador entra automaticamente, então um grupo pode ser criado mesmo sem membros extras.

// Garante que os IDs são inteiros válidos
$membros = array_map('intval', $membros);
$membros = array_filter($membros, fn($id) => $id > 0);

// Ícone padrão se vazio
if ($icone === '') $icone = '👥';

try {

    // Verifica se já existe grupo com esse nome
    $check = $pdo->prepare("SELECT id FROM chat_grupos WHERE nome = :nome LIMIT 1");
    $check->execute([':nome' => $nome]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Já existe um grupo com esse nome.']);
        exit;
    }

    // Cria o grupo
    $stmt = $pdo->prepare("
        INSERT INTO chat_grupos (nome, icone, fixo, criado_por)
        VALUES (:nome, :icone, 0, :criado_por)
    ");
    $stmt->execute([
        ':nome'       => $nome,
        ':icone'      => $icone,
        ':criado_por' => $meu_id,
    ]);

    $grupo_id = (int)$pdo->lastInsertId();

    // Adiciona membros + admin criador
    $membros[] = $meu_id;
    $membros   = array_unique($membros);

    // ── CORRIGIDO: se não há membros externos, apenas o admin entra no grupo ──
    if (!empty($membros)) {
        $placeholders = implode(',', array_fill(0, count($membros), '?'));
        $checkUsers   = $pdo->prepare("SELECT id FROM chat_users WHERE id IN ($placeholders)");
        $checkUsers->execute(array_values($membros));
        $ids_validos  = $checkUsers->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $ids_validos = [$meu_id];
    }

    // Insere todos os membros
    $valoresInsert = implode(',', array_fill(0, count($ids_validos), '(?, ?)'));
    $valores       = [];
    foreach ($ids_validos as $uid) {
        $valores[] = $grupo_id;
        $valores[] = (int)$uid;
    }

    $pdo->prepare("
        INSERT IGNORE INTO chat_grupo_membros (grupo_id, usuario_id)
        VALUES $valoresInsert
    ")->execute($valores);

    // Mensagem de sistema no grupo
    $pdo->prepare("
        INSERT INTO chat_mensagens
            (tipo_chat, grupo_id, remetente_id, tipo_msg, conteudo)
        VALUES
            ('grupo', :grupo, :rem, 'sistema', :msg)
    ")->execute([
        ':grupo' => $grupo_id,
        ':rem'   => $meu_id,
        ':msg'   => 'Grupo criado por ' . $eu['nome'] . '. Bem-vindos! 👋',
    ]);

    echo json_encode([
        'success'       => true,
        'grupo_id'      => $grupo_id,
        'nome'          => $nome,
        'icone'         => $icone,
        'total_membros' => count($ids_validos),
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
