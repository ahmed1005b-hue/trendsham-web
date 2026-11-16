<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserAddressService;
use Illuminate\Http\Request;

/** عناوين الشحن المحفوظة للمستخدم الحالي فقط (عناوين غيره = 404). */
class AddressController extends Controller
{
    public function __construct(private UserAddressService $addresses) {}

    public function index(Request $request)
    {
        $list = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get();

        return response()->json([
            'data' => $list->map->toApiArray()->values(),
            'max' => \App\Models\UserAddress::MAX_PER_USER,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(UserAddressService::rules(), UserAddressService::messages());
        $address = $this->addresses->create($request->user(), $data);

        return response()->json(['data' => $address->toApiArray()], 201);
    }

    public function update(Request $request, int $address)
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $data = $request->validate(UserAddressService::rules(), UserAddressService::messages());

        return response()->json(['data' => $this->addresses->update($model, $data)->toApiArray()]);
    }

    public function makeDefault(Request $request, int $address)
    {
        $model = $request->user()->addresses()->findOrFail($address);

        return response()->json(['data' => $this->addresses->makeDefault($model)->toApiArray()]);
    }

    public function destroy(Request $request, int $address)
    {
        $this->addresses->delete($request->user()->addresses()->findOrFail($address));

        return response()->noContent();
    }
}
