<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnswerController extends Controller
{
    /**
     * Add an answer to a question.
     */
    public function store(Request $request, Question $question)
    {
        $quiz = $question->quiz;

        // Security: Only the quiz creator can add answers
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only add answers to your own quizzes.');
        }

        // Can't modify published quiz
        if ($quiz->published_at) {
            return back()->with('error', 'Cannot modify a published quiz.');
        }

        $validated = $request->validate([
            'answer_text' => 'required|string|max:1000',
            'is_correct' => 'boolean',
        ]);

        Answer::create([
            'question_id' => $question->question_id,
            'answer_text' => $validated['answer_text'],
            'is_correct' => $validated['is_correct'] ?? false,
        ]);

        return back()->with('success', 'Answer added.');
    }

    /**
     * Delete an answer from a question.
     */
    public function destroy(Answer $answer)
    {
        $quiz = $answer->question->quiz;

        // Security: Only the quiz creator can delete answers
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only delete answers from your own quizzes.');
        }

        // Can't modify published quiz
        if ($quiz->published_at) {
            return back()->with('error', 'Cannot modify a published quiz.');
        }

        $answer->delete();

        return back()->with('success', 'Answer deleted.');
    }
}
