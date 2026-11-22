/* الحركة في الموقع: إخفاء شاشة البداية، ظهور البطاقات، والإضافة للسلة مع طيران المنتج.
   الإعدادات تأتي من window.tsMotion (في القالب): رابط الإضافة للسلة ونصوص الرسائل. */
(() => {
    'use strict';
    const cfg = window.tsMotion || {};
    const root = document.documentElement;
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.tsMotionReady = true;

    /* ---------- 1. شاشة البداية ---------- */
    const splash = document.getElementById('ts-splash');
    if (splash && root.classList.contains('ts-splash-on')) {
        const shownAt = window.tsSplashAt || performance.now();
        const leave = () => {
            const wait = Math.max(0, 1000 - (performance.now() - shownAt));
            setTimeout(() => {
                splash.classList.add('is-leaving');
                setTimeout(() => { root.classList.add('ts-splash-done'); splash.remove(); }, 420);
            }, wait);
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', leave, { once: true });
        else leave();
    }

    /* ---------- 2. ظهور البطاقات ---------- */
    if (root.classList.contains('ts-motion') && 'IntersectionObserver' in window) {
        let batch = 0;
        let batchTimer = null;
        const settle = (card, delay) => setTimeout(() => card.classList.add('is-settled'), delay + 500);
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const card = entry.target;
                io.unobserve(card);
                // البطاقات التي تظهر معاً تأتي واحدة بعد الأخرى (حتى 6 خطوات)
                const delay = Math.min(batch++, 6) * 60;
                card.style.setProperty('--ts-reveal-delay', delay + 'ms');
                card.classList.add('is-in');
                settle(card, delay);
                clearTimeout(batchTimer);
                batchTimer = setTimeout(() => { batch = 0; }, 120);
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0.05 });

        const watch = (scope) => scope.querySelectorAll('.ts-market-card:not(.is-in)').forEach((card) => io.observe(card));
        watch(document);
        new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => {
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches('.ts-market-card:not(.is-in)')) io.observe(n);
            else watch(n);
        }))).observe(document.body, { childList: true, subtree: true });
    } else {
        root.classList.remove('ts-motion');
    }

    /* ---------- 3. رسالة صغيرة ---------- */
    let toastTimer = null;
    const toast = (message, { error = false, link = null } = {}) => {
        document.querySelectorAll('.ts-toast').forEach((t) => t.remove());
        const el = document.createElement('div');
        el.className = 'ts-toast' + (error ? ' is-error' : '');
        el.setAttribute('role', error ? 'alert' : 'status');
        const text = document.createElement('span');
        text.textContent = message;
        el.appendChild(text);
        if (link) {
            const a = document.createElement('a');
            a.href = link.href;
            a.textContent = link.label;
            el.appendChild(a);
        }
        // فوق شريط الموافقة على الخصوصية إن كان ظاهراً أسفل الصفحة
        const consent = document.getElementById('ts-consent');
        if (consent && !consent.hidden && consent.offsetHeight) {
            el.style.bottom = (consent.offsetHeight + 16) + 'px';
        }
        document.body.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-shown'));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.classList.remove('is-shown'); setTimeout(() => el.remove(), 300); }, 3200);
    };

    /* ---------- 4. الإضافة للسلة بدون مغادرة الصفحة ---------- */
    const sourceImage = (form) => {
        const card = form.closest('.ts-market-card, article');
        const inCard = card && (card.querySelector('img.ts-card-slide.is-active') || card.querySelector('img'));
        if (inCard) return inCard;
        const modal = form.closest('.modal');
        if (modal) return modal.querySelector('#qvMainImage img, img');
        return document.getElementById('product-main-image');
    };

    const fly = (img, target) => new Promise((resolve) => {
        if (reduce || !img || !target || !img.getBoundingClientRect().width) return resolve();
        const from = img.getBoundingClientRect();
        const to = target.getBoundingClientRect();
        const size = Math.min(from.width, from.height, 140);
        const clone = document.createElement('img');
        clone.src = img.currentSrc || img.src;
        clone.alt = '';
        clone.className = 'ts-fly';
        Object.assign(clone.style, {
            width: size + 'px', height: size + 'px',
            left: (from.left + from.width / 2 - size / 2) + 'px',
            top: (from.top + from.height / 2 - size / 2) + 'px',
        });
        document.body.appendChild(clone);
        const dx = (to.left + to.width / 2) - (from.left + from.width / 2);
        const dy = (to.top + to.height / 2) - (from.top + from.height / 2);
        const anim = clone.animate([
            { transform: 'translate(0, 0) scale(1)', opacity: 1 },
            { transform: `translate(${dx * 0.55}px, ${dy * 0.35 - 60}px) scale(.6)`, opacity: .95, offset: 0.55 },
            { transform: `translate(${dx}px, ${dy}px) scale(.12)`, opacity: .4 },
        ], { duration: 700, easing: 'cubic-bezier(.45, 0, .25, 1)' });
        anim.onfinish = anim.oncancel = () => { clone.remove(); resolve(); };
    });

    const bump = (count) => {
        const badge = document.getElementById('cart-badge');
        const icon = document.getElementById('cart-icon');
        if (badge && Number.isFinite(count)) {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
            if (!reduce) { badge.classList.remove('ts-bump'); void badge.offsetWidth; badge.classList.add('ts-bump'); }
        }
        if (icon && !reduce) { icon.classList.remove('ts-bump-icon'); void icon.offsetWidth; icon.classList.add('ts-bump-icon'); }
    };

    const isCartForm = (form) => {
        if (!cfg.cartStoreUrl || !(form instanceof HTMLFormElement)) return false;
        try { return new URL(form.action, location.href).pathname === new URL(cfg.cartStoreUrl, location.href).pathname; }
        catch (e) { return false; }
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (event.defaultPrevented || !isCartForm(form) || !window.fetch) return;
        event.preventDefault();

        const button = event.submitter || form.querySelector('[type="submit"], button:not([type])');
        if (button) button.classList.add('ts-adding');
        const nativeSubmit = () => HTMLFormElement.prototype.submit.call(form);

        let res;
        try {
            res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
        } catch (e) {
            if (button) button.classList.remove('ts-adding');
            toast(cfg.failText || 'Error', { error: true });
            return;
        }

        // غير مسجّل، أو انتهت الجلسة، أو ليس عميلاً: نترك النموذج يكمل بالطريقة العادية
        if ([401, 403, 419].includes(res.status)) { nativeSubmit(); return; }

        let data = {};
        try { data = await res.json(); } catch (e) { /* رد غير متوقع */ }
        if (button) button.classList.remove('ts-adding');

        if (!res.ok) {
            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
            toast(firstError || data.message || cfg.failText, { error: true });
            return;
        }

        const modal = form.closest('.modal');
        await fly(sourceImage(form), document.getElementById('cart-icon'));
        bump(Number(data.cart_count));
        toast(data.message || cfg.addedText, { link: { href: data.cart_url || cfg.cartUrl, label: cfg.viewCartText } });
        if (modal && window.bootstrap) window.bootstrap.Modal.getInstance(modal)?.hide();
    });
})();
