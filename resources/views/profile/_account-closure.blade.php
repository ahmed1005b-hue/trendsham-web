{{-- إيقاف الحساب مؤقتاً أو حذفه نهائياً. المتغير: $user --}}
@php
    $closure = app(\App\Services\AccountClosureService::class);
    $canDelete = $closure->canSelfDelete($user);
    $blockers = $canDelete ? $closure->deletionBlockers($user) : [];
    $closureErrors = $errors->getBag('closure');
    $needsPassword = $user->password !== null;
@endphp
<div class="card mt-4 border-danger-subtle" id="account-closure">
    <div class="card-body">
        <h2 class="h5 mb-1">إدارة الحساب</h2>
        <p class="text-muted small mb-3">يمكنك إيقاف حسابك مؤقتاً، أو طلب حذفه نهائياً.</p>

        @if ($closureErrors->any())
            <div class="alert alert-danger py-2 small" role="alert">{{ $closureErrors->first() }}</div>
        @endif

        @if ($user->role === 'admin')
            <p class="small mb-0">حسابات المديرين لا تُحذف من هنا.</p>
        @else
            {{-- 1. الإيقاف المؤقت --}}
            <details class="mb-3">
                <summary class="fw-semibold">إيقاف الحساب مؤقتاً</summary>
                <div class="mt-2 small">
                    <p class="mb-2">تخرج من كل الأجهزة ولن تصلك إشعارات. بياناتك وطلباتك تبقى كما هي، ويعود حسابك بمجرد تسجيل الدخول مجدداً.</p>
                    <form action="{{ route('profile.account.deactivate') }}" method="POST"
                          data-confirm="إيقاف حسابك وتسجيل خروجك من كل الأجهزة؟">
                        @csrf
                        @if ($needsPassword)
                            <label for="closure-deactivate-password" class="form-label small">كلمة السر للتأكيد</label>
                            <input id="closure-deactivate-password" type="password" name="password" class="form-control mb-2" required autocomplete="current-password">
                        @endif
                        <button type="submit" class="btn btn-outline-secondary btn-sm">إيقاف الحساب</button>
                    </form>
                </div>
            </details>

            {{-- 2. الحذف النهائي --}}
            <details @if($closureErrors->any()) open @endif>
                <summary class="fw-semibold text-danger">حذف الحساب نهائياً</summary>
                <div class="mt-2 small">
                    @if (! $canDelete)
                        @if ($user->deletion_requested_at)
                            <div class="alert alert-info py-2 mb-0">{{ __('أرسلت طلب حذف حسابك بتاريخ :date، والإدارة ستتواصل معك لإتمامه.', ['date' => $user->deletion_requested_at->format('Y-m-d')]) }}</div>
                        @else
                            <p class="mb-2">حساب المتجر مرتبط برصيد وطلبات وسجلات مالية، لذلك تراجع الإدارة طلب الحذف وتتواصل معك لتسوية الرصيد قبل إتمامه.</p>
                            <form action="{{ route('profile.account.delete') }}" method="POST" data-confirm="إرسال طلب حذف حسابك إلى الإدارة؟">
                                @csrf
                                @if ($needsPassword)
                                    <label for="closure-delete-password" class="form-label small">كلمة السر للتأكيد</label>
                                    <input id="closure-delete-password" type="password" name="password" class="form-control mb-2" required autocomplete="current-password">
                                @endif
                                <input type="hidden" name="confirm" value="حذف">
                                <button type="submit" class="btn btn-outline-danger btn-sm">إرسال طلب الحذف</button>
                            </form>
                        @endif
                    @elseif ($blockers)
                        <div class="alert alert-warning py-2 mb-0">{{ $blockers[0] }}</div>
                    @else
                        <ul class="mb-2 ps-3">
                            <li>يُغلق حسابك فوراً وتخرج من كل الأجهزة.</li>
                            <li>{{ __('بعد :days يوماً تُمسح بياناتك الشخصية: الاسم والهاتف والبريد والصورة والعناوين والبطاقات والمفضلة والسلة.', ['days' => \App\Services\AccountClosureService::GRACE_DAYS]) }}</li>
                            <li>غيّرت رأيك؟ سجّل الدخول خلال المهلة فيُلغى الطلب.</li>
                            <li>تبقى سجلات الطلبات السابقة لدى البائعين لأغراض المحاسبة، باسم «مستخدم محذوف». ونقاط الولاء تُفقد.</li>
                        </ul>
                        <form action="{{ route('profile.account.delete') }}" method="POST"
                              data-confirm="{{ __('سيُغلق حسابك الآن وتُحذف بياناتك بعد :days يوماً. متابعة؟', ['days' => \App\Services\AccountClosureService::GRACE_DAYS]) }}">
                            @csrf
                            @if ($needsPassword)
                                <label for="closure-delete-password" class="form-label small">كلمة السر</label>
                                <input id="closure-delete-password" type="password" name="password" class="form-control mb-2" required autocomplete="current-password">
                            @endif
                            <label for="closure-confirm" class="form-label small">{!! __('اكتب كلمة :word للتأكيد', ['word' => '<strong>'.e(__('حذف')).'</strong>']) !!}</label>
                            <input id="closure-confirm" type="text" name="confirm" class="form-control mb-2" required autocomplete="off">
                            <button type="submit" class="btn btn-danger btn-sm">حذف حسابي</button>
                        </form>
                    @endif
                </div>
            </details>
        @endif
    </div>
</div>
