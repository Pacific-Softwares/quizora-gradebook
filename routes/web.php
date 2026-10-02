<?php

use Illuminate\Support\Facades\Route;
use Modules\QuizoraGradebook\Models\ReportCard;
use Modules\QuizoraGradebook\Support\Gradebook;

// GET /quizora-gradebook/report/{token}: a student's printable report card. The 40-character
// token is the only key; it's never listed anywhere and the creator can revoke or renew it.
Route::get('/report/{token}', function (string $token) {
    abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);
    $card = ReportCard::with(['creator:id,name,avatar,certificate_logo', 'student:id,name'])->where('token', $token)->firstOrFail();
    abort_unless($card->creator && $card->student, 404);

    $card->increment('views', 1, ['last_viewed_at' => now()]);

    return response()
        ->view('quizora-gradebook::report-card', [
            'card' => $card,
            'report' => (new Gradebook($card->creator_id))->reportCard($card->student_id),
        ])
        ->header('X-Robots-Tag', 'noindex, nofollow');
})->middleware('throttle:60,1')->name('report');
