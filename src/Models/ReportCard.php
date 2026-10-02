<?php

namespace Modules\QuizoraGradebook\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A private, shareable link to one student's report card from one creator. */
class ReportCard extends Model
{
    protected $table = 'quizora_gradebook_report_cards';

    protected $fillable = ['creator_id', 'student_id', 'note'];

    protected function casts(): array
    {
        return ['last_viewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $card) => $card->token ??= Str::random(40));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function url(): string
    {
        return route('quizora-gradebook.report', $this->token);
    }

    /** A new link: the old one stops working at once. */
    public function regenerate(): void
    {
        $this->forceFill(['token' => Str::random(40), 'views' => 0, 'last_viewed_at' => null])->save();
    }
}
