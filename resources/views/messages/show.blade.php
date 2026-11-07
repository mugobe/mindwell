@extends('layouts.app')
@section('title', 'Messages')
@section('content')
{{--
    One conversation, added 2026-09-29 — see MessageController. Messages are rendered
    by Blade on load (so the page works even if JavaScript fails); the script then polls
    messages.poll every 4s (every 15s while the tab is in the background), appends new
    messages as text nodes only, and sends new ones without reloading. The form still
    posts normally if JavaScript is off.
--}}
@php
    $otherInitial = mb_strtoupper(mb_substr($otherName, 0, 1));
    $lastMineAt = collect($messages)->where('mine', true)->last()['at'] ?? null;
@endphp

@include('messages._inbox-style')
<style>
    .mw-chat { display:flex; flex-direction:column; height:100%; min-height:0; }
    .mw-chat > .card { border-radius:20px; }
    .mw-back { display:none; }
    @media (max-width: 960px) {
        .mw-back { display:flex; }
        .mw-chat { height: calc(100dvh - 140px); min-height: 420px; }
        body.mw-client .mw-chat { height: calc(100dvh - 190px); }
    }
    .mw-chat-head { display:flex; align-items:center; gap:12px; padding:14px 18px; border-bottom:1px solid rgba(0,0,0,.06); }
    .mw-chat-avatar { width:40px; height:40px; border-radius:50%; background:var(--teal-700, #2f6f66); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:16px; flex-shrink:0; }
    .mw-chat-card { flex:1; display:flex; flex-direction:column; padding:0; overflow:hidden; min-height:0; }
    .mw-chat-safety { margin:12px 18px 0; padding:10px 14px; border-radius:14px; background:var(--mw-note-bg, #EEF3F6); font-size:12.5px; color:var(--mw-note-text, #4A5560); line-height:1.5; }
    .mw-chat-list { flex:1; overflow-y:auto; padding:16px 18px 8px; display:flex; flex-direction:column; gap:4px; min-height:0; }
    .mw-day { align-self:center; font-size:11px; font-weight:700; color:var(--ink-300); margin:12px 0 6px; text-transform:uppercase; letter-spacing:.04em; }
    .mw-msg { max-width:78%; display:flex; flex-direction:column; }
    .mw-msg.mine { align-self:flex-end; align-items:flex-end; }
    .mw-msg.theirs { align-self:flex-start; align-items:flex-start; }
    .mw-bubble { padding:9px 13px; border-radius:16px; font-size:14px; line-height:1.45; white-space:pre-wrap; word-wrap:break-word; overflow-wrap:anywhere; }
    .mw-msg.mine .mw-bubble { background:var(--teal-700, #2f6f66); color:#fff; border-bottom-right-radius:5px; }
    .mw-msg.theirs .mw-bubble { background:#F0F3F7; color:var(--ink-900); border-bottom-left-radius:5px; }
    .mw-msg { animation: mwMsgIn .25s cubic-bezier(.2,.8,.2,1) both; }
    @keyframes mwMsgIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
    @media (prefers-reduced-motion: reduce) { .mw-msg { animation:none; } }
    .mw-msg.pending .mw-bubble { opacity:.6; }
    /* Attachments (2026-11-07): a photo shows as a picture, a PDF as a file card. */
    .mw-att { display:block; margin-top:4px; max-width:260px; }
    .mw-att img { display:block; max-width:100%; max-height:260px; border-radius:14px; object-fit:cover; background:#E8EDF3; }
    .mw-att-file { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:14px; background:#fff; border:1px solid rgba(3,40,80,.12); color:var(--ink-900); text-decoration:none; }
    .mw-att-file b { display:block; font-size:13px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px; }
    .mw-att-file small { font-size:11.5px; color:var(--ink-500); }
    .mw-att-file .i { width:34px; height:34px; flex:0 0 34px; border-radius:9px; background:#FDE7E7; color:#B42318; display:grid; place-items:center; font-size:10px; font-weight:800; }
    .mw-msg.mine .mw-att { margin-left:auto; }
    .mw-clip { width:44px; height:44px; flex:0 0 44px; border-radius:50%; border:0; background:#F0F3F7; color:var(--ink-700); cursor:pointer; display:grid; place-items:center; }
    .mw-clip:hover { background:var(--mw-navy-tint, #E6EFF9); }
    .mw-picked { display:flex; align-items:center; gap:8px; padding:6px 18px 0; font-size:12.5px; color:var(--ink-700); }
    .mw-picked button { border:0; background:none; color:#B3261E; font-weight:700; cursor:pointer; font-size:12.5px; }
    .mw-msg.failed .mw-bubble { border:1.5px solid #c9574d; }
    .mw-time { font-size:10.5px; color:var(--ink-300); margin:3px 4px 6px; }
    .mw-seen { font-size:10.5px; color:var(--teal-600, #2f6f66); font-weight:700; margin:-2px 4px 6px; align-self:flex-end; }
    .mw-empty { margin:auto; text-align:center; color:var(--ink-500); font-size:13px; padding:24px; }
    .mw-compose { display:flex; gap:10px; align-items:flex-end; padding:12px 14px; border-top:1px solid rgba(0,0,0,.06); background:#fff; }
    .mw-compose textarea { flex:1; resize:none; max-height:140px; min-height:44px; border-radius:22px; padding:11px 16px; font-size:14px; line-height:1.4; }
    .mw-send { width:44px; height:44px; border-radius:50%; border:none; background:var(--coral-500, #e8825e); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; }
    .mw-send[disabled] { opacity:.45; cursor:not-allowed; }
    .mw-newpill { position:sticky; bottom:6px; align-self:center; padding:6px 14px; border-radius:999px; background:var(--teal-600, #2f6f66); color:#fff; font-size:12px; font-weight:700; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,.18); }
    .mw-hidden { display:none !important; }
    @media (max-width: 640px) { .mw-msg { max-width:88%; } }
</style>

<div class="mw-panes is-chat">
<section class="mw-pane list">
    <div class="mw-pane-head">Messages</div>
    <div class="mw-pane-list">
        @include('messages._inbox-list', ['rows' => $rows, 'activeId' => $conversation->id])
    </div>
</section>
<div class="mw-chat">
    <div class="card mw-chat-card">
        <div class="mw-chat-head">
            <a href="{{ route('messages.index') }}" aria-label="Back to messages" class="mw-back" style="color:var(--ink-500);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M15 5l-7 7 7 7"/></svg>
            </a>
            <div class="mw-chat-avatar">{{ $otherInitial }}</div>
            <div style="min-width:0;">
                <div style="font-size:15px;font-weight:800;color:var(--ink-900);">{{ $otherName }}</div>
                <div style="font-size:11.5px;color:var(--ink-500);">{{ $role === 'client' ? 'Your therapist' : 'Your client' }} · private conversation</div>
            </div>
        </div>

        @if ($role === 'client')
            <div class="mw-chat-safety">
                Messages are not checked around the clock, so replies may take a while.
                If you are in crisis or thinking about harming yourself, <a href="{{ route('urgent-help') }}" style="color:var(--teal-700);font-weight:700;">get help now</a>.
            </div>
        @endif

        <div class="mw-chat-list" id="mw-list" aria-live="polite">
            @php $prevDay = null; @endphp
            @forelse ($messages as $m)
                @if ($m['day'] !== $prevDay)
                    <div class="mw-day">{{ $m['day'] }}</div>
                    @php $prevDay = $m['day']; @endphp
                @endif
                <div class="mw-msg {{ $m['mine'] ? 'mine' : 'theirs' }}" data-id="{{ $m['id'] }}" data-at="{{ $m['at'] }}" data-day="{{ $m['day'] }}">
                    @if ($m['body'] !== '')<div class="mw-bubble">{{ $m['body'] }}</div>@endif
                    @if ($m['attachment'])
                        @if ($m['attachment']['previewable'])
                            <a class="mw-att" href="{{ $m['attachment']['url'] }}" target="_blank" rel="noopener"><img src="{{ $m['attachment']['url'] }}" alt="{{ $m['attachment']['name'] }}" loading="lazy"></a>
                        @else
                            <a class="mw-att mw-att-file" href="{{ $m['attachment']['url'] }}" target="_blank" rel="noopener"><span class="i">{{ $m['attachment']['kind'] === 'pdf' ? 'PDF' : 'IMG' }}</span><span><b>{{ $m['attachment']['name'] }}</b><small>{{ $m['attachment']['size_label'] }}</small></span></a>
                        @endif
                    @endif
                    <div class="mw-time">{{ $m['time'] }}</div>
                </div>
            @empty
                <div class="mw-empty" id="mw-empty">
                    @include('partials.mw-illustration', ['variant' => 'sprout', 'size' => 96])
                    <div style="margin-top:6px;">No messages yet. Say hello to {{ $otherName }}.</div>
                </div>
            @endforelse
            <div class="mw-seen mw-hidden" id="mw-seen">Seen</div>
            <button type="button" class="mw-newpill mw-hidden" id="mw-newpill">New messages ↓</button>
        </div>

        @if ($canSend)
            <div class="mw-picked" id="mw-picked" hidden><span id="mw-picked-name"></span><button type="button" id="mw-picked-x">Remove</button></div>
            <form class="mw-compose" id="mw-compose" method="POST" action="{{ route('messages.send', $conversation) }}" enctype="multipart/form-data">
                @csrf
                <input type="file" id="mw-file" name="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" hidden>
                <button type="button" class="mw-clip" id="mw-clip" aria-label="Attach a photo or PDF" title="Attach a photo or PDF (up to 10 MB)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M21 11.5l-8.5 8.5a5 5 0 01-7-7L14 4.5a3.5 3.5 0 015 5l-8.5 8.5a2 2 0 01-3-3L15 7.5"/></svg>
                </button>
                <textarea class="mw-field" name="body" id="mw-input" rows="1" maxlength="5000" placeholder="Write a message…" aria-label="Message"></textarea>
                <button type="submit" class="mw-send" id="mw-send" aria-label="Send" title="Send (Enter)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;"><path d="M4 12l16-8-6 16-2.5-6.5z"/><path d="M11.5 13.5L20 4"/></svg>
                </button>
            </form>
        @else
            <div style="padding:14px 18px;border-top:1px solid rgba(0,0,0,.06);font-size:12.5px;color:var(--ink-500);text-align:center;">
                You can't send new messages here, because there's no longer a booked or completed session between you.
            </div>
        @endif
    </div>
</div>
</div>

<script>
(function () {
    var cfg = @json($jsConfig);
    var list = document.getElementById('mw-list');
    var seenEl = document.getElementById('mw-seen');
    var pill = document.getElementById('mw-newpill');
    var form = document.getElementById('mw-compose');
    var input = document.getElementById('mw-input');
    var sendBtn = document.getElementById('mw-send');

    var known = {};
    var lastAt = null, lastDay = null, lastMineAt = @json($lastMineAt), otherRead = cfg.otherLastReadAt;
    list.querySelectorAll('.mw-msg[data-id]').forEach(function (el) {
        known[el.dataset.id] = true; lastAt = el.dataset.at; lastDay = el.dataset.day;
    });

    function nearBottom() { return list.scrollHeight - list.scrollTop - list.clientHeight < 90; }
    function toBottom() { list.scrollTop = list.scrollHeight; pill.classList.add('mw-hidden'); }
    function updateSeen() {
        // Only meaningful when my message is the newest one in the conversation.
        var msgs = list.querySelectorAll('.mw-msg');
        var newestIsMine = msgs.length > 0 && msgs[msgs.length - 1].classList.contains('mine');
        var seen = newestIsMine && lastMineAt && otherRead && new Date(otherRead) >= new Date(lastMineAt);
        seenEl.classList.toggle('mw-hidden', !seen);
        list.insertBefore(seenEl, pill); // keep it right after the newest message
    }

    // Builds one message bubble. Text is only ever set via textContent — never HTML.
    function bubble(m, extraClass) {
        var wrap = document.createElement('div');
        wrap.className = 'mw-msg ' + (m.mine ? 'mine' : 'theirs') + (extraClass ? ' ' + extraClass : '');
        if (m.id) { wrap.dataset.id = m.id; }
        if (m.body) { var b = document.createElement('div'); b.className = 'mw-bubble'; b.textContent = m.body; wrap.appendChild(b); }
        if (m.attachment) { wrap.appendChild(attachmentEl(m.attachment)); }
        var t = document.createElement('div'); t.className = 'mw-time'; t.textContent = m.time || 'Sending…';
        wrap.appendChild(t);
        return wrap;
    }
    function attachmentEl(a) {
        var link = document.createElement('a'); link.href = a.url; link.target = '_blank'; link.rel = 'noopener';
        if (a.previewable) {
            link.className = 'mw-att';
            var img = document.createElement('img'); img.src = a.url; img.alt = a.name; img.loading = 'lazy'; link.appendChild(img);
        } else {
            link.className = 'mw-att mw-att-file';
            var i = document.createElement('span'); i.className = 'i'; i.textContent = a.kind === 'pdf' ? 'PDF' : 'IMG';
            var txt = document.createElement('span'); var bn = document.createElement('b'); bn.textContent = a.name;
            var sm = document.createElement('small'); sm.textContent = a.size_label || '';
            txt.appendChild(bn); txt.appendChild(sm); link.appendChild(i); link.appendChild(txt);
        }
        return link;
    }
    function append(m, el) {
        var empty = document.getElementById('mw-empty'); if (empty) empty.remove();
        if (m.day && m.day !== lastDay) {
            var d = document.createElement('div'); d.className = 'mw-day'; d.textContent = m.day;
            list.insertBefore(d, seenEl); lastDay = m.day;
        }
        list.insertBefore(el || bubble(m), seenEl);
    }

    function addFromServer(m) {
        if (known[m.id]) return false;
        known[m.id] = true;
        // A poll can return my own message before its send() request has answered —
        // adopt the matching "Sending…" bubble instead of showing it twice.
        if (m.mine) {
            var pending = Array.prototype.find.call(list.querySelectorAll('.mw-msg.pending'), function (el) {
                var b = el.querySelector('.mw-bubble');
                return !el.dataset.id && !m.attachment && b && b.textContent === m.body;
            });
            if (pending) {
                pending.dataset.id = m.id; pending.classList.remove('pending');
                pending.querySelector('.mw-time').textContent = m.time;
                lastAt = m.at; lastMineAt = m.at;
                return true;
            }
        }
        append(m);
        if (m.at) lastAt = m.at;
        if (m.mine) lastMineAt = m.at;
        return true;
    }

    async function poll() {
        try {
            var url = cfg.pollUrl + (lastAt ? '?since=' + encodeURIComponent(lastAt) : '');
            var r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) return;
            var data = await r.json();
            var stick = nearBottom(), gotTheirs = false;
            data.messages.forEach(function (m) { if (addFromServer(m) && !m.mine) gotTheirs = true; });
            otherRead = data.otherLastReadAt;
            updateSeen();
            if (gotTheirs) { if (stick) { toBottom(); } else { pill.classList.remove('mw-hidden'); } }
        } catch (e) { /* offline blip — next tick */ }
    }

    var timer = null;
    function schedule() { clearTimeout(timer); timer = setTimeout(function () { poll().finally(schedule); }, document.hidden ? 15000 : 4000); }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } schedule(); });
    pill.addEventListener('click', toBottom);
    list.addEventListener('scroll', function () { if (nearBottom()) pill.classList.add('mw-hidden'); });

    updateSeen();
    toBottom();
    schedule();

    if (!form) return;

    var fileInput = document.getElementById('mw-file'), picked = document.getElementById('mw-picked');
    function grow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; sendBtn.disabled = !input.value.trim() && !fileInput.files.length; }
    document.getElementById('mw-clip').addEventListener('click', function () { fileInput.click(); });
    function clearFile() { fileInput.value = ''; picked.hidden = true; grow(); }
    document.getElementById('mw-picked-x').addEventListener('click', clearFile);
    fileInput.addEventListener('change', function () {
        var f = fileInput.files[0];
        if (!f) { clearFile(); return; }
        if (f.size > 10 * 1024 * 1024) { alert('That file is bigger than 10 MB. Please choose a smaller one.'); clearFile(); return; }
        document.getElementById('mw-picked-name').textContent = 'Attached: ' + f.name;
        picked.hidden = false; grow();
    });
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
    });
    grow();
    input.focus();

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var text = input.value.trim(), file = fileInput.files[0] || null;
        if (!text && !file) return;
        input.value = '';

        var temp = bubble({ mine: true, body: text || (file ? 'Sending ' + file.name + '…' : '') }, 'pending');
        append({ mine: true, body: text, day: null }, temp);
        toBottom();

        try {
            var fd = new FormData(); fd.append('_token', cfg.csrf); fd.append('body', text);
            if (file) fd.append('file', file);
            clearFile();
            var r = await fetch(cfg.sendUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            if (r.status === 419) throw new Error('This page has been open for a long time. Please reload it.');
            if (r.status === 429) throw new Error("You are sending messages very quickly. Please wait a moment.");
            var data = await r.json();
            if (!r.ok) throw new Error(data.errors ? Object.values(data.errors)[0][0] : (data.message || "Couldn't send."));
            var m = data.message;
            if (temp.dataset.id) { updateSeen(); return; } // a poll already adopted it
            if (known[m.id]) { temp.remove(); updateSeen(); return; } // a poll already showed it
            if (m.attachment) { var fresh = bubble(m); list.replaceChild(fresh, temp); temp = fresh; }
            known[m.id] = true; lastAt = m.at; lastMineAt = m.at;
            temp.dataset.id = m.id; temp.classList.remove('pending');
            temp.querySelector('.mw-time').textContent = m.time;
            if (m.day !== lastDay) {
                var d = document.createElement('div'); d.className = 'mw-day'; d.textContent = m.day;
                list.insertBefore(d, temp); lastDay = m.day;
            }
            updateSeen();
        } catch (err) {
            temp.classList.remove('pending'); temp.classList.add('failed');
            var t = temp.querySelector('.mw-time');
            t.textContent = (err && err.message ? err.message : "Couldn't send.") + ' Tap to retry.';
            t.style.cursor = 'pointer'; t.style.color = '#c9574d';
            t.addEventListener('click', function () { temp.remove(); input.value = text; grow(); form.requestSubmit(); }, { once: true });
        }
    });
})();
</script>
@endsection
