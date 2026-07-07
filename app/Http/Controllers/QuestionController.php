<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    /**
     * Add a question to a quiz.
     */
    public function store(Request $request, Quiz $quiz)
    {
        // Security: Only the quiz creator can add questions
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only add questions to your own quizzes.');
        }

        // Can't add questions to a published quiz
        if ($quiz->published_at) {
            return back()->with('error', 'Cannot modify a published quiz.');
        }

        $validated = $request->validate([
            'question_text' => 'required|string|max:2000',
            'question_type' => 'required|in:MCQ,TF,Short',
            'marks' => 'required|integer|min:1|max:100',
        ]);

        // Determine the next order number
        $nextOrder = $quiz->questions()->max('question_order') + 1;

        Question::create([
            'quiz_id' => $quiz->quiz_id,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'marks' => $validated['marks'],
            'question_order' => $nextOrder,
        ]);

        return back()->with('success', 'Question added.');
    }

    /**
     * Delete a question from a quiz.
     */
    public function destroy(Question $question)
    {
        $quiz = $question->quiz;

        // Security: Only the quiz creator can delete questions
        if ($quiz->lecturer_id !== Auth::id()) {
            abort(403, 'You can only delete questions from your own quizzes.');
        }

        // Can't delete questions from a published quiz
        if ($quiz->published_at) {
            return back()->with('error', 'Cannot modify a published quiz.');
        }

        $question->delete();

        return back()->with('success', 'Question deleted.');
    }
}
