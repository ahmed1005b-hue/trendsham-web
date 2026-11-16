<?php

namespace App\Models;

use App\Notifications\Push\NotificationGroups;
use App\Notifications\Push\PushSender;
use App\Notifications\Push\PushTarget;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'group', 'title', 'body', 'link', 'target', 'push_status', 'pushed_at', 'broadcast_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'pushed_at' => 'datetime', 'target' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function send(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): self
    {
        $role = (string) User::whereKey($userId)->value('role');

        $notification = static::create([
            'user_id' => $userId,
            'type' => $type,
            'group' => NotificationGroups::groupOf($type, $role),
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'target' => PushTarget::fromLink($link),
            'push_status' => 'pending',
        ]);

        // الإرسال للهاتف بعد الرد: لا يؤخر العملية ولا يُفشلها، ويقع بعد انتهاء أي معاملة.
        $id = $notification->id;
        dispatch(fn () => app(PushSender::class)->send($id))->afterResponse();

        return $notification;
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
