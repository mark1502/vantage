<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentFile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_opened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public static function track(int $userId, int $fileId, string $filepart = 'correspondence', int $page = 1, int $show = 15): void
    {
        self::updateOrCreate(
            ['user_id' => $userId, 'file_id' => $fileId],
            [
                'last_opened_at' => now(),
                'filepart' => $filepart,
                'page' => $page,
                'show' => $show,
            ]
        );

        $keep = self::where('user_id', $userId)
            ->orderByDesc('last_opened_at')
            ->take(10)
            ->pluck('id');

        self::where('user_id', $userId)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
