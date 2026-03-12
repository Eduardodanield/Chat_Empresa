-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 11/03/2026 às 02:14
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `chat_megaaxnen`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_grupos`
--

CREATE TABLE `chat_grupos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `icone` varchar(10) DEFAULT '?',
  `fixo` tinyint(1) DEFAULT 0,
  `criado_por` int(11) NOT NULL DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_grupos`
--

INSERT INTO `chat_grupos` (`id`, `nome`, `descricao`, `icone`, `fixo`, `criado_por`, `criado_em`) VALUES
(1, 'Geral', 'Canal geral da equipe', '📢', 1, 1, '2026-03-10 22:00:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_grupo_membros`
--

CREATE TABLE `chat_grupo_membros` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `entrou_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_grupo_membros`
--

INSERT INTO `chat_grupo_membros` (`id`, `grupo_id`, `usuario_id`, `entrou_em`) VALUES
(1, 1, 1, '2026-03-10 22:00:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_leituras`
--

CREATE TABLE `chat_leituras` (
  `id` int(11) NOT NULL,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `lido_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_mensagens`
--

CREATE TABLE `chat_mensagens` (
  `id` int(11) NOT NULL,
  `tipo_chat` enum('grupo','individual') NOT NULL DEFAULT 'individual',
  `grupo_id` int(11) DEFAULT NULL,
  `remetente_id` int(11) NOT NULL,
  `destinatario_id` int(11) DEFAULT NULL,
  `tipo_msg` enum('texto','foto','video','documento','sistema') DEFAULT 'texto',
  `conteudo` text DEFAULT NULL,
  `mensagem` text DEFAULT NULL,
  `arquivo_url` varchar(500) DEFAULT NULL,
  `arquivo` varchar(500) DEFAULT NULL,
  `arquivo_nome` varchar(255) DEFAULT NULL,
  `apagado_todos` tinyint(1) DEFAULT 0,
  `lido` tinyint(1) DEFAULT 0,
  `data_envio` datetime DEFAULT current_timestamp(),
  `enviado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_mensagens_apagadas`
--

CREATE TABLE `chat_mensagens_apagadas` (
  `id` int(11) NOT NULL,
  `mensagem_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `apagado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_tarefas`
--

CREATE TABLE `chat_tarefas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `criado_por` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `horario_limite` time NOT NULL,
  `data_tarefa` date DEFAULT NULL,
  `data_criacao` date DEFAULT NULL,
  `status` enum('pendente','confirmada','atrasada','cancelada') DEFAULT 'pendente',
  `horario_check` datetime DEFAULT NULL,
  `hora_confirmacao` datetime DEFAULT NULL,
  `alerta_enviado` tinyint(1) DEFAULT 0,
  `alertado` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_users`
--

CREATE TABLE `chat_users` (
  `id` int(11) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `role` enum('admin','funcionario') DEFAULT 'funcionario',
  `avatar` varchar(255) DEFAULT NULL,
  `online` tinyint(1) DEFAULT 0,
  `ultimo_acesso` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_users`
--

INSERT INTO `chat_users` (`id`, `nome_completo`, `username`, `password`, `cpf`, `role`, `avatar`, `online`, `ultimo_acesso`, `last_login`, `last_logout`, `criado_em`) VALUES
(1, 'Administrador', 'Administrador', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '12345678901', 'admin', NULL, 0, NULL, NULL, NULL, '2026-03-10 22:00:58');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `chat_grupos`
--
ALTER TABLE `chat_grupos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `chat_grupo_membros`
--
ALTER TABLE `chat_grupo_membros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_membro` (`grupo_id`,`usuario_id`);

--
-- Índices de tabela `chat_leituras`
--
ALTER TABLE `chat_leituras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_leitura` (`mensagem_id`,`usuario_id`);

--
-- Índices de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grupo` (`grupo_id`,`data_envio`),
  ADD KEY `idx_individual` (`remetente_id`,`destinatario_id`,`data_envio`);

--
-- Índices de tabela `chat_mensagens_apagadas`
--
ALTER TABLE `chat_mensagens_apagadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unico_apagada` (`mensagem_id`,`usuario_id`);

--
-- Índices de tabela `chat_tarefas`
--
ALTER TABLE `chat_tarefas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`,`data_tarefa`);

--
-- Índices de tabela `chat_users`
--
ALTER TABLE `chat_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `chat_grupos`
--
ALTER TABLE `chat_grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `chat_grupo_membros`
--
ALTER TABLE `chat_grupo_membros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `chat_leituras`
--
ALTER TABLE `chat_leituras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_mensagens_apagadas`
--
ALTER TABLE `chat_mensagens_apagadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_tarefas`
--
ALTER TABLE `chat_tarefas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chat_users`
--
ALTER TABLE `chat_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
