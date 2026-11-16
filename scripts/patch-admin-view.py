p = r'C:\\xampp\\htdocs\\trendsham\\resources\\views\\admin\\orders\\show.blade.php'
s = open(p, encoding='utf-8').read()
i = s.find('shipment')
start = s.rfind('@if', 0, i)
end = s.find('@endif', start)
print('start', start, 'end', end)
if start != -1 and end != -1:
    old = s[start:end+len('@endif')]
    new = '''@if (->shipments->isNotEmpty())
                @foreach (->shipments as )
                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-3">الشحن @if(->seller) - {{ ->seller->store_name }} @endif</h2>
                            <form action="{{ route('admin.shipments.update', [, ]) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="mb-2">
                                    <label class="form-label small">شركة الشحن</label>
                                    <select name="shipping_company_id" class="form-select form-select-sm">
                                        <option value="">— بدون —</option>
                                        @foreach (\\App\\Models\\ShippingCompany::where('is_active', true)->get() as )
                                            <option value="{{ ->id }}" @selected(->shipping_company_id === ->id)>{{ ->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">رقم التتبع</label>
                                    <input type="text" name="tracking_number" class="form-control form-control-sm" value="{{ ->tracking_number }}">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">حالة الشحنة</label>
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach (['pending' => 'بانتظار الشحن', 'shipped' => 'تم الشحن', 'in_transit' => 'في الطريق', 'delivered' => 'تم التسليم', 'returned' => 'مرتجع'] as  => )
                                            <option value="{{  }}" @selected(->status === )>{{  }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-primary w-100">حفظ بيانات الشحن</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif'''
    s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8').write(s)
    print('ok')