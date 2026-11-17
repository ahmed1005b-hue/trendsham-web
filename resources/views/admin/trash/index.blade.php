@extends('layouts.app')

@section('title', 'المحذوفات - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - المحذوفات</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <p class="text-muted small">ما يُحذف هنا يختفي من الموقع والتطبيق، لكنه يبقى محفوظاً مع تقييماته وطلباته. تستطيع إرجاعه بزر «استرجاع».</p>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($types as $key => $label)
            <a href="{{ route('admin.trash.index', ['type' => $key]) }}"
               class="btn btn-sm {{ $current === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }} <span class="badge {{ $current === $key ? 'bg-light text-dark' : 'bg-secondary' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>الاسم</th><th>تفاصيل</th><th>حُذف</th><th></th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="fw-bold">{{ $item['title'] ?: '-' }}</td>
                            <td class="text-muted small">{{ $item['detail'] ?? '-' }}</td>
                            <td class="small" dir="ltr">{{ $item['deleted_at']?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <form action="{{ route('admin.trash.restore', [$current, $item['id']]) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">استرجاع</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">لا يوجد شيء محذوف هنا.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
