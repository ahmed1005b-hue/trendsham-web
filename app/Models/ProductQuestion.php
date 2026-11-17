<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = ['product_id', 'user_id', 'question', 'helpful_count'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(ProductQuestionAnswer::class)->oldest();
    }

    public function helpfulVotes()
    {
        return $this->hasMany(ProductQuestionHelpfulVote::class);
    }

    public function hasAnswer(): bool
    {
        return $this->answers()->exists();
    }

    public function isHelpfulBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->helpfulVotes()->where('user_id', $user->id)->exists();
    }
}
