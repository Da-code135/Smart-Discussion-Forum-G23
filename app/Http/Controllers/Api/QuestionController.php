<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    /**
     * Display a listing of questions for a quiz.
     */
    public function index(Quiz $quiz)
    {
        $questions = $quiz->questions()->with('answers')->orderBy('question_order')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * Store a newly created question.
     */
    public function store(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => ['required', Rule::in(['MCQ', 'TF', 'Short'])],
            'marks' => 'required|integer|min:1',
            'answers' => 'required|array|min:1',
            'answers.*.answer_text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // For TF (True/False) questions, validate that there are exactly 2 answers
        if ($validated['question_type'] === 'TF' && count($validated['answers']) !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'True/False questions must have exactly 2 answers',
            ], 422);
        }

        // For MCQ (Multiple Choice) questions, validate that at least one answer is correct
        if ($validated['question_type'] === 'MCQ' && collect($validated['answers'])->filter(fn ($answer) => $answer['is_correct'])->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'MCQ questions must have at least one correct answer',
            ], 422);
        }

        // For TF (True/False) questions, validate that exactly one answer is correct
        if ($validated['question_type'] === 'TF') {
            $correctAnswersCount = collect($validated['answers'])->filter(fn ($answer) => $answer['is_correct'])->count();
            if ($correctAnswersCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'True/False questions must have exactly one correct answer',
                ], 422);
            }
        }

        // For Short answer questions, typically there shouldn't be predefined answers
        // However, the requirements specify that answers are still provided for validation purposes
        // So we'll allow Short questions to have answers but without specific constraints

        $maxOrder = $quiz->questions()->max('question_order') ?? 0;

        $question = Question::create([
            'quiz_id' => $quiz->quiz_id,
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'marks' => $validated['marks'],
            'question_order' => $maxOrder + 1,
        ]);

        foreach ($validated['answers'] as $answerData) {
            Answer::create([
                'question_id' => $question->question_id,
                'answer_text' => $answerData['answer_text'],
                'is_correct' => $answerData['is_correct'],
            ]);
        }

        $question->load('answers');

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $question,
            ],
            'message' => 'Question added',
        ], 201);
    }

    /**
     * Update the specified question.
     */
    public function update(Request $request, Quiz $quiz, Question $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => ['required', Rule::in(['MCQ', 'TF', 'Short'])],
            'marks' => 'required|integer|min:1',
        ]);

        $question->update([
            'question_text' => $validated['question_text'],
            'question_type' => $validated['question_type'],
            'marks' => $validated['marks'],
        ]);

        $question->load('answers');

        return response()->json([
            'success' => true,
            'data' => [
                'question' => $question,
            ],
            'message' => 'Question updated',
        ]);
    }

    /**
     * Remove the specified question.
     */
    public function destroy(Quiz $quiz, Question $question)
    {
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted',
        ]);
    }

    /**
     * Reorder questions in a quiz.
     */
    public function reorder(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:questions,question_id',
            'questions.*.order' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['questions'] as $item) {
                Question::where('question_id', $item['id'])->update(['question_order' => $item['order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Questions reordered',
        ]);
    }
}
