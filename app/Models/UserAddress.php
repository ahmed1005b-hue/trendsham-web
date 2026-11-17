<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAddress extends Model
{
    use SoftDeletes;

    public const LABELS = ['home' => 'البيت', 'work' => 'العمل', 'other' => 'آخر'];

    public const MAX_PER_USER = 3;

    protected $fillable = ['label', 'city', 'details', 'latitude', 'longitude', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function labelText(): string
    {
        return isset(self::LABELS[$this->label]) ? __('addresses.labels.'.$this->label) : (string) $this->label;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return sprintf('https://www.google.com/maps?q=%.7F,%.7F', $this->latitude, $this->longitude);
    }

    /** النص الذي يُنسخ في "عنوان التوصيل" عند الطلب (يراه البائع كما هو). */
    public function shippingText(): string
    {
        $lines = [$this->city.' - '.$this->details];
        if ($url = $this->mapUrl()) {
            $lines[] = 'الموقع على الخريطة: '.$url;
        }

        return implode("\n", $lines);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'label_text' => $this->labelText(),
            'city' => $this->city,
            'details' => $this->details,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'map_url' => $this->mapUrl(),
            'is_default' => $this->is_default,
            'shipping_text' => $this->shippingText(),
        ];
    }
}
