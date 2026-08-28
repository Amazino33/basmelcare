<?php

namespace App\Models;

use App\Support\CloudinaryImage;
use Illuminate\Database\Eloquent\Model;

/**
 * One message sent to many customers, each individually.
 */
class Broadcast extends Model
{
    protected $fillable = ['message', 'image_path', 'audience', 'user_id', 'started_at', 'finished_at'];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** Who the audiences are, in the words used on the page. */
    public const AUDIENCES = [
        'all'       => 'All customers',
        'wholesale' => 'Wholesale customers',
        'retail'    => 'Retail customers',
        'recent'    => 'Bought in the last 90 days',
    ];

    public function recipients()
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The image as a public URL.
     *
     * The gateway fetches it rather than receiving an upload, so it has to be
     * reachable without a login. That is fine for a marketing picture and would
     * not be for anything about a patient.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? CloudinaryImage::deliver($this->image_path) : null;
    }

    public function pendingCount(): int
    {
        return $this->recipients()->where('status', 'pending')->count();
    }

    public function isFinished(): bool
    {
        return $this->pendingCount() === 0;
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }
}
