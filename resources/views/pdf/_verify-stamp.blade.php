{{-- ختم التحقق: رمز QR يفتح صفحة التحقق + رمز قصير للكتابة اليدوية. يُضمَّن في كل PDF يصدره الموقع. --}}
@if (! empty($verify['code']))
<table style="width:100%; margin-top:14px; border-top:1px solid #d9d9d9; padding-top:8px;" dir="rtl">
    <tr>
        <td style="width:90px; vertical-align:middle;">
            <barcode code="{{ $verify['url'] }}" type="QR" size="0.75" error="M" disableborder="1" />
        </td>
        <td style="vertical-align:middle; font-size:10px; color:#333;">
            <strong>للتأكد أن هذا المستند صادر من تريندشام ولم يُعدَّل</strong><br>
            امسح الرمز بكاميرا الهاتف، أو ادخل إلى صفحة التحقق واكتب الرمز:<br>
            <span style="direction:ltr; font-size:13px; font-weight:bold; letter-spacing:1px;">TS-{{ implode('-', str_split($verify['code'], 4)) }}</span><br>
            <span style="direction:ltr; color:#777;">{{ $verify['url'] }}</span>
        </td>
    </tr>
</table>
@endif
