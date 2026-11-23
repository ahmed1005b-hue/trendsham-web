{{-- ts-footer--mirrored: ترتيب الفوتر عكس اتجاه اللغة (بالعربية يبدأ من اليسار، وبالإنكليزية والتركية من اليمين). احذفه للعودة للترتيب الافتراضي. --}}
<footer class="ts-footer ts-footer--mirrored pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row g-4 ts-footer__cols">
            <div class="col-12 col-lg-4 ts-footer__brand-col">
                <a href="{{ route('home') }}" class="ts-footer__brand" aria-label="تريندشام">تريند<span>شام</span></a>
                <p class="small ts-footer__about mb-3">سوق إلكتروني يجمع بائعين من كل المحافظات في مكان واحد.</p>
                @php($socialLinks = \App\Models\PlatformSetting::current()->socialLinks())
                @if ($socialLinks)
                    <div class="ts-footer__social" aria-label="تابعنا">
                        @foreach ($socialLinks as $link)
                            <a href="{{ $link['url'] }}" class="ts-social ts-social--{{ \Illuminate\Support\Str::after($link['icon'], 'bi-') }}" target="_blank" rel="noopener" aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}"><i class="bi {{ $link['icon'] }}" aria-hidden="true"></i></a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h6>خدمة العملاء</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="{{ route('pages.help') }}">مركز المساعدة</a></li>
                    <li class="mb-2"><a href="{{ route('legal.refund-policy') }}">سياسة الإرجاع</a></li>
                    <li class="mb-2"><a href="{{ route('verify.index') }}">التحقق من فاتورة أو مستند</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h6>عن تريندشام</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="{{ route('pages.about') }}">من نحن</a></li>
                    <li class="mb-2"><a href="{{ route('legal.terms') }}">الشروط والأحكام</a></li>
                    <li class="mb-2"><a href="{{ route('legal.privacy') }}">سياسة الخصوصية</a></li>
                </ul>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <h6>تواصل معنا</h6>
                <p class="small mb-2"><i class="bi bi-truck me-1" aria-hidden="true"></i> توصيل لجميع المحافظات</p>
                <p class="small mb-0"><i class="bi bi-shield-check me-1" aria-hidden="true"></i> دفع آمن وبائعون موثّقون</p>
            </div>
        </div>
        <div class="ts-footer__bottom">
            <p class="mb-0">© {{ date('Y') }} تريندشام. جميع الحقوق محفوظة.</p>
            <p class="mb-0">صُنع بحب في الشام</p>
        </div>
    </div>
</footer>
