<?php
// ── CORRIGIDO: ob_start() evita qualquer saída acidental antes do JSON ──
ob_start();
require_once '../conexao.php';
exigir_admin();
header('Content-Type: application/json; charset=utf-8');

$d     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$nome  = trim($d['nome_completo'] ?? $d['nome'] ?? '');
$cpf   = preg_replace('/[^0-9]/', '', $d['cpf'] ?? '');
$cargo = trim($d['cargo'] ?? '');

// ── CORRIGIDO: strings agora em UTF-8 correto (eram Latin-1, quebravam json_encode) ──
if (empty($nome)) {
    echo json_encode(['success'=>false,'error'=>'Nome completo é obrigatório.']); exit;
}
if (empty($cpf)) {
    echo json_encode(['success'=>false,'error'=>'CPF é obrigatório.']); exit;
}
if (strlen($cpf) < 10 || strlen($cpf) > 11) {
    echo json_encode(['success'=>false,'error'=>'CPF inválido. Use apenas os números (10 ou 11 dígitos).']); exit;
}

try {
    // Verifica nome duplicado
    $checkNome = $pdo->prepare("SELECT id FROM chat_users WHERE nome_completo = :nome LIMIT 1");
    $checkNome->execute([':nome' => $nome]);
    if ($checkNome->rowCount() > 0) {
        echo json_encode(['success'=>false,'error'=>"Já existe um usuário com o nome \"$nome\". Use um nome diferente."]); exit;
    }

    // Verifica CPF duplicado
    $checkCpf = $pdo->prepare("SELECT id FROM chat_users WHERE cpf = :cpf LIMIT 1");
    $checkCpf->execute([':cpf' => $cpf]);
    if ($checkCpf->rowCount() > 0) {
        echo json_encode(['success'=>false,'error'=>'Este CPF já está cadastrado em outro usuário.']); exit;
    }

    // Garante coluna cargo existe
    try { $pdo->exec("ALTER TABLE chat_users ADD COLUMN IF NOT EXISTS cargo VARCHAR(100) DEFAULT ''"); } catch (Exception $e) {}

    $hash = password_hash($cpf, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO chat_users (nome_completo, username, password, cpf, role, cargo, online)
        VALUES (:nome, :username, :password, :cpf, 'funcionario', :cargo, 0)
    ");
    $stmt->execute([
        ':nome'     => $nome,
        ':username' => $nome,
        ':password' => $hash,
        ':cpf'      => $cpf,
        ':cargo'    => $cargo,
    ]);
    $novo_id = $pdo->lastInsertId();

    // Auto-adiciona ao Grupo Geral
    $geral = $pdo->query("SELECT id FROM chat_grupos WHERE fixo = 1 LIMIT 1")->fetch();
    if ($geral) {
        $pdo->prepare("INSERT IGNORE INTO chat_grupo_membros (grupo_id, usuario_id) VALUES (?,?)")
            ->execute([$geral['id'], $novo_id]);
    }

    $cpf_fmt = strlen($cpf) === 11
        ? substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2)
        : $cpf;

    // ── CORRIGIDO: string UTF-8 válida para json_encode não retornar false ──
    echo json_encode([
        'success' => true,
        'message' => "Funcionário criado com sucesso!\nLogin: $nome\nSenha (CPF): $cpf_fmt"
    ]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>'Erro no banco: '.$e->getMessage()]);
}
