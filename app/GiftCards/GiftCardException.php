<?php

namespace App\GiftCards;

use DomainException;

/** رفض عملية على بطاقة هدية أو رصيدها. الرسالة عربية تُعرض للمستخدم كما هي. */
class GiftCardException extends DomainException {}
