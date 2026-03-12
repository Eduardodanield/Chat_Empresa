// ══════════════════════════════════════════════════════════════════
//  CHAT MEGA AXNEN — tarefas.js
//  CORRIGIDO: encoding reescrito em UTF-8 (era Latin-1, quebrava strings)
// ══════════════════════════════════════════════════════════════════

// ── Estado global ──
let tarefaAtualAlerta  = null;
let filtroStatusAtual  = 'todas';
let intervalTarefas    = null;
let alertasDisparados  = new Set();

// ══════════════════════════════════════════════════════════════════
// 1. CARREGAR TAREFAS
// ══════════════════════════════════════════════════════════════════
async function carregarTarefas(status = filtroStatusAtual) {
    filtroStatusAtual = status;

    try {
        const data_hoje = document.getElementById('filtroData')?.value || '';
        const params    = new URLSearchParams({ status });
        if (data_hoje) params.append('data', data_hoje);

        const res  = await fetch('api/buscar_tarefas.php?' + params);
        const data = await res.json();

        if (!data.success) return;

        renderizarTarefas(data.tarefas);
        atualizarTotaisTab(data.totais);
        verificarAlertas(data.tarefas);

    } catch (e) {
        console.error('Erro ao carregar tarefas:', e);
    }
}

// ══════════════════════════════════════════════════════════════════
// 2. RENDERIZAR CARDS (estilo Trello — seções por status)
// ══════════════════════════════════════════════════════════════════
function renderizarTarefas(tarefas) {
    const lista = document.getElementById('listaTarefas');
    if (!lista) return;

    if (!tarefas.length) {
        lista.innerHTML = `
            <div style="text-align:center;padding:40px 20px;color:var(--texto-muted)">
                <div style="font-size:36px;margin-bottom:10px">📋</div>
                <div style="font-size:13px">Nenhuma tarefa encontrada</div>
            </div>`;
        return;
    }

    const secoes = [
        { key: 'pendente',   label: 'Pendentes',  cor: '#f59e0b' },
        { key: 'atrasada',   label: 'Atrasadas',  cor: '#ef4444' },
        { key: 'confirmada', label: 'Concluídas', cor: '#059669' },
        { key: 'cancelada',  label: 'Canceladas', cor: '#8696a0' },
    ];

    let html = '';
    secoes.forEach(sec => {
        const grupo = tarefas.filter(t => t.status === sec.key);
        if (!grupo.length) return;
        html += `
            <div class="tarefa-secao">
                <div class="tarefa-secao-header">
                    <span style="width:10px;height:10px;border-radius:50%;background:${sec.cor};display:inline-block;flex-shrink:0"></span>
                    ${sec.label}
                    <span class="tarefa-secao-count">${grupo.length}</span>
                </div>
                <div class="tarefa-secao-cards">
                    ${grupo.map(t => cardTarefa(t)).join('')}
                </div>
            </div>`;
    });

    lista.innerHTML = html;
}

function cardTarefa(t) {
    const corStatus = {
        pendente:   '#f59e0b',
        confirmada: '#059669',
        atrasada:   '#ef4444',
        cancelada:  '#8696a0',
    };

    const cor     = corStatus[t.status] || '#8696a0';
    const isMinha      = t.e_minha;
    const eOResponsavel = Number(EU.id) === Number(t.usuario_id);

    // Checkbox: ativo só para o responsável; admin vê desabilitado com tooltip
    const checkHtml = eOResponsavel ? `
        <div class="check-tarefa ${t.status === 'confirmada' ? 'confirmada' : ''}"
             onclick="toggleTarefa(${t.id}, '${t.status}')"
             title="${t.status === 'confirmada' ? 'Desmarcar' : 'Marcar como feita'}">
            ${t.status === 'confirmada' ? '✓' : ''}
        </div>` :
        `<div class="check-tarefa"
             style="cursor:not-allowed;opacity:0.35;background:var(--surface-3);"
             title="Apenas o responsável pode concluir esta tarefa">
        </div>`;

    // Nome do dono — admin vendo tarefa de outro funcionário
    const donoHtml = EU.role === 'admin' && !t.e_minha
        ? `<div class="tarefa-dono">👤 ${escHtml(t.usuario_nome)}</div>`
        : '';

    // Horário de conclusão
    const checkHoraHtml = t.horario_check
        ? `<span style="font-size:11px;color:var(--verde)">✔ ${t.horario_check}</span>`
        : '';

    // Botão deletar (admin ou dono)
    const deletarHtml = (EU.role === 'admin' || isMinha)
        ? `<button onclick="deletarTarefa(${t.id})" title="Cancelar tarefa"
               style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:14px;padding:2px 4px;border-radius:4px;transition:.2s"
               onmouseover="this.style.color='var(--erro)'" onmouseout="this.style.color='var(--muted)'">✕</button>`
        : '';

    return `
        <div class="tarefa-card ${t.status === 'confirmada' ? 'concluida' : ''}" id="tarefa-${t.id}">
            <div class="tarefa-stripe" style="background:${cor}"></div>
            ${donoHtml}
            <div class="tarefa-corpo">
                <div class="tarefa-topo">
                    ${checkHtml}
                    <div style="flex:1;overflow:hidden">
                        <div class="tarefa-titulo">${escHtml(t.titulo)}</div>
                        ${t.descricao ? `<div class="tarefa-desc">${escHtml(t.descricao)}</div>` : ''}
                    </div>
                    ${deletarHtml}
                </div>
                <div class="tarefa-meta">
                    <div style="display:flex;align-items:center;gap:6px">
                        <span>🕐</span>
                        <span class="tarefa-hora">${t.data_fmt} às ${t.horario_limite}</span>
                        ${t.alerta_proximo ? '<span style="font-size:10px;color:#f59e0b;animation:piscar 1s infinite">🔔 Urgente</span>' : ''}
                    </div>
                    ${checkHoraHtml}
                </div>
            </div>
        </div>
    `;
}

// ── Atualiza os números dos tabs ──
function atualizarTotaisTab(totais) {
    const tabs = {
        'todas':      document.getElementById('tab-todas'),
        'pendente':   document.getElementById('tab-pendente'),
        'confirmada': document.getElementById('tab-confirmada'),
        'atrasada':   document.getElementById('tab-atrasada'),
    };

    Object.entries(tabs).forEach(([key, el]) => {
        if (!el) return;
        const n    = totais[key] ?? 0;
        const base = el.dataset.label || el.textContent.replace(/\s*\d+$/, '').trim();
        el.dataset.label = base;
        el.textContent   = n > 0 ? `${base} ${n}` : base;
    });
}

// ══════════════════════════════════════════════════════════════════
// 3. CRIAR TAREFA
// ══════════════════════════════════════════════════════════════════

// Converte DD/MM/AAAA → YYYY-MM-DD para enviar à API
function dataBRparaISO(dataBR) {
    if (!dataBR || !/^\d{2}\/\d{2}\/\d{4}$/.test(dataBR)) return '';
    const [d, m, y] = dataBR.split('/');
    return `${y}-${m}-${d}`;
}

async function criarTarefa() {
    const titulo    = document.getElementById('tarefaTitulo')?.value.trim();
    const desc      = document.getElementById('tarefaDesc')?.value.trim() || '';
    const hora      = document.getElementById('tarefaHora')?.value;
    const entregaBR = document.getElementById('tarefaDataEntrega')?.value || '';
    const inicioBR  = document.getElementById('tarefaDataInicio')?.value  || '';
    const alvoEl    = document.getElementById('tarefaUsuario');
    const alvo      = alvoEl ? parseInt(alvoEl.value) : EU.id;
    const intervalo = parseInt(document.getElementById('tarefaIntervalo')?.value || '30');

    if (!titulo)    { mostrarToast('Informe o título da tarefa.', 'aviso'); return; }
    if (!entregaBR) { mostrarToast('Informe a data de entrega.', 'aviso'); return; }
    if (!hora)      { mostrarToast('Informe o horário limite.', 'aviso'); return; }

    const dataISO   = dataBRparaISO(entregaBR);
    const inicioISO = dataBRparaISO(inicioBR);
    if (!dataISO) { mostrarToast('Data de entrega inválida. Use DD/MM/AAAA.', 'aviso'); return; }

    try {
        const res = await fetch('api/criar_tarefa.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                titulo,
                descricao:        desc,
                horario_limite:   hora,
                data_tarefa:      dataISO,
                data_inicio:      inicioISO || null,
                usuario_alvo:     alvo,
                intervalo_alerta: intervalo,
            }),
        });
        const ret = await res.json();

        if (ret.success) {
            fecharModal('modalTarefa');
            limparFormTarefa();
            mostrarToast('✅ Tarefa criada com sucesso!');
            carregarTarefas();
        } else {
            mostrarToast(ret.error || 'Erro ao criar tarefa.', 'erro');
        }
    } catch (e) {
        console.error('Erro ao criar tarefa:', e);
    }
}

function limparFormTarefa() {
    ['tarefaTitulo','tarefaDesc','tarefaHora','tarefaDataEntrega','tarefaDataInicio'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const hint = document.getElementById('statusEntregaHint');
    if (hint) hint.className = 'cal-status-hint';
    const intervaloEl = document.getElementById('tarefaIntervalo');
    if (intervaloEl) intervaloEl.value = '30';
}

// ══════════════════════════════════════════════════════════════════
// 3b. CALENDÁRIO PICKER (vanilla JS, sem biblioteca)
// ══════════════════════════════════════════════════════════════════

let _calAtivo = null;

function abrirCalendario(campId, dropId) {
    if (_calAtivo === dropId) { fecharTodosCalendarios(); return; }
    fecharTodosCalendarios();
    _calAtivo = dropId;

    const val  = document.getElementById(campId)?.value || '';
    let ano = new Date().getFullYear(), mes = new Date().getMonth();
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(val)) {
        const [, m, y] = val.split('/');
        mes = parseInt(m) - 1; ano = parseInt(y);
    }
    renderizarCalendario(dropId, campId, ano, mes);
    document.getElementById(dropId)?.classList.add('show');
}

function fecharTodosCalendarios() {
    document.querySelectorAll('.cal-dropdown').forEach(d => d.classList.remove('show'));
    _calAtivo = null;
}

function renderizarCalendario(dropId, campId, ano, mes) {
    const drop = document.getElementById(dropId);
    if (!drop) return;

    const MESES = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                   'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const hoje = new Date();
    const hD = hoje.getDate(), hM = hoje.getMonth(), hA = hoje.getFullYear();

    const val  = document.getElementById(campId)?.value || '';
    let selD = null, selM = null, selA = null;
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(val)) {
        [selD, selM, selA] = val.split('/').map(Number);
        selM--;
    }

    const primeiroDia  = new Date(ano, mes, 1).getDay();
    const diasNoMes    = new Date(ano, mes + 1, 0).getDate();
    const mesAnterior  = mes === 0 ? 11  : mes - 1;
    const anoAnterior  = mes === 0 ? ano - 1 : ano;
    const mesProximo   = mes === 11 ? 0   : mes + 1;
    const anoProximo   = mes === 11 ? ano + 1 : ano;

    let html = `
        <div class="cal-nav">
            <button class="cal-nav-btn" type="button"
                onclick="renderizarCalendario('${dropId}','${campId}',${anoAnterior},${mesAnterior})">‹</button>
            <span class="cal-month-label">${MESES[mes]} ${ano}</span>
            <button class="cal-nav-btn" type="button"
                onclick="renderizarCalendario('${dropId}','${campId}',${anoProximo},${mesProximo})">›</button>
        </div>
        <div class="cal-grid">
            <div class="cal-day-label">D</div><div class="cal-day-label">S</div>
            <div class="cal-day-label">T</div><div class="cal-day-label">Q</div>
            <div class="cal-day-label">Q</div><div class="cal-day-label">S</div>
            <div class="cal-day-label">S</div>`;

    for (let i = 0; i < primeiroDia; i++) html += `<div class="cal-day empty"></div>`;

    for (let d = 1; d <= diasNoMes; d++) {
        const isHoje = d === hD && mes === hM && ano === hA;
        const isSel  = selA !== null && d === selD && mes === selM && ano === selA;
        const cls = ['cal-day', isHoje ? 'hoje' : '', isSel ? 'selecionado' : ''].filter(Boolean).join(' ');
        const dd  = String(d).padStart(2,'0'), mm = String(mes + 1).padStart(2,'0');
        html += `<div class="${cls}" onclick="selecionarDiaCalendario('${campId}','${dropId}','${dd}/${mm}/${ano}')">${d}</div>`;
    }

    html += `</div>`;
    drop.innerHTML = html;
}

function selecionarDiaCalendario(campId, dropId, dataBR) {
    const el = document.getElementById(campId);
    if (el) el.value = dataBR;
    fecharTodosCalendarios();
    if (campId === 'tarefaDataEntrega') atualizarStatusEntrega();
}

// Máscara automática DD/MM/AAAA enquanto o usuário digita
function mascaraData(input) {
    let v = input.value.replace(/\D/g,'');
    if (v.length > 2) v = v.slice(0,2) + '/' + v.slice(2);
    if (v.length > 5) v = v.slice(0,5) + '/' + v.slice(5,9);
    input.value = v;
    if (input.id === 'tarefaDataEntrega') atualizarStatusEntrega();
}

// Indicador colorido de prazo
function atualizarStatusEntrega() {
    const hint  = document.getElementById('statusEntregaHint');
    const valD  = document.getElementById('tarefaDataEntrega')?.value || '';
    const valH  = document.getElementById('tarefaHora')?.value || '';
    if (!hint) return;
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(valD)) { hint.className = 'cal-status-hint'; return; }

    const [d, m, y] = valD.split('/');
    const dt    = new Date(`${y}-${m}-${d}T${valH || '23:59'}`);
    const diffH = (dt - new Date()) / 3600000;

    if (diffH < 0) {
        hint.textContent = '⚠️ Data já passou — tarefa ficará como atrasada';
        hint.className   = 'cal-status-hint atrasado';
    } else if (diffH <= 24) {
        hint.textContent = '🟡 Entrega em breve (menos de 24 h)';
        hint.className   = 'cal-status-hint urgente';
    } else {
        hint.textContent = '✅ Prazo dentro do esperado';
        hint.className   = 'cal-status-hint ok';
    }
}

// Fecha calendário ao clicar fora
document.addEventListener('click', e => {
    if (!e.target.closest('.cal-picker') && !e.target.closest('.cal-btn-icon')) fecharTodosCalendarios();
});

// ══════════════════════════════════════════════════════════════════
// 4. TOGGLE: MARCAR / DESMARCAR TAREFA
// ══════════════════════════════════════════════════════════════════
async function toggleTarefa(id, statusAtual) {
    const acao = statusAtual === 'confirmada' ? 'desmarcar' : 'concluir';
    await confirmarTarefa(id, acao);
}

async function confirmarTarefa(id, acao) {
    try {
        const res  = await fetch('api/confirmar_tarefa.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ tarefa_id: id, acao }),
        });
        const data = await res.json();

        if (data.success) {
            carregarTarefas();
        } else {
            mostrarToast(data.error || 'Erro ao atualizar tarefa.', 'erro');
        }
    } catch (e) {
        console.error('Erro ao confirmar tarefa:', e);
    }
}

// ══════════════════════════════════════════════════════════════════
// 5. DELETAR TAREFA
// ══════════════════════════════════════════════════════════════════
async function deletarTarefa(id) {
    if (!confirm('Excluir esta tarefa?')) return;

    try {
        const res  = await fetch('api/confirmar_tarefa.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ tarefa_id: id, acao: 'cancelar' }),
        });
        const data = await res.json();
        if (data.success) {
            carregarTarefas(filtroStatusAtual);
            mostrarToast('Tarefa excluída.');
        } else {
            mostrarToast(data.error || 'Erro ao excluir.', 'erro');
        }
    } catch (e) {
        console.error('Erro ao deletar tarefa:', e);
    }
}

// ══════════════════════════════════════════════════════════════════
// 6. MODAL DE ALERTA (popup automático)
// ══════════════════════════════════════════════════════════════════
function verificarAlertas(tarefas) {
    // Alerta só para o dono da tarefa — nunca para o admin vendo tarefas da equipe
    const urgente = tarefas.find(t =>
        t.alerta_proximo &&
        t.status === 'pendente' &&
        !t.alerta_enviado &&
        !alertasDisparados.has(t.id) &&
        Number(t.usuario_id) === Number(EU.id)
    );

    if (urgente) {
        alertasDisparados.add(urgente.id);
        dispararAlerta(urgente);
    }
}

function tocarSomAlerta() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        // Três bipes urgentes: 880Hz → 1100Hz → 880Hz
        const bipes = [
            { freq: 880,  start: 0,    dur: 0.15 },
            { freq: 1100, start: 0.22, dur: 0.15 },
            { freq: 880,  start: 0.44, dur: 0.22 }
        ];
        bipes.forEach(b => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = b.freq;
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0, ctx.currentTime + b.start);
            gain.gain.linearRampToValueAtTime(0.45, ctx.currentTime + b.start + 0.01);
            gain.gain.linearRampToValueAtTime(0, ctx.currentTime + b.start + b.dur);
            osc.start(ctx.currentTime + b.start);
            osc.stop(ctx.currentTime + b.start + b.dur + 0.05);
        });
    } catch (e) {}
}

function dispararAlerta(tarefa) {
    tarefaAtualAlerta = tarefa.id;

    const tituloEl = document.getElementById('alertaTarefaTitulo');
    const descEl   = document.getElementById('alertaTarefaDesc');

    if (tituloEl) tituloEl.textContent = tarefa.titulo;
    // ── CORRIGIDO: "às" com UTF-8 correto ──
    if (descEl)   descEl.textContent   = `Prazo: ${tarefa.data_fmt} às ${tarefa.horario_limite}. Você está fazendo esta atividade?`;

    tocarSomAlerta();
    abrirModal('modalAlertaTarefa');
}

async function responderAlerta(estaFazendo) {
    if (!tarefaAtualAlerta) return;

    const acao = estaFazendo ? 'concluir' : 'adiar';
    fecharModal('modalAlertaTarefa');

    await confirmarTarefa(tarefaAtualAlerta, acao);

    // ── CORRIGIDO: strings UTF-8 correto ──
    if (estaFazendo) {
        mostrarToast('✅ Ótimo! Tarefa confirmada!');
    } else {
        mostrarToast('⚠️ O admin foi notificado.', 'aviso');
    }

    tarefaAtualAlerta = null;
}

// ══════════════════════════════════════════════════════════════════
// 7. FILTRO POR STATUS (tabs)
// ══════════════════════════════════════════════════════════════════
function filtrarTarefas(status, btn) {
    document.querySelectorAll('.tab-tarefa').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    carregarTarefas(status);
}

// ══════════════════════════════════════════════════════════════════
// 8. INICIALIZAÇÃO
// ══════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    carregarTarefas();
    intervalTarefas = setInterval(() => carregarTarefas(), 30000);
});

// ── Animação piscar para urgente ──
const styleAnim = document.createElement('style');
styleAnim.textContent = `@keyframes piscar { 0%,100%{opacity:1} 50%{opacity:.3} }`;
document.head.appendChild(styleAnim);
