// ══════════════════════════════════════════════════════════════════
//  CHAT MEGA AXNEN — chat.js
//  CORRIGIDO: encoding reescrito em UTF-8 (era Latin-1, quebrava strings)
// ══════════════════════════════════════════════════════════════════

// ── Estado global ──
let chatAtualTipo = null;   // 'individual' | 'grupo'
let chatAtualId   = null;   // ID do contato ou grupo
let ultimoId      = 0;      // Polling incremental
let intervalMsg   = null;
let intervalSidebar = null;
let arquivoSelecionado = null;

// ══════════════════════════════════════════════════════════════════
// 1. ABRIR CONVERSA
//    Chamado pelo index.php como: abrirChat('individual', id, nome)
//                              ou abrirChat('grupo', id, nome)
// ══════════════════════════════════════════════════════════════════
function abrirChat(tipo, id, nome, avatarUrl = '') {
    chatAtualTipo = tipo;
    chatAtualId   = Number(id);
    ultimoId      = 0;

    // Atualiza header do chat
    document.getElementById('chatNome').textContent = nome;
    document.getElementById('chatSub').textContent  = tipo === 'grupo' ? 'Grupo' : 'Mensagem direta';

    // Avatar no header — foto real ou círculo de letra
    const av = document.getElementById('chatAvatar');
    if (avatarUrl && tipo === 'individual') {
        av.innerHTML = `<img src="${avatarUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        av.classList.remove('avatar-letra');
    } else {
        av.innerHTML = '';
        av.textContent = nome.charAt(0).toUpperCase();
        av.classList.add('avatar-letra');
    }

    // Mostra área de chat
    document.getElementById('chatVazio').style.display  = 'none';
    document.getElementById('chatAtivo').style.display  = 'flex';

    // Limpa mensagens antigas
    document.getElementById('mensagensArea').innerHTML = '';

    // Marca itens ativos na sidebar
    document.querySelectorAll('.contato-item, .grupo-card-grande, .geral-card').forEach(el => el.classList.remove('active'));
    const selecionado = document.getElementById(tipo === 'individual' ? `dm-${id}` : `grupo-${id}`);
    if (selecionado) selecionado.classList.add('active');

    // ── NOVO: mostra botão "Gerenciar Grupo" no header se admin e em grupo ──
    const btnGerenciar = document.getElementById('btnGerenciarGrupo');
    if (btnGerenciar) {
        btnGerenciar.style.display = (tipo === 'grupo') ? 'inline-flex' : 'none';
    }

    // Inicia polling
    if (intervalMsg) clearInterval(intervalMsg);
    carregarMensagens();
    intervalMsg = setInterval(carregarMensagens, 3000);
}

// ══════════════════════════════════════════════════════════════════
// 2. CARREGAR MENSAGENS (polling)
// ══════════════════════════════════════════════════════════════════
async function carregarMensagens() {
    if (!chatAtualId) return;
    try {
        const url = `api/buscar_mensagens.php?tipo=${chatAtualTipo}&id=${chatAtualId}&ultimo_id=${ultimoId}`;
        const res  = await fetch(url);
        const data = await res.json();

        if (data.success && data.mensagens.length > 0) {
            renderizarMensagens(data.mensagens, data.append ?? false);
            if (data.ultimo_id) ultimoId = data.ultimo_id;
        }

        // Atualiza ticks sem re-render
        if (data.lidos_ids && data.lidos_ids.length > 0) {
            data.lidos_ids.forEach(mid => {
                const tick = document.querySelector(`#msg-${mid} .tick`);
                if (tick) tick.classList.add('lido');
            });
        }
    } catch (e) {
        console.error('Erro ao buscar mensagens:', e);
    }
}

// ══════════════════════════════════════════════════════════════════
// 3. RENDERIZAR MENSAGENS
// ══════════════════════════════════════════════════════════════════
function renderizarMensagens(mensagens, append = false) {
    const area = document.getElementById('mensagensArea');
    const eraBaixo = area.scrollHeight - area.scrollTop - area.clientHeight < 80;

    let html = '';
    mensagens.forEach(m => {
        const isMeu   = Number(m.sou_eu ?? (m.remetente_id == EU.id ? 1 : 0));
        const classe  = isMeu ? 'balao-eu' : 'balao-outro';
        const apagada = m.apagada || m.apagado_todos;

        // Nome do remetente (grupos, mensagem de outro)
        let remetente = '';
        if (!isMeu && chatAtualTipo === 'grupo') {
            remetente = `<div class="balao-remetente">${escHtml(m.nome_remetente ?? m.nome ?? '')}</div>`;
        }

        // Avatar do remetente (grupos)
        let avRemetente = '';
        if (!isMeu && chatAtualTipo === 'grupo') {
            const _uid   = m.remetente_id ?? '';
            const _nome  = escHtml(m.nome_remetente ?? m.nome ?? '');
            avRemetente = m.remetente_avatar
                ? `<img src="${m.remetente_avatar}" class="av-bolha" data-ttuid="${_uid}" data-ttnome="${_nome}" alt=""/>`
                : `<div class="av-bolha av-bolha-letra" data-ttuid="${_uid}" data-ttnome="${_nome}">${escHtml((m.nome_remetente ?? m.nome ?? '?').charAt(0).toUpperCase())}</div>`;
        }

        // Conteúdo
        let corpo = '';
        if (apagada) {
            corpo = `<em style="color:var(--texto-muted);font-size:13px">🚫 Mensagem apagada</em>`;
        } else {
            const texto = m.conteudo ?? m.mensagem ?? '';
            if (m.tipo_msg === 'foto' || (m.arquivo_url && /\.(jpg|jpeg|png|gif|webp)/i.test(m.arquivo_url ?? ''))) {
                corpo = `<img src="${m.arquivo_url}" style="max-width:260px;border-radius:10px;display:block;cursor:pointer" onclick="abrirFoto('${m.arquivo_url}')" loading="lazy"/>`;
            } else if (m.tipo_msg === 'video' || (m.arquivo_url && /\.(mp4|mov|webm)/i.test(m.arquivo_url ?? ''))) {
                corpo = `<video src="${m.arquivo_url}" controls style="max-width:260px;border-radius:10px;display:block"></video>`;
            } else if (m.tipo_msg === 'documento' || m.arquivo_url) {
                const nome = m.arquivo_nome ?? m.arquivo_url?.split('/').pop() ?? 'arquivo';
                corpo = `<a href="${m.arquivo_url}" target="_blank" style="color:var(--verde-light);display:flex;align-items:center;gap:6px;text-decoration:none;font-size:13px">📄 ${escHtml(nome)}</a>`;
            }
            if (texto) corpo += `<div style="margin-top:${corpo ? '6px' : '0'}">${escHtml(texto)}</div>`;
        }

        // Ticks e hora
        let tick = '';
        if (isMeu && !apagada) {
            const lidoClass = (m.tick === 'lido' || m.lido == 1) ? 'tick lido' : 'tick';
            tick = `<span class="${lidoClass}">✔✔</span>`;
        }

        // Menu de contexto (apagar/encaminhar)
        const acoes = !apagada ? `
            <div class="msg-acoes" style="display:none;position:absolute;${isMeu ? 'left' : 'right'}:-30px;top:0">
                <button onclick="abrirApagarMsg(${m.id})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Apagar">🗑️</button>
                <button onclick="prepararEncaminhar(${m.id})" style="background:none;border:none;cursor:pointer;font-size:14px" title="Encaminhar">↪️</button>
            </div>` : '';

        if (m.tipo_msg === 'sistema') {
            html += `<div class="balao balao-sistema" id="msg-${m.id}">${escHtml(m.conteudo ?? m.mensagem ?? '')}</div>`;
        } else {
            const balao = `
                <div class="balao ${classe}" id="msg-${m.id}" style="position:relative"
                     onmouseenter="this.querySelector('.msg-acoes')&&(this.querySelector('.msg-acoes').style.display='flex')"
                     onmouseleave="this.querySelector('.msg-acoes')&&(this.querySelector('.msg-acoes').style.display='none')">
                    ${acoes}
                    ${remetente}
                    ${corpo}
                    <div class="balao-meta">${m.hora ?? m.enviado_em ?? ''} ${tick}</div>
                </div>`;
            if (avRemetente) {
                html += `<div class="msg-grupo-row">${avRemetente}${balao}</div>`;
            } else {
                html += balao;
            }
        }
    });

    if (append) {
        area.insertAdjacentHTML('beforeend', html);
    } else {
        area.innerHTML = html;
    }

    if (eraBaixo) area.scrollTop = area.scrollHeight;
}

// ══════════════════════════════════════════════════════════════════
// 4. ENVIAR MENSAGEM
// ══════════════════════════════════════════════════════════════════
async function enviarMensagem() {
    if (!chatAtualId) return;

    const inputMsg = document.getElementById('inputMensagem');
    const texto    = inputMsg.value.trim();

    if (!texto && !arquivoSelecionado) return;

    const form = new FormData();
    form.append('tipo', chatAtualTipo);
    form.append('id',   chatAtualId);
    form.append('mensagem', texto);
    if (arquivoSelecionado) form.append('arquivo', arquivoSelecionado);

    inputMsg.value = '';
    removerArquivo();

    try {
        const res  = await fetch('api/enviar_mensagem.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            ultimoId = 0; // força reload completo para mostrar mensagem enviada
            carregarMensagens();
        } else {
            mostrarToast('Erro ao enviar: ' + (data.error ?? ''), 'erro');
        }
    } catch (e) {
        console.error('Erro ao enviar:', e);
    }
}

// Enter envia, Shift+Enter nova linha
document.addEventListener('DOMContentLoaded', () => {
    const inp = document.getElementById('inputMensagem');
    if (inp) {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                enviarMensagem();
            }
        });
        inp.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }

    // Inicia polling da sidebar
    carregarBadges();
    intervalSidebar = setInterval(carregarBadges, 5000);

    // Aplica ícones de silenciado salvos
    aplicarIconesSilenciados();

    // Aplica tema salvo no localStorage
    const temaSalvo = localStorage.getItem('tema_chat') || '';
    if (temaSalvo.startsWith('custom:')) {
        aplicarCorCustom(temaSalvo.slice(7), false);
    } else {
        aplicarTema(temaSalvo, false);
    }
});

// ══════════════════════════════════════════════════════════════════
// 5. SIDEBAR BADGES (não lidas + preview) + NOTIFICAÇÕES
// ══════════════════════════════════════════════════════════════════

let badgesPrevios   = {};   // { 'dm-id': n, 'grupo-id': n }
let notifIniciado   = false;
let gruposSilenciados = new Set(JSON.parse(localStorage.getItem('chat_grupos_silenciados') || '[]'));

function salvarGruposSilenciados() {
    localStorage.setItem('chat_grupos_silenciados', JSON.stringify([...gruposSilenciados]));
}

function aplicarIconesSilenciados() {
    gruposSilenciados.forEach(id => {
        const icone = document.getElementById(`silenciado-grupo-${id}`);
        if (icone) icone.style.display = 'inline';
    });
}

async function carregarBadges() {
    try {
        const res  = await fetch('api/buscar_contatos.php');
        const data = await res.json();
        if (!data.success) return;

        // ── DMs ──
        data.dms?.forEach(c => {
            const chave   = `dm-${c.id}`;
            const badge   = document.getElementById(`badge-dm-${c.id}`);
            const preview = document.getElementById(`preview-dm-${c.id}`);

            if (badge) {
                badge.textContent   = c.nao_lidas > 0 ? c.nao_lidas : '';
                badge.style.display = c.nao_lidas > 0 ? 'flex' : 'none';
            }
            if (preview) preview.textContent = c.preview ?? '...';

            // Notificação: novo não lido em conversa inativa
            const prev = badgesPrevios[chave] ?? 0;
            if (notifIniciado && c.nao_lidas > prev) {
                const estaAtivo = chatAtualTipo === 'individual' && chatAtualId === c.id;
                if (!estaAtivo) {
                    mostrarNotificacao(c.nome, c.preview ?? '', 'individual', c.id);
                    tocarSomNotificacao();
                }
            }
            badgesPrevios[chave] = c.nao_lidas;
        });

        // ── Reordena lista de DMs pela mensagem mais recente (igual WhatsApp) ──
        const listaContatos = document.getElementById('listaContatos');
        if (listaContatos && data.dms?.length) {
            data.dms.forEach((c, idx) => {
                const el = document.getElementById(`dm-${c.id}`);
                if (el) listaContatos.appendChild(el);
            });
        }

        // ── Grupos ──
        data.grupos?.forEach(g => {
            const chave = `grupo-${g.id}`;
            const badge = document.getElementById(`badge-grupo-${g.id}`);

            if (badge) {
                badge.textContent   = g.nao_lidas > 0 ? g.nao_lidas : '';
                badge.style.display = g.nao_lidas > 0 ? 'flex' : 'none';
            }

            const prev = badgesPrevios[chave] ?? 0;
            if (notifIniciado && g.nao_lidas > prev) {
                const estaAtivo    = chatAtualTipo === 'grupo' && chatAtualId === g.id;
                const silenciado   = gruposSilenciados.has(g.id);
                if (!estaAtivo && !silenciado) {
                    mostrarNotificacao(g.nome, g.preview ?? '', 'grupo', g.id);
                    tocarSomNotificacao();
                }
            }
            badgesPrevios[chave] = g.nao_lidas;
        });

        // Após a primeira chamada começamos a monitorar mudanças
        if (!notifIniciado) notifIniciado = true;

    } catch (e) {}
}

// ── Popup de notificação estilo WhatsApp ──
function mostrarNotificacao(nome, preview, tipo, id) {
    const container = document.getElementById('notifContainer');
    if (!container) return;

    const notif = document.createElement('div');
    notif.className = 'notif-card';
    notif.innerHTML = `
        <div class="notif-avatar">${escHtml(nome.charAt(0).toUpperCase())}</div>
        <div class="notif-corpo" onclick="abrirChat('${tipo}', ${id}, '${escHtml(nome).replace(/'/g, "\\'")}');dispensarNotif(this.closest('.notif-card'))">
            <div class="notif-nome">${escHtml(nome)}</div>
            <div class="notif-preview">${escHtml((preview || '').slice(0, 60))}</div>
        </div>
        <button class="notif-fechar" onclick="dispensarNotif(this.closest('.notif-card'))" title="Fechar">✕</button>
        <div class="notif-barra"></div>`;

    container.appendChild(notif);
    // Força reflow para a transição CSS funcionar
    notif.getBoundingClientRect();
    notif.classList.add('show');

    // Auto-dismiss após 5 s
    const timer = setTimeout(() => dispensarNotif(notif), 5000);
    notif._timer = timer;
}

function dispensarNotif(notif) {
    if (!notif) return;
    clearTimeout(notif._timer);
    notif.classList.remove('show');
    notif.classList.add('escondendo');
    setTimeout(() => notif.remove(), 400);
}

// ── Som de notificação (Web Audio API) ──
function tocarSomNotificacao() {
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const gain = ctx.createGain();
        gain.connect(ctx.destination);

        // Tom 1: G5 (784 Hz) — 0 a 180 ms
        const osc1 = ctx.createOscillator();
        osc1.type = 'sine';
        osc1.frequency.value = 784;
        osc1.connect(gain);
        gain.gain.setValueAtTime(0, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0.28, ctx.currentTime + 0.01);
        gain.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.18);
        osc1.start(ctx.currentTime);
        osc1.stop(ctx.currentTime + 0.18);

        // Tom 2: C6 (1047 Hz) — 220 a 420 ms
        const gain2 = ctx.createGain();
        gain2.connect(ctx.destination);
        const osc2 = ctx.createOscillator();
        osc2.type = 'sine';
        osc2.frequency.value = 1047;
        osc2.connect(gain2);
        gain2.gain.setValueAtTime(0, ctx.currentTime + 0.22);
        gain2.gain.linearRampToValueAtTime(0.22, ctx.currentTime + 0.23);
        gain2.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.42);
        osc2.start(ctx.currentTime + 0.22);
        osc2.stop(ctx.currentTime + 0.42);
    } catch (e) {}
}

// ── Menu de contexto para silenciar grupos ──
let ctxGrupoAtual = { id: null, nome: '' };

function abrirMenuGrupo(event, grupoId, grupoNome) {
    event.preventDefault();
    event.stopPropagation();
    ctxGrupoAtual = { id: grupoId, nome: grupoNome };

    const menu = document.getElementById('ctxMenuGrupo');
    if (!menu) return;

    const silenciado = gruposSilenciados.has(grupoId);
    const iconeEl = document.getElementById('ctxMenuIcone');
    const textoEl = document.getElementById('ctxMenuTexto');
    if (iconeEl) iconeEl.textContent = silenciado ? '🔔' : '🔕';
    if (textoEl) textoEl.textContent = silenciado ? 'Ativar notificações' : 'Silenciar notificações';

    menu.style.left = event.clientX + 'px';
    menu.style.top  = event.clientY + 'px';
    menu.classList.add('show');
}

function fecharMenuGrupo() {
    document.getElementById('ctxMenuGrupo')?.classList.remove('show');
}

function toggleSilenciarGrupo() {
    const id = ctxGrupoAtual.id;
    if (!id) return;
    const icone = document.getElementById(`silenciado-grupo-${id}`);

    if (gruposSilenciados.has(id)) {
        gruposSilenciados.delete(id);
        if (icone) icone.style.display = 'none';
    } else {
        gruposSilenciados.add(id);
        if (icone) icone.style.display = 'inline';
    }
    salvarGruposSilenciados();
    fecharMenuGrupo();
}

// Fecha menu de contexto ao clicar em qualquer lugar
document.addEventListener('click', () => fecharMenuGrupo());

// ══════════════════════════════════════════════════════════════════
// 6. ARQUIVO (foto/vídeo/doc)
// ══════════════════════════════════════════════════════════════════
function selecionarArquivo(input) {
    const arq = input.files[0];
    if (!arq) return;
    arquivoSelecionado = arq;

    const preview     = document.getElementById('uploadPreview');
    const previewNome = document.getElementById('previewNome');
    if (preview)     preview.style.display = 'flex';
    if (previewNome) previewNome.textContent = arq.name;

    input.value = '';
}

function removerArquivo() {
    arquivoSelecionado = null;
    const preview = document.getElementById('uploadPreview');
    if (preview) preview.style.display = 'none';
}

// ══════════════════════════════════════════════════════════════════
// 7. APAGAR MENSAGEM
// ══════════════════════════════════════════════════════════════════
let msgParaApagar = null;

function abrirApagarMsg(id) {
    msgParaApagar = id;
    document.getElementById('modalApagarMsg').dataset.msgId = id;
    abrirModal('modalApagarMsg');
}

async function apagarMensagem(id, modo) {
    fecharModal('modalApagarMsg');
    try {
        const res  = await fetch('api/deletar_mensagem.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, modo: modo })
        });
        const data = await res.json();
        if (data.success) {
            ultimoId = 0;
            carregarMensagens();
        } else {
            mostrarToast(data.error ?? 'Erro ao apagar.', 'erro');
        }
    } catch (e) {}
}

// ══════════════════════════════════════════════════════════════════
// 8. ENCAMINHAR MENSAGEM
// ══════════════════════════════════════════════════════════════════
let msgParaEncaminhar = null;

function prepararEncaminhar(id) {
    msgParaEncaminhar = id;
    abrirModal('modalEncaminhar');
}

async function confirmarEncaminhar() {
    const checks = document.querySelectorAll('#listaEncaminhar input[name=encaminhar]:checked');
    if (!checks.length) { mostrarToast('Selecione pelo menos um destino.', 'aviso'); return; }

    const destinos = Array.from(checks).map(c => {
        const [tipo, id] = c.value.split('-');
        return { tipo, id: Number(id) };
    });

    fecharModal('modalEncaminhar');
    checks.forEach(c => c.checked = false);

    for (const d of destinos) {
        try {
            await fetch('api/encaminhar_mensagem.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensagem_id: msgParaEncaminhar, tipo: d.tipo, id: d.id })
            });
        } catch (e) {}
    }
    mostrarToast('✅ Encaminhado!');
    carregarMensagens();
}

// ══════════════════════════════════════════════════════════════════
// 9. ADMIN: CRIAR USUÁRIO
// ══════════════════════════════════════════════════════════════════
async function criarUsuario() {
    const nome  = document.getElementById('novoNome')?.value.trim();
    const cpf   = document.getElementById('novoCpf')?.value.trim();
    const cargo = document.getElementById('novoCargo')?.value.trim() || '';
    const errEl = document.getElementById('erroUsuario');

    const setErro = msg => { if (errEl) { errEl.textContent = msg; errEl.style.display = msg ? 'block' : 'none'; } };
    setErro('');

    if (!nome) { setErro('Informe o nome completo.'); return; }
    if (!cpf)  { setErro('Informe o CPF.'); return; }

    const cpfLimpo = cpf.replace(/\D/g,'');
    // ── CORRIGIDO: string UTF-8 correto ──
    if (cpfLimpo.length < 10) { setErro('CPF inválido — use pelo menos 10 dígitos.'); return; }

    const btn = document.querySelector('#adminUsuarios .btn-confirmar');
    if (btn) { btn.textContent = 'Criando...'; btn.disabled = true; }

    try {
        const res  = await fetch('api/criar_usuario.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome_completo: nome, cpf: cpfLimpo, cargo })
        });
        const data = await res.json();

        if (data.success) {
            setErro('');
            const ok = document.getElementById('sucessoUsuario');
            if (ok) {
                // ── CORRIGIDO: string UTF-8 correto ──
                ok.innerHTML = '✅ ' + (data.message ?? 'Funcionário criado!').replace(/\n/g,'<br>');
                ok.style.display = 'block';
                setTimeout(() => { ok.style.display = 'none'; }, 6000);
            }
            document.getElementById('novoNome').value  = '';
            document.getElementById('novoCpf').value   = '';
            const cargoEl = document.getElementById('novoCargo');
            if (cargoEl) cargoEl.value = '';
            carregarUsuariosAdmin();
        } else {
            // ── CORRIGIDO: string UTF-8 correto ──
            setErro(data.error ?? 'Erro ao criar funcionário.');
        }
    } catch (e) {
        // ── CORRIGIDO: string UTF-8 correto ──
        setErro('Erro de conexão com o servidor.');
    } finally {
        // ── CORRIGIDO: string UTF-8 correto ──
        if (btn) { btn.textContent = 'Criar Funcionário'; btn.disabled = false; }
    }
}

// ══════════════════════════════════════════════════════════════════
// 10. ADMIN: LISTAR USUÁRIOS (com editar nome, foto, excluir)
// ══════════════════════════════════════════════════════════════════
async function carregarUsuariosAdmin() {
    const lista = document.getElementById('listaUsuariosAdmin');
    if (!lista) return;
    lista.innerHTML = '<p style="color:var(--texto-muted);font-size:13px">Carregando...</p>';

    try {
        const res  = await fetch('api/buscar_contatos.php?modo=admin');
        const data = await res.json();
        if (!data.success) { lista.innerHTML = ''; return; }

        const todos = [...(data.contatos ?? [])];
        if (!todos.length) {
            lista.innerHTML = '<p style="color:var(--texto-muted);font-size:13px">Nenhum usuário.</p>';
            return;
        }

        lista.innerHTML =
            `<div style="font-size:11px;font-weight:800;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">
                Usuários cadastrados
            </div>` +
            todos.map(u => {
                const nome    = escHtml(u.nome_completo ?? u.nome ?? '');
                const inicial = (u.nome_completo ?? u.nome ?? '?').charAt(0).toUpperCase();
                const avatarHtml = u.avatar
                    ? `<img src="${escHtml(u.avatar)}" class="avatar-sm" style="object-fit:cover;border-radius:50%;width:34px;height:34px;" alt=""/>`
                    : `<div class="avatar-sm avatar-letra" style="font-size:14px;width:34px;height:34px;">${inicial}</div>`;

                return `
                <div style="background:var(--surface-2);border-radius:10px;margin-bottom:8px;overflow:hidden;" id="adminUser-${u.id}">
                    <div style="display:flex;align-items:center;gap:10px;padding:9px 10px;">
                        <!-- Avatar com botão de troca -->
                        <div style="position:relative;flex-shrink:0;">
                            ${avatarHtml}
                            <label title="Trocar foto" style="position:absolute;bottom:-2px;right:-2px;background:var(--verde);border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:9px;color:#fff;line-height:1;">
                                📷<input type="file" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadAvatarUsuario(this,${u.id})"/>
                            </label>
                        </div>
                        <!-- Info + edição inline -->
                        <div style="flex:1;min-width:0;">
                            <div id="nomeViz-${u.id}" style="font-size:13.5px;font-weight:600">${nome}</div>
                            <div id="nomeEd-${u.id}" style="display:none;">
                                <input type="text" id="nomeInp-${u.id}" class="form-input"
                                       style="padding:4px 8px;font-size:12.5px;margin-bottom:4px" value="${nome}"/>
                                <div style="display:flex;gap:4px;">
                                    <button onclick="salvarNomeUsuario(${u.id})"
                                        style="background:var(--verde);color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer;font-weight:700">Salvar</button>
                                    <button onclick="cancelarEdicaoNome(${u.id})"
                                        style="background:var(--surface-3);color:var(--texto-muted);border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">✕</button>
                                </div>
                            </div>
                            <div style="font-size:11px;color:var(--verde-light)">${u.role === 'admin' ? '⭐ Admin' : '👤 ' + (u.cargo ? escHtml(u.cargo) : 'Funcionário')}</div>
                        </div>
                        <!-- Ações -->
                        <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
                            <button onclick="iniciarEdicaoNome(${u.id})"
                                style="background:none;border:1px solid var(--borda-2);color:var(--texto-muted);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;font-weight:600;">
                                ✏ Editar</button>
                            ${u.role !== 'admin' ? `
                            <button onclick="excluirUsuarioAdmin(${u.id},'${nome.replace(/'/g,"\\'")}' )"
                                style="background:none;border:1px solid rgba(244,63,94,0.3);color:#f43f5e;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;font-weight:600;">
                                🗑 Excluir</button>` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
    } catch (e) {
        lista.innerHTML = '';
    }
}

// ── Editar nome inline ──
function iniciarEdicaoNome(id) {
    document.getElementById(`nomeViz-${id}`).style.display = 'none';
    document.getElementById(`nomeEd-${id}`).style.display  = 'block';
    document.getElementById(`nomeInp-${id}`)?.focus();
}
function cancelarEdicaoNome(id) {
    document.getElementById(`nomeViz-${id}`).style.display = 'block';
    document.getElementById(`nomeEd-${id}`).style.display  = 'none';
}
async function salvarNomeUsuario(id) {
    const novoNome = document.getElementById(`nomeInp-${id}`)?.value.trim();
    if (!novoNome) { mostrarToast('Informe o nome.', 'aviso'); return; }
    try {
        const res  = await fetch('api/editar_usuario.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usuario_id: id, nome_completo: novoNome })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById(`nomeViz-${id}`).textContent = novoNome;
            cancelarEdicaoNome(id);
            mostrarToast('✅ Nome atualizado!');
        } else {
            mostrarToast(data.error ?? 'Erro ao salvar.', 'erro');
        }
    } catch (e) { mostrarToast('Erro de conexão.', 'erro'); }
}

// ── Upload de avatar ──
async function uploadAvatarUsuario(input, id) {
    const file = input.files[0];
    if (!file) return;
    const form = new FormData();
    form.append('usuario_id', id);
    form.append('avatar', file);
    try {
        const res  = await fetch('api/upload_avatar.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            mostrarToast('✅ Foto atualizada!');
            carregarUsuariosAdmin();
        } else {
            mostrarToast(data.error ?? 'Erro ao enviar foto.', 'erro');
        }
    } catch (e) { mostrarToast('Erro de conexão.', 'erro'); }
}

// ── Excluir usuário ──
async function excluirUsuarioAdmin(id, nome) {
    if (!confirm(`Excluir "${nome}"?\nAs mensagens serão mantidas, mas o nome será anonimizado.`)) return;
    try {
        const res  = await fetch('api/excluir_usuario.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usuario_id: id })
        });
        const data = await res.json();
        if (data.success) {
            mostrarToast('Usuário removido.');
            carregarUsuariosAdmin();
        } else {
            mostrarToast(data.error ?? 'Erro ao excluir.', 'erro');
        }
    } catch (e) { mostrarToast('Erro de conexão.', 'erro'); }
}

// ══════════════════════════════════════════════════════════════════
// 10b. ADMIN — LISTAR E GERENCIAR GRUPOS (com editar nome/ícone)
// ══════════════════════════════════════════════════════════════════
async function carregarGruposAdmin() {
    const lista = document.getElementById('listaGruposAdmin');
    if (!lista) return;
    lista.innerHTML = '<p style="color:var(--texto-muted);font-size:13px">Carregando...</p>';

    try {
        const res  = await fetch('api/gerenciar_grupo.php?acao=listar');
        const data = await res.json();
        if (!data.success || !data.grupos.length) {
            lista.innerHTML = '<p style="color:var(--texto-muted);font-size:13px">Nenhum grupo encontrado.</p>';
            return;
        }
        lista.innerHTML =
            `<div style="font-size:11px;font-weight:800;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">
                Grupos criados
            </div>` +
            data.grupos.map(g => `
                <div style="background:var(--surface-2);border-radius:10px;margin-bottom:8px;overflow:hidden;" id="adminGrupo-${g.id}">
                    <div style="display:flex;align-items:center;gap:10px;padding:9px 10px;">
                        <!-- Visualização -->
                        <div id="grupoViz-${g.id}" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                            <div style="font-size:20px;width:34px;text-align:center;flex-shrink:0">${escHtml(g.icone)}</div>
                            <div>
                                <div style="font-size:13.5px;font-weight:600">${escHtml(g.nome)}</div>
                                <div style="font-size:11px;color:var(--texto-muted)">${g.total_membros} membro${g.total_membros != 1 ? 's' : ''}${g.fixo ? ' · Grupo fixo' : ''}</div>
                            </div>
                        </div>
                        <!-- Edição inline -->
                        <div id="grupoEd-${g.id}" style="display:none;flex:1;min-width:0;">
                            <div style="display:flex;gap:4px;margin-bottom:4px;">
                                <input type="text" id="grupoIconeInp-${g.id}" class="form-input"
                                       style="padding:4px 8px;font-size:18px;width:50px;text-align:center;" value="${escHtml(g.icone)}" maxlength="4"/>
                                <input type="text" id="grupoNomeInp-${g.id}" class="form-input"
                                       style="padding:4px 8px;font-size:12.5px;flex:1;" value="${escHtml(g.nome)}"/>
                            </div>
                            <div style="display:flex;gap:4px;">
                                <button onclick="salvarEdicaoGrupo(${g.id})"
                                    style="background:var(--verde);color:#fff;border:none;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer;font-weight:700">Salvar</button>
                                <button onclick="cancelarEdicaoGrupo(${g.id})"
                                    style="background:var(--surface-3);color:var(--texto-muted);border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer">✕</button>
                            </div>
                        </div>
                        <!-- Ações -->
                        <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
                            <button onclick="iniciarEdicaoGrupo(${g.id})"
                                style="background:none;border:1px solid var(--borda-2);color:var(--texto-muted);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;font-weight:600;">
                                ✏ Editar</button>
                            <button onclick="abrirGerenciarGrupoAdmin(${g.id}, '${escHtml(g.nome).replace(/'/g,"\\'")}' )"
                                style="background:var(--verde);color:#fff;border:none;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;font-weight:600;">
                                👥 Membros</button>
                        </div>
                    </div>
                </div>`
            ).join('');
    } catch (e) {
        lista.innerHTML = '<p style="color:var(--texto-muted);font-size:13px">Erro ao carregar grupos.</p>';
    }
}

// ── Editar nome/ícone de grupo inline ──
function iniciarEdicaoGrupo(id) {
    document.getElementById(`grupoViz-${id}`).style.display = 'none';
    document.getElementById(`grupoEd-${id}`).style.display  = 'flex';
    document.getElementById(`grupoNomeInp-${id}`)?.focus();
}
function cancelarEdicaoGrupo(id) {
    document.getElementById(`grupoViz-${id}`).style.display = 'flex';
    document.getElementById(`grupoEd-${id}`).style.display  = 'none';
}
async function salvarEdicaoGrupo(id) {
    const nome  = document.getElementById(`grupoNomeInp-${id}`)?.value.trim();
    const icone = document.getElementById(`grupoIconeInp-${id}`)?.value.trim() || '👥';
    if (!nome) { mostrarToast('Informe o nome do grupo.', 'aviso'); return; }
    try {
        const res  = await fetch('api/editar_grupo.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ grupo_id: id, nome, icone })
        });
        const data = await res.json();
        if (data.success) {
            mostrarToast('✅ Grupo atualizado!');
            carregarGruposAdmin();
        } else {
            mostrarToast(data.error ?? 'Erro ao salvar.', 'erro');
        }
    } catch (e) { mostrarToast('Erro de conexão.', 'erro'); }
}

let grupoGerenciarAtual = null;

async function abrirGerenciarGrupoAdmin(grupoId, grupoNome) {
    grupoGerenciarAtual = grupoId;
    document.getElementById('gerenciarGrupoTitulo').textContent = '👥 ' + grupoNome;
    document.getElementById('gerenciarGrupoCorpo').innerHTML =
        '<div style="text-align:center;padding:20px;color:var(--texto-muted)">Carregando...</div>';
    abrirModal('modalGerenciarGrupo');
    await recarregarMembrosGrupo(grupoId);
}

async function abrirGerenciarGrupo() {
    // Chamado pelo botão no header do chat (quando está em um grupo)
    if (chatAtualTipo !== 'grupo' || !chatAtualId) return;
    const nome = document.getElementById('chatNome')?.textContent || 'Grupo';
    await abrirGerenciarGrupoAdmin(chatAtualId, nome);
}

async function recarregarMembrosGrupo(grupoId) {
    const corpo = document.getElementById('gerenciarGrupoCorpo');
    if (!corpo) return;
    try {
        const res  = await fetch(`api/gerenciar_grupo.php?acao=membros&grupo_id=${grupoId}`);
        const data = await res.json();
        if (!data.success) { corpo.innerHTML = '<p style="color:var(--erro)">Erro ao carregar membros.</p>'; return; }

        corpo.innerHTML =
            `<div style="font-size:11px;font-weight:800;color:var(--texto-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">
                Clique para adicionar ou remover membros
            </div>` +
            data.usuarios.map(u => {
                const isMembro = u.membro == 1;
                return `
                <div style="display:flex;align-items:center;gap:10px;padding:9px 10px;background:var(--surface-2);border-radius:10px;margin-bottom:6px;opacity:${isMembro ? '1' : '0.65'}">
                    <div class="avatar-sm avatar-letra">${(u.nome_completo ?? '?').charAt(0).toUpperCase()}</div>
                    <div style="flex:1">
                        <div style="font-size:13.5px;font-weight:600">${escHtml(u.nome_completo)}</div>
                        <div style="font-size:11px;color:var(--verde-light)">${u.role === 'admin' ? '⭐ Admin' : '👤 Funcionário'}</div>
                    </div>
                    <button onclick="toggleMembroGrupo(${grupoId}, ${u.id}, ${isMembro ? 1 : 0})"
                        style="background:${isMembro ? 'rgba(244,63,94,0.15)' : 'rgba(5,150,105,0.15)'};
                               color:${isMembro ? '#f43f5e' : 'var(--verde)'};
                               border:none;border-radius:8px;padding:5px 12px;font-size:12px;cursor:pointer;font-weight:600">
                        ${isMembro ? 'Remover' : '+ Adicionar'}
                    </button>
                </div>`;
            }).join('') +
            // ── NOVO: botão excluir grupo no final do modal ──
            `<div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--borda)">
                <button onclick="excluirGrupo(${grupoId})"
                    style="width:100%;background:rgba(244,63,94,0.1);color:#f43f5e;border:1px solid rgba(244,63,94,0.3);border-radius:10px;padding:9px;font-size:13px;cursor:pointer;font-weight:600"
                    onmouseover="this.style.background='rgba(244,63,94,0.2)'"
                    onmouseout="this.style.background='rgba(244,63,94,0.1)'">
                    🗑️ Excluir este grupo
                </button>
            </div>`;
    } catch (e) {
        corpo.innerHTML = '<p style="color:var(--erro)">Erro de conexão.</p>';
    }
}

async function excluirGrupo(grupoId) {
    if (!confirm('Excluir este grupo? Todas as mensagens do grupo serão apagadas.')) return;
    try {
        const res  = await fetch('api/gerenciar_grupo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'excluir', grupo_id: grupoId })
        });
        const data = await res.json();
        if (data.success) {
            fecharModal('modalGerenciarGrupo');
            mostrarToast('Grupo excluído.');
            setTimeout(() => location.reload(), 1200);
        } else {
            mostrarToast(data.error ?? 'Erro ao excluir grupo.', 'erro');
        }
    } catch (e) {
        mostrarToast('Erro de conexão.', 'erro');
    }
}

async function toggleMembroGrupo(grupoId, usuarioId, isMembro) {
    const acao = isMembro ? 'remover' : 'adicionar';
    try {
        const res  = await fetch('api/gerenciar_grupo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao, grupo_id: grupoId, usuario_id: usuarioId })
        });
        const data = await res.json();
        if (data.success) {
            mostrarToast(isMembro ? 'Membro removido.' : '✅ Membro adicionado!');
            await recarregarMembrosGrupo(grupoId);
            if (EU.role === 'admin') carregarGruposAdmin();
        } else {
            mostrarToast(data.error ?? 'Erro ao atualizar membro.', 'erro');
        }
    } catch (e) {
        mostrarToast('Erro de conexão.', 'erro');
    }
}

// ══════════════════════════════════════════════════════════════════
// 11. CRIAR GRUPO
// ══════════════════════════════════════════════════════════════════
async function criarGrupo() {
    const nome   = document.getElementById('grupoNome')?.value.trim();
    const icone  = document.getElementById('grupoIcone')?.value.trim() || '👥';
    const checks = document.querySelectorAll('[name=membros]:checked');
    const membros = Array.from(checks).map(c => Number(c.value));

    if (!nome) { mostrarToast('Informe o nome do grupo.', 'aviso'); return; }

    try {
        const res  = await fetch('api/criar_grupo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome, icone, membros })
        });
        const data = await res.json();

        if (data.success) {
            fecharModal('modalGrupo');
            mostrarToast('✅ Grupo criado!');
            setTimeout(() => location.reload(), 1200);
        } else {
            mostrarToast(data.error ?? 'Erro ao criar grupo.', 'erro');
        }
    } catch (e) {
        // ── CORRIGIDO: string UTF-8 correto ──
        mostrarToast('Erro de conexão.', 'erro');
    }
}

// ══════════════════════════════════════════════════════════════════
// 12. BUSCA DE MENSAGENS (header)
// ══════════════════════════════════════════════════════════════════
function buscarMensagens(query) {
    if (!query || !chatAtualId) return;
    const baloes = document.querySelectorAll('#mensagensArea .balao');
    baloes.forEach(b => {
        const txt = b.textContent.toLowerCase();
        b.style.opacity = txt.includes(query.toLowerCase()) ? '1' : '0.25';
    });
    if (!query) baloes.forEach(b => b.style.opacity = '1');
}

// ══════════════════════════════════════════════════════════════════
// 13. MODAL HELPERS
// ══════════════════════════════════════════════════════════════════
function abrirModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('show');
}
function fecharModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('show');
}
function fecharModalFora(e, id) {
    if (e.target.id === id) fecharModal(id);
}

// ══════════════════════════════════════════════════════════════════
// 14. MENU PERFIL
// ══════════════════════════════════════════════════════════════════
function toggleMenuPerfil() {
    document.getElementById('menuPerfil')?.classList.toggle('show');
}
document.addEventListener('click', e => {
    const btn  = document.getElementById('perfilBtn');
    const menu = document.getElementById('menuPerfil');
    if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('show');
    }
});

// ══════════════════════════════════════════════════════════════════
// 15. SIDEBAR TOGGLE
// CORRIGIDO: agora funciona no desktop também (colapsa a sidebar)
// ══════════════════════════════════════════════════════════════════
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    if (window.innerWidth <= 700) {
        sidebar.classList.toggle('open');   // mobile: slide-in
    } else {
        sidebar.classList.toggle('fechada'); // desktop: colapsa com animação
    }
}

// ══════════════════════════════════════════════════════════════════
// 16. ADMIN TAB
// ══════════════════════════════════════════════════════════════════
function adminTab(aba, btn) {
    document.querySelectorAll('.admin-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    document.getElementById('adminUsuarios').style.display = aba === 'usuarios' ? 'block' : 'none';
    document.getElementById('adminGrupos').style.display   = aba === 'grupos'   ? 'block' : 'none';

    if (aba === 'usuarios') carregarUsuariosAdmin();
    if (aba === 'grupos')   carregarGruposAdmin();
}

// ══════════════════════════════════════════════════════════════════
// 16b. MODAL CONFIGURAÇÕES
// ══════════════════════════════════════════════════════════════════
function abrirModalConfiguracoes() {
    document.getElementById('menuPerfil')?.classList.remove('show');
    abrirModal('modalConfiguracoes');
    cfgTab('conta', document.getElementById('cfgTabConta'));
    // Limpa campos
    ['cfgNovoUsername','cfgSenhaAtual','cfgNovaSenha','cfgConfirmarSenha'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const msg = document.getElementById('cfgContaMensagem');
    if (msg) msg.style.display = 'none';
}

function cfgTab(aba, btn) {
    document.querySelectorAll('#modalConfiguracoes .admin-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const conta     = document.getElementById('cfgConta');
    const historico = document.getElementById('cfgHistorico');
    if (conta)     conta.style.display     = aba === 'conta'     ? 'block' : 'none';
    if (historico) historico.style.display = aba === 'historico' ? 'block' : 'none';

    if (aba === 'historico') carregarHistoricoRemovidos();
}

async function salvarAlteracoesConta() {
    const username    = document.getElementById('cfgNovoUsername')?.value.trim() || '';
    const senhaAtual  = document.getElementById('cfgSenhaAtual')?.value  || '';
    const novaSenha   = document.getElementById('cfgNovaSenha')?.value   || '';
    const confirmar   = document.getElementById('cfgConfirmarSenha')?.value || '';
    const msgEl       = document.getElementById('cfgContaMensagem');

    function mostrarMsg(texto, sucesso) {
        msgEl.style.display = 'block';
        msgEl.style.background    = sucesso ? 'rgba(5,150,105,0.12)' : 'rgba(244,63,94,0.12)';
        msgEl.style.border        = sucesso ? '1px solid rgba(5,150,105,0.4)' : '1px solid rgba(244,63,94,0.4)';
        msgEl.style.color         = sucesso ? '#6ee7b7' : '#fca5a5';
        msgEl.textContent         = texto;
    }

    if (!username && !senhaAtual && !novaSenha) {
        mostrarMsg('Preencha ao menos um campo para salvar.', false); return;
    }
    if (novaSenha || confirmar) {
        if (!senhaAtual) { mostrarMsg('Informe a senha atual para trocar a senha.', false); return; }
        if (novaSenha.length < 6) { mostrarMsg('Nova senha deve ter pelo menos 6 caracteres.', false); return; }
        if (novaSenha !== confirmar) { mostrarMsg('As senhas não coincidem.', false); return; }
    }

    try {
        const res  = await fetch('api/alterar_conta.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ username, senha_atual: senhaAtual, nova_senha: novaSenha }),
        });
        const data = await res.json();
        if (data.success) {
            mostrarMsg('Alterações salvas com sucesso!', true);
            ['cfgSenhaAtual','cfgNovaSenha','cfgConfirmarSenha'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        } else {
            mostrarMsg(data.error || 'Erro ao salvar.', false);
        }
    } catch (e) {
        mostrarMsg('Erro de conexão.', false);
    }
}

async function carregarHistoricoRemovidos() {
    const corpo = document.getElementById('cfgHistoricoCorpo');
    if (!corpo) return;
    corpo.innerHTML = '<div style="text-align:center;padding:20px;color:var(--texto-muted)">Carregando...</div>';
    try {
        const res  = await fetch('api/historico_removidos.php');
        const data = await res.json();
        if (!data.success) { corpo.innerHTML = `<p style="color:var(--erro);padding:12px">${data.error}</p>`; return; }

        let html = '';

        if (data.usuarios && data.usuarios.length) {
            html += `<h4 style="color:var(--texto-muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Funcionários removidos (${data.usuarios.length})</h4>`;
            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:20px;">';
            data.usuarios.forEach(u => {
                html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface-2);border-radius:10px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:var(--surface-3);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--texto-muted);">👤</div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--texto-muted);">${u.username || 'removido'}</div>
                        <div style="font-size:11px;color:var(--texto-faint);">ID ${u.id} — removido em ${u.removido_em || 'data desconhecida'}</div>
                    </div>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p style="color:var(--texto-muted);font-size:13px;margin-bottom:16px;">Nenhum funcionário removido.</p>';
        }

        if (data.grupos && data.grupos.length) {
            html += `<h4 style="color:var(--texto-muted);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">Grupos excluídos (${data.grupos.length})</h4>`;
            html += '<div style="display:flex;flex-direction:column;gap:6px;">';
            data.grupos.forEach(g => {
                html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface-2);border-radius:10px;">
                    <div style="font-size:22px;">${g.icone || '👥'}</div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--texto-muted);">${g.nome}</div>
                        <div style="font-size:11px;color:var(--texto-faint);">Excluído em ${g.excluido_em || 'data desconhecida'}</div>
                    </div>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p style="color:var(--texto-muted);font-size:13px;">Nenhum grupo excluído.</p>';
        }

        corpo.innerHTML = html;
    } catch(e) {
        corpo.innerHTML = '<p style="color:var(--erro);padding:12px">Erro ao carregar histórico.</p>';
    }
}

// ══════════════════════════════════════════════════════════════════
// 17. FOTO VIEWER (lightbox simples)
// ══════════════════════════════════════════════════════════════════
function abrirFoto(url) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;z-index:9999;cursor:zoom-out';
    overlay.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.8)"/>`;
    overlay.onclick = () => overlay.remove();
    document.body.appendChild(overlay);
}

// ══════════════════════════════════════════════════════════════════
// 18. TOAST
// ══════════════════════════════════════════════════════════════════
function mostrarToast(msg, tipo = 'ok') {
    const t = document.getElementById('toast');
    const s = document.getElementById('toastMsg');
    if (!t || !s) return;
    s.textContent = msg;
    t.className   = 'toast show ' + (tipo !== 'ok' ? tipo : '');
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ══════════════════════════════════════════════════════════════════
// 19. UTILITÁRIO: escapar HTML
// ══════════════════════════════════════════════════════════════════
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ══════════════════════════════════════════════════════════════════
// 20. SELETOR DE TEMAS
// ══════════════════════════════════════════════════════════════════

const TODOS_TEMAS = ['azul-mega', 'noite', 'ceu', 'creme', 'floresta', 'roxo', 'cinza', 'rosa'];

// Remove classes de tema e limpa vars CSS inline (usadas pelo color picker)
function resetTema() {
    TODOS_TEMAS.forEach(t => document.body.classList.remove('tema-' + t));
    const vars = [
        '--bg-gradient', '--sidebar-gradient', '--header-gradient',
        '--chat-gradient', '--grupos-gradient',
        '--surface-1', '--surface-2', '--surface-3', '--surface-glass',
        '--texto', '--texto-2', '--texto-muted', '--texto-faint',
        '--borda', '--borda-2', '--msg-eu', '--msg-outro'
    ];
    vars.forEach(v => document.documentElement.style.removeProperty(v));
    document.body.style.removeProperty('background');
}

// Aplica um tema pré-definido (ou o padrão se tema = '')
function aplicarTema(tema, salvar = true) {
    resetTema();
    if (tema) document.body.classList.add('tema-' + tema);
    if (salvar) localStorage.setItem('tema_chat', tema);

    // Marca o swatch ativo
    document.querySelectorAll('.tema-swatch').forEach(s => {
        s.classList.toggle('ativo', s.dataset.tema === tema);
    });

    // Atualiza o color picker com a cor base do tema selecionado
    const coresBase = {
        '':          '#eef0f5', 'azul-mega': '#1a2744', 'noite':   '#0d1117',
        'ceu':       '#dbeafe', 'creme':     '#fdf6e8', 'floresta':'#ecfdf5',
        'roxo':      '#f4f0ff', 'cinza':     '#f1f5f9', 'rosa':    '#fff1f2'
    };
    const cor = coresBase[tema] || '#eef0f5';
    const ci = document.getElementById('colorPickerInput');
    const ch = document.getElementById('colorPickerHex');
    if (ci) ci.value = cor;
    if (ch) ch.value = cor;
}

// ── Toggle do painel de temas ──
function togglePainelTemas() {
    const painel = document.getElementById('painelTemas');
    const btn    = document.getElementById('btnTemas');
    if (!painel) return;
    const aberto = painel.classList.toggle('show');
    btn?.classList.toggle('ativo', aberto);
    // Fecha menu perfil se estiver aberto
    document.getElementById('menuPerfil')?.classList.remove('show');
}

// Fecha painel ao clicar fora
document.addEventListener('click', e => {
    const painel = document.getElementById('painelTemas');
    const btn    = document.getElementById('btnTemas');
    if (painel?.classList.contains('show') && !painel.contains(e.target) && e.target !== btn && !btn?.contains(e.target)) {
        painel.classList.remove('show');
        btn?.classList.remove('ativo');
    }
});

// ── Color Picker personalizado ──

// Sincroniza o input[type=color] com o campo de texto
function sincronizarColorPicker(hex) {
    const ch = document.getElementById('colorPickerHex');
    if (ch) ch.value = hex;
}

// Valida o campo de texto hex e aplica automaticamente quando completo
function validarHexInput(valor) {
    const hex = valor.startsWith('#') ? valor : '#' + valor;
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        const ci = document.getElementById('colorPickerInput');
        if (ci) ci.value = hex;
        aplicarCorCustom(hex, true);
    }
}

// Aplica a cor do input de texto ao clicar OK
function aplicarCorDoInput() {
    const ch  = document.getElementById('colorPickerHex');
    const val = ch?.value || '';
    const hex = val.startsWith('#') ? val : '#' + val;
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        aplicarCorCustom(hex, true);
    }
}

// Aplica cor personalizada: gera superfícies derivadas automaticamente
function aplicarCorCustom(hex, salvar = true) {
    resetTema();

    const [h, s, l] = hexToHsl(hex);
    const escuro = l < 42;

    // Gradientes derivados
    const bgSidebar = hslToHex(h, s, Math.min(l + 5, 95));
    const bgChat    = hslToHex(h, s, Math.max(l - 4, 4));
    const bgGrupos  = hslToHex(h, s, Math.min(l + 3, 94));
    const bgHeader  = hslToHex(h, Math.max(s - 5, 0), Math.min(l + 7, 97));

    // Superfícies
    const s1 = escuro ? hslToHex(h, Math.max(s - 5, 0), Math.min(l + 12, 88)) : '#ffffff';
    const s2 = escuro ? hslToHex(h, Math.max(s - 5, 0), Math.min(l + 8,  85)) : hslToHex(h, Math.max(s - 15, 0), Math.min(l + 10, 97));
    const s3 = escuro ? hslToHex(h, Math.max(s - 5, 0), Math.min(l + 16, 90)) : hslToHex(h, Math.max(s - 10, 0), Math.min(l + 6,  94));
    const [r1, g1, b1] = hexToRgb(s1);
    const glassAlpha = escuro ? '0.82' : '0.76';

    // Texto
    const texto      = escuro ? '#f1f5f9' : '#1e293b';
    const texto2     = escuro ? '#e2e8f0' : '#334155';
    const textoMuted = escuro ? '#94a3b8' : '#64748b';
    const textoFaint = escuro ? '#475569' : '#94a3b8';
    const borda      = escuro ? 'rgba(255,255,255,0.10)' : 'rgba(0,0,0,0.08)';
    const borda2     = escuro ? 'rgba(255,255,255,0.16)' : 'rgba(0,0,0,0.12)';
    const msgEu      = escuro ? 'rgba(5,150,105,0.28)' : hslToHex(h, Math.min(s + 10, 80), Math.min(l + 22, 94));
    const msgOutro   = escuro ? `rgba(${r1},${g1},${b1},0.12)` : '#ffffff';

    const root = document.documentElement;
    root.style.setProperty('--surface-1',     s1);
    root.style.setProperty('--surface-2',     s2);
    root.style.setProperty('--surface-3',     s3);
    root.style.setProperty('--surface-glass', `rgba(${r1},${g1},${b1},${glassAlpha})`);
    root.style.setProperty('--texto',         texto);
    root.style.setProperty('--texto-2',       texto2);
    root.style.setProperty('--texto-muted',   textoMuted);
    root.style.setProperty('--texto-faint',   textoFaint);
    root.style.setProperty('--borda',         borda);
    root.style.setProperty('--borda-2',       borda2);
    root.style.setProperty('--msg-eu',        msgEu);
    root.style.setProperty('--msg-outro',     msgOutro);
    root.style.setProperty('--bg-gradient',      `linear-gradient(135deg, ${bgSidebar} 0%, ${hex} 40%, ${bgChat} 100%)`);
    root.style.setProperty('--sidebar-gradient', `linear-gradient(180deg, ${bgSidebar} 0%, ${hslToHex(h, s, Math.max(l - 2, 4))} 100%)`);
    root.style.setProperty('--header-gradient',  `linear-gradient(135deg, ${bgHeader} 0%, ${s1} 100%)`);
    root.style.setProperty('--chat-gradient',    `linear-gradient(160deg, ${bgChat} 0%, ${hex} 50%, ${hslToHex(h, s, Math.max(l - 6, 3))} 100%)`);
    root.style.setProperty('--grupos-gradient',  `linear-gradient(135deg, ${bgGrupos} 0%, ${bgSidebar} 100%)`);

    if (salvar) localStorage.setItem('tema_chat', 'custom:' + hex);

    // Atualiza inputs
    const ci = document.getElementById('colorPickerInput');
    const ch = document.getElementById('colorPickerHex');
    if (ci) ci.value = hex;
    if (ch) ch.value = hex;

    // Desmarca swatches
    document.querySelectorAll('.tema-swatch').forEach(s => s.classList.remove('ativo'));
}

// ── Utilitários de cor ──
function hexToHsl(hex) {
    let r = parseInt(hex.slice(1,3), 16) / 255;
    let g = parseInt(hex.slice(3,5), 16) / 255;
    let b = parseInt(hex.slice(5,7), 16) / 255;
    const max = Math.max(r,g,b), min = Math.min(r,g,b);
    let h, s, l = (max + min) / 2;
    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            default: h = (r - g) / d + 4;
        }
        h /= 6;
    }
    return [h * 360, s * 100, l * 100];
}

function hslToHex(h, s, l) {
    h /= 360; s /= 100; l /= 100;
    const k = n => (n + h * 12) % 12;
    const a = s * Math.min(l, 1 - l);
    const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return '#' + [f(0), f(8), f(4)].map(x => Math.round(x * 255).toString(16).padStart(2, '0')).join('');
}

function hexToRgb(hex) {
    return [
        parseInt(hex.slice(1,3), 16),
        parseInt(hex.slice(3,5), 16),
        parseInt(hex.slice(5,7), 16)
    ];
}

// ══════════════════════════════════════════════════════════════════
// SELETOR DE EMOJIS
// ══════════════════════════════════════════════════════════════════
const EMOJIS = [
    // Rostos
    {e:'😀',n:'sorriso feliz grinning'},{e:'😃',n:'rindo alegre'},{e:'😄',n:'rindo muito'},{e:'😁',n:'feliz beaming'},
    {e:'😆',n:'gargalhando laughing'},{e:'😅',n:'suando nervoso sweat'},{e:'🤣',n:'rolando rindo rofl'},{e:'😂',n:'chorando rindo joy'},
    {e:'🙂',n:'leve sorriso slight smile'},{e:'🙃',n:'de cabeça pra baixo upside'},{e:'😉',n:'piscando wink'},{e:'😊',n:'envergonhado blush'},
    {e:'😇',n:'anjinho inocente halo'},{e:'🥰',n:'apaixonado smiling hearts'},{e:'😍',n:'olhos de coração heart eyes'},
    {e:'🤩',n:'estrelado star struck'},{e:'😘',n:'beijinho kiss'},{e:'😗',n:'beijo kissing'},{e:'😚',n:'beijo fechado kissing closed'},
    {e:'😙',n:'beijo smile kissing'},{e:'🥲',n:'sorrindo chorando'},{e:'😋',n:'gostoso yum'},{e:'😛',n:'língua tongue'},
    {e:'😜',n:'língua piscando winking tongue'},{e:'🤪',n:'louco zany'},{e:'😝',n:'língua fechado'},{e:'🤑',n:'dinheiro money mouth'},
    {e:'🤗',n:'abraço hugging'},{e:'🤭',n:'mão na boca hand over mouth'},{e:'🤫',n:'quieto shushing'},{e:'🤔',n:'pensando thinking'},
    {e:'🤐',n:'boca fechada zipper mouth'},{e:'🤨',n:'sobrancelha eyebrow'},{e:'😐',n:'neutro neutral'},{e:'😑',n:'expressionless'},
    {e:'😶',n:'sem boca no mouth'},{e:'😏',n:'malicioso smirking'},{e:'😒',n:'unamused desanimado'},{e:'🙄',n:'olhos virando rolling eyes'},
    {e:'😬',n:'grimacing tenso'},{e:'🤥',n:'mentira pinocchio lying'},{e:'😔',n:'pensativo pensive'},{e:'😪',n:'sonolento sleepy'},
    {e:'🤤',n:'babando drooling'},{e:'😴',n:'dormindo sleeping'},{e:'😷',n:'máscara mask doente'},{e:'🤒',n:'termômetro sick'},
    {e:'🤕',n:'curativo hurt'},{e:'🤢',n:'nauseado nauseous'},{e:'🤮',n:'vomitando vomiting'},{e:'🤧',n:'espirrando sneezing'},
    {e:'🥵',n:'calor hot'},{e:'🥶',n:'frio cold'},{e:'🥴',n:'tonto woozy'},{e:'😵',n:'atordoado dizzy'},{e:'🤯',n:'cabeça explodindo'},
    {e:'😎',n:'óculos de sol cool'},{e:'🥸',n:'disfarce disguised'},{e:'😕',n:'confuso confused'},{e:'🫤',n:'diagonal mouth'},
    {e:'😟',n:'preocupado worried'},{e:'🙁',n:'levemente triste frowning'},{e:'😮',n:'surpreso open mouth'},{e:'😯',n:'hushed'},
    {e:'😲',n:'chocado astonished'},{e:'😳',n:'envergonhado flushed'},{e:'🥺',n:'pleading implorando'},{e:'😦',n:'frowning'},
    {e:'😧',n:'angustiado anguished'},{e:'😨',n:'com medo fearful'},{e:'😰',n:'ansioso anxious'},{e:'😥',n:'sad relieved'},
    {e:'😢',n:'chorando crying'},{e:'😭',n:'chorando muito sobbing'},{e:'😱',n:'gritando screaming'},{e:'😖',n:'confounded'},
    {e:'😣',n:'persevering'},{e:'😞',n:'decepcionado disappointed'},{e:'😓',n:'downcast sweat'},{e:'😩',n:'weary cansado'},
    {e:'😫',n:'tired exausto'},{e:'🥱',n:'bocejando yawning'},{e:'😤',n:'bufando triumph'},{e:'😡',n:'com raiva pouting'},
    {e:'😠',n:'angry bravo'},{e:'🤬',n:'xingando symbols'},{e:'👿',n:'diabinho imp'},{e:'💀',n:'caveira skull morto'},
    // Gestos
    {e:'👍',n:'positivo joinha thumbs up'},{e:'👎',n:'negativo thumbs down'},{e:'👌',n:'ok certo'},{e:'✌️',n:'paz vitória victory'},
    {e:'🤞',n:'dedos cruzados crossed fingers'},{e:'🤟',n:'love you'},{e:'🤙',n:'call me'},{e:'👈',n:'apontando esquerda'},
    {e:'👉',n:'apontando direita'},{e:'👆',n:'apontando cima'},{e:'👇',n:'apontando baixo'},{e:'☝️',n:'indicador'},
    {e:'✋',n:'mão levantada raised hand'},{e:'🤚',n:'mão costas'},{e:'🖐️',n:'mão aberta'},{e:'🖖',n:'vulcano spock'},
    {e:'👋',n:'tchauzinho waving'},{e:'🤜',n:'soco fist right'},{e:'🤛',n:'soco fist left'},{e:'👊',n:'soco punch'},
    {e:'✊',n:'punho raised fist'},{e:'🙌',n:'palmas raising hands'},{e:'👏',n:'aplausos clapping'},{e:'🙏',n:'orando please obrigado'},
    {e:'🤝',n:'aperto de mão handshake'},{e:'💪',n:'forte músculo flexed'},{e:'🦾',n:'braço mecânico'},{e:'🦿',n:'perna mecânica'},
    // Corações e símbolos
    {e:'❤️',n:'coração vermelho red heart'},{e:'🧡',n:'coração laranja'},{e:'💛',n:'coração amarelo'},{e:'💚',n:'coração verde'},
    {e:'💙',n:'coração azul'},{e:'💜',n:'coração roxo'},{e:'🖤',n:'coração preto'},{e:'🤍',n:'coração branco'},
    {e:'💔',n:'coração partido broken'},{e:'❣️',n:'exclamação coração'},{e:'💕',n:'dois corações'},{e:'💞',n:'corações girando'},
    {e:'💓',n:'coração batendo'},{e:'💗',n:'coração crescendo'},{e:'💖',n:'coração brilhante'},{e:'💘',n:'seta cupido'},
    {e:'💝',n:'coração laço'},{e:'💟',n:'decoração coração'},{e:'☮️',n:'paz peace'},{e:'✝️',n:'cruz'},
    {e:'⭐',n:'estrela star'},{e:'🌟',n:'estrela brilhante glowing'},{e:'💫',n:'tonteando dizzy'},{e:'✨',n:'brilho sparkles'},
    {e:'🔥',n:'fogo fire'},{e:'💥',n:'explosão collision'},{e:'💯',n:'cem por cento hundred'},{e:'✅',n:'ok check verde'},
    {e:'❌',n:'errado x cross'},{e:'⚠️',n:'aviso warning'},{e:'🚨',n:'alerta sirene'},{e:'💬',n:'balão mensagem'},
    {e:'💭',n:'pensamento thought'},{e:'📌',n:'alfinete pin'},{e:'📎',n:'clipe paperclip'},{e:'🔗',n:'link corrente'},
    {e:'📊',n:'gráfico barras bar chart'},{e:'📈',n:'gráfico subindo'},{e:'📉',n:'gráfico caindo'},{e:'📋',n:'prancheta clipboard'},
    {e:'📝',n:'memo nota escrita'},{e:'✏️',n:'lápis pencil'},{e:'🔍',n:'lupa busca search'},{e:'📞',n:'telefone phone'},
    {e:'💻',n:'computador laptop'},{e:'🖥️',n:'monitor desktop'},{e:'📱',n:'celular smartphone'},{e:'⌨️',n:'teclado keyboard'},
    {e:'🖱️',n:'mouse'},{e:'💾',n:'disquete save'},{e:'📂',n:'pasta folder'},{e:'📁',n:'pasta fechada'},
    {e:'🗂️',n:'fichário tabs'},{e:'🗃️',n:'arquivo card box'},{e:'🗑️',n:'lixo trash'},{e:'🔐',n:'cadeado fechado locked'},
    {e:'🔑',n:'chave key'},{e:'🔧',n:'chave inglesa wrench'},{e:'⚙️',n:'engrenagem gear'},{e:'🛠️',n:'ferramentas tools'},
    // Objetos
    {e:'🎉',n:'festa comemoração party'},{e:'🎊',n:'confete confetti'},{e:'🎁',n:'presente gift'},{e:'🏆',n:'troféu trophy'},
    {e:'🥇',n:'medalha ouro first medal'},{e:'🎯',n:'alvo dardo bullseye'},{e:'💡',n:'ideia lâmpada bulb'},
    {e:'📣',n:'megafone loudspeaker'},{e:'📢',n:'anúncio bullhorn'},{e:'🔔',n:'sino bell'},
    {e:'⏰',n:'alarme alarm clock'},{e:'⏳',n:'ampulheta hourglass'},{e:'📅',n:'calendário calendar'},
    {e:'🚀',n:'foguete rocket'},{e:'🌈',n:'arco-íris rainbow'},{e:'☀️',n:'sol sun'},{e:'🌙',n:'lua moon'},
    // Animais
    {e:'🐶',n:'cachorro dog'},{e:'🐱',n:'gato cat'},{e:'🐭',n:'rato mouse'},{e:'🐰',n:'coelho rabbit'},
    {e:'🦊',n:'raposa fox'},{e:'🐻',n:'urso bear'},{e:'🐼',n:'panda'},{e:'🐨',n:'coala koala'},
    {e:'🦁',n:'leão lion'},{e:'🐯',n:'tigre tiger'},{e:'🦄',n:'unicórnio unicorn'},{e:'🐸',n:'sapo frog'},
    {e:'🐧',n:'pinguim penguin'},{e:'🦋',n:'borboleta butterfly'},{e:'🐝',n:'abelha bee'},{e:'🌸',n:'flor rosa flower'},
];

let emojiPainelAberto = false;

function inicializarEmojis() {
    renderizarEmojis(EMOJIS);
}

function renderizarEmojis(lista) {
    const grid = document.getElementById('emojiGrid');
    if (!grid) return;
    grid.innerHTML = lista.map(item =>
        `<button onclick="inserirEmoji('${item.e}')" title="${item.n}"
          style="background:none;border:none;cursor:pointer;font-size:22px;padding:4px 5px;
          border-radius:6px;line-height:1;transition:background .15s;"
          onmouseover="this.style.background='var(--surface-2)'"
          onmouseout="this.style.background='none'">${item.e}</button>`
    ).join('');
}

function filtrarEmojis(query) {
    const q = query.toLowerCase().trim();
    renderizarEmojis(q ? EMOJIS.filter(i => i.n.toLowerCase().includes(q) || i.e.includes(q)) : EMOJIS);
}

function togglePainelEmoji() {
    const painel = document.getElementById('painelEmoji');
    if (!painel) return;
    emojiPainelAberto = !emojiPainelAberto;
    painel.style.display = emojiPainelAberto ? 'flex' : 'none';
    if (emojiPainelAberto) {
        inicializarEmojis();
        const s = document.getElementById('emojiSearch');
        if (s) { s.value = ''; s.focus(); }
    }
}

function inserirEmoji(emoji) {
    const ta = document.getElementById('inputMensagem');
    if (!ta) return;
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + emoji + ta.value.slice(end);
    const pos = start + emoji.length;
    ta.setSelectionRange(pos, pos);
    ta.focus();
}

// Fecha o painel de emojis ao clicar fora
document.addEventListener('click', function(e) {
    if (!emojiPainelAberto) return;
    const painel = document.getElementById('painelEmoji');
    const btn    = document.getElementById('btnEmoji');
    if (painel && btn && !painel.contains(e.target) && !btn.contains(e.target)) {
        emojiPainelAberto = false;
        painel.style.display = 'none';
    }
});
