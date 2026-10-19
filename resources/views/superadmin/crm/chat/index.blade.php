@extends('superadmin.layouts.crm')

@section('content')
@if ($errors->any())
    <div class="crm-chat-error">{{ $errors->first() }}</div>
@endif

<div class="crm-chat-app {{ $active ? 'has-chat' : '' }}">
    <aside class="crm-chat-list">
        <div class="crm-chat-list-head">
            <h2>CRM Chat</h2>
        </div>

        <div class="crm-chat-search">
            <i data-lucide="search"></i>
            <input id="crmChatSearch" type="text" placeholder="Search user..." autocomplete="off">
        </div>

        <p class="crm-chat-section" id="crmUserLabel" hidden>Users</p>
        <div class="crm-chat-threads crm-chat-user-hits" id="crmUsers" hidden>
            @foreach ($users as $user)
                <form method="POST" action="{{ route('crm.chat.store') }}" class="crm-chat-hit" data-find="{{ strtolower($user->name . ' ' . $user->role) }}" hidden>
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $user->id }}">
                    <button type="submit" class="crm-chat-row">
                        <span class="crm-chat-avatar" style="background:#5c7cfa">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        <span class="crm-chat-row-text">
                            <strong>{{ $user->name }}</strong>
                            <small>{{ ucfirst(str_replace('_', ' ', (string) $user->role)) }}</small>
                        </span>
                    </button>
                </form>
            @endforeach
        </div>

        <p class="crm-chat-section">Messages</p>
        <div class="crm-chat-threads" id="crmChats">
            @forelse ($chats as $chat)
                <a href="{{ route('crm.chat', ['chat' => $chat['id']]) }}" data-find="{{ strtolower($chat['title'] . ' ' . $chat['preview']) }}" class="crm-chat-row {{ $active && $active['id'] === $chat['id'] ? 'active' : '' }}">
                    <span class="crm-chat-avatar" style="background: {{ $chat['color'] }}">{{ $chat['initials'] }}</span>
                    <span class="crm-chat-row-text">
                        <strong>{{ $chat['title'] }}</strong>
                        <small>{{ $chat['preview'] }}</small>
                    </span>
                    @if ($chat['time'])
                        <em>{{ $chat['time'] }}</em>
                    @endif
                </a>
            @empty
                <p class="crm-chat-empty">No conversations yet.</p>
            @endforelse
        </div>
    </aside>

    <section class="crm-chat-room">
        @if ($active)
            <header>
                <a href="{{ route('crm.chat') }}" class="crm-chat-back" title="Back">
                    <i data-lucide="arrow-left"></i>
                </a>
                <span class="crm-chat-avatar" style="background: {{ $active['color'] }}">{{ $active['initials'] }}</span>
                <div>
                    <h3>{{ $active['title'] }}</h3>
                    <p>{{ $active['kind'] }}</p>
                </div>
            </header>
            <div class="crm-chat-messages" id="crmChatMessages">
                @php $day = null; @endphp
                @forelse ($active['messages'] as $message)
                    @if ($message['day'] !== $day)
                        @php $day = $message['day']; @endphp
                        <div class="crm-chat-day"><span>{{ $day }}</span></div>
                    @endif
                    <article class="{{ $message['mine'] ? 'mine' : '' }}">
                        <p>{{ $message['message'] }}</p>
                        <time>{{ $message['clock'] }}</time>
                    </article>
                @empty
                    <p class="crm-chat-empty">Send the first message.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('crm.chat.message', $active['id']) }}" class="crm-chat-compose">
                @csrf
                <input class="crm-chat-type" type="text" name="message" maxlength="5000" placeholder="Type a message" required autocomplete="off">
                <button type="submit" class="crm-chat-send" title="Send">
                    <i data-lucide="send"></i>
                </button>
            </form>
        @else
            <div class="crm-chat-placeholder">
                <h3>Select a conversation</h3>
                <p>Search a user to start a new chat.</p>
            </div>
        @endif
    </section>
</div>
<script>
    const box = document.getElementById('crmChatMessages');
    if (box) box.scrollTop = box.scrollHeight;

    const chatList = document.getElementById('crmChats');
    const chatRows = Array.from(chatList.querySelectorAll('.crm-chat-row'));

    document.getElementById('crmChatSearch').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('#crmUsers .crm-chat-hit').forEach(function (row) {
            const ok = q !== '' && row.dataset.find.includes(q);
            row.hidden = !ok;
            if (ok) shown++;
        });
        document.getElementById('crmUsers').hidden = shown === 0;
        document.getElementById('crmUserLabel').hidden = shown === 0;

        const matches = [];
        const rest = [];
        chatRows.forEach(function (row) {
            const ok = q === '' || row.dataset.find.includes(q);
            row.hidden = !ok;
            (ok ? matches : rest).push(row);
        });
        matches.concat(rest).forEach(function (row) {
            chatList.appendChild(row);
        });
    });
</script>
@endsection
