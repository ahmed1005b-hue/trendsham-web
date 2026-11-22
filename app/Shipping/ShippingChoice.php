<?php

namespace App\Shipping;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * اختيارات الزبون للشحن عند إتمام الطلب (الموقع والتطبيق): طريقة الاستلام، ومحافظته
 * (تحدد سعر المتاجر التي توصل بنفسها)، والمتاجر التي سيستلم طردها من محلها.
 */
final class ShippingChoice
{
    /** @param  list<int>  $pickupVendorIds */
    public function __construct(
        public readonly string $method,
        public readonly ?string $governorate,
        public readonly array $pickupVendorIds,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'shipping_governorate' => ['nullable', 'string', Rule::in(Governorates::ALL)],
            'pickup_stores' => ['nullable', 'array', 'max:50'],
            'pickup_stores.*' => ['integer'],
        ];
    }

    /**
     * المحافظة المكتوبة صراحة أولاً، وإلا نخمّنها من نص العنوان (للتطبيقات القديمة).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, ?string $addressText = null): self
    {
        $governorate = $input['shipping_governorate'] ?? null;

        return new self(
            ShippingQuote::method($input['delivery_method'] ?? null),
            Governorates::isValid($governorate) ? $governorate : Governorates::guess($addressText),
            array_values(array_unique(array_map('intval', (array) ($input['pickup_stores'] ?? [])))),
        );
    }

    /** @return array{fee: int, parcels: list<array<string, mixed>>} */
    public function quote(Collection $items, ?string $method = null): array
    {
        return app(ShippingQuote::class)->quote($items, $method ?? $this->method, $this->governorate, $this->pickupVendorIds);
    }
}
