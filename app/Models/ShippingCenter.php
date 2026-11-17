<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** مركز من مراكز شركة الشحن: يسلّم فيه البائع طروده، ويستلم منه الزبون إن اختار ذلك. */
class ShippingCenter extends Model
{
    protected $fillable = ['company_code', 'name', 'governorate', 'address', 'phone', 'hours', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** «الاسم - المحافظة» للقوائم والفواتير. */
    public function label(): string
    {
        return $this->name === $this->governorate ? $this->name : $this->name.' - '.$this->governorate;
    }

    /** عنوان الطلب عندما يستلمه الزبون من المركز. */
    public function pickupAddress(): string
    {
        return __('استلام من مركز كرم :center (رقم :code): :address - هاتف :phone', [
            'center' => $this->label(), 'code' => $this->company_code ?? '-', 'address' => $this->address, 'phone' => $this->phone,
        ]);
    }

    /**
     * المراكز الفعّالة مجمّعة بالمحافظة، لقوائم الاختيار.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function groupedForSelect(): Collection
    {
        return static::active()->orderBy('governorate')->orderBy('name')->get()->groupBy('governorate');
    }
}
