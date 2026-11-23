<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>من نحن - تريندشام</title>
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
        .ts-hero { background: linear-gradient(135deg, var(--ts-emerald) 0%, var(--ts-emerald-dark) 100%); position: relative; overflow: hidden; }
        .ts-hero::after {
            content: ''; position: absolute; inset: 0;
            background-image: linear-gradient(45deg, rgba(201,150,44,.08) 25%, transparent 25%), linear-gradient(-45deg, rgba(201,150,44,.08) 25%, transparent 25%);
            background-size: 40px 40px;
        }
        .ts-hero-content { position: relative; z-index: 1; }
        .ts-value-card { background: #fff; border: 1px solid #ECE4D3; border-radius: 14px; padding: 1.5rem; height: 100%; }
        .ts-value-card i { font-size: 1.8rem; color: var(--ts-emerald); }
        .ts-stat { text-align: center; }
        .ts-stat .num { font-family: 'Reem Kufi', sans-serif; font-size: 2rem; color: var(--ts-gold); font-weight: bold; }
        .ts-divider { display: flex; align-items: center; gap: .5rem; color: var(--ts-gold); }
        .ts-divider::before, .ts-divider::after { content: ''; flex: 1; height: 1px; background: var(--ts-gold-light); }
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

<section class="ts-hero py-5">
    <div class="container ts-hero-content text-center text-white">
        <h1 class="fw-bold mb-3">من نحن</h1>
        <p class="mb-0 opacity-75" style="max-width: 600px; margin: 0 auto;">
            تريندشام سوق إلكتروني وُلد من فكرة بسيطة: نجمع بائعين موثوقين من كل المحافظات السورية بمكان واحد،
            ونخلّي وصول أي منتج لأي بيت أسهل بكبسة زر.
        </p>
    </div>
</section>

<div class="container my-5">
    <div class="row g-4 align-items-center">
        <div class="col-md-6">
            <h2 class="h4 mb-3">قصتنا</h2>
            <p class="text-muted">
                بلشنا تريندشام لأننا لاحظنا إنه كتير بائعين محليين — من صنّاع يدويين لأصحاب متاجر صغيرة — عندهم منتجات
                ممتازة بس ما عندهم منصة سهلة توصلهم للزبون مباشرة بدون تعقيد. بنفس الوقت، الزبون كان محتاج مكان واحد
                موثوق يلاقي فيه تنوّع من المنتجات بدل ما يدور بين صفحات متفرقة.
            </p>
            <p class="text-muted">
                من هون جاءت الفكرة: منصة توفّر للبائع أدوات حقيقية لإدارة متجره (منتجات، مخزون، طلبات، أرباح)، وتوفّر
                للزبون تجربة شراء آمنة وواضحة من التصفح لحد التوصيل.
            </p>
        </div>
        <div class="col-md-6">
            <div class="row g-3">
                <div class="col-6">
                    <div class="ts-stat">
                        <div class="num">{{ $sellersCount }}+</div>
                        <div class="text-muted small">بائع معتمد</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="ts-stat">
                        <div class="num">{{ $productsCount }}+</div>
                        <div class="text-muted small">منتج متاح</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="ts-stat">
                        <div class="num">14</div>
                        <div class="text-muted small">محافظة نوصل إلها</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="ts-stat">
                        <div class="num">24/7</div>
                        <div class="text-muted small">متجر مفتوح دائماً</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mb-4">
    <div class="ts-divider small fw-bold">شو بيميّزنا</div>
</div>

<div class="container mb-5">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="ts-value-card">
                <i class="bi bi-shield-check d-block mb-2"></i>
                <h3 class="h6">بائعون معتمدون</h3>
                <p class="text-muted small mb-0">كل متجر ومنتج يمر بمراجعة إدارية قبل ما يظهر لك، عشان تشتري بثقة.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ts-value-card">
                <i class="bi bi-cash-coin d-block mb-2"></i>
                <h3 class="h6">دعم حقيقي للبائع المحلي</h3>
                <p class="text-muted small mb-0">عمولة عادلة، محفظة إلكترونية واضحة، وأدوات فعلية لإدارة متجرك بلا تعقيد.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ts-value-card">
                <i class="bi bi-headset d-block mb-2"></i>
                <h3 class="h6">دعم بالعربي</h3>
                <p class="text-muted small mb-0">من التسجيل لحد استلام الطلب، كل شي بلغتك وبتجربة مصمّمة إلك.</p>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5 text-center">
    <div class="p-4 p-md-5 rounded-4" style="background: linear-gradient(135deg, var(--ts-emerald), var(--ts-emerald-dark));">
        <h2 class="h4 text-white mb-2">عندك منتج تحب تبيعه؟</h2>
        <p class="text-white opacity-75 mb-3">الانضمام كبائع يكون بدعوة من إدارة تريندشام. راسلنا ونرسل لك رابط الدعوة.</p>
        <a href="{{ route('pages.help') }}#sellers"
           class="btn btn-lg" style="background: var(--ts-gold); color: var(--ts-emerald-dark); font-weight:700;">
            تواصل معنا للانضمام
        </a>
    </div>
</div>

<footer class="py-4 text-center text-muted small" style="background: var(--ts-emerald-dark); color: #C9D6CD !important;">
    <div class="container">
        <a href="{{ route('legal.terms') }}" class="text-decoration-none me-3" style="color:#EFE7D6;">الشروط والأحكام</a>
        <a href="{{ route('legal.privacy') }}" class="text-decoration-none me-3" style="color:#EFE7D6;">سياسة الخصوصية</a>
        <a href="{{ route('legal.refund-policy') }}" class="text-decoration-none" style="color:#EFE7D6;">سياسة الإرجاع</a>
        <p class="mt-2 mb-0 opacity-75">© {{ date('Y') }} تريندشام. جميع الحقوق محفوظة.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
