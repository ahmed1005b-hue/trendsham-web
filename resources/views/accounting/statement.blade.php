<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans;
            font-size: 10.5px;
            color: #111;
        }

        h1 {
            font-size: 20px;
            margin: 0;
            color: #0F3D2E;
        }

        .muted {
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .head td {
            vertical-align: top;
        }

        .box {
            border: 1px solid #0F3D2E;
            padding: 6px 8px;
        }

        .gold {
            color: #B8891F;
            font-weight: bold;
        }

        th {
            background: #0F3D2E;
            color: #fff;
            padding: 6px 5px;
            font-size: 10px;
        }

        td.c {
            border-bottom: 1px solid #ddd;
            padding: 5px;
        }

        .num {
            text-align: left;
            direction: ltr;
            white-space: nowrap;
        }

        .sum th {
            background: #0F3D2E;
            color: #fff;
        }

        .total td {
            font-weight: bold;
            background: #F5ECD2;
            padding: 6px 5px;
        }

        .small {
            font-size: 8.5px;
            color: #777;
        }
    </style>
</head>

<body>

    <table class="head">
        <tr>
            <td>
                <h1>كشف حساب بائع</h1>
                <div class="muted">رقم المستند: <b>{{ $number }}</b></div>
                <div class="muted">تاريخ الإصدار: {{ $issuedAt->format('Y-m-d H:i') }}</div>
            </td>
            <td style="text-align:left;">
                <div style="font-size:18px; font-weight:bold; color:#0F3D2E;">تريندشام</div>
                <div class="gold">TRENDSHAM</div>
            </td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td class="box" style="width:49%;">
                <b>البائع</b><br>
                {{ $storeName }}<br>
                <span class="muted">{{ $seller->first_name }} {{ $seller->last_name }} &nbsp;|&nbsp; رقم
                    #{{ $seller->id }}</span>
            </td>
            <td style="width:2%;"></td>
            <td class="box" style="width:49%;">
                <b>الفترة</b><br>
                من {{ $from->format('Y-m-d') }} إلى {{ $to->format('Y-m-d') }}<br>
                <span class="muted">عدد الحركات: {{ $entries->count() }}</span>
            </td>
        </tr>
    </table>

    <br>

    <table class="sum">
        <thead>
            <tr>
                <th>البند</th>
                <th>عدد</th>
                <th>مدين</th>
                <th>دائن</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="c"><b>الرصيد الافتتاحي</b></td>
                <td class="c"></td>
                <td class="c"></td>
                <td class="c num">{{ number_format($opening, 2) }}</td>
            </tr>
            @foreach ($totals as $type => $t)
                <tr>
                    <td class="c">{{ $labels[$type] ?? $type }}</td>
                    <td class="c num">{{ $t['count'] }}</td>
                    <td class="c num">{{ number_format($t['debit'], 2) }}</td>
                    <td class="c num">{{ number_format($t['credit'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>الإجمالي</td>
                <td></td>
                <td class="num">{{ number_format($totalDebit, 2) }}</td>
                <td class="num">{{ number_format($totalCredit, 2) }}</td>
            </tr>
            <tr class="total">
                <td>الرصيد الختامي</td>
                <td></td>
                <td></td>
                <td class="num">{{ number_format($closing, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <thead>
            <tr>
                <th style="width:15%;">التاريخ</th>
                <th style="width:14%;">النوع</th>
                <th>البيان</th>
                <th style="width:12%;">مدين</th>
                <th style="width:12%;">دائن</th>
                <th style="width:14%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td class="c num">{{ $e->posted_at->format('Y-m-d H:i') }}</td>
                    <td class="c">{{ $labels[$e->entry_type] ?? $e->entry_type }}</td>
                    <td class="c">{{ $e->description }}</td>
                    <td class="c num">{{ (float) $e->debit > 0 ? number_format($e->debit, 2) : '' }}</td>
                    <td class="c num">{{ (float) $e->credit > 0 ? number_format($e->credit, 2) : '' }}</td>
                    <td class="c num">{{ number_format($e->balance_after, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="c" colspan="6" style="text-align:center;">لا توجد حركات في هذه الفترة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <br>
    <div class="small">
        الرصيد بالليرة السورية. الدائن = مستحقات للبائع، المدين = مبالغ مخصومة منه.<br>
        هذا كشف حساب داخلي مُولَّد آلياً من سجل المنصة، وهو ليس فاتورة ضريبية.<br>
        بصمة التحقق: <span style="direction:ltr;">{{ $checksum }}</span>
    </div>

    @include('pdf._verify-stamp')

</body>

</html>