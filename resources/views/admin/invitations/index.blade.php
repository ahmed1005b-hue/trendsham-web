@extends('layouts.app')

@section('title', 'دعوات البائعين والموظفين - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - دعوات الانضمام</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-light btn-sm">لوحة الإدارة</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-light border small">
        إنشاء الحساب من الموقع والتطبيق للعملاء فقط. البائع والموظف ينضمان برابط دعوة تنشئه هنا وترسله لهم وحدهم.
        كل رابط يُستعمل مرة واحدة، وينتهي بعد المدة التي تختارها، ويمكنك إلغاؤه في أي وقت.
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">دعوة جديدة</h2>
                    <form action="{{ route('admin.invitations.store') }}" method="POST">
                        @csrf
                        <label class="form-label small mb-1" for="inv-role">نوع الدعوة</label>
                        <select name="role" id="inv-role" class="form-select mb-3">
                            <option value="seller" @selected(old('role') === 'seller')>بائع - فتح متجر</option>
                            <option value="employee" @selected(old('role') === 'employee')>موظف - فريق العمل</option>
                        </select>

                        <label class="form-label small mb-1" for="inv-name">لمن الدعوة؟ (اسم للتذكير)</label>
                        <input type="text" name="name" id="inv-name" class="form-control mb-3" maxlength="255" value="{{ old('name') }}">

                        <div id="inv-store-box">
                            <label class="form-label small mb-1" for="inv-store">اسم المتجر (اختياري)</label>
                            <input type="text" name="store_name" id="inv-store" class="form-control mb-3" maxlength="255" value="{{ old('store_name') }}">
                        </div>

                        <label class="form-label small mb-1" for="inv-phone">رقم هاتفه (اختياري)</label>
                        <input type="tel" name="phone" id="inv-phone" class="form-control mb-1 @error('phone') is-invalid @enderror" dir="ltr" value="{{ old('phone') }}">
                        @error('phone') <div class="text-danger small">{{ $message }}</div> @enderror
                        <label class="form-label small mb-1 mt-2" for="inv-email">بريده (اختياري)</label>
                        <input type="email" name="email" id="inv-email" class="form-control mb-1 @error('email') is-invalid @enderror" dir="ltr" value="{{ old('email') }}">
                        @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
                        <div class="form-text mb-3">إذا كتبت الهاتف أو البريد، لا يقبل الدعوة إلا صاحبه.</div>

                        <label class="form-label small mb-1" for="inv-days">صالحة لمدة</label>
                        <select name="days" id="inv-days" class="form-select mb-3">
                            @foreach ([1 => 'يوم واحد', 3 => '3 أيام', 7 => 'أسبوع', 14 => 'أسبوعين', 30 => 'شهر'] as $days => $label)
                                <option value="{{ $days }}" @selected((int) old('days', 7) === $days)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <button class="btn btn-primary w-100"><i class="bi bi-link-45deg" aria-hidden="true"></i> إنشاء رابط الدعوة</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>الدعوة</th>
                                <th>الحالة</th>
                                <th>الرابط</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invitations as $invitation)
                                @php($usable = $invitation->problem() === null)
                                <tr @class(['table-success' => session('new_invitation') === $invitation->id])>
                                    <td>
                                        <strong>{{ $invitation->roleLabel() }}</strong>
                                        @if ($invitation->name) — <span translate="no">{{ $invitation->name }}</span> @endif
                                        <div class="small text-muted">
                                            @if ($invitation->store_name) <span translate="no">{{ $invitation->store_name }}</span> · @endif
                                            @if ($invitation->phone) <span dir="ltr">{{ $invitation->phone }}</span> · @endif
                                            @if ($invitation->email) <span dir="ltr">{{ $invitation->email }}</span> · @endif
                                            حتى {{ $invitation->expires_at->format('Y-m-d') }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $usable ? 'bg-warning text-dark' : ($invitation->accepted_at ? 'bg-success' : 'bg-secondary') }}">{{ $invitation->statusLabel() }}</span>
                                        @if ($invitation->acceptor)
                                            <div class="small text-muted">{{ $invitation->acceptor->name }}</div>
                                        @endif
                                    </td>
                                    <td style="min-width: 14rem">
                                        @if ($usable)
                                            <div class="input-group input-group-sm mb-1">
                                                <input type="text" class="form-control" dir="ltr" readonly value="{{ $invitation->url() }}" aria-label="رابط الدعوة" data-ts-invite-link>
                                                <button type="button" class="btn btn-outline-secondary" data-ts-copy>نسخ</button>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <a class="btn btn-sm btn-success" target="_blank" rel="noopener"
                                                   href="https://wa.me/{{ $invitation->phone }}?text={{ rawurlencode(($invitation->role === 'seller' ? 'دعوة لفتح متجرك على تريندشام: ' : 'دعوة للانضمام لفريق تريندشام: ').$invitation->url()) }}">
                                                    <i class="bi bi-whatsapp" aria-hidden="true"></i> واتساب
                                                </a>
                                                <form action="{{ route('admin.invitations.revoke', $invitation) }}" method="POST" data-confirm="إلغاء الدعوة؟ لن يعمل رابطها بعد الآن.">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-danger">إلغاء</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-4">لا توجد دعوات بعد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3">{{ $invitations->links() }}</div>
        </div>
    </div>
</div>

<script>
(() => {
    document.querySelectorAll('[data-ts-copy]').forEach(btn => btn.addEventListener('click', async () => {
        const input = btn.closest('.input-group').querySelector('[data-ts-invite-link]');
        try { await navigator.clipboard.writeText(input.value); } catch (e) { input.select(); document.execCommand('copy'); }
        btn.textContent = 'تم النسخ';
        setTimeout(() => { btn.textContent = 'نسخ'; }, 1500);
    }));
    const role = document.getElementById('inv-role');
    const storeBox = document.getElementById('inv-store-box');
    const sync = () => { storeBox.hidden = role.value !== 'seller'; };
    role.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
