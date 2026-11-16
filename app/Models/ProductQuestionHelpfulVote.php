<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductQuestionHelpfulVote extends Model
{
    protected $fillable = ['product_question_id', 'user_id'];

    public function question()
    {
        return $this->belongsTo(ProductQuestion::class, 'product_question_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
