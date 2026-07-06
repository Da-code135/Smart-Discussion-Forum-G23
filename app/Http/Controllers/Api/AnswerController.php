<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;

class AnswerController extends Controller
{
    /**
     * Display a listing of answers for a question.
     */
    public function index(Question $question)
    {
        $answers = $question->answers;

        return response()->json([
            'success' => true,
            'data' => [
                'answers' => $answers,
            ],
        ]);
    }

    /**
     * Store a newly created answer.
     */
    public function store(Request $request, Question $question)
    {
        $validated = $request->validate([
            'answer_text' => 'required|string',
            'is_correct' => 'required|boolean',
        ]);

        $answer = Answer::create([
            'question_id' => $question->question_id,
            'answer_text' => $validated['answer_text'],
            'is_correct' => $validated['is_correct'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'answer' => $answer,
            ],
            'message' => 'Answer added',
        ], 201);
    }

    /**
     * Update the specified answer.
     */
    public function update(Request $request, Answer $answer)
    {
        $validated = $request->validate([
            'answer_text' => 'required|string',
            'is_correct' => 'required|boolean',
        ]);

        $answer->update([
            'answer_text' => $validated['answer_text'],
            'is_correct' => $validated['is_correct'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'answer' => $answer,
            ],
            'message' => 'Answer updated',
        ]);
    }

    /**
     * Remove the specified answer.
     */
    public function destroy(Answer $answer)
    {
        $answer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Answer deleted',
        ]);
    }
}
