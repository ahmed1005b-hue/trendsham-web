@extends('emails.layout')

@php($rtl = app()->getLocale() === 'ar')

@section('title', __('إعادة تعيين كلمة المرور'))
@section('preheader', __('طلبت إعادة تعيين كلمة المرور لحسابك في تريندشام. الرابط صالح لمدة :minutes دقيقة.', ['minutes' => $minutes]))

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.5;color:#0B5F4A;font-weight:bold;">
        {{ __('إعادة تعيين كلمة المرور') }}
    </h1>

    <p style="margin:0 0 12px;">{{ __('مرحباً :name،', ['name' => $name]) }}</p>

    <p style="margin:0 0 24px;">
        {{ __('تلقّينا طلباً لإعادة تعيين كلمة المرور لحسابك في تريندشام. اضغط على الزر أدناه لاختيار كلمة مرور جديدة.') }}
    </p>

    {{-- زر "مضاد للرصاص" يعمل في كل برامج البريد --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 24px;">
        <tr>
            <td align="center" bgcolor="#C9962C" style="border-radius:10px;">
                <a href="{{ $url }}" target="_blank"
                   style="display:inline-block;padding:14px 36px;font-family:Tahoma,Arial,sans-serif;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:10px;background-color:#C9962C;">
                    {{ __('إعادة تعيين كلمة المرور') }}
                </a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#f4f9f7;border-{{ $rtl ? 'right' : 'left' }}:4px solid #0B5F4A;border-radius:8px;padding:12px 16px;font-size:13px;color:#43534b;">
                ⏱ {!! __('الرابط صالح لمدة :duration ويُستخدم مرة واحدة فقط.', ['duration' => '<strong>'.e(__(':minutes دقيقة', ['minutes' => $minutes])).'</strong>']) !!}
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#6b7670;">
        {{ __('إذا لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:') }}
    </p>
    <p style="margin:0 0 24px;font-size:12px;line-height:1.6;word-break:break-all;direction:ltr;text-align:left;">
        <a href="{{ $url }}" style="color:#0B5F4A;text-decoration:underline;">{{ $url }}</a>
    </p>

    <hr style="border:none;border-top:1px solid #ece7da;margin:0 0 16px;">

    <p style="margin:0;font-size:13px;color:#6b7670;">
        🔒 {{ __('إذا لم تطلب إعادة تعيين كلمة المرور فتجاهل هذه الرسالة؛ ستبقى كلمة مرورك الحالية كما هي ولن يتغيّر شيء في حسابك.') }}
    </p>
@endsection
