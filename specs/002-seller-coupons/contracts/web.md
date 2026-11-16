# Web Contract: كوبونات البائع

## لوحة البائع (`auth, role:seller, seller.store`)

| المسار | الاسم | الوصف |
|---|---|---|
| GET `/seller/coupons` | `seller.coupons.index` | القائمة + زر "إضافة كوبون" ظاهر؛ لكل كوبون: الحالة، جُمع X من Y، استُخدم، أزرار |
| GET `/seller/coupons/create` | `seller.coupons.create` | النموذج: النوع، القيمة، النطاق، الهدية، التوزيع، الشروط، معاينة المبلغ، تنبيه "سيحتاج موافقة" فوق 50% |
| POST `/seller/coupons` | `seller.coupons.store` | ينشئ؛ يعيد للقائمة برسالة "يعمل الآن" أو "بانتظار موافقة الإدارة". لكوبون المتابعين: يعرض عدد من سيصلهم قبل التأكيد |
| GET `/seller/coupons/{campaign}` | `seller.coupons.show` | الإحصائيات |
| GET `/seller/coupons/{campaign}/edit` | `seller.coupons.edit` | قبل الجمع: كل الحقول؛ بعده: نهاية وعدد فقط |
| PUT `/seller/coupons/{campaign}` | `seller.coupons.update` | |
| POST `/seller/coupons/{campaign}/toggle` | `seller.coupons.toggle` | active ⇄ paused فقط؛ مرفوض لـ suspended/rejected/pending_review |
| DELETE `/seller/coupons/{campaign}` | `seller.coupons.destroy` | فقط إن لم يُجمع |

كل مسار يرفض حملة متجر آخر (404). المتجر غير الموثّق/الموقوف: صفحة الإنشاء تعرض السبب.
رابط "الكوبونات" في قائمة لوحة البائع.

## المدير (`admin`)

| المسار | الاسم | الوصف |
|---|---|---|
| GET `/admin/seller-coupons?store=&status=` | `admin.seller-coupons.index` | كل كوبونات البائعين + تبويب "بانتظار الموافقة" مع العدد |
| POST `/admin/seller-coupons/{campaign}/approve` | `admin.seller-coupons.approve` | pending_review ← active (+ إرسال المتابعين) + إشعار البائع |
| POST `/admin/seller-coupons/{campaign}/reject` | `admin.seller-coupons.reject` | `reason` إلزامي ← rejected + إشعار |
| POST `/admin/seller-coupons/{campaign}/suspend` | `admin.seller-coupons.suspend` | `reason` إلزامي ← suspended + إشعار |

رابط في لوحة المدير بجانب "حملات الكوبونات" مع عدد المنتظرة.

## العميل

| المسار | الاسم | الوصف |
|---|---|---|
| POST `/coupons/{campaign}/collect` | `coupons.collect` | `auth, role:customer, throttle:30,1`؛ زائر ← صفحة الدخول ثم يعود |

- `partials/store-coupons.blade.php` في صفحة المتجر وصفحة المنتج: بطاقة لكل كوبون (القيمة، الشرط، النهاية، المتبقي) وزر "احفظ" / "محفوظ" / "نفدت".
- السلة والدفع وكوبوناتي: بلا مسارات جديدة؛ بند الهدية يظهر بعلامة "هدية" وسعر 0 في صفحة الطلب والفاتورة.
