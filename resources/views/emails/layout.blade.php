{{--
    القالب الموحّد لرسائل تريندشام.
    برامج البريد (Gmail / Outlook) لا تدعم CSS الحديث، لذلك: جداول + تنسيق inline فقط.
    الاستخدام: @extends('emails.layout') ثم @section('content') ... @endsection
    اللغة والاتجاه من لغة المستلم (Laravel يضبطها من User::preferredLocale عند Mail::to($user)).
--}}
@php
    $mailLocale = app()->getLocale();
    $mailDir = $mailLocale === 'ar' ? 'rtl' : 'ltr';
    $mailAlign = $mailDir === 'rtl' ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $mailDir }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <title>@yield('title', __('تريندشام'))</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f1ea;-webkit-text-size-adjust:100%;">
    {{-- نص المعاينة الذي يظهر بجانب العنوان في صندوق الوارد --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f1ea;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

                    {{-- الترويسة --}}
                    <tr>
                        <td align="center" style="background-color:#0B5F4A;background-image:linear-gradient(135deg,#073D30,#0B5F4A);border-radius:16px 16px 0 0;padding:28px 24px;">
                            <a href="{{ url('/') }}" style="text-decoration:none;font-family:Tahoma,Arial,sans-serif;font-size:30px;font-weight:bold;color:#ffffff;letter-spacing:.5px;">
                                {{ __('تريند') }}<span style="color:#F1D9A0;">{{ __('شام') }}</span>
                            </a>
                            <div style="font-family:Tahoma,Arial,sans-serif;font-size:13px;color:#cfe3db;margin-top:6px;">
                                {{ __('سوقك الإلكتروني الموثوق') }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="height:4px;background-color:#C9962C;line-height:4px;font-size:0;">&nbsp;</td>
                    </tr>

                    {{-- المحتوى --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:36px 32px 28px;font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.9;color:#2b3a33;text-align:{{ $mailAlign }};direction:{{ $mailDir }};">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- التذييل --}}
                    <tr>
                        <td style="background-color:#fbfaf6;border-top:1px solid #ece7da;border-radius:0 0 16px 16px;padding:20px 32px;font-family:Tahoma,Arial,sans-serif;font-size:12px;line-height:1.8;color:#7b857f;text-align:center;direction:{{ $mailDir }};">
                            {!! __('وصلتك هذه الرسالة لأن لديك حساباً في :brand.', ['brand' => '<strong style="color:#0B5F4A;">'.e(__('تريندشام')).'</strong>']) !!}<br>
                            {{ __('للمساعدة تواصل معنا عبر') }}
                            <a href="mailto:{{ config('mail.from.address') }}" style="color:#0B5F4A;text-decoration:underline;">{{ config('mail.from.address') }}</a>
                            <div style="margin-top:10px;color:#a3aaa6;">&copy; {{ date('Y') }} {{ __('تريندشام. جميع الحقوق محفوظة.') }}</div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
