<?php
// CORRIGIDO: arquivo reescrito em UTF-8 (era Latin-1, letras ficavam "esticadas"/garbled)
require_once 'conexao.php';
exigir_login();
$eu = usuario_logado();

$eu_nome = $eu['nome_completo'] ?? $eu['username'] ?? $eu['nome'] ?? 'Usuário';

// Migration: colunas de status (silencia erro se já existirem)
try { $pdo->exec("ALTER TABLE chat_users ADD COLUMN status_atual VARCHAR(100) DEFAULT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE chat_users ADD COLUMN status_emoji VARCHAR(10) DEFAULT NULL");  } catch(Exception $e) {}

// Status do próprio usuário logado
$stmtSelf = $pdo->prepare("SELECT status_atual, status_emoji FROM chat_users WHERE id = :id");
$stmtSelf->execute([':id' => $eu['id']]);
$euStatusRow    = $stmtSelf->fetch(PDO::FETCH_ASSOC);
$eu_status_emoji = $euStatusRow['status_emoji'] ?: '🟢';
$eu_status_atual = $euStatusRow['status_atual']  ?: 'Disponível';

// Busca todos os usuários exceto eu (com status)
$stmt = $pdo->prepare("SELECT id, nome_completo, username, avatar, online, status_atual, status_emoji FROM chat_users WHERE id != :id ORDER BY online DESC, nome_completo ASC");
$stmt->execute([':id' => $eu['id']]);
$contatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca grupos que faço parte
$stmt = $pdo->prepare("
    SELECT g.* FROM chat_grupos g
    INNER JOIN chat_grupo_membros m ON m.grupo_id = g.id
    WHERE m.usuario_id = :uid
    ORDER BY g.fixo DESC, g.nome ASC
");
$stmt->execute([':uid' => $eu['id']]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grupo_geral   = null;
$outros_grupos = [];
foreach ($grupos as $g) {
    if ($g['fixo']) $grupo_geral = $g;
    else            $outros_grupos[] = $g;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Chat — Mega Axnen</title>
  <link rel="stylesheet" href="front/style.css?v=<?= filemtime(__DIR__.'/front/style.css') ?>"/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
</head>
<body>

<header class="header">
  <div class="header-left">
    <button class="btn-sidebar-toggle" onclick="toggleSidebar()">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
    <img src="assets/logo.png" alt="Mega Axnen" class="header-logo"
         onerror="this.style.display='none'"/>
    <span id="hbn" class="header-brand">MEGA AXNEN</span>
    <span class="header-title">Chat</span>
  </div>

  <div class="header-center">
    <div class="search-wrap">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="inputBusca" class="search-input" placeholder="Buscar mensagens..." oninput="buscarMensagens(this.value)"/>
    </div>
  </div>

  <div class="header-right">
    <?php if ($eu['role'] === 'admin'): ?>
    <button class="btn-header-acao" onclick="abrirModalAdmin('usuarios')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/>
      </svg>
      <!-- CORRIGIDO: UTF-8 correto -->
      Add Funcionário
    </button>
    <button class="btn-header-acao btn-header-verde" onclick="abrirModal('modalGrupo')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        <line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/>
      </svg>
      Criar Grupo
    </button>
    <?php endif; ?>

    <!-- ══ Botão + Painel de Temas ══ -->
    <div style="position:relative;">
      <button class="btn-tema-toggle" id="btnTemas" onclick="togglePainelTemas()" title="Temas da interface">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="13.5" cy="6.5" r="2"/><circle cx="19" cy="12" r="2"/>
          <circle cx="8"    cy="7"   r="2"/><circle cx="5"  cy="13" r="2"/>
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8z"/>
        </svg>
      </button>

      <div class="painel-temas" id="painelTemas">
        <!-- ── Coluna esquerda: Temas ── -->
        <div class="painel-col-temas">
          <div class="painel-temas-titulo">Tema da interface</div>
          <div class="temas-grid">
            <button class="tema-swatch" data-tema="" onclick="aplicarTema('')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#f5f6fa;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#eef0f5,#ede8e0);"></div></div>
              <span class="tema-swatch-nome">Padrão</span>
            </button>
            <button class="tema-swatch" data-tema="azul-mega" onclick="aplicarTema('azul-mega')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#1e3060;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#1a2744,#162240);"></div></div>
              <span class="tema-swatch-nome">Azul Mega</span>
            </button>
            <button class="tema-swatch" data-tema="noite" onclick="aplicarTema('noite')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#161b27;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#0d1117,#161b27);"></div></div>
              <span class="tema-swatch-nome">Noite</span>
            </button>
            <button class="tema-swatch" data-tema="ceu" onclick="aplicarTema('ceu')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#eff6ff;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);"></div></div>
              <span class="tema-swatch-nome">Céu</span>
            </button>
            <button class="tema-swatch" data-tema="creme" onclick="aplicarTema('creme')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#fef9f2;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#fdf6e8,#faf0d8);"></div></div>
              <span class="tema-swatch-nome">Creme</span>
            </button>
            <button class="tema-swatch" data-tema="floresta" onclick="aplicarTema('floresta')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#f7fdf9;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);"></div></div>
              <span class="tema-swatch-nome">Floresta</span>
            </button>
            <button class="tema-swatch" data-tema="roxo" onclick="aplicarTema('roxo')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#f9f7ff;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#f4f0ff,#ede9fe);"></div></div>
              <span class="tema-swatch-nome">Roxo Suave</span>
            </button>
            <button class="tema-swatch" data-tema="cinza" onclick="aplicarTema('cinza')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#f8fafc;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#f1f5f9,#e2e8f0);"></div></div>
              <span class="tema-swatch-nome">Corporativo</span>
            </button>
            <button class="tema-swatch" data-tema="rosa" onclick="aplicarTema('rosa')">
              <div class="tema-preview"><div class="tema-preview-sidebar" style="background:#fff5f7;"></div><div class="tema-preview-body" style="background:linear-gradient(135deg,#fff1f2,#fecdd3);"></div></div>
              <span class="tema-swatch-nome">Rosa Claro</span>
            </button>
          </div>
        </div>
        <!-- ── Divisor vertical ── -->
        <div class="painel-divider-v"></div>
        <!-- ── Coluna direita: Cores + Picker ── -->
        <div class="painel-col-cores">
          <div class="painel-temas-titulo">Paleta de cores</div>
          <div class="temas-grid temas-grid-cores">
            <button class="tema-swatch tema-cor" title="Vermelho"    onclick="aplicarCorCustom('#ef4444')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fca5a5,#ef4444);"></div></button>
            <button class="tema-swatch tema-cor" title="Laranja"     onclick="aplicarCorCustom('#f97316')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fdba74,#f97316);"></div></button>
            <button class="tema-swatch tema-cor" title="Âmbar"       onclick="aplicarCorCustom('#f59e0b')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fcd34d,#f59e0b);"></div></button>
            <button class="tema-swatch tema-cor" title="Amarelo"     onclick="aplicarCorCustom('#eab308')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fde047,#eab308);"></div></button>
            <button class="tema-swatch tema-cor" title="Lima"        onclick="aplicarCorCustom('#84cc16')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#bef264,#84cc16);"></div></button>
            <button class="tema-swatch tema-cor" title="Verde"       onclick="aplicarCorCustom('#22c55e')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#86efac,#22c55e);"></div></button>
            <button class="tema-swatch tema-cor" title="Esmeralda"   onclick="aplicarCorCustom('#10b981')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#6ee7b7,#10b981);"></div></button>
            <button class="tema-swatch tema-cor" title="Turquesa"    onclick="aplicarCorCustom('#14b8a6')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#5eead4,#14b8a6);"></div></button>
            <button class="tema-swatch tema-cor" title="Ciano"       onclick="aplicarCorCustom('#06b6d4')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#67e8f9,#06b6d4);"></div></button>
            <button class="tema-swatch tema-cor" title="Céu"         onclick="aplicarCorCustom('#38bdf8')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#bae6fd,#38bdf8);"></div></button>
            <button class="tema-swatch tema-cor" title="Azul"        onclick="aplicarCorCustom('#3b82f6')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#93c5fd,#3b82f6);"></div></button>
            <button class="tema-swatch tema-cor" title="Azul Escuro" onclick="aplicarCorCustom('#1d4ed8')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#93c5fd,#1d4ed8);"></div></button>
            <button class="tema-swatch tema-cor" title="Anil"        onclick="aplicarCorCustom('#6366f1')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#a5b4fc,#6366f1);"></div></button>
            <button class="tema-swatch tema-cor" title="Violeta"     onclick="aplicarCorCustom('#8b5cf6')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#c4b5fd,#8b5cf6);"></div></button>
            <button class="tema-swatch tema-cor" title="Roxo"        onclick="aplicarCorCustom('#a855f7')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#d8b4fe,#a855f7);"></div></button>
            <button class="tema-swatch tema-cor" title="Fúcsia"      onclick="aplicarCorCustom('#d946ef')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#f0abfc,#d946ef);"></div></button>
            <button class="tema-swatch tema-cor" title="Rosa"        onclick="aplicarCorCustom('#ec4899')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#f9a8d4,#ec4899);"></div></button>
            <button class="tema-swatch tema-cor" title="Rosa Quente" onclick="aplicarCorCustom('#f43f5e')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fda4af,#f43f5e);"></div></button>
            <button class="tema-swatch tema-cor" title="Coral"       onclick="aplicarCorCustom('#fb7185')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#fecdd3,#fb7185);"></div></button>
            <button class="tema-swatch tema-cor" title="Lavanda"     onclick="aplicarCorCustom('#c084fc')"><div class="tema-cor-preview" style="background:linear-gradient(135deg,#e9d5ff,#c084fc);"></div></button>
          </div>
          <div class="painel-sep"></div>
          <div class="cor-custom-label">Cor personalizada</div>
          <div class="cor-custom-row">
            <input type="color" id="colorPickerInput" value="#eef0f5" oninput="sincronizarColorPicker(this.value)"/>
            <input type="text"  id="colorPickerHex" class="cor-custom-hex" placeholder="#eef0f5" maxlength="7" oninput="validarHexInput(this.value)"/>
            <button class="btn-aplicar-cor" onclick="aplicarCorDoInput()">OK</button>
          </div>
        </div>
      </div>
    </div>
    <!-- ══ Fim Painel Temas ══ -->

    <div class="perfil-btn" onclick="toggleMenuPerfil()" id="perfilBtn">
      <?php if ($eu['avatar']): ?>
        <img src="<?= htmlspecialchars($eu['avatar']) ?>" class="avatar-sm" alt=""/>
      <?php else: ?>
        <div class="avatar-sm avatar-letra"><?= strtoupper(substr($eu_nome, 0, 1)) ?></div>
      <?php endif; ?>
      <span class="perfil-nome"><?= htmlspecialchars($eu_nome) ?></span>
      <span id="headerStatusEmoji" style="font-size:14px;line-height:1;" title="<?= htmlspecialchars($eu_status_atual) ?>"><?= $eu_status_emoji ?></span>
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
    </div>

    <div class="menu-perfil" id="menuPerfil">
      <div class="menu-perfil-header">
        <?php if ($eu['avatar']): ?>
          <img src="<?= htmlspecialchars($eu['avatar']) ?>" class="avatar-md" alt=""/>
        <?php else: ?>
          <div class="avatar-md avatar-letra"><?= strtoupper(substr($eu_nome, 0, 1)) ?></div>
        <?php endif; ?>
        <div>
          <div class="mp-nome"><?= htmlspecialchars($eu_nome) ?></div>
          <!-- CORRIGIDO: UTF-8 correto -->
          <div class="mp-role"><?= $eu['role'] === 'admin' ? '⭐ Admin' : '👤 Funcionário' ?></div>
        </div>
      </div>


      <!-- Meu Status -->
      <button onclick="toggleStatusSubmenu(event)" class="menu-perfil-item" style="background:none;border:none;cursor:pointer;width:100%;text-align:left;" id="btnMeuStatus">
        <span id="menuStatusEmoji" style="font-size:15px;line-height:1;"><?= $eu_status_emoji ?></span>
        <span>Meu status</span>
        <svg style="margin-left:auto;" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></svg>
      </button>
      <div id="statusSubmenu" style="display:none;flex-direction:column;padding:4px 8px 6px 16px;gap:2px;">
        <?php
        $statusOpcoes = [
            ['🟢','Disponível'],['💻','Trabalhando'],['📹','Em reunião'],
            ['🍽️','No almoço'],['☕','No café'],['⏸️','Em pausa'],['🚪','Saí mais cedo'],
        ];
        foreach ($statusOpcoes as [$emoji, $texto]): ?>
        <button onclick="selecionarStatus('<?= $emoji ?>','<?= $texto ?>')"
          style="background:none;border:none;cursor:pointer;text-align:left;padding:5px 8px;border-radius:7px;
                 font-size:13px;color:var(--texto);display:flex;align-items:center;gap:8px;transition:background .15s;"
          onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='none'">
          <span style="font-size:16px;"><?= $emoji ?></span><?= htmlspecialchars($texto) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <div style="height:1px;background:var(--borda);margin:2px 12px 4px;"></div>
      <input type="file" id="inputAvatarProprio" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="uploadAvatarProprio(this)"/>
      <button onclick="document.getElementById('inputAvatarProprio').click();document.getElementById('menuPerfil').classList.remove('show');" class="menu-perfil-item" style="background:none;border:none;cursor:pointer;width:100%;text-align:left;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
        Trocar foto de perfil
      </button>
      <button onclick="abrirModalRelatorio()" class="menu-perfil-item" style="background:none;border:none;cursor:pointer;width:100%;text-align:left;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/>
          <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>
        </svg>
        Histórico de Atividades
      </button>
      <button onclick="abrirModalConfiguracoes()" class="menu-perfil-item" style="background:none;border:none;cursor:pointer;width:100%;text-align:left;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06-.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        Configurações
      </button>
      <div style="height:1px;background:var(--borda);margin:2px 12px;"></div>
      <a href="logout.php" class="menu-perfil-item mp-sair">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sair
      </a>
    </div>
  </div>
</header>

<div class="chat-wrapper">
<div class="chat-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-section">
      <div class="sidebar-section-title">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        Mensagens Diretas
      </div>
      <div class="contatos-lista" id="listaContatos">
        <?php foreach ($contatos as $c):
              $c_nome = $c['nome_completo'] ?? $c['username']; ?>
        <div class="contato-item" id="dm-<?= $c['id'] ?>"
             onclick="abrirChat('individual', <?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c_nome)) ?>', '<?= htmlspecialchars(addslashes($c['avatar'] ?? '')) ?>')">
          <div class="avatar-wrap"
               data-ttuid="<?= $c['id'] ?>"
               data-ttnome="<?= htmlspecialchars($c_nome) ?>"
               data-ttstatus="<?= htmlspecialchars($c['status_atual'] ?? '') ?>"
               data-ttemoji="<?= htmlspecialchars($c['status_emoji'] ?? '') ?>">
            <?php if ($c['avatar']): ?>
              <img src="<?= htmlspecialchars($c['avatar']) ?>" class="avatar-sm" alt=""/>
            <?php else: ?>
              <div class="avatar-sm avatar-letra"><?= strtoupper(substr($c_nome, 0, 1)) ?></div>
            <?php endif; ?>
            <span class="status-dot <?= $c['online'] ? 'online' : 'offline' ?>"></span>
          </div>
          <div class="contato-info">
            <span class="contato-nome"><?= htmlspecialchars($c_nome) ?></span>
            <span class="contato-preview" id="preview-dm-<?= $c['id'] ?>">...</span>
          </div>
          <span class="badge-nao-lidas" id="badge-dm-<?= $c['id'] ?>" style="display:none;"></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </aside>

  <!-- CHAT PRINCIPAL -->
  <main class="chat-main" id="chatMain">
    <div class="chat-vazio" id="chatVazio">
      <div class="chat-vazio-icon">💬</div>
      <!-- CORRIGIDO: UTF-8 correto -->
      <h3>Bem-vindo, <?= htmlspecialchars($eu_nome) ?>!</h3>
      <p>Selecione uma conversa ou grupo para começar.</p>
    </div>

    <div class="chat-ativo" id="chatAtivo" style="display:none;flex-direction:column;">
      <div class="chat-cabecalho">
        <div class="chat-cab-info">
          <div class="avatar-sm avatar-letra" id="chatAvatar">?</div>
          <div>
            <div class="chat-cab-nome" id="chatNome">-</div>
            <div class="chat-cab-status" id="chatSub">-</div>
          </div>
        </div>
        <div class="chat-cab-acoes">
          <?php if ($eu['role'] === 'admin'): ?>
          <!-- ── NOVO: botão Gerenciar Grupo (só admin, só quando em grupo) ── -->
          <button class="btn-cab" id="btnGerenciarGrupo" onclick="abrirGerenciarGrupo()" title="Gerenciar membros do grupo" style="display:none;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
              <line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/>
            </svg>
          </button>
          <?php endif; ?>
          <button class="btn-cab" onclick="document.getElementById('inputBusca').focus()" title="Buscar">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="mensagens-area" id="mensagensArea"></div>

      <div class="chat-input-area" style="position:relative;">
        <div class="arquivo-preview" id="uploadPreview" style="display:none;align-items:center;gap:10px;padding:8px 12px;background:var(--superficie-2);border-radius:8px;margin-bottom:8px;">
          <span id="previewNome" style="flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          <button onclick="removerArquivo()" style="background:none;border:none;color:var(--texto-muted);cursor:pointer;font-size:16px;">✕</button>
        </div>
        <!-- Painel de emojis -->
        <div id="painelEmoji" style="display:none;position:absolute;bottom:calc(100% + 6px);left:0;right:0;z-index:200;
          background:var(--surface-1);border:1.5px solid var(--borda-2);border-radius:14px;
          box-shadow:0 8px 32px rgba(0,0,0,0.15);padding:10px;max-height:280px;flex-direction:column;gap:8px;">
          <input id="emojiSearch" type="text" placeholder="Buscar emoji..." oninput="filtrarEmojis(this.value)"
            style="width:100%;padding:7px 12px;border-radius:8px;border:1.5px solid var(--borda-2);
            background:var(--surface-2);color:var(--texto);font-size:13px;outline:none;"/>
          <div id="emojiGrid" style="overflow-y:auto;max-height:210px;display:flex;flex-wrap:wrap;gap:2px;"></div>
        </div>
        <div class="input-row">
          <div class="anexo-btns">
            <button class="btn-anexo" onclick="togglePainelEmoji()" title="Emojis" id="btnEmoji">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
              </svg>
            </button>
            <button class="btn-anexo" onclick="document.getElementById('inputFoto').click()" title="Foto">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/>
              </svg>
            </button>
            <!-- CORRIGIDO: UTF-8 correto no title -->
            <button class="btn-anexo" onclick="document.getElementById('inputVideo').click()" title="Vídeo">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polygon points="23,7 16,12 23,17"/><rect x="1" y="5" width="15" height="14" rx="2"/>
              </svg>
            </button>
            <button class="btn-anexo" onclick="document.getElementById('inputDoc').click()" title="Documento">
              <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/>
              </svg>
            </button>
          </div>
          <input type="file" id="inputFoto"    accept="image/*"  style="display:none" onchange="selecionarArquivo(this)"/>
          <input type="file" id="inputVideo"   accept="video/*"  style="display:none" onchange="selecionarArquivo(this)"/>
          <input type="file" id="inputDoc"     accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip" style="display:none" onchange="selecionarArquivo(this)"/>
          <input type="file" id="inputArquivo" style="display:none"/>
          <textarea id="inputMensagem" class="input-msg" placeholder="Digite uma mensagem..." rows="1"></textarea>
          <button class="btn-enviar" onclick="enviarMensagem()">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
              <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </main>

  <!-- TAREFAS -->
  <aside class="tarefas-panel" id="tarefasPanel">
    <div class="tarefas-header">
      <span class="tarefas-titulo">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
        </svg>
        Tarefas
      </span>
      <button class="btn-add-tarefa" onclick="abrirModal('modalTarefa')">+ Nova</button>
    </div>
    <div class="tarefas-filtro">
      <button class="tab-tarefa active" id="tab-todas"      onclick="filtrarTarefas('todas',this)">Todas</button>
      <button class="tab-tarefa"        id="tab-pendente"   onclick="filtrarTarefas('pendente',this)">Pendente</button>
      <button class="tab-tarefa"        id="tab-confirmada" onclick="filtrarTarefas('confirmada',this)">Feitas</button>
    </div>
    <div class="tarefas-lista" id="listaTarefas">
      <div style="text-align:center;padding:20px;color:var(--texto-muted)">Carregando...</div>
    </div>
  </aside>

</div><!-- /chat-layout -->

<!-- ══ BARRA DE GRUPOS (embaixo de tudo) ══ -->
<div class="grupos-barra-bottom">
  <div class="grupos-barra-label">Grupos</div>
  <div class="grupos-cards-grid">

    <?php if ($grupo_geral): ?>
    <div class="geral-card" id="grupo-<?= $grupo_geral['id'] ?>"
         onclick="abrirChat('grupo', <?= $grupo_geral['id'] ?>, '<?= htmlspecialchars(addslashes($grupo_geral['nome'])) ?>')"
         oncontextmenu="abrirMenuGrupo(event, <?= $grupo_geral['id'] ?>, '<?= htmlspecialchars(addslashes($grupo_geral['nome'])) ?>')">
      <span class="silenciado-icone" id="silenciado-grupo-<?= $grupo_geral['id'] ?>">🔕</span>
      <div class="geral-card-icone"><?= $grupo_geral['icone'] ?></div>
      <div class="geral-card-nome">Geral</div>
      <span class="badge-nao-lidas badge-card" id="badge-grupo-<?= $grupo_geral['id'] ?>" style="display:none;"></span>
    </div>
    <?php endif; ?>

    <?php foreach ($outros_grupos as $g): ?>
    <div class="grupo-card-grande" id="grupo-<?= $g['id'] ?>"
         onclick="abrirChat('grupo', <?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['nome'])) ?>')"
         oncontextmenu="abrirMenuGrupo(event, <?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['nome'])) ?>')">
      <span class="silenciado-icone" id="silenciado-grupo-<?= $g['id'] ?>">🔕</span>
      <div class="grupo-card-grande-icone"><?= $g['icone'] ?></div>
      <div class="grupo-card-grande-nome"><?= htmlspecialchars($g['nome']) ?></div>
      <span class="badge-nao-lidas badge-card" id="badge-grupo-<?= $g['id'] ?>" style="display:none;"></span>
      <?php if ($eu['role'] === 'admin'): ?>
      <button class="btn-card-gerenciar"
              onclick="event.stopPropagation();abrirGerenciarGrupoAdmin(<?= $g['id'] ?>,'<?= htmlspecialchars(addslashes($g['nome'])) ?>')"
              title="Gerenciar membros">⚙</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

  </div>
</div><!-- /grupos-barra-bottom -->

</div><!-- /chat-wrapper -->

<!-- MODAIS -->
<div class="modal-overlay" id="modalTarefa" onclick="fecharModalFora(event,'modalTarefa')">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-titulo">Nova Tarefa</span>
      <button class="modal-fechar" onclick="fecharModal('modalTarefa')">✕</button>
    </div>
    <div class="modal-body">

      <div class="form-group">
        <label class="form-label">Título *</label>
        <input type="text" id="tarefaTitulo" class="form-input" placeholder="Ex: Fazer relatório"/>
      </div>

      <div class="form-group">
        <label class="form-label">Descrição (opcional)</label>
        <textarea id="tarefaDesc" class="form-input" rows="3" placeholder="Detalhes sobre a tarefa..."></textarea>
      </div>

      <div class="form-row">
        <!-- Data de início (opcional) -->
        <div class="form-group">
          <label class="form-label">Data de início (opcional)</label>
          <div class="cal-picker" id="calPickerInicio">
            <div class="cal-input-row">
              <input type="text" id="tarefaDataInicio" class="form-input" placeholder="DD/MM/AAAA"
                     maxlength="10" autocomplete="off" oninput="mascaraData(this)"/>
              <button type="button" class="cal-btn-icon"
                      onclick="abrirCalendario('tarefaDataInicio','calDropInicio')" title="Calendário">📅</button>
            </div>
            <div class="cal-dropdown" id="calDropInicio"></div>
          </div>
        </div>

        <!-- Data de entrega (obrigatório) -->
        <div class="form-group">
          <label class="form-label">Data de entrega *</label>
          <div class="cal-picker" id="calPickerEntrega">
            <div class="cal-input-row">
              <input type="text" id="tarefaDataEntrega" class="form-input" placeholder="DD/MM/AAAA"
                     maxlength="10" autocomplete="off"
                     oninput="mascaraData(this)" onchange="atualizarStatusEntrega()"/>
              <button type="button" class="cal-btn-icon"
                      onclick="abrirCalendario('tarefaDataEntrega','calDropEntrega')" title="Calendário">📅</button>
            </div>
            <div class="cal-dropdown" id="calDropEntrega"></div>
          </div>
          <div id="statusEntregaHint" class="cal-status-hint"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Horário limite *</label>
        <input type="time" id="tarefaHora" class="form-input" onchange="atualizarStatusEntrega()"/>
      </div>

      <div class="form-group">
        <label class="form-label">Alertar quanto tempo antes do prazo?</label>
        <select id="tarefaIntervalo" class="form-input">
          <option value="10">10 minutos antes</option>
          <option value="15">15 minutos antes</option>
          <option value="20">20 minutos antes</option>
          <option value="30" selected>30 minutos antes</option>
          <option value="45">45 minutos antes</option>
          <option value="60">1 hora antes</option>
          <option value="90">1h30 antes</option>
          <option value="120">2 horas antes</option>
        </select>
      </div>

      <?php if ($eu['role'] === 'admin'): ?>
      <div class="form-group">
        <label class="form-label">Atribuir a</label>
        <select id="tarefaUsuario" class="form-input">
          <option value="<?= $eu['id'] ?>">Mim mesmo</option>
          <?php foreach ($contatos as $c):
                $c_nome = $c['nome_completo'] ?? $c['username']; ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c_nome) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

    </div>
    <div class="modal-footer">
      <button class="btn-cancelar" onclick="fecharModal('modalTarefa')">Cancelar</button>
      <button class="btn-confirmar" onclick="criarTarefa()">Criar Tarefa</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalGrupo" onclick="fecharModalFora(event,'modalGrupo')">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-titulo">Criar Grupo</span>
      <button class="modal-fechar" onclick="fecharModal('modalGrupo')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Nome do Grupo</label>
        <input type="text" id="grupoNome" class="form-input" placeholder="Ex: Marketing"/></div>
      <!-- CORRIGIDO: UTF-8 correto no label -->
      <div class="form-group"><label class="form-label">Ícone (emoji)</label>
        <input type="text" id="grupoIcone" class="form-input" placeholder="👥" maxlength="4"/></div>
      <div class="form-group"><label class="form-label">Membros (opcional)</label>
        <div class="membros-lista">
          <?php if (empty($contatos)): ?>
          <!-- NOVO: aviso quando não há outros usuários -->
          <p style="color:var(--texto-muted);font-size:12px;padding:8px;">
            Nenhum outro usuário cadastrado. Você pode criar o grupo agora e adicionar membros depois.
          </p>
          <?php else: ?>
          <?php foreach ($contatos as $c):
                $c_nome = $c['nome_completo'] ?? $c['username']; ?>
          <label class="membro-check">
            <input type="checkbox" name="membros" value="<?= $c['id'] ?>"/>
            <div class="avatar-sm avatar-letra"><?= strtoupper(substr($c_nome, 0, 1)) ?></div>
            <?= htmlspecialchars($c_nome) ?>
          </label>
          <?php endforeach; ?>
          <?php endif; ?>
        </div></div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancelar" onclick="fecharModal('modalGrupo')">Cancelar</button>
      <button class="btn-confirmar" onclick="criarGrupo()">Criar Grupo</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalAdmin" onclick="fecharModalFora(event,'modalAdmin')">
  <div class="modal modal-lg">
    <div class="modal-header">
      <!-- CORRIGIDO: UTF-8 correto -->
      <span class="modal-titulo">⭐ Painel Admin</span>
      <button class="modal-fechar" onclick="fecharModal('modalAdmin')">✕</button>
    </div>
    <div class="modal-body">
      <div class="admin-tabs">
        <!-- CORRIGIDO: UTF-8 correto -->
        <button class="admin-tab active" id="adminTabUsuarios" onclick="adminTab('usuarios',this)">Usuários</button>
        <button class="admin-tab"        id="adminTabGrupos"   onclick="adminTab('grupos',this)">Grupos</button>
      </div>
      <div id="adminUsuarios">
        <!-- CORRIGIDO: UTF-8 correto -->
        <h4 style="color:var(--texto-muted);font-size:13px;margin-bottom:14px;">Criar novo funcionário</h4>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nome Completo</label>
            <input type="text" id="novoNome" class="form-input" placeholder="Ex: Eduardo Silva"/></div>
          <div class="form-group"><label class="form-label">CPF (será a senha)</label>
            <input type="text" id="novoCpf" class="form-input" placeholder="000.000.000-00"/></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Cargo / Função</label>
            <input type="text" id="novoCargo" class="form-input" placeholder="Ex: Vendedor, Atendente..."/></div>
        </div>
        <div id="erroUsuario" style="display:none;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.4);color:#fca5a5;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:10px;line-height:1.4;"></div>
        <div id="sucessoUsuario" style="display:none;background:rgba(5,150,105,0.12);border:1px solid rgba(5,150,105,0.4);color:#6ee7b7;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:10px;line-height:1.5;"></div>

        <!-- CORRIGIDO: UTF-8 correto no botão -->
        <button class="btn-confirmar" style="width:100%;margin-bottom:20px" onclick="criarUsuario()">Criar Funcionário</button>
        <div id="listaUsuariosAdmin"></div>
      </div>
      <div id="adminGrupos" style="display:none;">
        <div id="listaGruposAdmin"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalEncaminhar" onclick="fecharModalFora(event,'modalEncaminhar')">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-titulo">Encaminhar mensagem</span>
      <button class="modal-fechar" onclick="fecharModal('modalEncaminhar')">✕</button>
    </div>
    <div class="modal-body">
      <!-- CORRIGIDO: UTF-8 correto -->
      <p style="color:var(--texto-muted);font-size:13px;margin-bottom:14px;">Selecione os destinatários:</p>
      <div class="membros-lista" id="listaEncaminhar">
        <?php foreach ($contatos as $c):
              $c_nome = $c['nome_completo'] ?? $c['username']; ?>
        <label class="membro-check">
          <input type="checkbox" name="encaminhar" value="individual-<?= $c['id'] ?>"/>
          <div class="avatar-sm avatar-letra"><?= strtoupper(substr($c_nome, 0, 1)) ?></div>
          <?= htmlspecialchars($c_nome) ?>
        </label>
        <?php endforeach; ?>
        <?php foreach ($outros_grupos as $g): ?>
        <label class="membro-check">
          <input type="checkbox" name="encaminhar" value="grupo-<?= $g['id'] ?>"/>
          <div class="avatar-sm avatar-letra"><?= $g['icone'] ?></div>
          <?= htmlspecialchars($g['nome']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancelar" onclick="fecharModal('modalEncaminhar')">Cancelar</button>
      <button class="btn-confirmar" onclick="confirmarEncaminhar()">Encaminhar</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalAlertaTarefa">
  <div class="modal modal-alerta">
    <div class="alerta-icone">⏰</div>
    <div class="modal-titulo" id="alertaTarefaTitulo">Tarefa pendente</div>
    <!-- CORRIGIDO: UTF-8 correto -->
    <p class="alerta-desc" id="alertaTarefaDesc">Você está fazendo esta atividade?</p>
    <div class="modal-footer" style="justify-content:center;gap:16px">
      <button class="btn-cancelar" onclick="responderAlerta(false)">Não</button>
      <button class="btn-confirmar" onclick="responderAlerta(true)">✅ Sim, estou fazendo!</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalApagarMsg" onclick="fecharModalFora(event,'modalApagarMsg')">
  <div class="modal" style="max-width:320px">
    <div class="modal-header">
      <span class="modal-titulo">Apagar mensagem</span>
      <button class="modal-fechar" onclick="fecharModal('modalApagarMsg')">✕</button>
    </div>
    <div class="modal-footer" style="justify-content:center;gap:10px;flex-direction:column;padding:20px">
      <button class="btn-confirmar" style="width:100%" onclick="apagarMensagem(document.getElementById('modalApagarMsg').dataset.msgId,'todos')">
        🗑️ Apagar para todos
      </button>
      <button class="btn-cancelar" style="width:100%" onclick="apagarMensagem(document.getElementById('modalApagarMsg').dataset.msgId,'mim')">
        Apagar só para mim
      </button>
    </div>
  </div>
</div>

<!-- ══ Modal Configurações ══ -->
<div class="modal-overlay" id="modalConfiguracoes" onclick="fecharModalFora(event,'modalConfiguracoes')">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-titulo">⚙ Configurações</span>
      <button class="modal-fechar" onclick="fecharModal('modalConfiguracoes')">✕</button>
    </div>
    <div class="modal-body">
      <div class="admin-tabs">
        <button class="admin-tab active" id="cfgTabConta"    onclick="cfgTab('conta',this)">Minha Conta</button>
        <?php if ($eu['role'] === 'admin'): ?>
        <button class="admin-tab"        id="cfgTabHistorico" onclick="cfgTab('historico',this)">Histórico de Removidos</button>
        <?php endif; ?>
      </div>

      <!-- ABA: Minha Conta -->
      <div id="cfgConta">
        <h4 style="color:var(--texto-muted);font-size:13px;margin-bottom:16px;">Alterar nome de login</h4>
        <div class="form-group" style="margin-bottom:18px;">
          <label class="form-label">Novo nome de login (username)</label>
          <input type="text" id="cfgNovoUsername" class="form-input" placeholder="Ex: eduardo.silva" autocomplete="off"/>
        </div>
        <div class="painel-sep" style="margin-bottom:18px;"></div>
        <h4 style="color:var(--texto-muted);font-size:13px;margin-bottom:16px;">Alterar senha</h4>
        <div class="form-group">
          <label class="form-label">Senha atual</label>
          <input type="password" id="cfgSenhaAtual" class="form-input" placeholder="Digite sua senha atual" autocomplete="current-password"/>
        </div>
        <div class="form-group">
          <label class="form-label">Nova senha</label>
          <input type="password" id="cfgNovaSenha" class="form-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password"/>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" id="cfgConfirmarSenha" class="form-input" placeholder="Repita a nova senha" autocomplete="new-password"/>
        </div>
        <div id="cfgContaMensagem" style="display:none;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:10px;"></div>
        <div class="modal-footer" style="padding:0;margin-top:8px;">
          <button class="btn-confirmar" onclick="salvarAlteracoesConta()">Salvar alterações</button>
        </div>
      </div>

      <!-- ABA: Histórico de Removidos (admin) -->
      <?php if ($eu['role'] === 'admin'): ?>
      <div id="cfgHistorico" style="display:none;">
        <div id="cfgHistoricoCorpo" style="min-height:80px;">
          <div style="text-align:center;padding:20px;color:var(--texto-muted)">Carregando...</div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<div class="toast" id="toast"><span id="toastMsg"></span></div>

<!-- Container de notificações estilo WhatsApp -->
<div id="notifContainer"></div>

<!-- Menu de contexto de grupo (silenciar) -->
<div class="ctx-menu" id="ctxMenuGrupo">
  <button class="ctx-menu-item" id="ctxMenuToggle" onclick="toggleSilenciarGrupo()">
    <span id="ctxMenuIcone">🔕</span>
    <span id="ctxMenuTexto">Silenciar grupo</span>
  </button>
  <div class="ctx-menu-sep"></div>
  <button class="ctx-menu-item" onclick="fecharMenuGrupo();abrirChat('grupo', ctxGrupoAtual.id, ctxGrupoAtual.nome)">
    💬 Abrir conversa
  </button>
</div>

<!-- ── Modal Histórico de Atividades ── -->
<div class="modal-overlay" id="modalRelatorio" onclick="fecharModalFora(event,'modalRelatorio')">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-titulo">📋 Histórico de Atividades</span>
      <button class="modal-fechar" onclick="fecharModal('modalRelatorio')">✕</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:11px;font-weight:700;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;">De</label>
          <input type="date" id="relDe" style="padding:8px 10px;border-radius:8px;border:1.5px solid var(--borda-2);background:var(--surface-2);color:var(--texto);font-size:13px;outline:none;"/>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:11px;font-weight:700;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;">Até</label>
          <input type="date" id="relAte" style="padding:8px 10px;border-radius:8px;border:1.5px solid var(--borda-2);background:var(--surface-2);color:var(--texto);font-size:13px;outline:none;"/>
        </div>
        <?php if ($eu['role'] === 'admin'): ?>
        <div style="display:flex;flex-direction:column;gap:4px;">
          <label style="font-size:11px;font-weight:700;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.06em;">Funcionário</label>
          <select id="relFuncionario" style="padding:8px 10px;border-radius:8px;border:1.5px solid var(--borda-2);background:var(--surface-2);color:var(--texto);font-size:13px;outline:none;min-width:160px;">
            <option value="0">Todos</option>
            <?php foreach ($contatos as $c): if ($c['role'] ?? 'funcionario' !== 'admin'): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_completo'] ?? $c['username']) ?></option>
            <?php endif; endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button onclick="gerarRelatorio()" style="padding:8px 20px;background:var(--verde);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">Gerar</button>
      </div>
      <div id="relatorioResultado" style="min-height:80px;"></div>
    </div>
  </div>
</div>

<!-- ── NOVO: Modal Gerenciar Grupo ── -->
<div class="modal-overlay" id="modalGerenciarGrupo" onclick="fecharModalFora(event,'modalGerenciarGrupo')">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-titulo" id="gerenciarGrupoTitulo">👥 Gerenciar Grupo</span>
      <button class="modal-fechar" onclick="fecharModal('modalGerenciarGrupo')">✕</button>
    </div>
    <div class="modal-body">
      <div id="gerenciarGrupoCorpo" style="min-height:80px">
        <div style="text-align:center;padding:20px;color:var(--texto-muted)">Carregando...</div>
      </div>
    </div>
  </div>
</div>

<div id="tooltipStatus" style="display:none;position:fixed;z-index:9999;pointer-events:none;
  background:rgba(15,20,35,0.93);color:#e9edef;border-radius:10px;padding:8px 12px;
  font-size:13px;box-shadow:0 4px 20px rgba(0,0,0,0.35);max-width:220px;
  transition:opacity .15s ease;opacity:0;white-space:nowrap;">
  <div id="ttNome" style="font-weight:700;margin-bottom:2px;"></div>
  <div id="ttStatus" style="font-size:12px;opacity:0.8;"></div>
</div>

<script>
var EU = {
  id:   <?= $eu['id'] ?>,
  nome: "<?= htmlspecialchars($eu_nome) ?>",
  role: "<?= $eu['role'] ?>"
};

// Mapa de status de todos os contatos (atualizado via polling)
window.statusUsuarios = {
<?php foreach ($contatos as $c): ?>
  <?= $c['id'] ?>: { emoji: "<?= htmlspecialchars($c['status_emoji'] ?? '') ?>", status: "<?= htmlspecialchars($c['status_atual'] ?? '') ?>" },
<?php endforeach; ?>
};

function abrirModalAdmin(aba) {
  abrirModal('modalAdmin');
  const tabBtn = document.getElementById(aba === 'usuarios' ? 'adminTabUsuarios' : 'adminTabGrupos');
  if (tabBtn) adminTab(aba, tabBtn);
  carregarUsuariosAdmin();
}
// NOTA: selecionarArquivo() é definido em front/chat.js — não redefinir aqui para evitar conflito

// ── Relatório de Atividades ──
function abrirModalRelatorio() {
    document.getElementById('menuPerfil')?.classList.remove('show');
    // Preenche datas padrão: primeiro dia do mês até hoje
    const hoje = new Date();
    const primDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const fmt = d => d.toISOString().slice(0,10);
    document.getElementById('relDe').value  = fmt(primDia);
    document.getElementById('relAte').value = fmt(hoje);
    document.getElementById('relatorioResultado').innerHTML = '<p style="color:var(--texto-muted);font-size:13px;">Selecione o período e clique em Gerar.</p>';
    abrirModal('modalRelatorio');
}

async function gerarRelatorio() {
    const de  = document.getElementById('relDe')?.value;
    const ate = document.getElementById('relAte')?.value;
    const uid = document.getElementById('relFuncionario')?.value ?? '0';
    if (!de || !ate) { mostrarToast('Selecione o período.', 'aviso'); return; }
    const box = document.getElementById('relatorioResultado');
    box.innerHTML = '<p style="color:var(--texto-muted);font-size:13px;">Carregando...</p>';
    try {
        const res  = await fetch(`api/relatorio_atividades.php?de=${de}&ate=${ate}&usuario_id=${uid}`);
        const data = await res.json();
        if (!data.success) { box.innerHTML = `<p style="color:var(--erro)">${escHtml(data.error)}</p>`; return; }
        const t = data.tarefas;
        if (!t.length) { box.innerHTML = '<p style="color:var(--texto-muted);font-size:13px;">Nenhuma atividade no período.</p>'; return; }

        const corStatus = { pendente:'#f59e0b', confirmada:'#059669', atrasada:'#ef4444', cancelada:'#8696a0' };
        const labelStatus = { pendente:'Pendente', confirmada:'Concluída', atrasada:'Atrasada', cancelada:'Cancelada' };

        // Resumo
        const resumo = { pendente:0, confirmada:0, atrasada:0, cancelada:0 };
        t.forEach(x => resumo[x.status] = (resumo[x.status]||0)+1);
        let html = `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">`;
        Object.entries(resumo).forEach(([s,n]) => {
            if (!n) return;
            html += `<div style="background:${corStatus[s]}18;border:1.5px solid ${corStatus[s]}40;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;color:${corStatus[s]}">${labelStatus[s]}: ${n}</div>`;
        });
        html += `</div><div style="display:flex;flex-direction:column;gap:6px;">`;

        t.forEach(x => {
            const cor = corStatus[x.status] || '#8696a0';
            html += `<div style="background:var(--surface-2);border-radius:10px;padding:10px 12px;border-left:3px solid ${cor};display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <div style="flex:1;min-width:120px;">
                    <div style="font-weight:700;font-size:13px;color:var(--texto)">${escHtml(x.titulo)}</div>
                    ${x.descricao ? `<div style="font-size:11px;color:var(--texto-muted)">${escHtml(x.descricao)}</div>` : ''}
                </div>
                ${x.usuario_nome ? `<span style="font-size:11px;color:var(--texto-muted)">👤 ${escHtml(x.usuario_nome)}</span>` : ''}
                <span style="font-size:11px;color:var(--texto-muted)">📅 ${escHtml(x.data_fmt)}</span>
                <span style="font-size:11px;font-weight:700;color:${cor}">${labelStatus[x.status]||x.status}</span>
                ${x.horario_check ? `<span style="font-size:11px;color:var(--verde)">✔ ${escHtml(x.horario_check)}</span>` : ''}
            </div>`;
        });
        html += '</div>';
        box.innerHTML = html;
    } catch(e) {
        box.innerHTML = '<p style="color:var(--erro)">Erro ao gerar relatório.</p>';
    }
}

// ── Status: submenu ──
function toggleStatusSubmenu(e) {
    if (e) e.stopPropagation();
    const sub = document.getElementById('statusSubmenu');
    if (!sub) return;
    const aberto = sub.style.display === 'flex';
    sub.style.display = aberto ? 'none' : 'flex';
}

async function selecionarStatus(emoji, texto) {
    document.getElementById('statusSubmenu').style.display = 'none';
    document.getElementById('menuPerfil')?.classList.remove('show');
    try {
        const res  = await fetch('api/atualizar_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ emoji, status: texto })
        });
        const data = await res.json();
        if (!data.success) return;
        // Atualiza header
        const hEmoji  = document.getElementById('headerStatusEmoji');
        const mEmoji  = document.getElementById('menuStatusEmoji');
        if (hEmoji) { hEmoji.textContent = emoji; hEmoji.title = texto; }
        if (mEmoji) mEmoji.textContent = emoji;
        mostrarToast('Status atualizado: ' + emoji + ' ' + texto);
    } catch(e) {}
}

// ── Tooltip de status ──
const _tt = document.getElementById('tooltipStatus');
let _ttTimer = null;

function mostrarTooltipStatus(el) {
    clearTimeout(_ttTimer);
    const uid    = el.dataset.ttuid;
    const nome   = el.dataset.ttnome || '';
    const sData  = window.statusUsuarios?.[uid];
    const emoji  = el.dataset.ttemoji || sData?.emoji || '';
    const status = el.dataset.ttstatus || sData?.status || '';

    document.getElementById('ttNome').textContent = nome;
    const ttSt = document.getElementById('ttStatus');
    if (emoji || status) {
        ttSt.textContent = (emoji ? emoji + ' ' : '') + status;
        ttSt.style.display = '';
    } else {
        ttSt.style.display = 'none';
    }

    _tt.style.opacity = '0';
    _tt.style.display = 'block';
    const rect = el.getBoundingClientRect();
    const ttH  = _tt.offsetHeight;
    const top  = rect.top - ttH - 8 > 0 ? rect.top - ttH - 8 : rect.bottom + 8;
    _tt.style.top  = top + 'px';
    _tt.style.left = Math.max(4, rect.left) + 'px';
    _tt.style.opacity = '1';
}

function esconderTooltipStatus() {
    _tt.style.opacity = '0';
    _ttTimer = setTimeout(() => { _tt.style.display = 'none'; }, 150);
}

document.addEventListener('mouseover', function(e) {
    const el = e.target.closest('[data-ttuid]');
    if (el) mostrarTooltipStatus(el);
});
document.addEventListener('mouseout', function(e) {
    const el = e.target.closest('[data-ttuid]');
    if (el && !el.contains(e.relatedTarget)) esconderTooltipStatus();
});

async function uploadAvatarProprio(input) {
    const file = input.files[0];
    if (!file) return;
    const form = new FormData();
    form.append('avatar', file);
    try {
        const res  = await fetch('api/upload_avatar_proprio.php', { method: 'POST', body: form });
        const data = await res.json();
        if (!data.success) { alert(data.error || 'Erro ao enviar foto.'); return; }
        const url = data.avatar_url + '?v=' + Date.now();
        // Atualiza avatar no botão do header
        const perfilBtn = document.getElementById('perfilBtn');
        if (perfilBtn) {
            const avSm = perfilBtn.querySelector('.avatar-sm');
            if (avSm) {
                const img = document.createElement('img');
                img.src = url;
                img.className = 'avatar-sm';
                img.style.cssText = 'object-fit:cover;border-radius:50%;';
                avSm.replaceWith(img);
            }
        }
        // Atualiza avatar no menu de perfil
        const menuPerfil = document.getElementById('menuPerfil');
        if (menuPerfil) {
            const avMd = menuPerfil.querySelector('.menu-perfil-header .avatar-md, .menu-perfil-header img');
            if (avMd) {
                const img = document.createElement('img');
                img.src = url;
                img.className = 'avatar-md';
                img.style.cssText = 'object-fit:cover;border-radius:50%;';
                avMd.replaceWith(img);
            }
        }
        mostrarToast('Foto de perfil atualizada!');
    } catch(e) {
        alert('Erro de rede ao enviar foto.');
    }
    input.value = '';
}
</script>
<script src="front/chat.js?v=<?= filemtime(__DIR__.'/front/chat.js') ?>"></script>
<script src="front/tarefas.js?v=<?= filemtime(__DIR__.'/front/tarefas.js') ?>"></script>
</body>
</html>
