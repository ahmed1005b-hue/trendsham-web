@extends('layouts.app')

@section('title', 'إعدادات المنصة - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إعدادات المنصة</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 480px;">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3">نسبة عمولة المنصة</h2>
            <p class="text-muted small mb-3">
                هي النسبة اللي تاخدها المنصة من كل عملية بيع قبل ما يوصل الباقي لمحفظة البائع.
                التغيير بيطبّق فقط على الطلبات اللي "تُسلّم" بعد الحفظ — ما بيأثر على عمولات محسوبة مسبقاً.
            </p>
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="input-group mb-3">
                    <input type="number" name="commission_rate" class="form-control" value="{{ old('commission_rate', $setting->commission_rate) }}" min="0" max="100" required>
                    <span class="input-group-text">%</span>
                </div>
                @error('commission_rate') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

                <hr class="my-4">

                <h2 class="h5 mb-3">رسم نشر الستوري (للبائعين)</h2>
                <p class="text-muted small mb-3">
                    المبلغ اللي يُخصم من محفظة البائع مقابل نشر ستوري واحدة على الصفحة الرئيسية لمدة 24 ساعة.
                </p>
                <div class="input-group mb-3">
                    <input type="number" step="0.01" name="story_fee" class="form-control" value="{{ old('story_fee', $setting->story_fee / 100) }}" min="0" required>
                    <span class="input-group-text">ل.س</span>
                </div>
                @error('story_fee') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

                <hr class="my-4">

                <h2 class="h5 mb-3" id="shipping">أجور الشحن (شركة كرم)</h2>
                <p class="text-muted small mb-3">
                    كل متجر في الطلب يُشحن بطرد مستقل، فيدفع الزبون أجرة لكل طرد. اكتب ما يعادل سعر الشركة بالليرة، وعدّله عند تغيّر سعر الدولار.
                </p>
                @foreach ([
                    'shipping_parcel_fee' => ['أجرة الطرد (أقل من 10 كغ)', null],
                    'shipping_heavy_parcel_fee' => ['أجرة الطرد الثقيل (10 كغ أو أكثر)', null],
                    'shipping_home_extra' => ['مبلغ إضافي للتوصيل إلى البيت', 'لكل طرد. اكتب 0 إذا كان التوصيل للبيت بنفس سعر الاستلام من المركز.'],
                ] as $field => [$label, $hint])
                    <label class="form-label small mb-1" for="{{ $field }}">{{ $label }}</label>
                    <div class="input-group {{ $hint ? 'mb-1' : 'mb-3' }}">
                        <input type="number" step="0.01" name="{{ $field }}" id="{{ $field }}" class="form-control" value="{{ old($field, $setting->{$field} / 100) }}" min="0" required>
                        <span class="input-group-text">ل.س</span>
                    </div>
                    @if ($hint) <div class="form-text mb-3">{{ $hint }}</div> @endif
                    @error($field) <div class="text-danger small mb-3">{{ $message }}</div> @enderror
                @endforeach

                <hr class="my-4">

                <h2 class="h5 mb-3">مهل المرتجعات</h2>
                <p class="text-muted small mb-3">
                    عدد الأيام لكل مرحلة من مسار الإرجاع. التغيير يطبّق على المهل اللي تبدأ بعد الحفظ.
                </p>
                @foreach ([
                    'returns_window_days' => 'مدة الإرجاع بعد التسليم',
                    'returns_seller_response_days' => 'مهلة رد البائع قبل الإحالة للإدارة',
                    'returns_dispute_days' => 'مهلة فتح النزاع بعد رفض البائع',
                    'returns_ship_back_days' => 'مهلة إعادة المنتج بعد الموافقة',
                ] as $field => $label)
                    <label class="form-label small mb-1" for="{{ $field }}">{{ $label }}</label>
                    <div class="input-group mb-3">
                        <input type="number" name="{{ $field }}" id="{{ $field }}" class="form-control"
                            value="{{ old($field, $setting->{$field} ?? \App\Returns\ReturnSettings::DEFAULTS[$field]) }}" min="1" max="365" required>
                        <span class="input-group-text">أيام</span>
                    </div>
                    @error($field) <div class="text-danger small mb-3">{{ $message }}</div> @enderror
                @endforeach

                <hr class="my-4">

                <h2 class="h5 mb-3" id="social">حسابات التواصل الاجتماعي</h2>
                <p class="text-muted small mb-3">
                    الصق رابط كل حساب كما يظهر في المتصفح. تظهر أيقونته أسفل الموقع فوراً، والخانة الفارغة لا تظهر أيقونتها.
                </p>
                @foreach (\App\Models\PlatformSetting::SOCIAL as $field => $meta)
                    <label class="form-label small mb-1" for="{{ $field }}"><i class="bi {{ $meta['icon'] }}" aria-hidden="true"></i> {{ $meta['label'] }}</label>
                    <input type="url" name="{{ $field }}" id="{{ $field }}" class="form-control mb-3" dir="ltr"
                        value="{{ old($field, $setting->{$field}) }}" placeholder="https://www.{{ $meta['host'] }}/trendsham">
                    @error($field) <div class="text-danger small mb-3">{{ $message }}</div> @enderror
                @endforeach
                <label class="form-label small mb-1" for="social_whatsapp"><i class="bi bi-whatsapp" aria-hidden="true"></i> رقم واتساب</label>
                <input type="tel" name="social_whatsapp" id="social_whatsapp" class="form-control mb-1" dir="ltr"
                    value="{{ old('social_whatsapp', $setting->social_whatsapp) }}" placeholder="963912345678">
                <div class="form-text mb-3">مع رمز الدولة. يُفتح منه محادثة واتساب مباشرة.</div>
                @error('social_whatsapp') <div class="text-danger small mb-3">{{ $message }}</div> @enderror

                <button type="submit" class="btn btn-primary w-100">حفظ</button>
            </form>
        </div>
    </div>
</div>
@endsection
