<?php

namespace App\Returns\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** حدث في سجل المرتجع (actor_id فارغ للنظام). */
class ReturnEvent extends Model
{
    protected $fillable = ['return_request_id', 'actor_id', 'actor_role', 'type', 'note', 'photos'];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
