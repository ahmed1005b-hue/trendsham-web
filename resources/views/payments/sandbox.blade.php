<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>البنك التجريبي | تريندشام</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #eef1f6; font-family: Tahoma, Arial, sans-serif; color: #1d2433; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .bank { width: 100%; max-width: 440px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(20,30,60,.12); overflow: hidden; }
        .bank-head { background: #1d2a4d; color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; }
        .bank-head b { font-size: 18px; letter-spacing: .5px; }
        .tag { background: #ffb020; color: #3a2600; font-size: 11px; font-weight: bold; padding: 3px 8px; border-radius: 99px; }
        .bank-body { padding: 22px; }
        .merchant { display: flex; justify-content: space-between; border-bottom: 1px solid #e6e9ef; padding-bottom: 14px; margin-bottom: 16px; }
        .merchant .amt { font-size: 22px; font-weight: bold; }
        .muted { color: #6b7385; font-size: 13px; }
        .opt { display: flex; align-items: center; gap: 10px; border: 1px solid #dfe3ea; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; cursor: pointer; font-size: 14px; }
        .opt:has(input:checked) { border-color: #2d5bd3; background: #f3f6ff; }
        .opt code { direction: ltr; margin-inline-start: auto; color: #6b7385; }
        input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #dfe3ea; border-radius: 10px; font: inherit; margin: 6px 0 14px; }
        .btns { display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-top: 8px; }
        button { border: 0; border-radius: 10px; padding: 12px; font: inherit; font-weight: bold; cursor: pointer; }
        .pay { background: #2d5bd3; color: #fff; }
        .decline { background: #fde8e8; color: #b42318; }
        .cancel { background: none; color: #6b7385; width: 100%; margin-top: 6px; font-weight: normal; }
        .note { background: #fff8e6; border: 1px solid #ffe0a3; color: #6b4e00; border-radius: 10px; padding: 10px 12px; font-size: 12.5px; line-height: 1.7; margin-bottom: 16px; }
    </style>
</head>
<body>
<form class="bank" method="POST" action="{{ route('payments.sandbox.complete', [$kind, $reference]) }}">
    @csrf
    <div class="bank-head">
        <b>🏦 Sandbox Bank</b>
        <span class="tag">بيئة اختبار</span>
    </div>
    <div class="bank-body">
        <div class="note">
            {!! __('هذه صفحة :bank لاختبار الموقع قبل ربط البنك الحقيقي. لا تُدخل أي بطاقة حقيقية — اختر إحدى البطاقات التجريبية. عند ربط البنك ستظهر هنا صفحة البنك الفعلية.', ['bank' => '<strong>'.e(__('بنك تجريبي')).'</strong>']) !!}
        </div>

        <div class="merchant">
            <div>
                <div class="muted">التاجر</div>
                <div><b>تريندشام</b></div>
            </div>
            <div style="text-align: left">
                <div class="muted">{{ $kind === 'card' ? 'العملية' : 'المبلغ' }}</div>
                <div class="amt">{{ $kind === 'card' ? 'حفظ بطاقة' : $amount }}</div>
            </div>
        </div>

        <div class="muted" style="margin-bottom: 8px">اختر بطاقة تجريبية</div>
        @foreach ($cards as $key => $card)
            <label class="opt">
                <input type="radio" name="card" value="{{ $key }}" @checked($loop->first)>
                <span>{{ $card['label'] }}</span>
                <code>•••• {{ $card['last4'] }}</code>
            </label>
        @endforeach

        @if ($kind === 'card')
            <div class="muted" style="margin-top: 10px">الاسم على البطاقة</div>
            <input type="text" name="holder_name" value="{{ auth()->user()->name }}" maxlength="60">
        @endif

        <div class="btns">
            <button class="pay" name="outcome" value="approve">{{ $kind === 'card' ? 'حفظ البطاقة' : 'ادفع الآن' }}</button>
            <button class="decline" name="outcome" value="decline">رفض</button>
        </div>
        <button class="cancel" name="outcome" value="cancel" formnovalidate>إلغاء والعودة للمتجر</button>
    </div>
</form>
</body>
</html>
