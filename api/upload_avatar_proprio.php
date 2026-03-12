<?php
ob_start();
require_once '../conexao.php';
exigir_login();
header('Content-Type: application/json; charset=utf-8');

$usuario_id = intval($_SESSION['usuario_id'] ?? 0);
if (!$usuario_id) {
    echo json_encode(['success' => false, 'error' => 'Sessão inválida.']); exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']); exit;
}

$file = $_FILES['avatar'];
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Imagem muito grande (máx 5MB).']); exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$exts  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
if (!isset($exts[$mime])) {
    echo json_encode(['success' => false, 'error' => 'Apenas imagens JPG, PNG, WEBP ou GIF.']); exit;
}

$dir = __DIR__ . '/../uploads/avatares/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

// Remove avatar anterior
$old = $pdo->prepare("SELECT avatar FROM chat_users WHERE id = :id");
$old->execute([':id' => $usuario_id]);
$oldPath = $old->fetchColumn();
if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
    @unlink(__DIR__ . '/../' . $oldPath);
}

$filename = 'avatar_' . $usuario_id . '.' . $exts[$mime];
$dest     = $dir . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar o arquivo.']); exit;
}

$relPath = 'uploads/avatares/' . $filename;
$pdo->prepare("UPDATE chat_users SET avatar = :av WHERE id = :id")
    ->execute([':av' => $relPath, ':id' => $usuario_id]);

$_SESSION['avatar'] = $relPath;

echo json_encode(['success' => true, 'avatar_url' => $relPath]);
