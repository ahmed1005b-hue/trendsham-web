<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كل سطر في shipments صار «طرد متجر»: جزء الطلب الخاص بمتجر واحد، بحالته ورقم تتبعه وأجرة شحنه.
 * الطلبات القديمة تُقسَّم إلى طرد لكل متجر بحالة الطلب الحالية.
 */
return new class extends Migration
{
    private const STATUSES = ['pending', 'processing', 'shipped', 'in_transit', 'delivered', 'cancelled', 'returned'];

    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', self::STATUSES)->default('pending')->change();
            $table->unsignedBigInteger('shipping_fee')->default(0)->after('status');   // ما دفعه الزبون لشحن هذا الطرد
            $table->unsignedBigInteger('shipping_cost')->default(0)->after('shipping_fee'); // ما تأخذه شركة الشحن
            $table->unsignedBigInteger('refund_amount')->default(0)->after('shipping_cost'); // ما أُعيد للزبون عند إلغائه
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->unique(['order_id', 'seller_profile_id']);
        });

        $this->splitExistingOrders();
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'seller_profile_id']);
            $table->dropColumn(['shipping_fee', 'shipping_cost', 'refund_amount', 'cancelled_at', 'refused_at']);
        });
        DB::table('shipments')->whereIn('status', ['processing', 'cancelled'])->update(['status' => 'pending']);
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'shipped', 'in_transit', 'delivered', 'returned'])->default('pending')->change();
        });
    }

    private function splitExistingOrders(): void
    {
        $now = now();
        DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) use ($now) {
            foreach ($orders as $order) {
                $vendors = DB::table('order_items')->where('order_id', $order->id)
                    ->whereNotNull('vendor_id')->distinct()->orderBy('vendor_id')->pluck('vendor_id');
                if ($vendors->isEmpty()) {
                    continue;
                }

                $status = match ($order->status) {
                    'cancelled' => 'cancelled',
                    'delivered' => 'delivered',
                    'shipped' => 'shipped',
                    'processing' => 'processing',
                    default => 'pending',
                };
                $existing = DB::table('shipments')->where('order_id', $order->id)->whereNull('seller_profile_id')->orderBy('id')->first();

                foreach ($vendors as $i => $vendorId) {
                    // أجرة الشحن القديمة كانت واحدة للطلب: تُسجَّل على أول طرد
                    $fee = $i === 0 ? (int) $order->shipping_fee : 0;
                    $values = [
                        'seller_profile_id' => $vendorId,
                        'shipping_fee' => $fee,
                        'shipping_cost' => $i === 0 ? max((int) $order->shipping_cost, $fee) : 0,
                        'cancelled_at' => $status === 'cancelled' ? $order->updated_at : null,
                        'updated_at' => $now,
                    ];

                    if ($i === 0 && $existing) {
                        // نحتفظ بحالة الشحنة القديمة إن كانت أدق (في الطريق، مرتجع) ورقم تتبعها
                        $keep = in_array($existing->status, ['in_transit', 'returned'], true) && $status !== 'cancelled';
                        DB::table('shipments')->where('id', $existing->id)->update($values + ['status' => $keep ? $existing->status : $status]);

                        continue;
                    }

                    DB::table('shipments')->insertOrIgnore($values + [
                        'order_id' => $order->id,
                        'status' => $status,
                        'shipped_at' => in_array($status, ['shipped', 'delivered'], true) ? $order->updated_at : null,
                        'delivered_at' => $status === 'delivered' ? $order->updated_at : null,
                        'created_at' => $now,
                    ]);
                }

                // شحنات زائدة بلا متجر (لا ينبغي وجودها) تُحذف حتى لا تظهر طروداً وهمية
                DB::table('shipments')->where('order_id', $order->id)->whereNull('seller_profile_id')->delete();
            }
        });
    }
};
