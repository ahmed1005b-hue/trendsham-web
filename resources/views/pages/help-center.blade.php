<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مركز المساعدة - تريندشام</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230B5F4A'/%3E%3Cpath d='M32 8 56 32 32 56 8 32Z' fill='none' stroke='%23C9962C' stroke-width='3'/%3E%3Cpath d='M15 15h34v34H15z' fill='none' stroke='%23F1D9A0' stroke-width='3'/%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400..700&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --ts-emerald: #0B5F4A; --ts-emerald-dark: #073D30;
            --ts-gold: #C9962C; --ts-gold-light: #F1D9A0;
            --ts-cream: #FBF7F0; --ts-ink: #1F2A24; --ts-muted: #6B7A72;
        }
        body { font-family: 'Tajawal', sans-serif; background: var(--ts-cream); color: var(--ts-ink); }
        h1, h2, h3 { font-family: 'Reem Kufi', sans-serif; }
        .ts-header { background: var(--ts-emerald); }
        .ts-logo { font-family: 'Reem Kufi', sans-serif; font-size: 1.4rem; color: #fff; text-decoration: none; }
        .ts-logo span { color: var(--ts-gold-light); }
        .ts-search-box input { border: none; border-radius: 10px 0 0 10px; }
        .ts-search-box button { background: var(--ts-gold); border: none; border-radius: 0 10px 10px 0; color: var(--ts-emerald-dark); font-weight: 700; }
        .ts-cat-pill { border: none; background: #fff; border: 1px solid #ECE4D3; border-radius: 30px; padding: .5rem 1.2rem; font-size: .85rem; font-weight: 700; color: var(--ts-ink); cursor: pointer; }
        .ts-cat-pill.active { background: var(--ts-emerald); color: #fff; border-color: var(--ts-emerald); }
        .accordion-button:not(.collapsed) { background: var(--ts-cream); color: var(--ts-emerald-dark); font-weight: 700; box-shadow: none; }
        .accordion-button:focus { box-shadow: none; }
        .accordion-item { border: 1px solid #ECE4D3; border-radius: 10px !important; margin-bottom: .6rem; overflow: hidden; }
        html[dir="rtl"] .accordion-button::after { margin-right: auto; margin-left: 0; }
        .faq-section { display: none; }
        .faq-section.active { display: block; }
    </style>
    @include('partials.theme-head')
</head>
<body>

<header class="ts-header py-3">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="{{ route('home') }}" class="ts-logo">تريند<span>شام</span></a>
        <a href="{{ route('home') }}" class="btn btn-sm" style="background:var(--ts-gold); color:var(--ts-emerald-dark); font-weight:700;">تصفّح المتجر</a>
    </div>
</header>

<section class="py-5 text-center text-white" style="background: linear-gradient(135deg, var(--ts-emerald), var(--ts-emerald-dark));">
    <div class="container">
        <h1 class="fw-bold mb-3">مركز المساعدة</h1>
        <p class="opacity-75 mb-4">كيف نقدر نساعدك اليوم؟</p>
        <div class="ts-search-box d-flex mx-auto" style="max-width: 480px;">
            <input type="search" id="faq-search" class="form-control" placeholder="ابحث عن سؤالك..." aria-label="ابحث عن سؤالك">
            <button type="button" class="btn px-3" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i></button>
        </div>
    </div>
</section>

<div class="container my-4">
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <button class="ts-cat-pill active" data-target="all">الكل</button>
        <button class="ts-cat-pill" data-target="orders">الطلبات والشراء</button>
        <button class="ts-cat-pill" data-target="account">الحساب والأمان</button>
        <button class="ts-cat-pill" data-target="sellers">البيع معنا</button>
        <button class="ts-cat-pill" data-target="shipping">الشحن والإرجاع</button>
    </div>
</div>

<div class="container mb-5" style="max-width: 760px;">

    {{-- الطلبات والشراء --}}
    <div class="faq-section active" data-section="orders">
        <h2 class="h5 mb-3">الطلبات والشراء</h2>
        <div class="accordion mb-4" id="acc-orders">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q1">كيف أسوي طلب شراء؟</button>
                </h3>
                <div id="q1" class="accordion-collapse collapse" data-bs-parent="#acc-orders">
                    <div class="accordion-body">
                        تصفّح المنتجات، اضغط "أضف إلى السلة" على أي منتج (لازم تكون مسجّل دخول كعميل)، بعدين افتح
                        السلة واضغط "إتمام الشراء"، عبّي عنوان التوصيل واختار طريقة الدفع، وأكّد الطلب.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q2">قدرت ألغي طلبي؟</button>
                </h3>
                <div id="q2" class="accordion-collapse collapse" data-bs-parent="#acc-orders">
                    <div class="accordion-body">
                        نعم، طالما الطلب لسا بحالة "قيد المراجعة" (يعني ما بلّش البائع تجهيزه)، تقدر تلغيه بنفسك من
                        صفحة "طلباتي" وبيرجعلك المنتج تلقائياً متاح من جديد. بعد بدء التجهيز، لازم تتواصل مع الدعم.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q3">وين أشوف فاتورة طلبي؟</button>
                </h3>
                <div id="q3" class="accordion-collapse collapse" data-bs-parent="#acc-orders">
                    <div class="accordion-body">
                        من صفحة تفاصيل الطلب (طلباتي ← اختر الطلب)، في زر "الفاتورة" فوق يفتحلك نسخة PDF جاهزة للطباعة أو الحفظ.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q4">شو طرق الدفع المتاحة؟</button>
                </h3>
                <div id="q4" class="accordion-collapse collapse" data-bs-parent="#acc-orders">
                    <div class="accordion-body">
                        الدفع عند الاستلام متاح دائماً. طرق إلكترونية إضافية (بطاقة بنكية أو محفظة) قد تكون متاحة حسب
                        التفعيل الحالي للمنصة.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q5">عندي كود خصم، وين أدخله؟</button>
                </h3>
                <div id="q5" class="accordion-collapse collapse" data-bs-parent="#acc-orders">
                    <div class="accordion-body">
                        بصفحة "إتمام الشراء"، في خانة "كود الخصم" قبل تأكيد الطلب مباشرة.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- الحساب والأمان --}}
    <div class="faq-section" data-section="account">
        <h2 class="h5 mb-3">الحساب والأمان</h2>
        <div class="accordion mb-4" id="acc-account">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q6">كيف أسجّل دخول؟</button>
                </h3>
                <div id="q6" class="accordion-collapse collapse" data-bs-parent="#acc-account">
                    <div class="accordion-body">
                        من صفحة تسجيل الدخول، أدخل بريدك الإلكتروني أو رقم هاتفك مع كلمة السر. لو ما عندك حساب، أنشئ
                        واحد جديد من "أنشئ حساباً جديداً" وأكّد رقم هاتفك برمز تحقق.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q7">نسيت كلمة السر، شو أعمل؟</button>
                </h3>
                <div id="q7" class="accordion-collapse collapse" data-bs-parent="#acc-account">
                    <div class="accordion-body">
                        اضغط "نسيت كلمة السر؟" بصفحة تسجيل الدخول، أدخل بريدك الإلكتروني، وراح توصلك رسالة فيها رابط
                        لتعيين كلمة سر جديدة.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q8">كيف أعدّل بياناتي الشخصية؟</button>
                </h3>
                <div id="q8" class="accordion-collapse collapse" data-bs-parent="#acc-account">
                    <div class="accordion-body">
                        من "ملفي الشخصي" داخل لوحتك، تقدر تعدّل اسمك وبريدك وعنوانك، وتغيّر كلمة السر. رقم الهاتف بس
                        غير قابل للتعديل من هناك لأنه موثّق، تواصل مع الدعم لو احتجت تغييره.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- البيع معنا --}}
    <div class="faq-section" data-section="sellers">
        <h2 class="h5 mb-3">البيع معنا</h2>
        <div class="accordion mb-4" id="acc-sellers">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q9">كيف أفتح متجر على تريندشام؟</button>
                </h3>
                <div id="q9" class="accordion-collapse collapse" data-bs-parent="#acc-sellers">
                    <div class="accordion-body">
                        اختر "بائع" عند التسجيل وعبّي اسم متجرك، أو لو عندك حساب عميل أصلاً، اضغط "ابدأ البيع معنا"
                        من لوحتك. طلبك بينتظر موافقة الإدارة قبل ما تقدر تنشر منتجات.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q10">شو نسبة العمولة؟</button>
                </h3>
                <div id="q10" class="accordion-collapse collapse" data-bs-parent="#acc-sellers">
                    <div class="accordion-body">
                        في نسبة عامة تنطبق على كل البائعين، وممكن تكون عندك نسبة خاصة متفاوض عليها مع الإدارة حسب حجم
                        مبيعاتك. العمولة تُحتسب بس بعد ما يوصل الطلب فعلياً للعميل.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q11">كيف أسحب أرباحي؟</button>
                </h3>
                <div id="q11" class="accordion-collapse collapse" data-bs-parent="#acc-sellers">
                    <div class="accordion-body">
                        من "محفظتي" بلوحة البائع، اطلب سحب المبلغ اللي بدك ياه، وبعد موافقة الإدارة بيتخصم من رصيدك
                        ويتم التحويل خارج المنصة حسب الطريقة المتفق عليها.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q12">ليش منتجي ما ظاهر بالمتجر؟</button>
                </h3>
                <div id="q12" class="accordion-collapse collapse" data-bs-parent="#acc-sellers">
                    <div class="accordion-body">
                        أي منتج جديد أو معاد تفعيله لازم يمر بمراجعة الإدارة أولاً. تقدر تشوف حالة كل منتج ("بانتظار
                        المراجعة" أو "معتمد وظاهر") من صفحة "منتجاتي".
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- الشحن والإرجاع --}}
    <div class="faq-section" data-section="shipping">
        <h2 class="h5 mb-3">الشحن والإرجاع</h2>
        <div class="accordion mb-4" id="acc-shipping">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q13">كيف أتتبع شحنتي؟</button>
                </h3>
                <div id="q13" class="accordion-collapse collapse" data-bs-parent="#acc-shipping">
                    <div class="accordion-body">
                        من صفحة تفاصيل الطلب، قسم "الشحن" بيوريك حالة الشحنة وشركة الشحن ورقم التتبع لو صار تحديثه.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#q14">وصلني منتج تالف أو مختلف عن الوصف</button>
                </h3>
                <div id="q14" class="accordion-collapse collapse" data-bs-parent="#acc-shipping">
                    <div class="accordion-body">
                        تواصل مع البائع مباشرة أو مع الدعم خلال 3 أيام من الاستلام، مع صورة توضح المشكلة. التفاصيل
                        الكاملة موجودة بـ<a href="{{ route('legal.refund-policy') }}">سياسة الإرجاع والاستبدال</a>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-center text-muted small mt-4" id="no-results" style="display:none;">
        ما لقينا نتيجة تطابق بحثك. جرّب كلمة مختلفة أو تواصل معنا مباشرة تحت.
    </p>
</div>

<div class="container mb-5">
    <div class="p-4 p-md-5 rounded-4 text-center" style="background: #fff; border: 1px solid #ECE4D3;">
        <h2 class="h5 mb-2">لسا محتاج مساعدة؟</h2>
        <p class="text-muted mb-3">فريقنا جاهز يساعدك بأي استفسار ما لقيت جوابه فوق.</p>
        <a href="mailto:support@trendsham.com" class="btn" style="background: var(--ts-emerald); color: #fff;">
            <i class="bi bi-envelope"></i> راسلنا على support@trendsham.com
        </a>
    </div>
</div>

<footer class="py-4 text-center small" style="background: var(--ts-emerald-dark); color: #C9D6CD;">
    <div class="container">
        <a href="{{ route('pages.about') }}" class="text-decoration-none me-3" style="color:#EFE7D6;">من نحن</a>
        <a href="{{ route('legal.terms') }}" class="text-decoration-none me-3" style="color:#EFE7D6;">الشروط والأحكام</a>
        <a href="{{ route('legal.privacy') }}" class="text-decoration-none" style="color:#EFE7D6;">سياسة الخصوصية</a>
        <p class="mt-2 mb-0 opacity-75">© {{ date('Y') }} تريندشام. جميع الحقوق محفوظة.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
document.querySelectorAll('.ts-cat-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.ts-cat-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');

        const target = pill.dataset.target;
        document.querySelectorAll('.faq-section').forEach(section => {
            section.classList.toggle('active', target === 'all' || section.dataset.section === target);
        });
        document.getElementById('faq-search').value = '';
        document.getElementById('no-results').style.display = 'none';
    });
});

document.getElementById('faq-search').addEventListener('input', (e) => {
    const query = e.target.value.trim().toLowerCase();

    document.querySelectorAll('.ts-cat-pill').forEach(p => p.classList.remove('active'));
    document.querySelector('.ts-cat-pill[data-target="all"]').classList.add('active');
    document.querySelectorAll('.faq-section').forEach(s => s.classList.add('active'));

    let anyVisible = false;
    document.querySelectorAll('.accordion-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        const match = query === '' || text.includes(query);
        item.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });

    document.getElementById('no-results').style.display = (!anyVisible && query !== '') ? 'block' : 'none';
});
</script>
</body>
</html>
