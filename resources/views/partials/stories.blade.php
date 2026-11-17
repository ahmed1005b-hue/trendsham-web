{{--
    شريط القصص + عارض القصص الموحّد (الرئيسية وصفحة المتجر).
    المتغيرات: $stories (Collection<Story> مع vendor محمّل)
--}}
@php
    $tsStoryData = $stories->values()->map(fn ($story) => $story->toViewerArray());
    $tsStoryName = fn ($story) => $story->isFromPlatform() ? __('تريندشام') : $story->vendor?->store_name;
@endphp

<div class="ts-stories" data-ts-fade>
    @foreach ($stories->values() as $story)
        <button type="button" class="ts-story-item {{ $story->isFromPlatform() ? 'is-platform' : '' }}"
                id="offer-{{ $story->id }}" data-ts-story="{{ $loop->index }}"
                aria-label="{{ __('مشاهدة قصة :name', ['name' => $tsStoryName($story)]) }}">
            <span class="ts-story-ring">
                <img src="{{ $story->image_thumb_url }}" alt="" loading="lazy">
            </span>
            <span class="ts-story-name" translate="no">{{ $tsStoryName($story) }}</span>
        </button>
    @endforeach
</div>

@once
<div class="modal fade ts-story-modal" id="tsStoryModal" tabindex="-1" aria-label="{{ __('القصص') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="ts-story-card">
                <img class="ts-story-card__img" id="tsStoryImg" src="" alt="">
                <div class="ts-story-card__shade"></div>

                <div class="ts-story-card__bars" id="tsStoryBars"></div>

                <div class="ts-story-card__head">
                    <span class="ts-story-card__avatar" id="tsStoryAvatar"></span>
                    <strong class="ts-story-card__name" id="tsStoryName" translate="no"></strong>
                    <i class="bi bi-patch-check-fill ts-story-card__badge d-none" id="tsStoryBadge" aria-hidden="true"></i>
                    <button type="button" class="ts-story-card__close" data-bs-dismiss="modal" aria-label="{{ __('إغلاق') }}"><i class="bi bi-x-lg"></i></button>
                </div>

                <button type="button" class="ts-story-card__tap is-prev" id="tsStoryPrev" aria-label="{{ __('السابق') }}"></button>
                <button type="button" class="ts-story-card__tap is-next" id="tsStoryNext" aria-label="{{ __('التالي') }}"></button>

                <div class="ts-story-card__foot">
                    <p class="ts-story-card__caption" id="tsStoryCaption"></p>
                    <a class="ts-story-card__link d-none" id="tsStoryLink" href="#" rel="noopener nofollow">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i> <span>{{ __('اكتشف الآن') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .ts-stories { display: flex; gap: .85rem; overflow-x: auto; padding: 1rem 0 .25rem; scrollbar-width: none; }
    .ts-stories::-webkit-scrollbar { display: none; }
    .ts-story-item { flex: 0 0 auto; width: 74px; padding: 0; border: 0; background: transparent; color: var(--ts-ink); text-align: center; }
    .ts-story-ring { display: block; width: 66px; height: 66px; margin: 0 auto; padding: 3px; border-radius: 50%; background: conic-gradient(from 210deg, var(--ts-emerald), var(--ts-gold), var(--ts-gold-light), var(--ts-emerald)); transition: transform .2s ease; }
    .ts-story-item.is-platform .ts-story-ring { background: conic-gradient(from 210deg, var(--ts-gold), var(--ts-gold-light), var(--ts-gold)); }
    .ts-story-ring img { display: block; width: 100%; height: 100%; border: 2.5px solid var(--ts-surface); border-radius: 50%; object-fit: cover; background: var(--ts-surface-2); }
    .ts-story-item:hover .ts-story-ring { transform: scale(1.05); }
    .ts-story-name { display: block; margin-top: .3rem; overflow: hidden; font-size: .72rem; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
    .ts-story-item:focus-visible { outline: 2px solid var(--ts-emerald); outline-offset: 3px; border-radius: 8px; }

    /* بطاقة القصة: مقاس ثابت 9:16 والصورة تملأ البطاقة كاملة */
    .ts-story-modal .modal-dialog { width: min(400px, calc(100vw - 24px), calc((100vh - 32px) * 9 / 16)); max-width: none; margin-inline: auto; }
    .ts-story-modal .modal-content { background: transparent; border: 0; }
    .ts-story-card { position: relative; width: 100%; aspect-ratio: 9 / 16; overflow: hidden; border-radius: 18px; background: #111; color: #fff; box-shadow: 0 20px 50px rgba(0,0,0,.45); }
    .ts-story-card__img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .ts-story-card__shade { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(rgba(0,0,0,.45), transparent 22%, transparent 58%, rgba(0,0,0,.75)); }
    .ts-story-card__bars { position: absolute; top: 10px; inset-inline: 10px; display: flex; gap: 4px; z-index: 3; }
    .ts-story-card__bars span { flex: 1; height: 3px; border-radius: 3px; background: rgba(255,255,255,.4); overflow: hidden; }
    .ts-story-card__bars i { display: block; height: 100%; width: 0; background: #fff; }
    .ts-story-card__head { position: absolute; top: 22px; inset-inline: 10px; display: flex; align-items: center; gap: .5rem; z-index: 4; }
    .ts-story-card__avatar { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; border: 2px solid #fff; background: var(--ts-emerald) center / cover no-repeat; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; }
    .ts-story-card__name { font-size: .9rem; text-shadow: 0 1px 3px rgba(0,0,0,.5); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ts-story-card__badge { color: var(--ts-gold-light); font-size: .8rem; }
    .ts-story-card__close { margin-inline-start: auto; width: 36px; height: 36px; border: 0; border-radius: 50%; background: rgba(0,0,0,.35); color: #fff; display: inline-flex; align-items: center; justify-content: center; }
    .ts-story-card__tap { position: absolute; top: 70px; bottom: 110px; width: 40%; border: 0; background: transparent; z-index: 2; cursor: pointer; }
    .ts-story-card__tap.is-prev { inset-inline-start: 0; }
    .ts-story-card__tap.is-next { inset-inline-end: 0; width: 60%; }
    .ts-story-card__foot { position: absolute; bottom: 0; inset-inline: 0; padding: 16px; z-index: 4; text-align: center; }
    .ts-story-card__caption { margin: 0 0 .75rem; font-size: .92rem; line-height: 1.5; text-shadow: 0 1px 3px rgba(0,0,0,.6); }
    .ts-story-card__caption:empty { display: none; }
    .ts-story-card__link { display: inline-flex; align-items: center; gap: .35rem; min-height: 44px; padding: .55rem 1.4rem; border-radius: 999px; background: #fff; color: #111; font-weight: 700; text-decoration: none; box-shadow: 0 6px 18px rgba(0,0,0,.3); }
    .ts-story-card__link:hover { color: #111; transform: translateY(-1px); }
    @media (prefers-reduced-motion: reduce) { .ts-story-ring { transition: none; } }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const modalEl = document.getElementById('tsStoryModal');
    if (!modalEl) return;
    const stories = @json($tsStoryData);
    const DURATION = 10000;
    const brand = @json(__('تريندشام'));
    const img = document.getElementById('tsStoryImg');
    const bars = document.getElementById('tsStoryBars');
    const avatar = document.getElementById('tsStoryAvatar');
    const link = document.getElementById('tsStoryLink');
    let index = 0, timer = null;

    function isInternal(url) {
        try { return new URL(url, location.href).host === location.host; } catch (e) { return false; }
    }

    function show(i) {
        if (i < 0) i = 0;
        const story = stories[i];
        if (!story) { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); return; }
        index = i;
        const name = story.vendor ? story.vendor.store_name : brand;

        img.src = story.image;
        img.alt = story.caption || name;
        document.getElementById('tsStoryName').textContent = name;
        document.getElementById('tsStoryCaption').textContent = story.caption || '';
        document.getElementById('tsStoryBadge').classList.toggle('d-none', !story.is_from_platform);

        const logo = story.vendor && story.vendor.store_logo;
        avatar.style.backgroundImage = logo ? `url("${logo}")` : '';
        avatar.textContent = logo ? '' : name.charAt(0);

        if (story.link_url) {
            link.href = story.link_url;
            if (isInternal(story.link_url)) { link.removeAttribute('target'); } else { link.target = '_blank'; }
            link.classList.remove('d-none');
        } else {
            link.classList.add('d-none');
            link.removeAttribute('href');
        }

        bars.querySelectorAll('i').forEach((bar, n) => {
            bar.style.transition = 'none';
            bar.style.width = n < i ? '100%' : '0';
        });
        clearTimeout(timer);
        requestAnimationFrame(() => requestAnimationFrame(() => {
            const bar = bars.querySelectorAll('i')[i];
            if (bar) { bar.style.transition = `width ${DURATION}ms linear`; bar.style.width = '100%'; }
        }));
        timer = setTimeout(() => show(index + 1), DURATION);
    }

    bars.innerHTML = stories.map(() => '<span><i></i></span>').join('');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-ts-story]');
        if (!trigger) return;
        index = parseInt(trigger.dataset.tsStory, 10) || 0;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    document.getElementById('tsStoryPrev').addEventListener('click', () => show(index - 1));
    document.getElementById('tsStoryNext').addEventListener('click', () => show(index + 1));
    modalEl.addEventListener('keydown', (e) => {
        const rtl = document.dir === 'rtl';
        if (e.key === 'ArrowLeft') show(rtl ? index + 1 : index - 1);
        if (e.key === 'ArrowRight') show(rtl ? index - 1 : index + 1);
    });
    link.addEventListener('click', () => clearTimeout(timer));
    modalEl.addEventListener('shown.bs.modal', () => show(index));
    modalEl.addEventListener('hidden.bs.modal', () => { clearTimeout(timer); img.removeAttribute('src'); });

    // إشعار «ستوري جديدة» يفتح صفحة المتجر على #offer-{id}: نعرض تلك القصة مباشرة
    const fromHash = location.hash.startsWith('#offer-') && document.getElementById(location.hash.slice(1));
    if (fromHash && fromHash.dataset.tsStory !== undefined) { fromHash.click(); }
})();
</script>
@endpush
@endonce
