{{-- Conversation list, shared by the inbox and (on wide screens) the chat page.
     Needs: $rows (MessageController::inboxRows), optional $activeId. --}}
@foreach ($rows as $row)
    @php $mwActive = isset($activeId) && $row['conversation'] && $row['conversation']->id === $activeId; @endphp
    <a href="{{ $row['conversation'] ? route('messages.show', $row['conversation']) : route('messages.start', $row['profile_id']) }}"
       class="mw-thread {{ $row['unread'] > 0 ? 'unread' : '' }} {{ $mwActive ? 'is-on' : '' }}" @if ($mwActive) aria-current="page" @endif>
        <div class="mw-thread-avatar c{{ crc32($row['name']) % 4 }}">{{ mb_strtoupper(mb_substr($row['name'], 0, 1)) }}</div>
        <div class="mw-thread-main">
            <div class="mw-thread-name">{{ $row['name'] }}</div>
            <div class="mw-thread-preview">{{ $row['preview'] ? \Illuminate\Support\Str::limit($row['preview'], 90) : 'No messages yet' }}</div>
        </div>
        <div class="mw-thread-meta">
            @if ($row['last_at'])
                <span>{{ $row['last_at']->isToday() ? $row['last_at']->format('g:i A') : $row['last_at']->format('M j') }}</span>
            @endif
            @if ($row['unread'] > 0)
                <span class="mw-count">{{ $row['unread'] }}</span>
            @endif
        </div>
    </a>
@endforeach
