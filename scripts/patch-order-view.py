p = r'C:\\xampp\\htdocs\\trendsham\\resources\\views\\customer\\orders\\show.blade.php'
s = open(p, encoding='utf-8').read()
i = s.find('shipment')
start = s.rfind('@if', 0, i)
end = s.find('@endif', start)
if start == -1 or end == -1:
    print('miss', start, end)
else:
    old = s[start:end+len('@endif')]
    new = '''@if (->shipments->isNotEmpty())
                @foreach (->shipments as )
                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-2">الشحن @if(->seller) - {{ ->seller->store_name }} @endif</h2>
                            <p class="small mb-1">
                                الحالة:
                                @php
                                     = ['pending' => 'بانتظار الشحن', 'shipped' => 'تم الشحن', 'in_transit' => 'في الطريق', 'delivered' => 'تم التسليم', 'returned' => 'مرتجع'];
                                @endphp
                                {{ [->status] ?? ->status }}
                            </p>
                            @if (->shippingCompany)
                                <p class="small mb-1">شركة الشحن: {{ ->shippingCompany->name }}</p>
                            @endif
                            @if (->tracking_number)
                                <p class="small mb-1">رقم التتبع: {{ ->tracking_number }}</p>
                            @endif
                            @if (->seller)
                                <form action="{{ route('stores.message', ->seller) }}" method="POST" class="mt-2">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">راسل البائع</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif'''
    s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8').write(s)
    print('ok')