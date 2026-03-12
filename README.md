<div align="center">

# 💬 CHAT MEGA AXNEN
### Sistema Interno de Comunicação Empresarial

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![CSS3](https://img.shields.io/badge/CSS3-Variáveis_Dinâmicas-1572B6?style=for-the-badge&logo=css3&logoColor=white)

> Sistema de comunicação interna desenvolvido para a **Mega Axnen**, com chat em tempo real,  
> gestão de tarefas, grupos, notificações e painel administrativo completo.

---

</div>

## 📋 Índice

- [Visão Geral](#-visão-geral)
- [Funcionalidades](#-funcionalidades)
- [Arquitetura](#-arquitetura)
- [Estrutura de Pastas](#-estrutura-de-pastas)
- [Banco de Dados](#-banco-de-dados)
- [Fluxo do Sistema](#-fluxo-do-sistema)
- [Fluxo de Autenticação](#-fluxo-de-autenticação)
- [Fluxo de Mensagens](#-fluxo-de-mensagens)
- [Fluxo de Tarefas](#-fluxo-de-tarefas)
- [Temas Disponíveis](#-temas-disponíveis)
- [APIs](#-apis-endpoints)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Roles e Permissões](#-roles-e-permissões)

---

## 🌐 Visão Geral

```
┌─────────────────────────────────────────────────────────────────┐
│                        CHAT MEGA AXNEN                          │
│                                                                 │
│   ┌──────────────┐    ┌──────────────────┐    ┌─────────────┐  │
│   │   SIDEBAR    │    │    CHAT CENTRAL  │    │   TAREFAS   │  │
│   │              │    │                  │    │             │  │
│   │ 👤 DMs       │    │  💬 Mensagens    │    │ ✅ Pendente  │  │
│   │ 🔔 Badges    │    │  📎 Arquivos     │    │ ✅ Feitas    │  │
│   │ 🔍 Busca     │    │  😊 Emojis       │    │ ⏰ Atraso   │  │
│   │              │    │  ✔✔ Ticks        │    │             │  │
│   └──────────────┘    └──────────────────┘    └─────────────┘  │
│                                                                 │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │  📢 BARRA DE GRUPOS (horizontal, scroll)               │   │
│   │   [Geral] [Fotoso] [Vídeo] [+ outros grupos...]        │   │
│   └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✨ Funcionalidades

| Módulo | Funcionalidades |
|--------|----------------|
| 💬 **Chat** | Mensagens em tempo real, ticks de leitura (✔✔), arquivos, emojis, encaminhar, apagar |
| 👥 **Grupos** | Criar grupos, gerenciar membros, mute, barra visual inferior |
| ✅ **Tarefas** | Criar, atribuir, concluir, alertas sonoros com 1h e 30min de antecedência |
| 🔔 **Notificações** | Toast estilo WhatsApp no canto da tela, som de nova mensagem |
| 👤 **Perfil** | Foto de perfil, status com emoji (✍️ Trabalhando, ☕ Café, 🍽️ Almoço...) |
| 🎨 **Temas** | 9 temas visuais + color picker personalizado |
| 🔐 **Admin** | Criar/editar/excluir usuários, gerenciar grupos, relatório de atividades |
| 📊 **Relatório** | Histórico de tarefas filtrado por período e funcionário |

---

## 🏗 Arquitetura

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENTE (Navegador)                        │
│                                                                     │
│   index.php (SPA)                                                   │
│   ┌──────────────────────────────────────────────────────────────┐  │
│   │  front/style.css  │  front/chat.js  │  front/tarefas.js     │  │
│   │  (variáveis CSS)  │  (~1100 linhas) │  (~600 linhas)        │  │
│   └──────────────────────────────────────────────────────────────┘  │
│                              │ fetch() + setInterval                │
└──────────────────────────────┼──────────────────────────────────────┘
                               │ HTTP (JSON)
┌──────────────────────────────┼──────────────────────────────────────┐
│                          SERVIDOR PHP                               │
│                                                                     │
│   conexao.php (PDO + sessão)                                        │
│   ┌──────────────────────────────────────────────────────────────┐  │
│   │  api/buscar_mensagens    │  api/enviar_mensagem              │  │
│   │  api/buscar_contatos     │  api/buscar_tarefas               │  │
│   │  api/confirmar_tarefa    │  api/criar_usuario                │  │
│   │  api/upload_avatar       │  api/atualizar_status             │  │
│   │  api/gerenciar_grupo     │  api/relatorio_atividades         │  │
│   └──────────────────────────────────────────────────────────────┘  │
│                              │ PDO (prepared statements)            │
└──────────────────────────────┼──────────────────────────────────────┘
                               │
┌──────────────────────────────┼──────────────────────────────────────┐
│                           MySQL                                     │
│                                                                     │
│   chat_users │ chat_mensagens │ chat_leituras │ chat_tarefas        │
│   chat_grupos │ chat_grupo_membros │ chat_mensagens_apagadas        │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📁 Estrutura de Pastas

```
chat_megaaxnen/
│
├── 📄 index.php                  ← SPA principal (toda a UI renderizada aqui)
├── 📄 login.php                  ← Tela de autenticação
├── 📄 logout.php                 ← Destroi sessão e redireciona
├── 📄 conexao.php                ← PDO + funções de sessão (núcleo)
├── 📄 CLAUDE.md                  ← Documentação para o Claude Code
├── 📄 README.md                  ← Este arquivo
│
├── 📁 front/
│   ├── 🎨 style.css              ← Todo o CSS (variáveis + 9 temas)
│   ├── ⚡ chat.js                ← Mensagens, sidebar, admin, emojis
│   └── ⚡ tarefas.js             ← Painel de tarefas completo
│
├── 📁 api/
│   ├── buscar_contatos.php       ← DMs + grupos com badges
│   ├── buscar_mensagens.php      ← Polling de mensagens
│   ├── enviar_mensagem.php       ← Envio de texto e arquivos
│   ├── buscar_tarefas.php        ← Lista com filtros e totais
│   ├── confirmar_tarefa.php      ← Ações de status (com permissão)
│   ├── criar_tarefa.php          ← Nova tarefa
│   ├── criar_usuario.php         ← Admin cria funcionário
│   ├── editar_usuario.php        ← Admin edita dados
│   ├── excluir_usuario.php       ← Admin remove (soft-delete)
│   ├── criar_grupo.php           ← Admin cria grupo
│   ├── gerenciar_grupo.php       ← Editar/membros/excluir grupo
│   ├── upload_avatar.php         ← Admin sobe foto de qualquer usuário
│   ├── upload_avatar_proprio.php ← Funcionário troca própria foto
│   ├── atualizar_status.php      ← Status com emoji
│   ├── marcar_lido.php           ← Registra leitura (ticks)
│   ├── deletar_mensagem.php      ← Apagar (pra mim / pra todos)
│   ├── encaminhar_mensagem.php   ← Encaminhar mensagem
│   └── relatorio_atividades.php  ← Relatório PDF/tabela
│
└── 📁 uploads/
    └── avatares/                 ← Fotos de perfil (avatar_ID.jpg)
```

---

## 🗄 Banco de Dados

```
┌─────────────────────────────────────────────────────────────────────┐
│                         DIAGRAMA DE TABELAS                        │
└─────────────────────────────────────────────────────────────────────┘

  chat_users                      chat_grupos
  ┌──────────────────┐            ┌─────────────────┐
  │ id (PK)          │            │ id (PK)         │
  │ nome_completo    │            │ nome            │
  │ username         │            │ icone           │
  │ password (bcrypt)│            │ fixo (0/1)      │
  │ cpf              │            │ criado_por (FK) │──┐
  │ role             │            │ criado_em       │  │
  │ avatar           │            └─────────────────┘  │
  │ cargo            │                  │               │
  │ online           │     chat_grupo_membros           │
  │ status_emoji     │     ┌─────────────────────┐     │
  │ status_atual     │     │ grupo_id (FK)        │     │
  │ criado_em        │◄────│ usuario_id (FK)      │     │
  └──────────────────┘     │ entrou_em            │     │
          │                └─────────────────────┘     │
          │                                             │
          │         chat_mensagens                      │
          │         ┌────────────────────┐             │
          └────────►│ id (PK)            │◄────────────┘
                    │ remetente_id (FK)  │
                    │ destinatario_id    │
                    │ grupo_id (FK)      │
                    │ tipo_chat          │──► 'individual' | 'grupo'
                    │ tipo_msg           │──► 'texto' | 'foto' | ...
                    │ conteudo           │
                    │ arquivo_url        │
                    │ enviado_em         │
                    │ apagado_todos      │
                    └────────────────────┘
                             │
              ┌──────────────┴──────────────┐
              ▼                             ▼
   chat_leituras                chat_mensagens_apagadas
   ┌──────────────────┐         ┌──────────────────────┐
   │ mensagem_id (FK) │         │ mensagem_id (FK)     │
   │ usuario_id (FK)  │         │ usuario_id (FK)      │
   │ lido_em          │         │ apagado_em           │
   └──────────────────┘         └──────────────────────┘

  chat_tarefas
  ┌─────────────────────┐
  │ id (PK)             │
  │ usuario_id (FK)     │──► quem vai realizar
  │ criado_por (FK)     │──► quem criou (admin)
  │ titulo              │
  │ descricao           │
  │ status              │──► pendente | confirmada | atrasada | cancelada
  │ data_tarefa         │
  │ horario_limite      │
  │ horario_check       │
  │ alerta_enviado      │
  │ data_criacao        │
  └─────────────────────┘
```

---

## 🔄 Fluxo do Sistema

```
Usuário acessa URL
       │
       ▼
  ┌────────────┐    NÃO     ┌────────────┐
  │ Tem sessão?│──────────► │ login.php  │
  └────────────┘            └─────┬──────┘
       │ SIM                      │ autenticado
       │         ◄────────────────┘
       ▼
  ┌────────────────────────────────────────┐
  │              index.php                 │
  │                                        │
  │  PHP renderiza:                        │
  │  • Contatos da sidebar                 │
  │  • Grupos da barra inferior            │
  │  • Dados do usuário logado (EU)        │
  └────────────────────────────────────────┘
       │
       ▼
  ┌────────────────────────────────────────┐
  │         JavaScript inicia              │
  │                                        │
  │  setInterval(carregarBadges, 5000)     │──► badges + previews
  │  setInterval(carregarTarefas, 30000)   │──► alertas de prazo
  └────────────────────────────────────────┘
       │
       ▼ (usuário clica em contato ou grupo)
  ┌────────────────────────────────────────┐
  │         abrirChat(tipo, id, nome)      │
  │                                        │
  │  setInterval(carregarMensagens, 3000)  │──► polling ativo
  └────────────────────────────────────────┘
```

---

## 🔐 Fluxo de Autenticação

```
    [login.php]
         │
         │  POST: nome + CPF
         ▼
  ┌─────────────────────────┐
  │  Busca usuário por nome │
  │  password_verify (bcrypt)│
  └──────────┬──────────────┘
             │
       ┌─────┴─────┐
       │           │
    VÁLIDO      INVÁLIDO
       │           │
       ▼           ▼
  $_SESSION       Contador tentativas
  [usuario_id]    (máx 5 → bloqueio 15min)
  [role]
  [avatar]
       │
       ▼
  index.php

  ────────────────────────────────
  Roles e acessos:

  role = 'admin'
    ✔ Cria/edita/exclui usuários
    ✔ Cria/gerencia grupos
    ✔ Cria tarefas para qualquer funcionário
    ✔ Vê relatório de todos
    ✔ Faz upload de avatar de qualquer um
    ✗ Não pode concluir tarefas de outros

  role = 'funcionario'
    ✔ Envia mensagens (DM e grupos)
    ✔ Conclui as próprias tarefas
    ✔ Troca própria foto e status
    ✗ Não acessa painel admin
```

---

## 💬 Fluxo de Mensagens

```
Usuário digita mensagem + clica enviar
              │
              ▼
    enviar_mensagem.php (POST)
              │
    ┌─────────┴──────────┐
    │ Insere em           │
    │ chat_mensagens      │
    └─────────────────────┘
              │
              │  (outros usuários fazem polling a cada 3s)
              ▼
    buscar_mensagens.php (GET)
              │
    ┌─────────┴──────────────────────────────────┐
    │ Retorna mensagens + registra em            │
    │ chat_leituras (lido_em = NOW())            │
    └─────────────────────────────────────────────┘
              │
              ▼
    renderizarMensagens()
              │
    ┌─────────┴──────────────────────┐
    │ ✔  = enviada (1 leitura)       │
    │ ✔✔ = lida (todos leram)        │
    │ ✔✔ azul = confirmado           │
    └─────────────────────────────────┘
              │
              ▼ (se destinatário não está no chat)
    Notificação toast (canto inferior direito)
    + som de bipe (Web Audio API)
```

---

## ✅ Fluxo de Tarefas

```
ADMIN                              FUNCIONÁRIO
  │                                     │
  │  Abre modal Nova Tarefa             │
  │  Preenche: título, data,            │
  │  horário, seleciona funcionário     │
  │                                     │
  ▼                                     │
criar_tarefa.php                        │
  │                                     │
  │  Insere em chat_tarefas             │
  │  status = 'pendente'                │
  └─────────────────────────────────────┤
                                        │
                              carregarTarefas() a cada 30s
                                        │
                              ┌─────────┴──────────┐
                              │  Verifica prazo     │
                              │  ≤ 1h → alerta      │
                              │  ≤ 30min → alerta   │
                              │  + som 3 beeps      │
                              └─────────────────────┘
                                        │
                              Funcionário clica ✔ Concluir
                                        │
                              confirmar_tarefa.php
                              (verifica usuario_id === sessão)
                                        │
                              status = 'confirmada'
```

---

## 🎨 Temas Disponíveis

| # | Nome | Fundo Principal | Destaque |
|---|------|----------------|---------|
| 1 | ☁️ Padrão (Névoa) | `#dde3f0` | Verde `#059669` |
| 2 | 🌙 Noite | `#0f172a` | Verde `#059669` |
| 3 | 🌊 Azul Mega | `#1e3a5f` | Azul `#2563eb` |
| 4 | ☀️ Céu | `#dbeafe` | Azul `#1d4ed8` |
| 5 | 🌿 Floresta | `#f0fdf4` | Verde `#15803d` |
| 6 | 🫐 Roxo | `#faf5ff` | Roxo `#7c3aed` |
| 7 | 🏢 Cinza Pro | `#f8fafc` | Cinza `#475569` |
| 8 | 🌸 Rosa | `#fff1f2` | Rosa `#e11d48` |
| 9 | 🍂 Creme | `#faf7f2` | Bege `#92400e` |
| 🎨 | Personalizado | Escolha livre | Color picker |

---

## 🔌 APIs — Endpoints

| Arquivo | Método | Auth | Descrição |
|---------|--------|------|-----------|
| `buscar_contatos.php` | GET | funcionario | DMs + grupos com badges e preview |
| `buscar_mensagens.php` | GET | funcionario | Mensagens da conversa + marca lido |
| `enviar_mensagem.php` | POST | funcionario | Envia texto ou arquivo |
| `deletar_mensagem.php` | POST | funcionario | Apaga (pra mim ou pra todos) |
| `encaminhar_mensagem.php` | POST | funcionario | Encaminha para outro destino |
| `marcar_lido.php` | POST | funcionario | Registra leitura manual |
| `buscar_tarefas.php` | GET | funcionario | Lista tarefas com filtros e totais |
| `criar_tarefa.php` | POST | funcionario | Cria nova tarefa |
| `confirmar_tarefa.php` | POST | funcionario | Conclui/cancela tarefa (com permissão) |
| `criar_usuario.php` | POST | **admin** | Cria novo funcionário |
| `editar_usuario.php` | POST | **admin** | Edita dados do usuário |
| `excluir_usuario.php` | POST | **admin** | Remove usuário (soft-delete) |
| `criar_grupo.php` | POST | **admin** | Cria novo grupo |
| `gerenciar_grupo.php` | GET/POST | **admin** | Editar, membros, excluir grupo |
| `upload_avatar.php` | POST | **admin** | Foto de qualquer usuário |
| `upload_avatar_proprio.php` | POST | funcionario | Funcionário troca própria foto |
| `atualizar_status.php` | POST | funcionario | Define status + emoji |
| `relatorio_atividades.php` | GET | **admin** | Relatório por período/funcionário |

---

## ⚙️ Instalação

### Pré-requisitos
- XAMPP (PHP 8.0+ e MySQL 8.0+)
- Navegador moderno

### Passo a passo

```bash
# 1. Clone ou copie a pasta para:
C:\xampp\htdocs\chat_megaaxnen\

# 2. Inicie o XAMPP (Apache + MySQL)

# 3. Acesse o phpMyAdmin:
http://localhost/phpmyadmin

# 4. Crie o banco:
CREATE DATABASE chat_megaaxnen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 5. Execute o script de criação:
# Importe o arquivo db_completo.sql pelo phpMyAdmin

# 6. Acesse o sistema:
http://localhost/chat_megaaxnen/login.php
```

---

## ⚙️ Configuração

### `conexao.php` — Ambiente local (XAMPP)
```php
$host = 'localhost';
$db   = 'chat_megaaxnen';
$user = 'root';
$pass = '';              // padrão XAMPP
```

### `conexao.php` — Produção (KingHost)
```php
$host = 'mysql.megaaxnen.com.br';
$db   = 'megaaxnen';
$user = 'megaaxnen';
$pass = 'sua_senha';
```

### Login padrão após instalação
```
Usuário: Administrador
Senha:   12345678901
```
> ⚠️ Troque a senha após o primeiro acesso!

---

## 👥 Roles e Permissões

```
┌─────────────────────────────────────────────────────────┐
│                    PERMISSÕES POR ROLE                  │
├─────────────────────────────┬──────────┬───────────────┤
│ Ação                        │  Admin   │ Funcionário   │
├─────────────────────────────┼──────────┼───────────────┤
│ Enviar mensagens            │    ✅    │      ✅       │
│ Criar grupos                │    ✅    │      ❌       │
│ Criar funcionários          │    ✅    │      ❌       │
│ Editar/excluir usuários     │    ✅    │      ❌       │
│ Ver tarefas de todos        │    ✅    │      ❌       │
│ Criar tarefas para outros   │    ✅    │      ❌       │
│ Concluir tarefa de outro    │    ❌    │      ❌       │
│ Concluir própria tarefa     │    ❌    │      ✅       │
│ Trocar própria foto         │    ✅    │      ✅       │
│ Trocar foto de outro        │    ✅    │      ❌       │
│ Definir próprio status      │    ✅    │      ✅       │
│ Ver relatório de atividades │    ✅    │      ❌       │
│ Gerenciar grupos            │    ✅    │      ❌       │
└─────────────────────────────┴──────────┴───────────────┘
```

---

<div align="center">

**Desenvolvido para uso interno — Mega Axnen © 2026**

*Sistema construído com PHP puro, sem frameworks, pensado para  
ser simples de manter e rápido de usar em ambiente empresarial.*

</div>
