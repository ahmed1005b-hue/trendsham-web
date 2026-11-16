{{-- سجل طلب الإرجاع لصفحات البائع والإدارة (الأحداث محمّلة مسبقاً) --}}
<ul class="list-unstyled small mb-0">
    @foreach ($returnRequest->events as $event)
        <li class="mb-2">
            <strong>{{ __(\App\Returns\Models\ReturnRequest::EVENT_LABELS[$event->type] ?? $event->type) }}</strong>
            <span class="text-muted">{{ $event->created_at->format('Y-m-d H:i') }}</span>
            @if ($event->note)
                <div>{{ $event->note }}</div>
            @endif
            @foreach ($event->photos ?? [] as $photo)
                <a href="{{ asset('storage/'.$photo) }}" target="_blank"><img src="{{ asset('storage/'.$photo) }}" alt="" style="width:64px;height:64px;object-fit:cover" class="rounded border me-1"></a>
            @endforeach
        </li>
    @endforeach
</ul>
