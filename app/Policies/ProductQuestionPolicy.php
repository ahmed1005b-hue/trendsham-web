<?php

namespace App\Policies;

use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductQuestionPolicy
{
    /** السائل فقط (موقع الزبون). */
    public function delete(User $user, ProductQuestion $question): Response
    {
        return $question->user_id === $user->id ? Response::allow() : Response::deny('');
    }

    /** السائل أو المدير (API). */
    public function deleteAsAskerOrAdmin(User $user, ProductQuestion $question): Response
    {
        return $question->user_id === $user->id || $user->isAdmin() ? Response::allow() : Response::deny('');
    }

    /** مالك متجر المنتج صاحب السؤال. */
    public function answerAsSeller(User $user, ProductQuestion $question): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $question->product->vendor_id === $storeId ? Response::allow() : Response::deny('');
    }
}
