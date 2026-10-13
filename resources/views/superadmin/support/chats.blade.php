@extends('superadmin.layouts.app')

@push('styles')
<style>
    .sc-wrap { height: calc(100vh - 170px); min-height: 520px; }
    .sc-list, .sc-stream { overflow-y: auto; }
    .sc-stream { background: #f7f8fa; }
    .sc-thread { cursor: pointer; border-left: 3px solid transparent; }
    .sc-thread.active { background: #eef4ff; border-left-color: #1e58b4; }
    .sc-thread.sc-new { background: #fff8e1; border-left-color: #f59f00; }
    .sc-thread.sc-new .sc-name, .sc-thread.sc-new .sc-last { font-weight: 700; color: #212529 !important; }
    .sc-msg { width: fit-content; max-width: 70%; padding: .5rem .75rem; border-radius: 10px; margin-bottom: .5rem; white-space: pre-wrap; word-break: break-word; }
    .sc-partner { background: #fff; border: 1px solid #e5e7eb; margin-right: auto; }
    .sc-agent { background: #1e58b4; color: #fff; margin-left: auto; }
    .sc-note { background: #fff3cd; color: #664d03; border: 1px dashed #e0b84c; margin-left: auto; }
    .sc-system { color: #6c757d; font-size: .8rem; text-align: center; margin: 0 auto .5rem; }
    .sc-meta { font-size: .72rem; opacity: .75; }
    .sc-msg a { color: inherit; text-decoration: underline; }
    .sc-img { display: block; max-width: 220px; max-height: 180px; border-radius: 6px; margin-top: .35rem; }
    .sc-canned { position: absolute; bottom: 100%; left: 0; right: 0; z-index: 5; max-height: 220px; overflow: auto; }
</style>
@endpush

@section('content')
<div class="row g-3 sc-wrap mt-1" id="scApp" data-feed="{{ route('superadmin.support.chats.feed') }}" data-base="{{ url('superadmin/support/chats') }}">
    <div class="col-lg-4 h-100">
        <div class="card h-100 mb-0">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="mb-0 me-auto">Live Chats</h5>
                <select id="scStatus" class="form-select form-select-sm w-auto">
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div class="card-body p-0 sc-list" id="scThreads"></div>
        </div>
    </div>
    <div class="col-lg-8 h-100">
        <div class="card h-100 mb-0 d-flex flex-column">
            <div class="card-header" id="scHeader"><span class="text-muted">Select a chat</span></div>
            <div class="card-body sc-stream flex-grow-1" id="scStream"></div>
            <div class="card-footer position-relative" id="scComposer" hidden>
                <div class="list-group sc-canned shadow" id="scCanned" hidden></div>
                <textarea id="scInput" class="form-control mb-2" rows="2" placeholder="Type a reply... use / for canned replies (Enter to send, Shift+Enter for new line)"></textarea>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check me-auto">
                        <input class="form-check-input" type="checkbox" id="scInternal">
                        <label class="form-check-label" for="scInternal">Internal note (agents only)</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="scSend">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="scTransferForm">
            <div class="modal-header">
                <h5 class="modal-title">Transfer chat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Agent</label>
                <select class="form-select mb-3" name="agent_id" id="scAgentSel"></select>
                <label class="form-label">or Department</label>
                <select class="form-select mb-3" name="team_id">
                    <option value="">-</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
                <label class="form-label">Internal note <span class="text-danger">*</span></label>
                <textarea class="form-control" name="note" rows="3" required minlength="5"></textarea>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Transfer</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id);
    const app = $('scApp'), base = app.dataset.base, canned = @json($canned);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const toast = (msg, ok = true) => window.alertify ? alertify[ok ? 'success' : 'error'](msg) : alert(msg);
    let chatId = null, lastCount = -1, agents = [];

    // Unread tracking: last message id the agent has seen per chat (first visit marks everything as seen).
    const pageTitle = document.title;
    let firstVisit = !localStorage.getItem('scSeen');
    const seen = JSON.parse(localStorage.getItem('scSeen') || '{}');
    const markSeen = t => {
        if (t.last_message_id > (seen[t.id] || 0)) {
            seen[t.id] = t.last_message_id;
            localStorage.setItem('scSeen', JSON.stringify(seen));
        }
    };
    const isNew = t => t.last_sender === 'partner' && t.last_message_id > (seen[t.id] || 0);

    async function post(url, body) {
        const r = await fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify(body)});
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.success) throw new Error(j.message || 'Request failed');
        return j;
    }

    async function load() {
        const r = await fetch(app.dataset.feed + '?' + new URLSearchParams({status: $('scStatus').value, chat_id: chatId || ''}), {headers: {Accept: 'application/json'}});
        if (!r.ok) return;
        const d = await r.json();
        agents = d.agents || [];
        renderThreads(d.threads || []);
        renderChat(d.selected);
    }

    function renderThreads(threads) {
        threads.forEach(t => { if (firstVisit || t.id === chatId) markSeen(t); });
        firstVisit = false;

        // Unread first, then latest activity (server order). Map keeps that order; a plain object would sort by partner id.
        threads.sort((a, b) => isNew(b) - isNew(a));
        const groups = new Map();
        threads.forEach(t => {
            if (!groups.has(t.partner_id)) groups.set(t.partner_id, {name: t.partner_name, items: []});
            groups.get(t.partner_id).items.push(t);
        });

        const unread = threads.filter(isNew).length;
        document.title = (unread ? `(${unread}) ` : '') + pageTitle;

        $('scThreads').innerHTML = [...groups].map(([pid, g]) => `
            <div class="px-3 py-2 bg-light border-bottom small fw-semibold">${esc(g.name)} <span class="text-muted">· Partner #${pid}</span></div>
            ${g.items.map(t => `
                <div class="sc-thread px-3 py-2 border-bottom ${t.id === chatId ? 'active' : ''} ${isNew(t) ? 'sc-new' : ''}" data-id="${t.id}">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-medium sc-name">${esc(t.user_name || 'Company thread')}${isNew(t) ? ' <span class="badge bg-danger ms-1">New</span>' : ''}</span>
                        <span class="sc-meta">${esc(t.updated_at)}</span>
                    </div>
                    <div class="small text-muted text-truncate sc-last">${esc(t.last_message || '-')}</div>
                    <span class="badge ${t.assigned_agent_id ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning'}">${esc(t.agent_name || 'In queue')}</span>
                </div>`).join('')}`).join('') || '<p class="text-muted text-center my-4">No chats</p>';
    }

    function renderChat(sel) {
        $('scComposer').hidden = !sel || sel.chat.status !== 'open';
        if (!sel) {
            $('scHeader').innerHTML = '<span class="text-muted">Select a chat</span>';
            $('scStream').innerHTML = '';
            return;
        }
        const c = sel.chat;
        $('scHeader').innerHTML = `
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <h5 class="mb-1">${esc(c.partner_name)}${c.user_name ? ' · ' + esc(c.user_name) : ''}</h5>
                    <div class="sc-meta">Partner ID: ${c.partner_id} · User ID: ${c.partner_user_id ?? '-'} · Status: <b>${esc(c.status)}</b> · Agent: ${esc(c.agent_name || 'Queue')} · Created: ${esc(c.created_at)} · Updated: ${esc(c.updated_at)}</div>
                </div>
                ${c.status === 'open' ? '<button class="btn btn-sm btn-outline-primary" data-act="transfer">Transfer</button><button class="btn btn-sm btn-outline-danger" data-act="close">Close</button>' : ''}
            </div>`;
        if (sel.messages.length === lastCount) return;
        lastCount = sel.messages.length;
        $('scStream').innerHTML = sel.messages.map(m => {
            const cls = m.type === 'system' ? 'sc-system' : (m.is_internal ? 'sc-note' : (m.type === 'agent' ? 'sc-agent' : 'sc-partner'));
            const files = (m.attachments || []).map(a => /^image\//.test(a.mime)
                ? `<a href="${esc(a.url)}" target="_blank"><img src="${esc(a.url)}" alt="${esc(a.name)}" class="sc-img"></a>`
                : `<a href="${esc(a.url)}" target="_blank" class="d-block">&#128206; ${esc(a.name)}</a>`).join('');
            return `<div class="sc-msg ${cls}"><div class="sc-meta">${esc(m.author)}${m.is_internal ? ' · Internal note' : ''} · ${esc(m.time)}</div>${esc(m.message)}${files}</div>`;
        }).join('');
        $('scStream').scrollTop = $('scStream').scrollHeight;
    }

    async function send() {
        const message = $('scInput').value.trim();
        if (!message || !chatId) return;
        try {
            await post(`${base}/${chatId}/send`, {message, is_internal: $('scInternal').checked});
            $('scInput').value = '';
            $('scInternal').checked = false;
            lastCount = -1;
            load();
        } catch (err) { toast(err.message, false); }
    }

    $('scThreads').addEventListener('click', e => {
        const t = e.target.closest('.sc-thread');
        if (!t) return;
        chatId = +t.dataset.id;
        lastCount = -1;
        load();
    });
    $('scStatus').addEventListener('change', () => { chatId = null; lastCount = -1; load(); });
    $('scSend').addEventListener('click', send);

    $('scHeader').addEventListener('click', async e => {
        const act = e.target.dataset.act;
        if (act === 'transfer') {
            $('scAgentSel').innerHTML = '<option value="">-</option>' + agents.map(a => `<option value="${a.id}">${esc(a.name)} - ${esc(a.label)}</option>`).join('');
            bootstrap.Modal.getOrCreateInstance($('scTransferModal')).show();
        } else if (act === 'close' && confirm('Close this chat?')) {
            try { toast((await post(`${base}/${chatId}/close`, {})).message); lastCount = -1; load(); } catch (err) { toast(err.message, false); }
        }
    });

    $('scTransferForm').addEventListener('submit', async e => {
        e.preventDefault();
        const f = Object.fromEntries(new FormData(e.target));
        if (!f.agent_id) delete f.agent_id;
        if (!f.team_id) delete f.team_id;
        if (!f.agent_id && !f.team_id) return toast('Choose an agent or a department.', false);
        try {
            toast((await post(`${base}/${chatId}/transfer`, f)).message);
            bootstrap.Modal.getInstance($('scTransferModal')).hide();
            e.target.reset();
            lastCount = -1;
            load();
        } catch (err) { toast(err.message, false); }
    });

    // "/" canned replies: type /gre -> pick -> text is inserted.
    const box = $('scCanned');
    $('scInput').addEventListener('input', e => {
        const m = e.target.value.match(/^\/(\w*)$/);
        const keys = m ? Object.keys(canned).filter(k => k.startsWith(m[1].toLowerCase())) : [];
        box.hidden = !keys.length;
        box.innerHTML = keys.map(k => `<button type="button" class="list-group-item list-group-item-action small" data-key="${k}"><b>/${k}</b> - ${esc(canned[k])}</button>`).join('');
    });
    box.addEventListener('click', e => {
        const b = e.target.closest('[data-key]');
        if (!b) return;
        $('scInput').value = canned[b.dataset.key];
        box.hidden = true;
        $('scInput').focus();
    });
    $('scInput').addEventListener('keydown', e => {
        if (e.key !== 'Enter' || e.shiftKey) return;
        e.preventDefault();
        const first = box.hidden ? null : box.querySelector('[data-key]');
        first ? first.click() : send();
    });

    load();
    setInterval(() => { if (!document.hidden) load(); }, 5000);
})();
</script>
@endpush
