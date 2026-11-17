@extends('layouts.app')

@section('title', 'الستوري - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الستوري</span>
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

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">نشر ستوري منصة (مجاني)</h2>
                    <form action="{{ route('admin.stories.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label for="f-image" class="form-label small">الصورة</label>
                            <input id="f-image" type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="mb-2">
                            <label for="f-caption" class="form-label small">نص مختصر (اختياري)</label>
                            <input id="f-caption" type="text" name="caption" class="form-control" maxlength="150">
                        </div>
                        <div class="mb-2">
                            <label for="f-link_url" class="form-label small">{{ __('رابط الزر داخل الستوري (اختياري)') }}</label>
                            <input id="f-link_url" type="url" name="link_url" value="{{ old('link_url') }}" class="form-control @error('link_url') is-invalid @enderror" maxlength="500" dir="ltr" placeholder="https://">
                            @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="f-duration_hours" class="form-label small">مدة الظهور (ساعات)</label>
                            <input id="f-duration_hours" type="number" name="duration_hours" class="form-control" value="24" min="1" max="168" required>
                        </div>
                        <button class="btn btn-primary btn-sm w-100">نشر</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>الصورة</th><th>المصدر</th><th>تنتهي</th><th>الحالة</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($stories as $story)
                                <tr>
                                    <td>
                                        <img src="{{ $story->image_thumb_url }}" alt="صورة القصة" loading="lazy" width="40" height="60" class="rounded" style="object-fit:cover;">
                                    </td>
                                    <td>
                                        @if ($story->isFromPlatform())
                                            <span class="badge bg-warning text-dark">ستوري المنصة</span>
                                        @else
                                            <span translate="no">{{ $story->vendor->store_name }}</span>
                                            <div class="text-muted small">دفع {{ number_format($story->amount_paid / 100) }} ل.س</div>
                                        @endif
                                    </td>
                                    <td class="small">{{ $story->expires_at->diffForHumans() }}</td>
                                    <td>
                                        @if ($story->is_active && ! $story->isExpired())
                                            <span class="badge bg-success">نشطة</span>
                                        @else
                                            <span class="badge bg-secondary">منتهية/مخفية</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($story->is_active && ! $story->isExpired())
                                            <form action="{{ route('admin.stories.destroy', $story) }}" method="POST" data-confirm="إخفاء هذه الستوري؟">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">إخفاء</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد ستوريات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
