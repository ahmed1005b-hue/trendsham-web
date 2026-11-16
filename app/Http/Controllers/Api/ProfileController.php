<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService)
    {
    }

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'country_code' => ['nullable', 'string', 'max:8'],
            'national_number' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'avatar' => ImageUploadService::validationRules(required: false),
        ], ImageUploadService::validationMessages('avatar'));

        $oldAvatar = $user->avatar;
        if ($request->hasFile('avatar')) {
            $avatar = $this->imageUploadService->store($request->file('avatar'), 'avatars');
            if ($avatar === null) {
                return response()->json(['message' => 'الملف المرفوع ليس صورة صالحة.'], 422);
            }

            $validated['avatar'] = $avatar;
        }

        $user->update($validated);
        if (isset($validated['avatar']) && $oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return new UserResource($user);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'كلمة السر الحالية غير صحيحة.'], 422);
        }

        $user->update(['password' => $validated['password']]);

        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'تم تغيير كلمة السر.']);
    }

    public function followedStores(Request $request)
    {
        $stores = $request->user()->followedStores()
            ->where('status', 'approved')
            ->withCount('products')
            ->withCount('followers')
            ->withAvg('sellerReviews', 'rating')
            ->latest('store_followers.created_at')
            ->paginate(20);

        $data = $stores->getCollection()->map(fn ($store) => [
            'id' => $store->id,
            'store_name' => $store->store_name,
            'store_description' => $store->store_description,
            'store_logo' => $store->store_logo ? asset('storage/'.$store->store_logo) : null,
            'store_banner' => $store->store_banner ? asset('storage/'.$store->store_banner) : null,
            'products_count' => (int) ($store->products_count ?? 0),
            'followers_count' => (int) ($store->followers_count ?? 0),
            'seller_rating' => $store->seller_reviews_avg_rating !== null ? round((float) $store->seller_reviews_avg_rating, 1) : null,
            'is_followed' => true,
            'followed_at' => optional($store->pivot?->created_at)->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $stores->currentPage(),
                'last_page' => $stores->lastPage(),
                'per_page' => $stores->perPage(),
                'total' => $stores->total(),
            ],
        ]);
    }
}
