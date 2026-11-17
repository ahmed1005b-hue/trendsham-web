<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'store_logo',
        'store_banner',
        'store_contact_phone',
        'store_whatsapp',
        'store_instagram',
        'business_hours',
        'shipping_policy',
        'dropoff_center_id',
        'courier_pickup',
        'return_policy',
        'commercial_register',
        'identity_type',
        'identity_number',
        'identity_document_path',
        'identity_document_back_path',
        'identity_document_submitted_at',
        'identity_verified_at',
        'status',
        'rejection_reason',
        'suspended_at',
        'suspension_reason',
        'commission_rate',
        'business_hours_schedule',
    ];

    protected function casts(): array
    {
        return [
            'business_hours_schedule' => 'array',
            'identity_document_submitted_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'courier_pickup' => 'boolean',
        ];
    }

    /** متجر موقوف من الإدارة: مخفي عن الزوار ولا يُشترى منه، وتبقى سجلاته كاملة. */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasIdentityDocument(): bool
    {
        return filled($this->identity_document_path);
    }

    public function isIdentityVerified(): bool
    {
        return $this->status === 'approved' && $this->identity_verified_at !== null;
    }

    public function identityStatusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'موثق / معتمد',
            'pending' => filled($this->identity_document_path) ? 'قيد المراجعة' : 'بانتظار وثيقة الهوية',
            'rejected' => 'مرفوض - يلزم إعادة الإرسال',
            'suspended' => 'موقوف من الإدارة',
            default => 'غير مكتمل',
        };
    }

    public function getEffectiveCommissionRateAttribute(): int
    {
        return $this->commission_rate ?? PlatformSetting::current()->commission_rate;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** مركز الشحن الذي يسلّم فيه البائع طروده. */
    public function dropoffCenter()
    {
        return $this->belongsTo(ShippingCenter::class, 'dropoff_center_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'vendor_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class, 'vendor_id');
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class, 'vendor_id');
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class, 'vendor_id');
    }

    public function staffConversations(): HasMany
    {
        return $this->hasMany(StaffConversation::class, 'vendor_id');
    }

    public function sellerReviews()
    {
        return $this->hasMany(SellerReview::class, 'vendor_id');
    }

    public function storefrontVisits(): HasMany
    {
        return $this->hasMany(StorefrontVisit::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(StoreFollower::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'vendor_id');
    }

    public function getSellerRatingAttribute(): ?float
    {
        $avg = $this->sellerReviews()->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    /**
     * كل بائع لازم يكون عنده محفظة؛ ننشئها أول مرة يحتاجها بدل الاعتماد على seeder/event خارجي.
     */
    public function walletOrCreate(): Wallet
    {
        // نحدّث العلاقة المحمّلة بعد الإنشاء، وإلا فاستدعاء ثانٍ على نفس الكائن يرى null
        // ويحاول الإنشاء مجدداً فيصطدم بقيد التفرد (wallets_vendor_id_unique).
        // firstOrCreate يحمي أيضاً من طلبين متزامنين أنشأ أحدهما المحفظة.
        if (! $this->wallet) {
            $this->setRelation('wallet', $this->wallet()->firstOrCreate([], ['balance' => 0]));
        }

        return $this->wallet;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getStoreWhatsappUrlAttribute(): ?string
    {
        $value = trim((string) $this->store_whatsapp);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\+?[0-9\s().-]{6,32}$/', $value)) {
            return 'https://wa.me/'.preg_replace('/\D/', '', $value);
        }

        return $this->isSafeWebUrl($value) ? $value : null;
    }

    public function getStoreInstagramUrlAttribute(): ?string
    {
        $value = trim((string) $this->store_instagram);

        return $this->isSafeWebUrl($value) ? $value : null;
    }

    private function isSafeWebUrl(string $value): bool
    {
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
