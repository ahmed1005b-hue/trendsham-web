<?php

namespace App\Assistant;

/** نصوص الاقتراحات السريعة — كل واحد منها يُفهم كرسالة عادية عند الضغط عليه */
final class Chips
{
    public const TRACK = 'تتبع طلبي';
    public const COUPONS = 'كوبوناتي';
    public const DEALS = 'عروض اليوم';
    public const HUMAN = 'تحدث مع موظف';
    public const BOT = 'رجوع للمساعد';
    public const YES = 'نعم';
    public const NO = 'لا';

    public static function home(): array
    {
        return [self::TRACK, self::COUPONS, self::DEALS, self::HUMAN];
    }
}
