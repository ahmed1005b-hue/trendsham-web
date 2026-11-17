<?php

namespace App\Models;

use App\Loyalty\Models\LoyaltyAccount;
use App\Support\Phone;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens, HasFactory, Notifiable;

    public const LOCALES = ['ar', 'en', 'tr'];

    /** لغة ما يُرسل للمستخدم في الخلفية (إشعارات، رسائل، بريد). Laravel يستخدمها للبريد تلقائياً. */
    public function preferredLocale(): string
    {
        return in_array($this->locale, self::LOCALES, true) ? $this->locale : 'ar';
    }

    /** تحفظ لغة الواجهة الحالية إن تغيّرت، دون لمس updated_at. */
    public function rememberLocale(string $locale): void
    {
        if (in_array($locale, self::LOCALES, true) && $this->locale !== $locale) {
            static::whereKey($this->getKey())->toBase()->update(['locale' => $locale]);
            $this->setRawAttributes(['locale' => $locale] + $this->getAttributes(), true);
        }
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value ? Phone::normalize($value) : null;
    }

    /**
     * البحث برقم الهاتف كما يكتبه المستخدم (0933.. أو +963933.. أو 00963..).
     * الأرقام تُحفظ موحّدة (963933..)، لذا نطابق الصيغة الموحّدة والصيغة الخام معاً.
     */
    public function scopePhoneIs($query, ?string $raw)
    {
        $raw = trim((string) $raw);
        $normalized = Phone::normalize($raw);

        return $query->where(function ($q) use ($raw, $normalized) {
            $q->where('phone', $raw);
            if ($normalized && $normalized !== $raw) {
                $q->orWhere('phone', $normalized);
            }
        });
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'address',
        'email',
        'password',
        'role',
        'avatar',
        'status',
        'employee_status',
        'country_code',
        'national_number',
        'birth_date',
        'city',
        'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'notification_prefs' => 'array',
            'offers_prompted_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** فعّل التحقق بخطوتين وأكّده برمز صحيح. */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /**
     * للتوافق مع أي كود/عرض قديم كان يستخدم $user->name مباشرة.
     */
    public function getNameAttribute(): string
    {
        $full = trim("{$this->first_name} {$this->last_name}");

        return $full !== '' ? $full : $this->phone;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    /** بطاقات الدفع المحفوظة (توكنات البنك فقط) */
    public function customerCards(): HasMany
    {
        return $this->hasMany(CustomerCard::class);
    }

    /** رصيد نقاط الولاء (قد لا يوجد قبل أول حركة) */
    public function loyaltyAccount()
    {
        return $this->hasOne(LoyaltyAccount::class);
    }

    /** عناوين الشحن المحفوظة (حتى 3) — انظر UserAddressService. */
    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function followedStores()
    {
        return $this->belongsToMany(SellerProfile::class, 'store_followers')->withTimestamps();
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id')->latest();
    }

    public function hasFavorited(int $productId): bool
    {
        return $this->favorites()->where('product_id', $productId)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee' && $this->employee_status === 'approved';
    }

    public function scopeSellers($query)
    {
        return $query->where('role', 'seller');
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function staffConversations(): HasMany
    {
        return $this->hasMany(StaffConversation::class, 'staff_id');
    }
}
