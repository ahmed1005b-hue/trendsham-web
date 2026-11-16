@extends('layouts.app')

@section('title', 'حجز مساحة عرض جديدة - لوحة البائع')

@push('styles')
<style>
    .color-picker-btn { width: 40px; height: 40px; border-radius: 8px; border: 2px solid transparent; cursor: pointer; transition: transform .15s, border-color .15s; }
    .color-picker-btn:hover { transform: scale(1.05); }
    .color-picker-btn.selected { border-color: #0F3D2E; box-shadow: 0 0 0 3px rgba(15,61,46,.2); }
    .preset-btn { border: 2px solid #ECE4D3; border-radius: 10px; padding: 12px; text-align: center; cursor: pointer; transition: all .15s; background: #fff; }
    .preset-btn:hover { border-color: #D4A537; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(212,165,55,.15); }
    .preset-btn.selected { border-color: #0F3D2E; background: linear-gradient(135deg, rgba(15,61,46,.05), rgba(212,165,55,.05)); }
    .preset-btn .preview { height: 50px; border-radius: 8px; margin-bottom: 8px; }
    .product-select-item { border: 1px solid #ECE4D3; border-radius: 8px; padding: 10px; transition: all .15s; background: #fff; }
    .product-select-item.selected { border-color: #0F3D2E; background: linear-gradient(135deg, rgba(15,61,46,.05), rgba(212,165,55,.05)); }
    .product-select-item input[type="checkbox"] { accent-color: #0F3D2E; }
    .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2rem; position: relative; }
    .step-indicator::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #ECE4D3; z-index: 1; }
    .step-indicator .step { position: relative; z-index: 2; text-align: center; flex: 1; }
    .step-indicator .step .circle { width: 40px; height: 40px; border-radius: 50%; background: #ECE4D3; border: 3px solid #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; color: #6B7A72; margin: 0 auto 8px; transition: all .3s; }
    .step-indicator .step.active .circle { background: #0F3D2E; color: #fff; border-color: #0F3D2E; }
    .step-indicator .step.completed .circle { background: #0F3D2E; color: #fff; border-color: #0F3D2E; }
    .step-indicator .step label { font-size: .8rem; color: #6B7A72; display: block; }
    .step-indicator .step.active label { color: #0F3D2E; font-weight: 700; }
    .contrast-warning { background: #FFF3E0; border: 1px solid #FFB74D; border-radius: 8px; padding: 12px; color: #E65100; display: none; }
    .contrast-warning.show { display: block; }
    .contrast-ok { background: #E8F5E9; border: 1px solid #81C784; border-radius: 8px; padding: 12px; color: #2E7D32; display: none; }
    .contrast-ok.show { display: block; }
    .preview-container { border: 1px solid #ECE4D3; border-radius: 16px; overflow: hidden; background: #fff; }
    .live-preview { min-height: 280px; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - حجز مساحة عرض</span>
        <a href="{{ route('seller.showcases.index') }}" class="btn btn-outline-secondary btn-sm">عودة</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-bold mb-1">تعذر إكمال الحجز:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- مؤشر الخطوات --}}
    <div class="step-indicator" id="stepIndicator">
        <div class="step" data-step="1">
            <div class="circle">1</div>
            <label>المساحة والتاريخ</label>
        </div>
        <div class="step" data-step="2">
            <div class="circle">2</div>
            <label>التصميم والألوان</label>
        </div>
        <div class="step" data-step="3">
            <div class="circle">3</div>
            <label>المنتجات</label>
        </div>
        <div class="step" data-step="4">
            <div class="circle">4</div>
            <label>المراجعة والدفع</label>
        </div>
    </div>

    <form id="showcaseForm" action="{{ route('seller.showcases.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- الخطوة 1: المساحة والتاريخ --}}
        <div class="form-step" data-step="1">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>الخطوة 1: اختر المساحة والفترة</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">المساحة <span class="text-danger">*</span></label>
                            <div class="row g-2" id="slotsContainer">
                                @foreach ($slots as $slot)
                                    <div class="col-md-6">
                                        <div class="preset-btn slot-btn" data-slot-id="{{ $slot->id }}" data-price="{{ $slot->price_per_day }}" data-min-days="{{ $slot->min_days }}" data-max-days="{{ $slot->max_days }}" data-min-products="{{ $slot->min_products }}" data-max-products="{{ $slot->max_products }}">
                                            <div class="fw-bold">{{ $slot->name }}</div>
                                            <div class="small text-muted">{{ $slot->key }}</div>
                                            <div class="mt-2 fw-bold text-success">{{ __(':price ل.س/يوم', ['price' => number_format($slot->price_per_day, 2)]) }}</div>
                                            <div class="small text-muted">{{ __('سعة: :capacity | منتجات: :min-:max', ['capacity' => $slot->capacity, 'min' => $slot->min_products, 'max' => $slot->max_products]) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="slot_id" id="selectedSlotId" required>
                            @error('slot_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="startDate" class="form-label fw-bold">تاريخ البداية <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="startDate" class="form-control" required min="{{ now()->format('Y-m-d') }}">
                            @error('start_date')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="endDate" class="form-label fw-bold">تاريخ النهاية <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="endDate" class="form-control" required min="{{ now()->format('Y-m-d') }}">
                            @error('end_date')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info d-flex justify-content-between align-items-center" id="availabilityInfo" style="display:none;">
                                <div>
                                    <strong>المساحة المختارة:</strong> <span id="slotName"></span><br>
                                    <strong>المدة:</strong> <span id="durationDays"></span> أيام |
                                    <strong>الإجمالي:</strong> <span id="totalAmount"></span> ل.س
                                </div>
                                <span class="badge bg-success" id="availabilityBadge">متاحة ✓</span>
                            </div>
                            <div class="alert alert-warning d-none" id="notAvailableInfo">
                                <span id="notAvailableReason"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary btn-next" data-next="2">التالي <i class="bi bi-chevron-left ms-1"></i></button>
            </div>
        </div>

        {{-- الخطوة 2: التصميم والألوان --}}
        <div class="form-step d-none" data-step="2">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-palette me-2"></i>الخطوة 2: تصميم العرض</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="f-title" class="form-label fw-bold">العنوان <span class="text-danger">*</span></label>
                            <input id="f-title" type="text" name="title" class="form-control" required maxlength="60" placeholder="مثال: عروض نهاية الأسبوع">
                            <div class="form-text">حتى 60 حرفاً</div>
                            @error('title')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="f-subtitle" class="form-label">النص الفرعي</label>
                            <input id="f-subtitle" type="text" name="subtitle" class="form-control" maxlength="100" placeholder="مثال: خصومات تصل إلى 50% على أفضل المنتجات">
                            <div class="form-text">اختياري، حتى 100 حرف</div>
                            @error('subtitle')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">تدرج الألوان <span class="text-danger">*</span></label>
                            <div class="form-text">اختر من الألوان الجاهزة أو اصنع تدرجاً مخصصاً (2-3 ألوان)</div>
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2 mb-3" id="presetsContainer">
                                @foreach ($presets as $name => $colors)
                                    <div class="preset-btn preset-color-btn" data-colors='@json($colors)' style="min-width: 140px;">
                                        <div class="preview" style="background: linear-gradient(45deg, {{ implode(', ', $colors) }});"></div>
                                        <div class="small fw-bold">{{ $name }}</div>
                                        <div class="text-muted small">{{ implode(', ', $colors) }}</div>
                                    </div>
                                @endforeach
                                <div class="preset-btn" id="customGradientBtn" style="min-width: 140px; border-style: dashed;">
                                    <div class="preview" id="customPreview" style="background: #f8f9fa;"></div>
                                    <div class="small fw-bold">تدرج مخصص</div>
                                    <div class="text-muted small">اختر 2-3 ألوان</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="customColorsSection" style="display:none;">
                            <label class="form-label">اختر 2 إلى 3 ألوان</label>
                            <div class="d-flex flex-wrap gap-2" id="colorPickerContainer">
                                <div class="color-picker-btn" data-color="#0F3D2E" style="background:#0F3D2E;" title="#0F3D2E"></div>
                                <div class="color-picker-btn" data-color="#D4A537" style="background:#D4A537;" title="#D4A537"></div>
                                <div class="color-picker-btn" data-color="#2E7D5B" style="background:#2E7D5B;" title="#2E7D5B"></div>
                                <div class="color-picker-btn" data-color="#141E30" style="background:#141E30;" title="#141E30"></div>
                                <div class="color-picker-btn" data-color="#243B55" style="background:#243B55;" title="#243B55"></div>
                                <div class="color-picker-btn" data-color="#B24592" style="background:#B24592;" title="#B24592"></div>
                                <div class="color-picker-btn" data-color="#F15F79" style="background:#F15F79;" title="#F15F79"></div>
                                <div class="color-picker-btn" data-color="#4A00E0" style="background:#4A00E0;" title="#4A00E0"></div>
                                <div class="color-picker-btn" data-color="#8E2DE2" style="background:#8E2DE2;" title="#8E2DE2"></div>
                                <div class="color-picker-btn" data-color="#00B09B" style="background:#00B09B;" title="#00B09B"></div>
                                <div class="color-picker-btn" data-color="#96C93D" style="background:#96C93D;" title="#96C93D"></div>
                                <div class="color-picker-btn" data-color="#FF512F" style="background:#FF512F;" title="#FF512F"></div>
                                <div class="color-picker-btn" data-color="#F09819" style="background:#F09819;" title="#F09819"></div>
                                <div class="color-picker-btn" data-color="#1A2980" style="background:#1A2980;" title="#1A2980"></div>
                                <div class="color-picker-btn" data-color="#26D0CE" style="background:#26D0CE;" title="#26D0CE"></div>
                                <div class="color-picker-btn" data-color="#780206" style="background:#780206;" title="#780206"></div>
                                <div class="color-picker-btn" data-color="#C79081" style="background:#C79081;" title="#C79081"></div>
                                <div class="color-picker-btn" data-color="#DFA579" style="background:#DFA579;" title="#DFA579"></div>
                                <div class="color-picker-btn" data-color="#FFFFFF" style="background:#fff; border:1px solid #ddd;" title="#FFFFFF"></div>
                                <div class="color-picker-btn" data-color="#111111" style="background:#111;" title="#111111"></div>
                                <div class="color-picker-btn" style="background:repeating-conic-gradient(#000 0 25%, transparent 0 50%) 50%/200% 200%;" title="ألوان أخرى" id="moreColorsBtn"><i class="bi bi-plus-lg text-muted"></i></div>
                            </div>
                            <div class="form-text mt-2">الألوان المختارة: <span id="selectedColorsCount">0</span>/3</div>
                            <div id="gradientColorsInputs"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="gradientDirection" class="form-label fw-bold">اتجاه التدرج <span class="text-danger">*</span></label>
                            <select name="gradient_direction" id="gradientDirection" class="form-select" required>
                                <option value="diagonal">قطري (الافتراضي)</option>
                                <option value="vertical">عمودي (أعلى → أسفل)</option>
                                <option value="horizontal">أفقي (يمين → يسار)</option>
                            </select>
                            @error('gradient_direction')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="textColorInput">لون الخط</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap" id="textColorDisplay">
                                <button type="button" class="btn btn-sm btn-outline-secondary text-color-quick" data-color="#FFFFFF">
                                    <span class="d-inline-block rounded-circle border" style="width:14px;height:14px;background:#FFFFFF;vertical-align:middle"></span> أبيض
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary text-color-quick" data-color="#111111">
                                    <span class="d-inline-block rounded-circle border" style="width:14px;height:14px;background:#111111;vertical-align:middle"></span> أسود
                                </button>
                                <input type="color" class="form-control form-control-color" id="textColorInput" value="{{ old('text_color_hex', '#FFFFFF') }}" title="اختر أي لون للخط">
                                <input type="hidden" name="text_color_hex" id="textColorHex" value="{{ old('text_color_hex') }}">
                                <span class="badge bg-secondary" id="textColorBadge">#FFFFFF</span>
                            </div>
                            <div class="small text-muted mt-1" id="contrastMessage"></div>
                            @error('text_color_hex')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold" for="backgroundImage">صورة خلفية للإعلان <span class="text-muted fw-normal">(اختياري)</span></label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="file" name="background_image" id="backgroundImage" class="form-control" style="max-width:420px" accept="image/jpeg,image/png,image/webp">
                                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="removeBackground"><i class="bi bi-x-lg"></i> إزالة الصورة</button>
                            </div>
                            <div class="form-text">تظهر خلف المنتجات بدل التدرج (يبقى التدرج احتياطاً). الأفضل صورة عريضة 1600×500 تقريباً — JPG أو PNG أو WEBP حتى 3 ميغابايت.</div>
                            @error('background_image')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- معاينة حية --}}
                    <div class="card mt-3 preview-container">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-eye me-1"></i>معاينة حية</h6>
                        </div>
                        <div class="card-body live-preview" id="livePreview">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-palette fs-1"></i>
                                <p class="mt-2">أكمل التصميم لرؤية المعاينة</p>
                            </div>
                        </div>
                    </div>

                    <div class="contrast-warning" id="contrastWarning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span id="contrastWarningText"></span>
                    </div>
                    <div class="contrast-ok" id="contrastOk">
                        <i class="bi bi-check-circle me-2"></i>
                        الخط مقروء وواضح على جميع ألوان التدرج
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="1"><i class="bi bi-chevron-right me-1"></i> رجوع</button>
                <button type="button" class="btn btn-primary btn-next" data-next="3">التالي <i class="bi bi-chevron-left ms-1"></i></button>
            </div>
        </div>

        {{-- الخطوة 3: المنتجات --}}
        <div class="form-step d-none" data-step="3">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>الخطوة 3: اختر المنتجات (5-10)</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        اختر <strong id="minProductsRequired">5</strong> إلى <strong id="maxProductsAllowed">10</strong> منتجات من متجرك.
                        <span id="selectedProductsCountBadge" class="badge bg-primary ms-2">تم اختيار 0</span>
                    </div>

                    <div class="row g-2" id="productsContainer">
                        @forelse ($products as $product)
                            <div class="col-md-4 col-lg-3">
                                <div class="product-select-item" data-product-id="{{ $product['id'] }}">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product['id'] }}" class="product-checkbox d-none">
                                    @if ($product['image_url'] ?? false)
                                        <img src="{{ $product['image_url'] }}" alt="" class="img-fluid rounded mb-2" style="height:120px;object-fit:cover;width:100%;">
                                    @else
                                        <div class="bg-light rounded mb-2 d-flex align-items-center justify-content-center" style="height:120px;">
                                            <i class="bi bi-image text-muted fs-3"></i>
                                        </div>
                                    @endif
                                    <div class="fw-bold small text-truncate">{{ $product['name'] }}</div>
                                    <div class="text-success fw-bold small">{{ number_format($product['price'], 0) }} ل.س</div>
                                    @if ($product['compare_at_price'])
                                        <div class="text-muted small text-decoration-line-through">{{ number_format($product['compare_at_price'], 0) }} ل.س</div>
                                    @endif
                                    <div class="text-muted small">المخزون: {{ $product['stock'] }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted py-4">لا توجد منتجات متاحة للحجز</div>
                        @endforelse
                    </div>

                    <input type="hidden" id="productIdsInput">
                    @error('product_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="2"><i class="bi bi-chevron-right me-1"></i> رجوع</button>
                <button type="button" class="btn btn-primary btn-next" data-next="4" id="goToReviewBtn" disabled>التالي <i class="bi bi-chevron-left ms-1"></i></button>
            </div>
        </div>

        {{-- الخطوة 4: المراجعة والدفع --}}
        <div class="form-step d-none" data-step="4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>الخطوة 4: المراجعة والدفع من المحفظة</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2">ملخص الحجز</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr><td>المساحة</td><td class="text-end fw-bold" id="reviewSlotName">-</td></tr>
                                    <tr><td>العنوان</td><td class="text-end" id="reviewTitle">-</td></tr>
                                    <tr><td>الفترة</td><td class="text-end" id="reviewPeriod">-</td></tr>
                                    <tr><td>المدة</td><td class="text-end" id="reviewDuration">-</td></tr>
                                    <tr><td class="fw-bold">الإجمالي</td><td class="text-end fw-bold text-success fs-5" id="reviewTotal">-</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2">رصيد المحفظة</h6>
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <div class="display-6 text-success" id="walletBalance">0 ل.س</div>
                                    <div class="text-muted small">رصيدك الحالي</div>
                                    @if (auth()->user()->sellerProfile->wallet)
                                        <a href="{{ route('seller.wallet.index') }}" class="btn btn-sm btn-outline-primary mt-2">شحن المحفظة</a>
                                    @endif
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3 d-none" id="insufficientBalanceAlert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                رصيد محفظتك غير كافٍ. يرجى شحن المحفظة أولاً.
                            </div>
                        </div>

                        <div class="col-12">
                            <h6 class="fw-bold border-bottom pb-2">الشروط والأحكام</h6>
                            <div class="small text-muted">
                                <ol class="mb-0 ps-3">
                                    <li>العرض يظهر بعد مراجعة الإدارة، ويحق للإدارة رفضه أو إزالته إن خالف الشروط.</li>
                                    <li>المنتجات المعروضة يجب أن تكون فعّالة ومتوفرة، وأسعارها صحيحة.</li>
                                    <li>عند الرفض يُسترجع المبلغ كاملاً للمحفظة. عند الإلغاء قبل بدء الفترة يُسترجع كاملاً.</li>
                                    <li>بعد بدء الفترة لا يُلغى إلا عبر الدعم. إن أزالت الإدارة العرض يُسترجع مبلغ الأيام المتبقية.</li>
                                    <li>إن نقصت المنتجات المتاحة عن الحد الأدنى بسبب نفاد المخزون يُخفى العرض مؤقتاً دون تمديد الفترة.</li>
                                    <li>يظهر على العرض وسم "إعلان" بوضوح.</li>
                                </ol>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="termsCheck" required>
                                <label class="form-check-label" for="termsCheck">أوافق على الشروط والأحكام وأؤكد أن المنتجات المختارة ملك متجري ومتوفرة</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-prev" data-prev="3"><i class="bi bi-chevron-right me-1"></i> رجوع</button>
                <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                    <i class="bi bi-wallet2 me-2"></i> ادفع من محفظتي واحجز
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// ألوان جاهزة (تطابق kGradientPresets في Flutter)
const presets = {
    @json(__('غروب')): ['#F46B45', '#EEA849'],
    @json(__('محيط')): ['#2193B0', '#6DD5ED'],
    @json(__('غابة')): ['#0F3D2E', '#2E7D5B'],
    @json(__('ذهب أخضر')): ['#0F3D2E', '#9A7425'],
    @json(__('ليل')): ['#141E30', '#243B55'],
    @json(__('وردي')): ['#B24592', '#F15F79'],
    @json(__('بنفسجي')): ['#4A00E0', '#8E2DE2'],
    @json(__('نعناع')): ['#00B09B', '#96C93D'],
    @json(__('عنابي')): ['#780206', '#061161'],
    @json(__('رمل')): ['#C79081', '#DFA579'],
    @json(__('ثلاثي دافئ')): ['#FF512F', '#F09819', '#FFD200'],
    @json(__('ثلاثي بارد')): ['#1A2980', '#1F6F8B', '#2A8C7C'],
};

let selectedColors = [];
// لون الخط يختاره البائع؛ إلى أن يلمسه نقترح الأبيض أو الأسود الأوضح على التدرج
let textColorTouched = !!document.getElementById('textColorHex').value;
let selectedSlot = null;
let selectedSlotAvailable = false;
let walletBalance = {{ auth()->user()->sellerProfile->wallet ? auth()->user()->sellerProfile->wallet->balance / 100 : 0 }};

// تهيئة التواريخ
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('walletBalance').textContent = walletBalance.toLocaleString() + ' ' + @json(__('ل.س'));
    updateSubmitButton();
});

// التنقل بين الخطوات
document.querySelectorAll('.btn-next').forEach(btn => {
    btn.addEventListener('click', () => goToStep(parseInt(btn.dataset.next)));
});
document.querySelectorAll('.btn-prev').forEach(btn => {
    btn.addEventListener('click', () => goToStep(parseInt(btn.dataset.prev)));
});

function goToStep(step) {
    document.querySelectorAll('.form-step').forEach(s => s.classList.add('d-none'));
    document.querySelector(`.form-step[data-step="${step}"]`).classList.remove('d-none');
    document.querySelectorAll('.step').forEach(s => {
        s.classList.remove('active', 'completed');
        if (parseInt(s.dataset.step) < step) s.classList.add('completed');
        if (parseInt(s.dataset.step) === step) s.classList.add('active');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// اختيار المساحة
document.querySelectorAll('.slot-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedSlot = {
            id: btn.dataset.slotId,
            name: btn.querySelector('.fw-bold').textContent,
            price: parseFloat(btn.dataset.price),
            minDays: parseInt(btn.dataset.minDays),
            maxDays: parseInt(btn.dataset.maxDays),
            minProducts: parseInt(btn.dataset.minProducts),
            maxProducts: parseInt(btn.dataset.maxProducts),
        };
        selectedSlotAvailable = false;
        document.getElementById('selectedSlotId').value = selectedSlot.id;
        checkAvailability();
        updateReviewStep();
        updateSubmitButton();
    });
});

// التحقق من التوفر عند تغيير التواريخ
['startDate', 'endDate'].forEach(id => {
    document.getElementById(id).addEventListener('change', checkAvailability);
});

async function checkAvailability() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    if (!start || !end || !selectedSlot) {
        selectedSlotAvailable = false;
        document.getElementById('availabilityInfo').style.display = 'none';
        document.getElementById('notAvailableInfo').classList.add('d-none');
        updateSubmitButton();
        return;
    }

    selectedSlotAvailable = false;
    updateSubmitButton();

    try {
        const res = await fetch(`{{ route('seller.showcases.slots-availability') }}?start_date=${start}&end_date=${end}`);
        const data = await res.json();
        const slotData = data.find(s => s.id == selectedSlot.id);
        if (slotData) {
            selectedSlotAvailable = slotData.available;
            if (slotData.available) {
                document.getElementById('availabilityInfo').style.display = 'flex';
                document.getElementById('notAvailableInfo').classList.add('d-none');
                document.getElementById('slotName').textContent = slotData.name;
                document.getElementById('durationDays').textContent = slotData.days;
                document.getElementById('totalAmount').textContent = slotData.total.toLocaleString();
                document.getElementById('availabilityBadge').textContent = @json(__('متاحة ✓'));
                document.getElementById('availabilityBadge').className = 'badge bg-success';
            } else {
                document.getElementById('availabilityInfo').style.display = 'none';
                document.getElementById('notAvailableInfo').classList.remove('d-none');
                document.getElementById('notAvailableReason').textContent = slotData.not_available_reason;
                document.getElementById('availabilityBadge').textContent = @json(__('غير متاحة ✗'));
                document.getElementById('availabilityBadge').className = 'badge bg-danger';
            }
            updateReviewStep();
        } else {
            selectedSlotAvailable = false;
            updateSubmitButton();
        }
    } catch (e) {
        console.error(e);
        selectedSlotAvailable = false;
        document.getElementById('availabilityInfo').style.display = 'none';
        document.getElementById('notAvailableInfo').classList.remove('d-none');
        document.getElementById('notAvailableReason').textContent = @json(__('تعذر التحقق من توفر المساحة. حاول مجددًا.'));
        updateSubmitButton();
    }
}

// اختيار الألوان الجاهزة
document.querySelectorAll('.preset-color-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.preset-color-btn').forEach(b => b.classList.remove('selected'));
        document.getElementById('customGradientBtn').classList.remove('selected');
        document.getElementById('customColorsSection').style.display = 'none';
        btn.classList.add('selected');
        selectedColors = JSON.parse(btn.dataset.colors);
        updateGradientInput();
        checkContrast();
        updateLivePreview();
    });
});

// تدرج مخصص
document.getElementById('customGradientBtn').addEventListener('click', () => {
    document.querySelectorAll('.preset-color-btn').forEach(b => b.classList.remove('selected'));
    document.getElementById('customGradientBtn').classList.add('selected');
    document.getElementById('customColorsSection').style.display = 'block';
});

// اختيار لون من لوحة الألوان
document.querySelectorAll('.color-picker-btn[data-color]').forEach(btn => {
    btn.addEventListener('click', () => {
        const color = btn.dataset.color;
        const idx = selectedColors.indexOf(color);
        if (idx > -1) {
            selectedColors.splice(idx, 1);
            btn.classList.remove('selected');
        } else if (selectedColors.length < 3) {
            selectedColors.push(color);
            btn.classList.add('selected');
        } else {
            window.tsAlert(@json(__('يمكنك اختيار 3 ألوان كحد أقصى')));
            return;
        }
        document.getElementById('selectedColorsCount').textContent = selectedColors.length;
        updateGradientInput();
        checkContrast();
        updateLivePreview();
    });
});

function updateGradientInput() {
    const inputs = document.getElementById('gradientColorsInputs');
    inputs.replaceChildren(...selectedColors.map(color => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'gradient_colors[]';
        input.value = color;
        return input;
    }));
    document.getElementById('customPreview').style.background = 
        selectedColors.length >= 2 ? `linear-gradient(45deg, ${selectedColors.join(', ')})` : '#f8f9fa';
    document.querySelector('select[name="gradient_direction"]').dispatchEvent(new Event('change'));
    updateSubmitButton();
}

// لون الخط ونسبة التباين (نفس معادلة الخادم ColorContrast)
function luminance(hex) {
    const v = [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255)
        .map(c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
    return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
}
function ratio(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}
function minRatio(text) {
    return Math.min(...selectedColors.map(c => ratio(text, c)));
}
function currentTextColor() {
    return (document.getElementById('textColorInput').value || '#FFFFFF').toUpperCase();
}
function setTextColor(hex, byUser) {
    if (byUser) textColorTouched = true;
    document.getElementById('textColorInput').value = hex.toLowerCase();
    document.getElementById('textColorHex').value = textColorTouched ? hex.toUpperCase() : '';
    checkContrast();
    updateLivePreview();
}
document.querySelectorAll('.text-color-quick').forEach(b => b.addEventListener('click', () => setTextColor(b.dataset.color, true)));
document.getElementById('textColorInput').addEventListener('input', e => setTextColor(e.target.value, true));

function checkContrast() {
    const warn = document.getElementById('contrastWarning');
    const ok = document.getElementById('contrastOk');
    const badge = document.getElementById('textColorBadge');
    if (selectedColors.length < 2) {
        warn.classList.remove('show');
        ok.classList.remove('show');
        document.getElementById('contrastMessage').textContent = '';
        return;
    }
    // اقتراح تلقائي حتى يختار البائع بنفسه
    if (!textColorTouched) {
        const auto = minRatio('#FFFFFF') >= minRatio('#111111') ? '#FFFFFF' : '#111111';
        document.getElementById('textColorInput').value = auto.toLowerCase();
    }
    const text = currentTextColor();
    const r = minRatio(text);
    badge.textContent = text;
    badge.style.background = text;
    badge.style.color = luminance(text) > 0.5 ? '#111' : '#fff';
    document.getElementById('contrastMessage').textContent =
        @json(__('نسبة التباين: :ratio:1')).replace(':ratio', r.toFixed(1)) + (textColorTouched ? '' : ' ' + @json(__('(اقتراح تلقائي — يمكنك تغييره)')));

    if (r >= 3) {
        ok.classList.add('show');
        warn.classList.remove('show');
    } else {
        warn.classList.add('show');
        ok.classList.remove('show');
        document.getElementById('contrastWarningText').textContent =
            @json(__('تباين ضعيف: قد يصعب على العملاء قراءة الخط على بعض ألوان التدرج. يمكنك المتابعة، والقرار لك.'));
    }
}

// صورة الخلفية: معاينة فورية من جهاز البائع قبل الرفع
let bgPreviewUrl = null;
const bgInput = document.getElementById('backgroundImage');
const bgRemove = document.getElementById('removeBackground');
bgInput.addEventListener('change', () => {
    if (bgPreviewUrl) URL.revokeObjectURL(bgPreviewUrl);
    const file = bgInput.files[0];
    if (file && file.size > 3 * 1024 * 1024) {
        window.tsAlert(@json(__('حجم الصورة أكبر من 3 ميغابايت')));
        bgInput.value = '';
    }
    bgPreviewUrl = bgInput.files[0] ? URL.createObjectURL(bgInput.files[0]) : null;
    bgRemove.classList.toggle('d-none', !bgPreviewUrl);
    updateLivePreview();
});
bgRemove.addEventListener('click', () => { bgInput.value = ''; bgInput.dispatchEvent(new Event('change')); });

// تحديث المعاينة الحية
function updateLivePreview() {
    const title = document.querySelector('input[name="title"]').value || @json(__('عنوان العرض'));
    const subtitle = document.querySelector('input[name="subtitle"]').value || '';
    const direction = document.getElementById('gradientDirection').value;
    const storeName = '{{ auth()->user()->sellerProfile->store_name }}';
    const storeLogo = '{{ auth()->user()->sellerProfile->store_logo ? asset("storage/" . auth()->user()->sellerProfile->store_logo) : "" }}';

    let gradientDir = '45deg';
    if (direction === 'vertical') gradientDir = 'to bottom';
    else if (direction === 'horizontal') gradientDir = 'to left';

    const textColor = currentTextColor();
    const badgeBg = luminance(textColor) > 0.5 ? 'rgba(255,255,255,0.2)' : 'rgba(17,17,17,0.2)';

    let productsHtml = '';
    document.querySelectorAll('.product-select-item.selected').forEach((item, i) => {
        if (i >= 5) return;
        const name = item.querySelector('.fw-bold').textContent;
        const price = item.querySelector('.text-success').textContent;
        productsHtml += `
            <div style="width:140px;flex-shrink:0;scroll-snap-align:start;">
                <div class="bg-white bg-opacity-10 rounded p-2 text-center">
                    <div style="height:100px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-image fs-2"></i>
                    </div>
                    <div class="mt-2 text-start">
                        <small class="d-block text-truncate">${name}</small>
                        <span class="fw-bold">${price}</span>
                    </div>
                </div>
            </div>
        `;
    });

    document.getElementById('livePreview').innerHTML = `
        <div style="background: ${bgPreviewUrl ? `url('${bgPreviewUrl}') center/cover, ` : ''}linear-gradient(${gradientDir}, ${selectedColors.join(', ') || '#0F3D2E, #D4A537'}); border-radius: 20px; padding: 20px; color: ${textColor}; direction: rtl;">
            <div class="d-flex align-items-center mb-3">
                <div class="me-3">
                    ${storeLogo ? `<img src="${storeLogo}" alt="" width="50" height="50" class="rounded-circle">` : 
                      `<div class="d-flex align-items-center justify-content-center rounded-circle ${badgeBg}" style="width:50px;height:50px;"><i class="bi bi-shop fs-3"></i></div>`
                    }
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1">${title}</h5>
                    ${subtitle ? `<small>${subtitle}</small>` : ''}
                </div>
                <span class="badge" style="background:${badgeBg};">${@json(__('إعلان'))}</span>
            </div>
            <div class="d-flex gap-2 overflow-auto pb-2" style="scroll-snap-type: x mandatory;">
                ${productsHtml || '<div class="text-center text-white-50 w-100 py-3">' + @json(__('اختر المنتجات في الخطوة التالية')) + '</div>'}
            </div>
        </div>
    `;
}

// مراقبة تغييرات الحقول
document.querySelector('input[name="title"]').addEventListener('input', updateLivePreview);
document.querySelector('input[name="subtitle"]').addEventListener('input', updateLivePreview);
document.getElementById('gradientDirection').addEventListener('change', () => {
    checkContrast();
    updateLivePreview();
});

// اختيار المنتجات
document.querySelectorAll('.product-select-item').forEach(item => {
    item.addEventListener('click', (e) => {
        if (e.target.type === 'checkbox') return;
        const checkbox = item.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        item.classList.toggle('selected', checkbox.checked);
        updateProductsSelection();
    });
    item.querySelector('input[type="checkbox"]').addEventListener('change', () => {
        item.classList.toggle('selected', item.querySelector('input[type="checkbox"]').checked);
        updateProductsSelection();
    });
});

function updateProductsSelection() {
    const selected = document.querySelectorAll('.product-select-item.selected');
    const count = selected.length;
    document.getElementById('selectedProductsCountBadge').textContent = @json(__('تم اختيار :n')).replace(':n', count);
    document.getElementById('productIdsInput').value = Array.from(selected).map(i => i.dataset.productId).join(',');
    
    const minP = selectedSlot?.minProducts || 5;
    const maxP = selectedSlot?.maxProducts || 10;
    document.getElementById('goToReviewBtn').disabled = count < minP || count > maxP;
    document.getElementById('minProductsRequired').textContent = minP;
    document.getElementById('maxProductsAllowed').textContent = maxP;
    
    updateLivePreview();
    updateReviewStep();
    updateSubmitButton();
}

function updateReviewStep() {
    const period = document.getElementById('reviewPeriod');
    const duration = document.getElementById('reviewDuration');
    const totalElement = document.getElementById('reviewTotal');
    const insufficientBalanceAlert = document.getElementById('insufficientBalanceAlert');

    if (!selectedSlot) {
        document.getElementById('reviewSlotName').textContent = '-';
        document.getElementById('reviewTitle').textContent = document.querySelector('input[name="title"]').value || '-';
        period.textContent = '-';
        duration.textContent = '-';
        totalElement.textContent = '-';
        insufficientBalanceAlert.classList.add('d-none');
        updateSubmitButton();
        return;
    }

    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    document.getElementById('reviewSlotName').textContent = selectedSlot.name;
    document.getElementById('reviewTitle').textContent = document.querySelector('input[name="title"]').value || '-';

    if (start && end) {
        const days = Math.ceil((new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24)) + 1;
        const total = days * selectedSlot.price;
        period.textContent = `${start} → ${end}`;
        duration.textContent = @json(__(':n أيام')).replace(':n', days);
        totalElement.textContent = total.toLocaleString() + ' ' + @json(__('ل.س'));
        insufficientBalanceAlert.classList.toggle('d-none', walletBalance >= total);
    } else {
        period.textContent = '-';
        duration.textContent = '-';
        totalElement.textContent = '-';
        insufficientBalanceAlert.classList.add('d-none');
    }

    updateSubmitButton();
}

function updateSubmitButton() {
    const hasDates = !!document.getElementById('startDate').value && !!document.getElementById('endDate').value;
    // The server rechecks capacity during booking; an availability lookup failure must not lock the submit button.
    const step1Valid = !!selectedSlot && hasDates;
    const step2Valid = selectedColors.length >= 2;
    const selectedCount = document.querySelectorAll('.product-select-item.selected').length;
    const step3Valid = selectedCount >= (selectedSlot?.minProducts || 5)
        && selectedCount <= (selectedSlot?.maxProducts || 10);
    const step4Valid = document.getElementById('termsCheck').checked;
    const enoughBalance = document.getElementById('insufficientBalanceAlert').classList.contains('d-none');

    document.getElementById('submitBtn').disabled = !(
        step1Valid && step2Valid && step3Valid && step4Valid && enoughBalance
    );
}
document.getElementById('termsCheck').addEventListener('change', updateSubmitButton);

// منع الإرسال إن كان هناك أخطاء
document.getElementById('showcaseForm').addEventListener('submit', (e) => {
    if (!selectedSlot) { e.preventDefault(); window.tsAlert(@json(__('اختر مساحة'))); return; }
    if (selectedColors.length < 2) { e.preventDefault(); window.tsAlert(@json(__('اختر ألوان التدرج'))); return; }
    const selectedProducts = document.querySelectorAll('.product-select-item.selected');
    if (selectedProducts.length < (selectedSlot?.minProducts || 5)) { e.preventDefault(); window.tsAlert(@json(__('اختر المنتجات المطلوبة'))); return; }
    if (!document.getElementById('termsCheck').checked) { e.preventDefault(); window.tsAlert(@json(__('يجب الموافقة على الشروط'))); return; }
    if (walletBalance < parseFloat(document.getElementById('reviewTotal').textContent.replace(/,/g, ''))) { e.preventDefault(); window.tsAlert(@json(__('رصيد المحفظة غير كافٍ'))); return; }
});
</script>
@endpush
