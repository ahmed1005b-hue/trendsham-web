<?php

namespace Tests\Feature\Verify;

use App\Documents\DocumentRegistry;
use App\Documents\Models\IssuedDocument;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\StatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('documents'); // لا تُكتب ملفات المستندات في مجلد التخزين الحقيقي
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'first_name' => 'سارة', 'last_name' => 'خليل', 'phone_verified_at' => now()]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function order(User $u): Order
    {
        $sellerUser = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $sellerUser->id, 'store_name' => 'متجر النور', 'status' => 'approved', 'commission_rate' => 10]);
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'c']);
        $p = Product::create(['vendor_id' => $vendor->id, 'category_id' => $cat->id, 'name' => 'قميص', 'slug' => 'p-'.Str::random(5),
            'sku' => 'S-'.Str::random(6), 'price' => 2_500_000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now()]);
        $order = Order::create([
            'order_number' => 'TS-20261112-'.strtoupper(Str::random(6)), 'user_id' => $u->id, 'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal' => 5_000_000, 'shipping_fee' => 150_000, 'discount_amount' => 0, 'total' => 5_150_000,
            'shipping_name' => 'سارة خليل', 'shipping_phone' => '0999123456', 'shipping_address' => 'دمشق، المزة',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'vendor_id' => $vendor->id, 'product_name' => 'قميص',
            'unit_price' => 2_500_000, 'quantity' => 2, 'subtotal' => 5_000_000]);

        return $order;
    }

    private function invoice(User $as, Order $order)
    {
        $res = $this->actingAs($as)->get(route('invoices.show', $order));
        \Illuminate\Support\Facades\Auth::forgetGuards(); // الطلبات التالية كزائر ما لم يُحدَّد غير ذلك

        return $res;
    }

    public function test_invoice_is_registered_and_served_file_matches_the_record(): void
    {
        $u = $this->customer();
        $order = $this->order($u);

        $res = $this->invoice($u, $order)->assertOk();
        $this->assertStringStartsWith('application/pdf', $res->headers->get('Content-Type'));

        $doc = IssuedDocument::where('type', 'invoice')->where('subject_id', $order->id)->sole();
        $this->assertSame(12, strlen($doc->code));
        $this->assertSame(hash('sha256', $res->getContent()), $doc->file_hash);
        $this->assertSame($u->id, $doc->owner_user_id);
        $this->assertSame($order->order_number, $doc->summary['number']);
        $this->assertSame(5_150_000, $doc->summary['amount']);
        $this->assertTrue(Storage::disk('documents')->exists($doc->file_path));
    }

    public function test_reprinting_unchanged_invoice_reuses_code_and_file(): void
    {
        $u = $this->customer();
        $order = $this->order($u);

        $first = $this->invoice($u, $order)->getContent();
        $second = $this->invoice($u, $order)->getContent();
        // من التطبيق أيضاً
        Sanctum::actingAs($u);
        $third = $this->get("/api/v1/orders/{$order->id}/invoice")->assertOk()->getContent();

        $this->assertSame(1, IssuedDocument::count());
        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
    }

    public function test_changed_order_gets_new_code_and_old_one_is_outdated(): void
    {
        $u = $this->customer();
        $order = $this->order($u);
        $this->invoice($u, $order);
        $old = IssuedDocument::sole();

        $order->update(['shipping_fee' => 0, 'total' => 5_000_000]);
        $this->invoice($u, $order);

        $this->assertSame(2, IssuedDocument::count());
        $this->get(route('verify.show', $old->code))->assertOk()->assertSee('صدرت نسخة أحدث');
        $new = IssuedDocument::latest('id')->first();
        $this->get(route('verify.show', $new->code))->assertOk()->assertSee('مستند صحيح')->assertDontSee('صدرت نسخة أحدث');
    }

    public function test_cancelled_order_is_flagged(): void
    {
        $u = $this->customer();
        $order = $this->order($u);
        $this->invoice($u, $order);
        $order->update(['status' => 'cancelled']);

        $this->get(route('verify.show', IssuedDocument::sole()->code))->assertOk()->assertSee('أُلغي الطلب');
    }

    public function test_visitor_sees_summary_without_personal_data(): void
    {
        $u = $this->customer();
        $order = $this->order($u);
        $this->invoice($u, $order);
        $doc = IssuedDocument::sole();

        $this->get(route('verify.show', $doc->code))->assertOk()
            ->assertSee('مستند صحيح')
            ->assertSee($doc->displayCode())
            ->assertSee($order->order_number)
            ->assertSee('51,500')
            ->assertDontSee('سارة')
            ->assertDontSee('0999123456')
            ->assertDontSee('المزة')
            ->assertDontSee('قميص');

        // عميل آخر مسجّل الدخول = زائر
        $this->actingAs($this->customer())->get(route('verify.show', $doc->code))->assertDontSee('0999123456')->assertDontSee('قميص');
    }

    public function test_owner_and_admin_see_details(): void
    {
        $u = $this->customer();
        $order = $this->order($u);
        $this->invoice($u, $order);
        $code = IssuedDocument::sole()->code;

        $this->actingAs($u)->get(route('verify.show', $code))->assertSee('سارة خليل')->assertSee('قميص')->assertSee('متجر النور');
        $this->actingAs($this->admin())->get(route('verify.show', $code))->assertSee('سارة خليل');
    }

    public function test_unknown_code_warns_of_forgery_and_manual_entry_is_normalized(): void
    {
        $this->get(route('verify.show', 'ABCDEFGHJKMN'))->assertNotFound()->assertSee('قد يكون مزوراً');

        $u = $this->customer();
        $this->invoice($u, $this->order($u));
        $doc = IssuedDocument::sole();

        $typed = ' ts-'.strtolower(substr($doc->code, 0, 4)).' '.strtolower(substr($doc->code, 4, 4)).'-'.substr($doc->code, 8);
        $this->get(route('verify.index', ['code' => $typed]))->assertRedirect(route('verify.show', $doc->code));
    }

    public function test_file_check_detects_original_and_tampered_files(): void
    {
        $u = $this->customer();
        $original = $this->invoice($u, $this->order($u))->getContent();
        $doc = IssuedDocument::sole();

        $this->post(route('verify.file'), ['file' => UploadedFile::fake()->createWithContent('invoice.pdf', $original)])
            ->assertOk()->assertSee('مطابق تماماً')->assertSee($doc->displayCode());

        $tampered = str_replace('51,500', '11,500', $original).' ';
        $this->post(route('verify.file'), ['file' => UploadedFile::fake()->createWithContent('invoice.pdf', $tampered)])
            ->assertOk()->assertSee('لا يطابق أي مستند أصلي');

        $this->post(route('verify.file'), [])->assertSessionHasErrors('file');
    }

    public function test_seller_statement_is_registered_and_superseded_one_is_outdated(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر الكشف', 'status' => 'approved']);
        app(LedgerService::class)->sale($seller->id, 100, 'بيع', 'manual_test', 1);
        $svc = app(StatementService::class);

        $st = $svc->generate($seller, Carbon::now()->subDay(), Carbon::now(), $this->admin()->id);
        $doc = IssuedDocument::where('type', 'seller_statement')->sole();
        $this->assertSame($st->id, $doc->subject_id);
        $this->assertSame($seller->id, $doc->owner_user_id);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($st->file_path)), $doc->file_hash);
        $this->get(route('verify.show', $doc->code))->assertOk()->assertSee('مستند صحيح')->assertSee($st->number)->assertDontSee('متجر الكشف');

        $svc->generate($seller, Carbon::now()->subDay(), Carbon::now(), $this->admin()->id);
        $this->assertSame('superseded', SellerStatement::find($st->id)->status);
        $this->get(route('verify.show', $doc->code))->assertSee('صدرت نسخة أحدث');
    }

    public function test_admin_report_is_registered(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin)->get(route('admin.reports.export', ['from' => '2026-11-01', 'to' => '2026-11-12']))->assertOk();

        $doc = IssuedDocument::where('type', 'admin_report')->sole();
        $this->assertSame(hash('sha256', $res->getContent()), $doc->file_hash);
        $this->assertSame($admin->id, $doc->owner_user_id);
    }

    public function test_stamp_partial_shows_code_and_qr(): void
    {
        $html = view('pdf._verify-stamp', ['verify' => ['code' => 'ABCDEFGHJKMN', 'url' => 'http://x/verify/ABCDEFGHJKMN']])->render();
        $this->assertStringContainsString('TS-ABCD-EFGH-JKMN', $html);
        $this->assertStringContainsString('<barcode', $html);
        $this->assertStringContainsString('http://x/verify/ABCDEFGHJKMN', $html);
    }

    public function test_codes_are_random_and_from_the_safe_alphabet(): void
    {
        $registry = app(DocumentRegistry::class);
        $codes = collect(range(1, 50))->map(fn () => $registry->newCode());

        $this->assertCount(50, $codes->unique());
        $codes->each(fn ($c) => $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{12}$/', $c));
        $this->assertSame('ABCDEFGHJKMN', DocumentRegistry::normalize(' ts-abcd efgh-jkmn '));
        $this->assertNull(DocumentRegistry::normalize('short'));
    }

    public function test_verify_page_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('verify.show', 'ABCDEFGHJKMN'));
        }
        $this->get(route('verify.show', 'ABCDEFGHJKMN'))->assertStatus(429);
    }
}
