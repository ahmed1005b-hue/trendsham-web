{{-- نسبة التغيّر عن الفترة السابقة؛ null حين لا مبيعات سابقة للمقارنة --}}
@if (($value ?? null) === null)
    <span class="an-change flat" title="لا توجد بيانات للمقارنة">—</span>
@elseif ($value > 0)
    <span class="an-change up" dir="ltr"><i class="bi bi-arrow-up-short" aria-hidden="true"></i>{{ number_format($value) }}%</span>
@elseif ($value < 0)
    <span class="an-change down" dir="ltr"><i class="bi bi-arrow-down-short" aria-hidden="true"></i>{{ number_format(abs($value)) }}%</span>
@else
    <span class="an-change flat">0%</span>
@endif
