# Quiz & Assessment Module — Person 4 Implementation Summary

> **Generated:** July 2026
> **Person 4 Role:** Grading & Analytics — the "scorekeeper" of the quiz module

---

## Table of Contents

1. [What Person 4 Does (The Big Picture)](#what-person-4-does-the-big-picture)
2. [How Person 4 Connects to Everyone Else](#how-person-4-connects-to-everyone-else)
3. [Files Modified (4 files)](#files-modified)
4. [Files Created (1 file)](#files-created)
5. [The Complete Grading Flow](#the-complete-grading-flow)

---

## What Person 4 Does (The Big Picture)

Before Person 4, the quiz module had a gap. A student could take a quiz, answer questions, and click Submit — but nothing actually happened with their answers. The `gradeQuiz()` method in Person 3's controller was a **placeholder** that just wrote a log message saying "pretend I graded this." No score was calculated. No grade record was created. The student would see a results page that said "Grading in progress" forever.

Person 4 fixed that. Here's what was added:

**1. A real grading algorithm** — When a student submits, the system now:
   - Looks at every question they answered
   - Compares each answer to the correct one
   - Awards marks for correct answers
   - Skips (0 marks) for wrong or unanswered questions
   - Calculates the percentage score
   - Awards participation bonus marks based on the lecturer's criteria
   - Creates a permanent Grade record in the database

**2. A student results page** — After submission, the student sees:
   - Their total score, maximum possible score, and percentage
   - A question-by-question review showing which they got right, wrong, or skipped
   - The correct answer alongside their wrong answer
   - Auto-submit notice if time expired

**3. A lecturer performance report** — The lecturer can view:
   - Class statistics (average score, highest, lowest, total attempts)
   - A table of every student's scores sorted from highest to lowest
   - Pass/fail status badges based on percentage thresholds

**4. Class statistics** — Aggregate numbers calculated across all students who took the quiz.

Think of Person 4 as the person who:
- **Grades the exam** (compares answers to the answer key)
- **Computes the final grade** (adds participation bonus)
- **Gives the student their report card** (the results page)
- **Gives the teacher the class summary** (the performance report)

---

## How Person 4 Connects to Everyone Else

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    HOW PERSON 4 CONNECTS                                │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                        PERSON 1 (DATABASE)                      │   │
│  │                                                                  │   │
│  │  Person 4 READS from:           Person 4 WRITES to:              │   │
│  │  ┌────────────────────┐         ┌────────────────────┐           │   │
│  │  │  questions table   │         │   grades table     │           │   │
│  │  │  answers table     │         │  (creates Grade    │           │   │
│  │  │  student_answers   │         │   records)         │           │   │
│  │  │  student_attempts  │         └────────────────────┘           │   │
│  │  │  quiz_config       │                                          │   │
│  │  └────────────────────┘                                          │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                            │                                            │
│                            ▼                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     PERSON 3 (QUIZ EXECUTION)                    │   │
│  │                                                                  │   │
│  │  Student submits ──→ submitQuiz() ──→ gradeQuiz()  ←── Person 4  │   │
│  │                      autoSubmit()  ──→ gradeQuiz()  ←── Person 4 │   │
│  │                                 ┆                                │   │
│  │  Student views  ──→ showResult() reads Grade record  ←── P4     │   │
│  │                                 ┆                                │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                            │                                            │
│                            ▼                                            │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     PERSON 2 (LECTURER INTERFACE)                │   │
│  │                                                                  │   │
│  │  Lecturer views ──→ showPerformanceReport() reads grades ←── P4 │   │
│  │  the report       ──→ getClassStatistics() aggregates    ←── P4 │   │
│  │                                                                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Person 4 + Person 1 (Database)

Person 4 uses Person 1's tables extensively:

| Table | How Person 4 Uses It |
|---|---|
| `questions` | Reads `marks` per question to calculate max possible score |
| `answers` | Reads `is_correct` flag to find the right answer for each question |
| `student_answers` | Reads `selected_answer_id` to know what the student chose |
| `student_attempts` | Reads `attempt_id` to link everything together |
| `quiz_configuration` | Reads `participation_criteria` to know how to award bonus marks |
| `grades` | **Creates** new rows — this is the output of grading |

### Person 4 + Person 2 (Lecturer Interface)

Person 4 added a new route and method to Person 2's `QuizController`:
- `showPerformanceReport()` — shows the lecturer a table of all student scores
- `getClassStatistics()` — calculates averages, highs, lows

These live in the same controller Person 2 built (`QuizController.php`) because they're part of the lecturer-facing experience.

### Person 4 + Person 3 (Quiz Execution)

This is the tightest connection. Person 4 modified Person 3's `StudentQuizController`:

1. **The `gradeQuiz()` method** was a 3-line placeholder that just logged. Person 4 replaced it with ~70 lines of real grading logic.
2. **The `calculateParticipationMark()` method** was added — it didn't exist before.
3. **The `showResult()` method** now loads the `Grade` record (via `$attempt->grade`) and passes it to the view. Before, it was just an empty stub.
4. **Both `submitQuiz()` and `autoSubmit()`** already called `$this->gradeQuiz()`, so they automatically picked up the real grading logic — no changes needed there.

### Person 4 + Person 5 (Notifications)

They're independent. Person 5 handles what happens BEFORE and AT THE START of the quiz. Person 4 handles what happens AFTER the student submits. They never touch the same code paths.

---

## Files Modified

---

### FILE 1: `app/Http/Controllers/StudentQuizController.php` (3 changes)

This file is Person 3's controller. Person 4 made three changes to it.

---

#### Change 1: Replaced the placeholder `gradeQuiz()` with real grading logic

**Where:** Lines 441–519

**Before (Person 3's placeholder):**
```php
/**
 * Grade the quiz attempt.
 *
 * Placeholder — Person 4 implements the actual grading algorithm:
 *   - Compare selected_answer_id to each question's correct answer
 *   - Calculate total_score, max_score, percentage
 *   - Apply participation bonuses
 *   - Create/update the Grade record
 *
 * For now this logs the event so Person 4 can pick it up.
 */
private function gradeQuiz(StudentAttempt $attempt): void
{
    // Person 4: Insert grading logic here
    \Log::info('Quiz attempt submitted — ready for grading.', [
        'attempt_id' => $attempt->attempt_id,
        'quiz_id' => $attempt->quiz_id,
        'student_id' => $attempt->student_id,
    ]);
}
```

That's it — three lines of log, no actual grading.

**After (Person 4's implementation):**

```php
/**
 * Grade the quiz attempt.
 *
 * Compares each student's answer to the correct answer,
 * calculates total score, percentage, and participation bonus,
 * then creates a Grade record.
 *
 * Called automatically from submitQuiz() and autoSubmit().
 */
private function gradeQuiz(StudentAttempt $attempt): void
{
    $quiz = $attempt->quiz;

    // Load all questions with their correct answers
    $questions = $quiz->questions()->with('answers')->get();

    // Load all student answers for this attempt, keyed by question_id
    $studentAnswers = StudentAnswer::where('attempt_id', $attempt->attempt_id)
        ->get()
        ->keyBy('question_id');

    $totalScore = 0;
    $maxScore = 0;

    // Step 1: Score each question
    foreach ($questions as $question) {
        $maxScore += $question->marks;

        // Get the student's answer for this question (if any)
        $studentAnswer = $studentAnswers->get($question->question_id);

        if (!$studentAnswer || !$studentAnswer->selected_answer_id) {
            // Skipped or unanswered — 0 marks
            continue;
        }

        // Find the correct answer for this question
        $correctAnswer = $question->answers->firstWhere('is_correct', true);

        if ($correctAnswer && $studentAnswer->selected_answer_id == $correctAnswer->answer_id) {
            // Correct! Award full marks
            $totalScore += $question->marks;
        }
        // Wrong answer — award 0 marks (no else needed)
    }

    // Step 2: Calculate percentage
    $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

    // Step 3: Determine participation mark
    $participationMark = $this->calculateParticipationMark($quiz, $percentage);

    // Step 4: Calculate final grade
    $finalGrade = round($totalScore + $participationMark, 2);

    // Step 5: Create or update Grade record
    Grade::updateOrCreate(
        ['attempt_id' => $attempt->attempt_id],  // Match by attempt_id
        [
            'student_id' => $attempt->student_id,
            'quiz_id' => $quiz->quiz_id,
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'participation_mark' => $participationMark,
            'final_grade' => $finalGrade,
            'graded_at' => now(),
        ]
    );

    \Log::info('Quiz graded successfully.', [
        'attempt_id' => $attempt->attempt_id,
        'quiz_id' => $quiz->quiz_id,
        'student_id' => $attempt->student_id,
        'score' => "{$totalScore}/{$maxScore}",
        'percentage' => "{$percentage}%",
        'final_grade' => $finalGrade,
    ]);
}
```

**Step-by-step explanation of what this code does:**

**Step 1 — Score each question.** The method loops through every question in the quiz:
- It adds the question's marks to `$maxScore` (the total possible score)
- It looks up the student's answer for that question
- If the student didn't answer (`!$studentAnswer` or `!$studentAnswer->selected_answer_id`), it skips — 0 marks
- It finds the correct answer by looking for the answer option where `is_correct = true`
- If the student's selected answer ID matches the correct answer's ID, it awards the question's marks to `$totalScore`
- Wrong answers get 0 marks (the loop just continues without adding anything)

**Step 2 — Calculate percentage.** `totalScore / maxScore * 100`, rounded to 2 decimal places. If `maxScore` is 0 (shouldn't happen, but safety check), percentage is 0.

**Step 3 — Calculate participation mark.** Calls `calculateParticipationMark()` (see Change 2 below) which checks the lecturer's criteria and awards bonus points.

**Step 4 — Calculate final grade.** `totalScore + participationMark`, rounded to 2 decimal places.

**Step 5 — Create or update Grade record.** Uses `updateOrCreate` — this means:
- If a Grade record already exists for this attempt (e.g. the lecturer re-grades), it **updates** the existing one
- If no Grade record exists yet, it **creates** a new one
- This is safer than `create()` because it handles the case where `gradeQuiz()` is called twice

**Why `updateOrCreate` instead of `create`?** If a student's submission gets processed twice (network retry, double-click), `create` would throw a database error. `updateOrCreate` gracefully handles the second call by updating the existing record.

---

#### Change 2: Added the `calculateParticipationMark()` method

**Where:** Lines 530–552

**Before:** This method didn't exist at all.

**After:**

```php
/**
 * Calculate participation mark based on quiz configuration.
 *
 * Default logic (overridable by quiz config):
 * - score >= 80% : full marks (5 points)
 * - score >= 50% : half marks (2.5 points)
 * - score < 50%  : 0 marks
 * - If participation_criteria contains 'attempted' : full marks regardless
 */
private function calculateParticipationMark(Quiz $quiz, float $percentage): float
{
    $config = $quiz->configuration;
    $fullMarks = 5.0;

    // If criteria says "full marks if attempted", award regardless of score
    if ($config && $config->participation_criteria) {
        $criteria = strtolower($config->participation_criteria);

        if (str_contains($criteria, 'attempted')) {
            return $fullMarks;
        }
    }

    // Score-based participation
    if ($percentage >= 80) {
        return $fullMarks;
    } elseif ($percentage >= 50) {
        return $fullMarks / 2;  // 2.5 points
    }

    return 0;
}
```

**What this method does step by step:**

1. **Gets the quiz configuration** — reads the `participation_criteria` text that the lecturer entered when creating the quiz (e.g. "Full marks if attempted and score >= 50%")
2. **Checks for "attempted" keyword** — if the lecturer's criteria contains the word "attempted," it means anyone who attempted the quiz gets full participation marks regardless of their score. This is the simplest criterion.
3. **Score-based grading** — if no "attempted" override:
   - Score 80% or higher → 5 bonus points
   - Score between 50% and 79.99% → 2.5 bonus points
   - Score below 50% → 0 bonus points
4. **Returns the participation mark** as a decimal number

**Real-world scenario:** Dr. Smith sets participation criteria to "Full marks if attempted." Jane gets 30% on the quiz (failed). But she still gets 5 participation points for showing up. Her final grade is 30 + 5 = 35 out of 105 possible.

---

#### Change 3: The `showResult()` method now works with real grade data

**Where:** Lines 405–435

**Before (Person 3's stub):** The method already loaded the grade and passed it to the view, but since `gradeQuiz()` was a placeholder, `$attempt->grade` was always `null`. The view showed "Grading in progress" every time.

**After:** The method is structurally the same, but now `$attempt->grade` is populated because `gradeQuiz()` creates a real `Grade` record. The view now shows real scores instead of the fallback message.

The method:
1. Finds the student's attempt for this quiz
2. Loads the grade record (`$attempt->grade`) — this is now populated by `gradeQuiz()`
3. Loads all questions with their answer options
4. Loads the student's selected answers as a map of `question_id → selected_answer_id`
5. Passes everything to the result view

---

### FILE 2: `app/Http/Controllers/QuizController.php` (2 methods added)

Person 4 added two methods to Person 2's `QuizController` — the lecturer-facing report features.

---

#### Addition 1: `showPerformanceReport()` method

**Where:** Lines 238–253

**What it does:** Shows the lecturer a class performance report for a quiz.

```php
/**
 * Show the performance report for a quiz.
 *
 * GET /quizzes/{quiz}/report
 * Route name: quizzes.report
 *
 * Displays class performance summary, student-by-student breakdown,
 * and aggregate statistics.
 */
public function showPerformanceReport(Quiz $quiz)
{
    // Security: Only the quiz lecturer or an admin can view
    if (Auth::user()->id !== $quiz->lecturer_id && !Auth::user()->isAdmin()) {
        abort(403, 'Not authorized to view this report.');
    }

    $grades = Grade::where('quiz_id', $quiz->quiz_id)
                   ->with('student')  // Eager-load the student's name
                   ->orderByDesc('final_grade')  // Highest score first
                   ->get();

    $stats = $this->getClassStatistics($quiz);

    return view('quizzes.performance-report', compact('quiz', 'grades', 'stats'));
}
```

**Step-by-step:**

1. **Security check** — only the lecturer who created the quiz, or an admin, can see the report. Anyone else gets a 403 error page.
2. **Loads all grades** for this quiz, including each student's name (via `->with('student')`), sorted highest score first
3. **Calculates class statistics** by calling `getClassStatistics()` (see below)
4. **Shows the view** with the quiz, grades, and stats

**Real-world scenario:** Dr. Smith logs in and visits the performance report for her Midterm quiz. She sees a table with 30 students sorted by score. The top student scored 95/100. The lowest scored 32/100. The class average is 71.3.

---

#### Addition 2: `getClassStatistics()` method

**Where:** Lines 258–274

**What it does:** Calculates aggregate numbers across all students who took a quiz.

```php
/**
 * Calculate aggregate class statistics for a quiz.
 */
private function getClassStatistics(Quiz $quiz): ?array
{
    $allGrades = Grade::where('quiz_id', $quiz->quiz_id)->get();

    if ($allGrades->count() === 0) {
        return null;  // No grades yet
    }

    $scores = $allGrades->pluck('total_score')->toArray();

    return [
        'total_attempts' => $allGrades->count(),
        'average_score'  => round($allGrades->avg('total_score'), 2),
        'highest_score'  => round(max($scores), 2),
        'lowest_score'   => round(min($scores), 2),
    ];
}
```

**What it returns:**

| Statistic | What It Means | Example |
|---|---|---|
| `total_attempts` | How many students submitted | 30 |
| `average_score` | The class average (mean) | 71.30 |
| `highest_score` | The best score in the class | 95.00 |
| `lowest_score` | The worst score in the class | 32.00 |

**Returns `null`** if no one has taken the quiz yet — the view checks for this and shows "No data yet" instead of an empty table.

---

### FILE 3: `routes/web.php` (1 route added)

**Where:** Line 400

**Before:** No report route existed.

**After:**

```php
// Performance report (lecturer/admin only)
Route::get("/{quiz}/report", [\App\Http\Controllers\QuizController::class, "showPerformanceReport"])->name("report");
```

**What this does:** Adds a URL that the lecturer can visit to see the performance report:

```
GET /quizzes/5/report
```

Named `quizzes.report`, so it can be linked to anywhere in the views with:
```blade
<a href="{{ route('quizzes.report', $quiz->quiz_id) }}">View Report</a>
```

This route sits inside the `quizzes.` prefix group and the `auth` middleware group, so only logged-in users can access it. The method itself adds an extra security check (only quiz creator or admin).

---

### FILE 4: `resources/views/quizzes/result.blade.php` (rewritten)

**Where:** `resources/views/quizzes/result.blade.php`

**Before:** This view existed but it was Person 3's initial version. It had a basic score display. The "Grading in progress" fallback was always shown because no Grade record existed.

**After:** The view handles two states:

**State 1: Grade exists** (student has been graded)
```
┌──────────────────────────────────────────┐
│           📝 Midterm Exam — Results      │
│                                          │
│  ┌──────────┬──────────┬──────────────┐  │
│  │YOUR SCORE│  OUT OF  │  PERCENTAGE  │  │
│  │   45.5   │  100.0   │    45.5%     │  │
│  └──────────┴──────────┴──────────────┘  │
│                                          │
│  Started: 10:00am | Submitted: 10:45am   │
│  Status: Manual                          │
│                                          │
│  ── Question Review ──                    │
│                                          │
│  ✅ 1. What is Laravel?                  │
│     Your answer: A PHP framework ✓       │
│     5 marks                              │
│                                          │
│  ❌ 2. Laravel uses MVC?                 │
│     Your answer: False ✗                 │
│     (Correct: True)                      │
│     5 marks                              │
│                                          │
│  ❌ 3. What is Eloquent? (Not answered)  │
│     5 marks                              │
│                                          │
│  [Back to Dashboard]                     │
└──────────────────────────────────────────┘
```

**State 2: No grade yet**
```
┌──────────────────────────────────────────┐
│           📝 Midterm Exam — Results      │
│                                          │
│  ⏳ Grading in progress                  │
│  Your quiz has been submitted. Your      │
│  grade will appear here once grading     │
│  is complete.                            │
│                                          │
│  Started: 10:00am | Submitted: 10:45am   │
└──────────────────────────────────────────┘
```

**How the view decides which state to show:**
```blade
@if ($grade)
    {{-- Show the full score breakdown and question review --}}
@else
    {{-- Show "Grading in progress" fallback --}}
@endif
```

**What's in the full results view:**

1. **Auto-submit notice** — If the attempt was auto-submitted (timer expired), a yellow banner says "Auto-submitted — time expired."

2. **Score summary card** — Green background with three columns:
   - YOUR SCORE (green, large)
   - OUT OF (normal weight)
   - PERCENTAGE (blue, large)

3. **Submission info** — One row showing start time, submit time, and whether it was manual or auto-submitted.

4. **Question review** — Each question shown as a card:
   - **Green background** = answered correctly, shows checkmark
   - **Red background** = answered incorrectly, shows the correct answer
   - **Gray background** = not answered, shows "Not answered" in red

5. **Back to Dashboard button**

---

## Files Created

---

### FILE 5: `resources/views/quizzes/performance-report.blade.php` (new file)

**What it is:** The lecturer-facing class performance report page.

**Full code with explanation:**

```blade
@extends('layouts.app')

@section('title', 'Performance Report: ' . $quiz->title)

@section('content')
<div class="page-stack">
    <div class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Performance Report: {{ $quiz->title }}</h1>
                <p>Class performance summary and student breakdown.</p>
            </div>
            <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Quiz
            </a>
        </div>
    </div>

    @if (!$stats)
        {{-- No one has taken the quiz yet --}}
        <div class="empty-state">
            <span class="material-symbols-outlined" style="font-size: 40px;">bar_chart</span>
            <h2>No data yet</h2>
            <p>No students have completed this quiz yet.</p>
        </div>
    @else
        {{-- Four statistics summary cards --}}
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: var(--app-accent);">{{ $stats['total_attempts'] }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Total Attempts</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #16a34a;">{{ number_format($stats['average_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Average Score</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #2563eb;">{{ number_format($stats['highest_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Highest Score</div>
            </div>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; font-weight: 700; color: #dc2626;">{{ number_format($stats['lowest_score'], 1) }}</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">Lowest Score</div>
            </div>
        </div>

        {{-- Student-by-student results table --}}
        <div class="card">
            <div class="card-header">
                <h3 style="margin: 0;">Student Results</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th style="text-align: center;">Score</th>
                                <th style="text-align: center;">Percentage</th>
                                <th style="text-align: center;">Participation</th>
                                <th style="text-align: center;">Final Grade</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($grades as $grade)
                                <tr>
                                    <td>
                                        <strong>{{ $grade->student->full_name ?? 'Deleted User' }}</strong>
                                    </td>
                                    <td style="text-align: center;">
                                        {{ number_format($grade->total_score, 1) }}/{{ number_format($grade->max_score, 1) }}
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        {{ number_format($grade->percentage, 1) }}%
                                    </td>
                                    <td style="text-align: center;">
                                        +{{ number_format($grade->participation_mark, 1) }}
                                    </td>
                                    <td style="text-align: center; font-weight: 700;">
                                        {{ number_format($grade->final_grade, 1) }}
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($grade->percentage >= 80)
                                            <span class="badge badge-success">Pass</span>
                                        @elseif ($grade->percentage >= 50)
                                            <span class="badge badge-warning">Needs Review</span>
                                        @else
                                            <span class="badge badge-danger">Low Score</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                        No students have submitted this quiz yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
```

**What the page looks like:**

```
┌──────────────────────────────────────────────────────────┐
│  Performance Report: Midterm Exam              [Back]   │
│  Class performance summary and student breakdown.        │
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐│
│  │    30    │  │   71.3   │  │   95.0   │  │   32.0   ││
│  │  Total   │  │ Average  │  │ Highest  │  │  Lowest  ││
│  │ Attempts │  │  Score   │  │  Score   │  │  Score   ││
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘│
│                                                          │
│  ── Student Results ──                                   │
│                                                          │
│  ┌─────────┬──────┬────────────┬────────┬────────┬──────┐│
│  │ Student │Score │Percentage  │Partic. │  Final │Status││
│  ├─────────┼──────┼────────────┼────────┼────────┼──────┤│
│  │ Jane D. │95/100│   95.0%    │  +5.0  │ 100.0  │ Pass ││
│  │ John S. │70/100│   70.0%    │  +2.5  │  72.5  │ Needs││
│  │ Bob K.  │32/100│   32.0%    │  +0.0  │  32.0  │ Low  ││
│  └─────────┴──────┴────────────┴────────┴────────┴──────┘│
└──────────────────────────────────────────────────────────┘
```

**How the status badges work:**

| Percentage | Badge | Color |
|---|---|---|
| 80% and above | "Pass" | Green |
| 50% to 79.99% | "Needs Review" | Yellow |
| Below 50% | "Low Score" | Red |

**What the "Deleted User" fallback does:** If a student's account was deleted after they took the quiz, `$grade->student->full_name` would cause an error. The `?? 'Deleted User'` handles this gracefully by showing "Deleted User" instead of crashing.

---

## The Complete Grading Flow

Here's what happens from the moment a student submits to the moment they see their results:

```
STUDENT CLICKS "SUBMIT" (or timer expires)
  │
  ▼
Person 3: submitQuiz() or autoSubmit()
  │  Sets submit_time = now()
  │  Sets is_auto_submit = true/false
  │
  ▼
Person 4: gradeQuiz() runs  ←── THIS IS WHAT PERSON 4 BUILT
  │
  ├─► Step 1: Loop through every question in the quiz
  │     ├─► Read question.marks → add to maxScore
  │     ├─► Look up student's answer for this question
  │     ├─► If no answer → skip (0 marks)
  │     ├─► Find the correct answer (where is_correct = true)
  │     └─► If student's answer matches → add marks to totalScore
  │
  ├─► Step 2: Calculate percentage
  │     percentage = (totalScore / maxScore) × 100
  │
  ├─► Step 3: Calculate participation mark
  │     ├─► Read quiz configuration's participation_criteria
  │     ├─► If criteria says "attempted" → full marks (5.0)
  │     ├─► If percentage >= 80% → 5.0
  │     ├─► If percentage >= 50% → 2.5
  │     └─► Otherwise → 0
  │
  ├─► Step 4: Calculate final grade
  │     final_grade = totalScore + participationMark
  │
  └─► Step 5: Save to database
        Grade::updateOrCreate(
          attempt_id → totalScore, maxScore, percentage,
          participationMark, finalGrade, graded_at = now
        )
  │
  ▼
RESULT: Grade record is created
  │
  ├─► Student sees result page (showResult)
  │     ├─► Score summary (Your Score / Out Of / Percentage)
  │     ├─► Question-by-question review
  │     │     ├─► Correct ✅ (green)
  │     │     ├─► Incorrect ❌ (red, shows correct answer)
  │     │     └─► Skipped ❌ (gray)
  │     └─► Back to Dashboard button
  │
  └─► Lecturer sees performance report (showPerformanceReport)
        ├─► Class statistics cards
        │     ├─► Total Attempts: 30
        │     ├─► Average Score: 71.3
        │     ├─► Highest Score: 95.0
        │     └─► Lowest Score: 32.0
        └─► Student results table
              ├─► Each student's name, score, percentage
              ├─► Participation bonus, final grade
              └─► Pass / Needs Review / Low Score badge
```

### Summary of All Person 4 Deliverables

| # | File | What Changed |
|---|---|---|
| 1 | `app/Http/Controllers/StudentQuizController.php` | Replaced placeholder `gradeQuiz()` with real grading algorithm (lines 450–518) |
| 2 | `app/Http/Controllers/StudentQuizController.php` | Added `calculateParticipationMark()` method (lines 530–552) |
| 3 | `app/Http/Controllers/QuizController.php` | Added `showPerformanceReport()` method (lines 238–253) |
| 4 | `app/Http/Controllers/QuizController.php` | Added `getClassStatistics()` method (lines 258–274) |
| 5 | `routes/web.php` | Added `/quizzes/{quiz}/report` route (line 400) |
| 6 | `resources/views/quizzes/result.blade.php` | Updated to display real grade data with score breakdown and question review |
| 7 | `resources/views/quizzes/performance-report.blade.php` | **Created** — class statistics cards + student results table |

**No new files were created except for the view.** Person 4 integrated all grading logic directly into the existing `StudentQuizController` and `QuizController`, keeping the codebase compact and avoiding unnecessary new classes.

---

*End of document.*
