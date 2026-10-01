<?php

namespace App\Http\Middleware;

use App\Feature;
use App\Models\Quiz;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rute quiz dipakai bersama oleh quiz kelas dan lembar soal ujian online (quizzes.ujian_id terisi).
 * Lembar soal ikut fitur ujian_online, selain itu fitur quiz. Rute tanpa {quiz} (buat baru) selalu quiz kelas.
 */
class PastikanFiturQuiz
{
    public function handle(Request $request, Closure $next): Response
    {
        $quiz = $request->route('quiz');
        if ($quiz !== null && ! $quiz instanceof Quiz) {
            $quiz = Quiz::query()->find($quiz);
        }

        abort_unless(Feature::aktif($quiz?->ujian_id !== null ? 'ujian_online' : 'quiz'), 404);

        return $next($request);
    }
}
