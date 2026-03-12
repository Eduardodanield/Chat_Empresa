<?php
require_once 'conexao.php';

// Marca usuário como offline
if (!empty($_SESSION['usuario_id'])) {
    $pdo->prepare("UPDATE chat_users SET online = 0, last_logout = NOW() WHERE id = :id")
        ->execute([':id' => $_SESSION['usuario_id']]);
}

// Destroi a sessão
session_destroy();

// Redireciona para o login
header('Location: login.php');
exit;