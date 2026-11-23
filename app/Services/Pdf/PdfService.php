<?php

namespace App\Services\Pdf;

use Illuminate\Http\Response;
use Mpdf\Mpdf;

class PdfService
{
    /**
     * توليد محتوى PDF كنص خام من Blade view، مع إعدادات تضمن ظهور العربي
     * متصل وباتجاه صحيح (dompdf ما بيدعم ربط الأحرف العربية أصلاً، لهيك
     * استخدمنا mPDF).
     *
     * @param  array<string, mixed>  $page  إعدادات صفحة إضافية لـ mPDF (مثل مقاس ملصق الشحنة)
     */
    public function render(string $view, array $data = [], array $page = []): string
    {
        $locale = in_array(app()->getLocale(), ['ar', 'en', 'tr'], true) ? app()->getLocale() : 'ar';
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $mpdf = new Mpdf($page + [
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusanscondensed',
            'directionality' => $direction,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf->WriteHTML(view($view, $data + compact('locale', 'direction'))->render());

        return $mpdf->Output('', 'S');
    }

    /** إرسال بايتات PDF جاهزة (مستند مسجّل برمز تحقق). */
    public function respond(string $bytes, string $filename, bool $inline = true): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * عرض الملف مباشرة بالمتصفح (لفتحه بتاب جديد، مثل الفاتورة).
     */
    public function stream(string $view, array $data = [], string $filename = 'document.pdf'): Response
    {
        return response($this->render($view, $data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * إجبار تحميل الملف (لتصدير كشف الحساب مثلاً).
     */
    public function download(string $view, array $data = [], string $filename = 'document.pdf'): Response
    {
        return response($this->render($view, $data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
