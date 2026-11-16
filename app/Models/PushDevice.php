<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** هاتف مسجَّل لاستقبال الإشعارات (token من Firebase، فريد). */
class PushDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'app_version', 'last_seen_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
