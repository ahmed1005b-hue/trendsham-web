<?php

namespace App\Http\Controllers;

use App\Returns\ReturnSettings;

class LegalController extends Controller
{
    public function terms()
    {
        return view('legal.terms');
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    /** مُهل الإرجاع تُقرأ من إعدادات المدير، فالصفحة تطابق ما تعمل به المنصة. */
    public function refundPolicy(ReturnSettings $settings)
    {
        return view('legal.refund-policy', ['days' => [
            'window' => $this->days($settings->windowDays()),
            'respond' => $this->days($settings->sellerResponseDays()),
            'dispute' => $this->days($settings->disputeDays()),
            'ship' => $this->days($settings->shipBackDays()),
        ]]);
    }

    /** عدد أيام بصيغة عربية صحيحة (3–10 «أيام»، وما فوقها «يوماً»). */
    private function days(int $count): string
    {
        return match (true) {
            $count === 1 => __('يوم واحد'),
            $count === 2 => __('يومان'),
            $count <= 10 => __(':count أيام', ['count' => $count]),
            default => __(':count يوماً', ['count' => $count]),
        };
    }
}
