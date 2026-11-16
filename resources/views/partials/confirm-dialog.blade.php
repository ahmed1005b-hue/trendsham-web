{{--
    نافذة تأكيد/تنبيه موحّدة بهوية الموقع بدل confirm()/alert() الافتراضية.

    الاستخدام في النماذج:
      <form ... data-confirm="حذف الكوبون؟">                    تأكيد قبل الإرسال
      <button ... data-confirm="...">                           تأكيد لزر إرسال بعينه
      data-confirm-when="status=cancelled"                       يُسأل فقط إن كانت قيمة الحقل كذلك
    ومن JavaScript:
      tsConfirm('رسالة').then(ok => { ... })    و    tsAlert('رسالة')
--}}
<div class="modal fade" id="tsConfirmModal" tabindex="-1" aria-labelledby="tsConfirmTitle" aria-describedby="tsConfirmMessage" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content ts-confirm">
            <div class="modal-body text-center pt-4 pb-3 px-4">
                <span class="ts-confirm__icon" aria-hidden="true"><i class="bi bi-question-lg"></i></span>
                <h2 class="h6 fw-bold mt-3 mb-2" id="tsConfirmTitle">تأكيد</h2>
                <p class="mb-0 text-muted" id="tsConfirmMessage" data-ts-confirm-message></p>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex gap-2 flex-nowrap">
                <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal" data-ts-confirm-cancel>إلغاء</button>
                <button type="button" class="btn btn-primary flex-fill" data-ts-confirm-ok>تأكيد</button>
            </div>
        </div>
    </div>
</div>
<style>
    #tsConfirmModal { z-index: 1090; }
    .ts-confirm { border: 0; border-radius: 14px; }
    .ts-confirm__icon { display: inline-grid; place-items: center; width: 48px; height: 48px; border-radius: 50%; background: var(--ts-emerald-soft, #E5F1EC); color: var(--ts-emerald, #0B5F4A); font-size: 1.4rem; }
    .ts-confirm.is-danger .ts-confirm__icon { background: #FBE9E6; color: var(--ts-sale, #C23B2A); }
    .ts-confirm.is-alert .ts-confirm__icon { background: var(--ts-deal-soft, #FBF0D8); color: var(--ts-deal-ink, #7A5410); }
    .ts-confirm #tsConfirmMessage { white-space: pre-line; line-height: 1.6; }
    .ts-confirm .btn { min-height: 44px; }
</style>
<script>
(function () {
    const el = document.getElementById('tsConfirmModal');
    const DANGER = /حذف|إلغاء|إيقاف|إزالة|نهائي|إخفاء|أرشفة/;

    // بدون Bootstrap نعود للنوافذ الافتراضية حتى لا يتعطل أي إجراء
    const nativeConfirm = (m) => Promise.resolve(window.confirm(m));
    const nativeAlert = (m) => { window.alert(m); return Promise.resolve(true); };
    if (!el || !window.bootstrap) {
        window.tsConfirm = nativeConfirm;
        window.tsAlert = nativeAlert;
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(el);
    const box = el.querySelector('.ts-confirm');
    const title = el.querySelector('#tsConfirmTitle');
    const icon = el.querySelector('.ts-confirm__icon i');
    const msg = el.querySelector('[data-ts-confirm-message]');
    const ok = el.querySelector('[data-ts-confirm-ok]');
    const cancel = el.querySelector('[data-ts-confirm-cancel]');
    let resolver = null;

    function settle(value) {
        const r = resolver;
        resolver = null;
        if (r) r(value);
    }

    function open(message, alertOnly) {
        // نافذة مفتوحة أصلاً (نادر): نرفض السابقة ونعرض الجديدة
        settle(false);
        const danger = !alertOnly && DANGER.test(message);
        msg.textContent = message;
        title.textContent = alertOnly ? @json(__('تنبيه')) : @json(__('تأكيد'));
        box.classList.toggle('is-danger', danger);
        box.classList.toggle('is-alert', alertOnly);
        icon.className = alertOnly ? 'bi bi-info-lg' : (danger ? 'bi bi-exclamation-lg' : 'bi bi-question-lg');
        cancel.hidden = alertOnly;
        ok.textContent = alertOnly ? @json(__('حسناً')) : @json(__('تأكيد'));
        ok.className = 'btn flex-fill ' + (danger ? 'btn-danger' : 'btn-primary');
        return new Promise((resolve) => { resolver = resolve; modal.show(); });
    }

    ok.addEventListener('click', () => { settle(true); modal.hide(); });
    el.addEventListener('hidden.bs.modal', () => settle(false));
    // التركيز على "إلغاء" في الإجراءات الخطرة حتى لا يُؤكَّد بضغطة Enter عابرة
    el.addEventListener('shown.bs.modal', () => (cancel.hidden || !box.classList.contains('is-danger') ? ok : cancel).focus());

    window.tsConfirm = (message) => open(String(message), false);
    window.tsAlert = (message) => open(String(message), true);

    function shouldAsk(form, submitter) {
        const when = (submitter && submitter.dataset.confirmWhen) || form.dataset.confirmWhen;
        if (!when) return true;
        const eq = when.indexOf('=');
        const field = form.elements[when.slice(0, eq)];
        return !!field && field.value === when.slice(eq + 1);
    }

    // مرحلة الالتقاط: نسبق أي مستمع آخر على النموذج (مثل مؤشرات التحميل)
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const submitter = e.submitter || null;
        const message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
        if (!message) return;
        if (form.dataset.tsConfirmed === '1') { delete form.dataset.tsConfirmed; return; }
        if (!shouldAsk(form, submitter)) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        open(message, false).then((yes) => {
            if (!yes) return;
            form.dataset.tsConfirmed = '1';
            if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
            else form.submit();
        });
    }, true);
})();
</script>
