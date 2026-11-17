<?php

namespace App\Referrals;

use DomainException;

/** رفض إدخال كود دعوة. الرسالة عربية تُعرض للمستخدم كما هي. */
class ReferralException extends DomainException {}
