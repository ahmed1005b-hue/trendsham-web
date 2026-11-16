<?php

namespace App\Http\Controllers;

use App\Services\UserAddressService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * إدارة عناوين الشحن من صفحة الملف الشخصي في الويب.
 * أخطاء التحقق في حزمة "addresses" حتى لا تختلط بحقول نموذج البيانات الشخصية (city مثلاً).
 */
class AddressController extends Controller
{
    private const BAG = 'addresses';

    public function __construct(private UserAddressService $addresses) {}

    public function store(Request $request)
    {
        $data = $request->validateWithBag(self::BAG, UserAddressService::rules(), UserAddressService::messages());
        $this->inBag(fn () => $this->addresses->create($request->user(), $data));

        return $this->back('تم حفظ العنوان.');
    }

    public function update(Request $request, int $address)
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $data = $request->validateWithBag(self::BAG, UserAddressService::rules(), UserAddressService::messages());
        $this->addresses->update($model, $data);

        return $this->back('تم تحديث العنوان.');
    }

    public function makeDefault(Request $request, int $address)
    {
        $this->addresses->makeDefault($request->user()->addresses()->findOrFail($address));

        return $this->back('أصبح هذا العنوان معتمداً لإرسال الشحنات.');
    }

    public function destroy(Request $request, int $address)
    {
        $this->addresses->delete($request->user()->addresses()->findOrFail($address));

        return $this->back('تم حذف العنوان.');
    }

    private function inBag(callable $fn): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            throw $e->errorBag(self::BAG);
        }
    }

    private function back(string $message)
    {
        return redirect()->to(route('profile.edit').'#addresses')->with('success', $message);
    }
}
