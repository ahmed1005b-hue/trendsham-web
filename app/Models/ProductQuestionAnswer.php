<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductQuestionAnswer extends Model
{
    protected $fillable = ['product_question_id', 'user_id', 'answer', 'is_seller_answer'];

    protected function casts(): array
    {
        return ['is_seller_answer' => 'boolean'];
    }

    public function question()
    {
        return $this->belongsTo(ProductQuestion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
