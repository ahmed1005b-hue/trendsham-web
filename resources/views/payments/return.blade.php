<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>حالة الدفع | تريندشام</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               background:#0F3D2E; color:#F5ECD2; font-family: Tahoma, Arial, sans-serif; text-align:center; }
        .card { max-width:420px; padding:32px; }
        h1 { font-size:24px; margin:0 0 12px; }
        p  { color:#D4A537; margin:0 0 24px; line-height:1.7; }
        .ok { color:#7ee2a8; } .bad { color:#ff8a8a; }
    </style>
    @include('partials.theme-head')
</head>
<body>
<div class="card">
    @if ($status === 'paid')
        <h1 class="ok">تم استلام الدفع بنجاح</h1>
        <p>شكراً لك، سنبدأ بتجهيز طلبك.</p>
    @elseif (in_array($status, ['failed', 'cancelled']))
        <h1 class="bad">لم تكتمل عملية الدفع</h1>
        <p>لم يتم خصم أي مبلغ. يمكنك المحاولة من جديد.</p>
    @else
        <h1>جارٍ التحقق من الدفع</h1>
        <p>قد يستغرق التأكيد لحظات. ارجع إلى التطبيق لمتابعة حالة الطلب.</p>
    @endif
    @if (! empty($order))
        <a href="{{ route('customer.orders.show', $order) }}" style="display:inline-block;background:#D4A537;color:#14251e;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:bold;">عرض الطلب</a>
        @if (in_array($status, ['failed', 'cancelled']))
            <a href="{{ route('customer.checkout.pay', $order) }}" style="display:inline-block;color:#F5ECD2;padding:12px 18px;">إعادة المحاولة</a>
        @endif
    @else
        <p>يمكنك إغلاق هذه الصفحة والعودة إلى تطبيق تريندشام.</p>
    @endif
</div>
</body>
</html>
