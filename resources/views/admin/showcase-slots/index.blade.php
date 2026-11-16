@extends('layouts.app')

@section('title', 'مساحات العرض - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - مساحات العرض</span>
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">مساحات العرض المؤجّرة</h2>
        <a href="{{ route('admin.showcase-slots.create') }}" class="btn btn-primary btn-sm">إضافة مساحة جديدة</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>المفتاح</th>
                        <th>الاسم</th>
                        <th>الموضع</th>
                        <th>سعر اليوم</th>
                        <th>أقل/أقصى أيام</th>
                        <th>السعة</th>
                        <th>أقل/أقصى منتجات</th>
                        <th>الحالة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($slots as $slot)
                        <tr>
                            <td><code>{{ $slot->key }}</code></td>
                            <td>{{ $slot->name }}</td>
                            <td>{{ $slot->position }}</td>
                            <td>{{ number_format($slot->price_per_day, 2) }} ل.س</td>
                            <td>{{ $slot->min_days }} / {{ $slot->max_days }}</td>
                            <td>{{ $slot->capacity }}</td>
                            <td>{{ $slot->min_products }} / {{ $slot->max_products }}</td>
                            <td>
                                <span class="badge {{ $slot->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $slot->is_active ? 'نشط' : 'معطل' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.showcase-slots.edit', $slot) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                                @if (!$slot->showcases()->exists())
                                    <form action="{{ route('admin.showcase-slots.destroy', $slot) }}" method="POST" class="d-inline" data-confirm="حذف هذه المساحة؟">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                    </form>
                                @else
                                    <span class="text-muted small">لها حجوزات</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">لا توجد مساحات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection