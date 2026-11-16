<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** إشعار جماعي من المدير لكل العملاء (مرة كل 7 أيام). */
class PushBroadcast extends Model
{
    protected $fillable = ['title', 'body', 'link', 'created_by', 'scheduled_for', 'sent_at', 'recipients_count', 'pushed_count'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'pushed_count' => 'integer',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}
