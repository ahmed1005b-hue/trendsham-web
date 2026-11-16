<?php

namespace App\Loyalty;

/** خطأ يُعرض للعميل كما هو (رصيد غير كافٍ، أقل من الحد الأدنى…) */
class LoyaltyException extends \RuntimeException {}
