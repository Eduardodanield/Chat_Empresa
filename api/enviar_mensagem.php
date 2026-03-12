<?php
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$meu_id     = $_SESSION['usuario_id'];
$tipo       = $_POST['tipo']     ?? '';
$contato_id = intval($_POST['id'] ?? 0);
$texto      = trim($_POST['mensagem'] ?? '');

// ─── Upload de arquivo ───────────────────────────────────
$arquivo_url  = null;
$arquivo_nome = null;
$tipo_msg     = 'texto';

if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {

    // ── 1. Limite de tamanho: 50 MB ──
    $limite_bytes = 50 * 1024 * 1024;
    if ($_FILES['arquivo']['size'] > $limite_bytes) {
        echo json_encode(['success' => false, 'error' => 'Arquivo muito grande. Limite: 50 MB.']); exit;
    }

    // ── 2. Extensões permitidas (sem executáveis) ──
    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));

    $extensoes_imagem    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensoes_video     = ['mp4', 'mov', 'avi', 'webm'];
    $extensoes_documento = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'rar'];
    $extensoes_permitidas = array_merge($extensoes_imagem, $extensoes_video, $extensoes_documento);

    if (!in_array($ext, $extensoes_permitidas)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido.']); exit;
    }

    // ── 3. Verificação do MIME type real (não confia só na extensão) ──
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($_FILES['arquivo']['tmp_name']);

    $mimes_permitidos = [
        // Imagens
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        // Vídeos
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
        'application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed',
    ];

    if (!in_array($mime_real, $mimes_permitidos)) {
        echo json_encode(['success' => false, 'error' => 'Conteúdo do arquivo não permitido.']); exit;
    }

    // ── 4. Nunca permite PHP ou executáveis mesmo com extensão disfarçada ──
    $mimes_proibidos = ['application/x-php', 'text/x-php', 'application/x-httpd-php',
                        'application/octet-stream', 'application/x-executable',
                        'application/x-sh', 'text/x-shellscript'];
    if (in_array($mime_real, $mimes_proibidos)) {
        echo json_encode(['success' => false, 'error' => 'Arquivo executável não permitido.']); exit;
    }

    // ── 5. Nome do arquivo sanitizado (sem path traversal) ──
    $nome_original = basename($_FILES['arquivo']['name']);
    $nome_original = preg_replace('/[^a-zA-Z0-9._\- ]/', '', $nome_original);

    // ── 6. Salva com nome aleatório (sem usar nome do usuário) ──
    $pasta = '../uploads/' . date('Y/m/');
    if (!is_dir($pasta)) mkdir($pasta, 0755, true);

    $novo = bin2hex(random_bytes(12)) . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $pasta . $novo)) {
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo.']); exit;
    }

    $tipo_msg     = in_array($ext, $extensoes_imagem) ? 'foto' : (in_array($ext, $extensoes_video) ? 'video' : 'documento');
    $arquivo_url  = 'uploads/' . date('Y/m/') . $novo;
    $arquivo_nome = $nome_original;
}

if ($texto === '' && !$arquivo_url) {
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']); exit;
}
if (!in_array($tipo, ['individual', 'grupo'])) {
    echo json_encode(['success' => false, 'error' => 'Tipo inválido.']); exit;
}

try {
    if ($tipo === 'individual') {
        $stmt = $pdo->prepare("
            INSERT INTO chat_mensagens
              (tipo_chat, remetente_id, destinatario_id, tipo_msg, conteudo, arquivo_url, arquivo_nome, mensagem, arquivo, data_envio, lido)
            VALUES ('individual', :rem, :dest, :tmsg, :cont, :aurl, :anome, :msg, :arq, NOW(), 0)
        ");
        $stmt->execute([
            ':rem'   => $meu_id,
            ':dest'  => $contato_id,
            ':tmsg'  => $tipo_msg,
            ':cont'  => $texto ?: null,
            ':aurl'  => $arquivo_url,
            ':anome' => $arquivo_nome,
            ':msg'   => $texto ?: '',
            ':arq'   => $arquivo_url,
        ]);
    } else {
        $mem = $pdo->prepare("SELECT 1 FROM chat_grupo_membros WHERE grupo_id = ? AND usuario_id = ?");
        $mem->execute([$contato_id, $meu_id]);
        if (!$mem->rowCount()) {
            echo json_encode(['success' => false, 'error' => 'Você não é membro deste grupo.']); exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO chat_mensagens
              (tipo_chat, grupo_id, remetente_id, tipo_msg, conteudo, arquivo_url, arquivo_nome, mensagem, arquivo, data_envio, lido)
            VALUES ('grupo', :grp, :rem, :tmsg, :cont, :aurl, :anome, :msg, :arq, NOW(), 0)
        ");
        $stmt->execute([
            ':grp'   => $contato_id,
            ':rem'   => $meu_id,
            ':tmsg'  => $tipo_msg,
            ':cont'  => $texto ?: null,
            ':aurl'  => $arquivo_url,
            ':anome' => $arquivo_nome,
            ':msg'   => $texto ?: '',
            ':arq'   => $arquivo_url,
        ]);
    }
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Erro interno.';
    echo json_encode(['success' => false, 'error' => $msg]);
}
