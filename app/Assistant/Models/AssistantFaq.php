<?php

namespace App\Assistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistantFaq extends Model
{
    use SoftDeletes;

    protected $fillable = ['question', 'keywords', 'answer', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    /** @return list<string> */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) $this->keywords) ?: [])));
    }
}
