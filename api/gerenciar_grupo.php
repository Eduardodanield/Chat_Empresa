<?php
// ── NOVO: API para gerenciamento de membros de grupos (admin) ──
ob_start();
require_once '../conexao.php';
exigir_login();
exigir_admin();

header('Content-Type: application/json; charset=utf-8');

$d    = json_decode(file_get_contents('php://input'), true) ?? [];
$acao = $_GET['acao'] ?? $d['acao'] ?? '';

try {

    // ── Listar todos os grupos com contagem de membros ──
    if ($acao === 'listar') {
        $stmt = $pdo->query("
            SELECT g.id, g.nome, g.icone, g.fixo,
                   COUNT(m.usuario_id) AS total_membros
            FROM chat_grupos g
            LEFT JOIN chat_grupo_membros m ON m.grupo_id = g.id
            GROUP BY g.id
            ORDER BY g.fixo DESC, g.nome ASC
        ");
        echo json_encode(['success' => true, 'grupos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // ── Listar usuários com flag se são membros do grupo ──
    } elseif ($acao === 'membros') {
        $grupo_id = (int)($_GET['grupo_id'] ?? $d['grupo_id'] ?? 0);
        if (!$grupo_id) {
            echo json_encode(['success' => false, 'error' => 'ID de grupo inválido.']); exit;
        }
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome_completo, u.role,
                   IF(m.usuario_id IS NOT NULL, 1, 0) AS membro
            FROM chat_users u
            LEFT JOIN chat_grupo_membros m ON m.grupo_id = :gid AND m.usuario_id = u.id
            ORDER BY m.usuario_id IS NULL ASC, u.nome_completo ASC
        ");
        $stmt->execute([':gid' => $grupo_id]);
        echo json_encode(['success' => true, 'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    // ── Adicionar membro ao grupo ──
    } elseif ($acao === 'adicionar') {
        $grupo_id   = (int)($d['grupo_id']   ?? 0);
        $usuario_id = (int)($d['usuario_id'] ?? 0);
        if (!$grupo_id || !$usuario_id) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit;
        }
        $pdo->prepare("INSERT IGNORE INTO chat_grupo_membros (grupo_id, usuario_id) VALUES (?, ?)")
            ->execute([$grupo_id, $usuario_id]);
        echo json_encode(['success' => true]);

    // ── Remover membro do grupo ──
    } elseif ($acao === 'remover') {
        $grupo_id   = (int)($d['grupo_id']   ?? 0);
        $usuario_id = (int)($d['usuario_id'] ?? 0);
        if (!$grupo_id || !$usuario_id) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']); exit;
        }
        $grupo = $pdo->query("SELECT fixo FROM chat_grupos WHERE id = $grupo_id")->fetch();
        if ($grupo && $grupo['fixo']) {
            echo json_encode(['success' => false, 'error' => 'Não é possível remover membros do Grupo Geral.']); exit;
        }
        $pdo->prepare("DELETE FROM chat_grupo_membros WHERE grupo_id = ? AND usuario_id = ?")
            ->execute([$grupo_id, $usuario_id]);
        echo json_encode(['success' => true]);

    // ── Excluir grupo ──
    } elseif ($acao === 'excluir') {
        $grupo_id = (int)($d['grupo_id'] ?? 0);
        if (!$grupo_id) {
            echo json_encode(['success' => false, 'error' => 'ID de grupo inválido.']); exit;
        }
        $grupo = $pdo->query("SELECT fixo FROM chat_grupos WHERE id = $grupo_id")->fetch();
        if (!$grupo) {
            echo json_encode(['success' => false, 'error' => 'Grupo não encontrado.']); exit;
        }
        if ($grupo['fixo']) {
            echo json_encode(['success' => false, 'error' => 'Não é possível excluir o Grupo Geral.']); exit;
        }
        // Remove membros e mensagens do grupo, depois o grupo em si
        $pdo->exec("DELETE FROM chat_grupo_membros WHERE grupo_id = $grupo_id");
        $pdo->exec("DELETE FROM chat_mensagens WHERE grupo_id = $grupo_id");
        $pdo->exec("DELETE FROM chat_grupos WHERE id = $grupo_id");
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
