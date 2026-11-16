<div id="ts-consent" class="ts-consent" role="dialog" aria-live="polite" aria-label="الموافقة على الشروط والخصوصية" aria-describedby="ts-consent-text" hidden>
    <div class="ts-consent__inner container">
        <p id="ts-consent-text" class="ts-consent__text mb-0">
            {!! __('نستخدم ملفات تعريف الارتباط والتخزين المحلي لتحسين تجربتك وتشغيل الموقع. بالمتابعة، يمكنك الموافقة على :terms و:privacy، أو رفض الملفات غير الضرورية.', [
                'terms' => '<a href="'.e(route('legal.terms')).'">'.e(__('الشروط والأحكام')).'</a>',
                'privacy' => '<a href="'.e(route('legal.privacy')).'">'.e(__('سياسة الخصوصية')).'</a>',
            ]) !!}
        </p>
        <div class="ts-consent__actions">
            <button type="button" class="btn btn-sm btn-outline-light ts-consent__reject" data-consent-choice="rejected">رفض</button>
            <button type="button" class="btn btn-sm btn-buy ts-consent__accept" data-consent-choice="accepted">موافقة</button>
        </div>
    </div>
</div>
<script>
(() => {
    const key = 'ts_consent_choice';
    const banner = document.getElementById('ts-consent');
    if (!banner) return;
    const read = () => { try { return localStorage.getItem(key); } catch { return document.cookie.match(/(?:^|; )ts_consent_choice=([^;]+)/)?.[1] || null; } };
    const write = (value) => {
        try { localStorage.setItem(key, value); } catch {}
        document.cookie = `ts_consent_choice=${encodeURIComponent(value)}; path=/; max-age=31536000; SameSite=Lax`;
    };
    if (read()) return;
    banner.hidden = false;
    banner.querySelectorAll('[data-consent-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            write(button.dataset.consentChoice);
            banner.hidden = true;
        });
    });
})();
</script>
