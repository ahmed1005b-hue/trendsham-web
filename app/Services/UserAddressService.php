<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * قواعد العناوين في مكان واحد (للويب والتطبيق):
 * - حتى 3 عناوين لكل مستخدم.
 * - عنوان واحد معتمد للشحن دائماً إن وُجد أي عنوان: الأول يُعتمد تلقائياً،
 *   واعتماد عنوان يلغي اعتماد غيره، وحذف المعتمد يعتمد الأقدم الباقي.
 */
class UserAddressService
{
    public static function rules(): array
    {
        return [
            'label' => ['required', Rule::in(array_keys(UserAddress::LABELS))],
            'city' => ['required', 'string', 'max:120'],
            'details' => ['required', 'string', 'max:500'],
            // الإحداثيات اختيارية لكن تأتي زوجاً صالحاً
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'label.in' => 'اختر نوع الموقع: البيت أو العمل أو آخر.',
            'city.required' => 'المدينة مطلوبة.',
            'details.required' => 'اكتب العنوان التفصيلي.',
            'latitude.required_with' => 'الإحداثيات ناقصة، أعد تحديد موقعك.',
            'longitude.required_with' => 'الإحداثيات ناقصة، أعد تحديد موقعك.',
        ];
    }

    public function create(User $user, array $data): UserAddress
    {
        return DB::transaction(function () use ($user, $data) {
            // قفل صفوف المستخدم يمنع تجاوز الحد بطلبين متزامنين
            $count = $user->addresses()->lockForUpdate()->count();
            if ($count >= UserAddress::MAX_PER_USER) {
                throw ValidationException::withMessages([
                    'address' => __('يمكنك حفظ :max عناوين كحد أقصى. احذف عنواناً أو عدّله.', ['max' => UserAddress::MAX_PER_USER]),
                ]);
            }

            $makeDefault = $count === 0 || ! empty($data['is_default']);
            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create($this->fields($data) + ['is_default' => $makeDefault]);
        });
    }

    public function update(UserAddress $address, array $data): UserAddress
    {
        return DB::transaction(function () use ($address, $data) {
            $address->fill($this->fields($data));
            if (! empty($data['is_default'])) {
                $this->clearDefault($address);
                $address->is_default = true;
            }
            // إلغاء اعتماد العنوان المعتمد يُتجاهَل: يبقى عنوان واحد معتمد دائماً
            $address->save();

            return $address;
        });
    }

    public function makeDefault(UserAddress $address): UserAddress
    {
        return DB::transaction(function () use ($address) {
            $this->clearDefault($address);
            $address->update(['is_default' => true]);

            return $address;
        });
    }

    public function delete(UserAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $user = $address->user;
            $address->delete();

            if ($wasDefault) {
                $user->addresses()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });
    }

    private function clearDefault(UserAddress $address): void
    {
        UserAddress::where('user_id', $address->user_id)->whereKeyNot($address->id)->update(['is_default' => false]);
    }

    private function fields(array $data): array
    {
        return [
            'label' => $data['label'],
            'city' => trim($data['city']),
            'details' => trim($data['details']),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ];
    }
}
