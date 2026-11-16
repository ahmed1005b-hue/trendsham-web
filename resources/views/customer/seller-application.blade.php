@extends('layouts.app')

@section('title', 'التحقق من هوية البائع - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - التحقق من هوية البائع</span>
        <a href="{{ auth()->user()?->role === 'seller' ? route('seller.dashboard') : route('customer.dashboard') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
    </div>
</nav>

<div class="container pb-4" style="max-width: 720px;">
    <div class="card">
        <div class="card-body">
            <h1 class="h5 mb-2">بيانات المتجر والتحقق من الهوية</h1>
            <p class="text-muted small mb-3">لن تتمكن من نشر المنتجات قبل مراجعة الإدارة واعتماد المتجر. وثائق الهوية خاصة ولا يطّلع عليها إلا فريق الإدارة عبر مسار محمي.</p>

            @if ($sellerProfile)
                @php
                    $status = $sellerProfile->status;
                    $badge = match($status) { 'approved' => 'success', 'pending' => 'warning text-dark', 'rejected' => 'danger', default => 'secondary' };
                    $label = method_exists($sellerProfile, 'identityStatusLabel') ? $sellerProfile->identityStatusLabel() : $status;
                @endphp
                <div class="alert alert-{{ $status === 'rejected' ? 'danger' : ($status === 'pending' ? 'info' : 'success') }} d-flex flex-column gap-1">
                    <div><strong>حالة التحقق:</strong> <span class="badge bg-{{ $badge }}">{{ $label }}</span></div>
                    @if ($sellerProfile->identity_document_submitted_at)
                        <div class="small">آخر إرسال: {{ $sellerProfile->identity_document_submitted_at->format('Y-m-d H:i') }}</div>
                    @endif
                    @if ($sellerProfile->identity_verified_at)
                        <div class="small">تم الاعتماد: {{ $sellerProfile->identity_verified_at->format('Y-m-d H:i') }}</div>
                    @endif
                    @if ($status === 'rejected' && $sellerProfile->rejection_reason)
                        <div><strong>سبب الرفض:</strong> {{ $sellerProfile->rejection_reason }}</div>
                        <div class="small">ارفع وثيقة واضحة (أو وجهين عند الحاجة) وأعد الإرسال للمراجعة.</div>
                    @elseif ($status === 'pending' && $sellerProfile->identity_document_path)
                        <div class="small">طلبك قيد المراجعة. يمكنك استبدال الوثيقة وإعادة الإرسال إذا أخطأت في الملف.</div>
                    @endif
                </div>
            @endif

            <form action="{{ $sellerProfile ? route('seller.identity-verification.store') : route('customer.seller-application.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label for="f-store_name" class="form-label">اسم المتجر</label>
                        <input id="f-store_name" type="text" name="store_name" class="form-control" value="{{ old('store_name', $sellerProfile?->store_name) }}" required>
                        @error('store_name') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="f-store_description" class="form-label">وصف المتجر</label>
                        <textarea id="f-store_description" name="store_description" class="form-control" rows="3">{{ old('store_description', $sellerProfile?->store_description) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="f-commercial_register" class="form-label">السجل التجاري (اختياري)</label>
                        <input id="f-commercial_register" type="text" name="commercial_register" class="form-control" value="{{ old('commercial_register', $sellerProfile?->commercial_register) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="f-identity_type" class="form-label">نوع الوثيقة (اختياري)</label>
                        <select id="f-identity_type" name="identity_type" class="form-select">
                            @php($type = old('identity_type', $sellerProfile?->identity_type))
                            <option value="">اختر النوع</option>
                            <option value="national_id" @selected($type==='national_id')>هوية وطنية</option>
                            <option value="passport" @selected($type==='passport')>جواز سفر</option>
                            <option value="residence" @selected($type==='residence')>إقامة</option>
                            <option value="commercial_register" @selected($type==='commercial_register')>سجل تجاري</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="f-identity_number" class="form-label">رقم الهوية/السجل (اختياري)</label>
                        <input id="f-identity_number" type="text" name="identity_number" class="form-control" value="{{ old('identity_number', $sellerProfile?->identity_number) }}" maxlength="64" autocomplete="off">
                        <div class="form-text">يُحفظ للمراجعة الإدارية فقط؛ لا يظهر رقم الهوية كاملاً للعملاء.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identity_document">وثيقة الهوية - الوجه الأمامي</label>
                        <input type="file" id="identity_document" name="identity_document" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <div class="form-text">JPG/PNG/WEBP أو PDF، حد أقصى 5MB. يجب أن تكون البيانات واضحة.</div>
                        @error('identity_document') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="identity_document_back">الوجه الخلفي / صفحة إضافية (اختياري)</label>
                        <input type="file" id="identity_document_back" name="identity_document_back" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <div class="form-text">مفيد للهوية ذات الوجهين أو صفحة بيانات إضافية.</div>
                        @error('identity_document_back') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">{{ $sellerProfile ? 'إعادة إرسال الطلب للمراجعة' : 'إرسال طلب التحقق' }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
