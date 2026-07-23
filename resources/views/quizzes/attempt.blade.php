@extends('layouts.app')

@section('title', 'Quiz: ' . $quiz->title)

@push('styles')
<style>
    /* Lock the page — prevent scrolling the background */
    body.app-layout {
        overflow: hidden !important;
    }
    /* Full-screen quiz overlay */
    .quiz-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.15);
    }
    .quiz-container {
        background: #fff;
        border-radius: 12px;
        width: 90%;
        max-width: 800px;
        max-height: 95vh;
        overflow-y: auto;
        padding: 32px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }
    .quiz-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }
    .quiz-timer {
        text-align: right;
    }
	.quiz-timer__display {
	        font-size: 36px;
	        font-weight: 700;
	        font-variant-numeric: tabular-nums;
	        color: var(--app-accent);
	        letter-spacing: 2px;
	    }
    .quiz-timer__label {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
    }
    .question-card {
        margin-bottom: 28px;
        padding: 24px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fafafa;
    }
    .question-card.current {
        border-color: #3b82f6;
        background: #fff;
    }
    .question-text {
        font-size: 17px;
        font-weight: 600;
        margin-bottom: 18px;
        line-height: 1.5;
    }
    .question-marks {
        color: #dc2626;
        font-weight: 400;
        font-size: 14px;
        margin-left: 8px;
    }
    .answer-option {
        display: block;
        margin-bottom: 8px;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
    }
    .answer-option:hover {
        border-color: #93c5fd;
        background: #f0f7ff;
    }
    .answer-option input[type="radio"] {
        margin-right: 12px;
    }
    .answer-option.selected {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .quiz-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    .question-dots {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .question-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        background: #fff;
        transition: all 0.15s;
    }
    .question-dot.current {
        border-color: #3b82f6;
        background: #3b82f6;
        color: #fff;
    }
    .question-dot.answered {
        border-color: #16a34a;
        background: #16a34a;
        color: #fff;
    }
    .quiz-lock-notice {
        text-align: center;
        color: #dc2626;
        font-size: 12px;
        margin-top: 20px;
        padding: 10px;
        background: #fef2f2;
        border-radius: 6px;
    }
</style>
@endpush

@section('content')
<div class="quiz-overlay" id="quizOverlay">
    <div class="quiz-container">

        {{-- ========== HEADER: Title + Timer ========== --}}
        <div class="quiz-header">
            <div>
                <h1 style="margin: 0 0 4px 0; font-size: 22px;">{{ $quiz->title }}</h1>
                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                    Question <span id="currentQuestionNum">1</span> of {{ $questions->count() }}
                </p>
            </div>
            <div class="quiz-timer">
                <div class="quiz-timer__display" id="timerDisplay">
                    {{ gmdate('H:i:s', $timeRemaining) }}
                </div>
                <div class="quiz-timer__label">Time Remaining</div>
            </div>
        </div>

        {{-- ========== QUESTIONS ========== --}}
        <form id="quizForm">
            @csrf
            <div id="questionsContainer">
                @foreach ($questions as $index => $question)
                    <div class="question-card {{ $index === 0 ? 'current' : '' }}"
                         data-question-id="{{ $question->question_id }}"
                         style="{{ $index === 0 ? '' : 'display: none;' }}">

                        <div class="question-text">
                            {{ $question->question_text }}
                            <span class="question-marks">
                                ({{ $question->marks }} mark{{ $question->marks > 1 ? 's' : '' }})
                            </span>
                        </div>

                        @foreach ($question->answers as $answer)
                            @php
                                $isSelected = isset($studentAnswers[$question->question_id])
                                    && $studentAnswers[$question->question_id] == $answer->answer_id;
                            @endphp
                            <label class="answer-option {{ $isSelected ? 'selected' : '' }}"
                                   onclick="selectAnswer({{ $question->question_id }}, {{ $answer->answer_id }}, this)">
                                <input type="radio"
                                       name="q_{{ $question->question_id }}"
                                       value="{{ $answer->answer_id }}"
                                       {{ $isSelected ? 'checked' : '' }}>
                                {{ $answer->answer_text }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </form>

        {{-- ========== QUESTION NAVIGATION DOTS ========== --}}
        <div class="question-dots" style="margin-bottom: 16px;">
            @foreach ($questions as $index => $question)
                <div class="question-dot {{ $index === 0 ? 'current' : '' }}"
                     data-index="{{ $index }}"
                     onclick="goToQuestion({{ $index }})">
                    {{ $index + 1 }}
                </div>
            @endforeach
        </div>

        {{-- ========== NAVIGATION BUTTONS ========== --}}
        <div class="quiz-nav">
            <button type="button" id="prevBtn" class="btn btn-secondary"
                    style="display: none;" onclick="prevQuestion()">
                <span class="material-symbols-outlined">arrow_back</span> Previous
            </button>

            <div></div>

            <button type="button" id="nextBtn" class="btn btn-secondary" onclick="nextQuestion()">
                Next <span class="material-symbols-outlined">arrow_forward</span>
            </button>

            <button type="button" id="submitBtn" class="btn btn-success"
                    style="display: none;" onclick="confirmSubmit()">
                <span class="material-symbols-outlined">check_circle</span>
                Submit Quiz
            </button>
        </div>

        {{-- ========== LOCK NOTICE ========== --}}
        @if ($quiz->configuration && $quiz->configuration->lock_screen_on_start)
            <div class="quiz-lock-notice">
                <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">lock</span>
                This quiz is locked. You cannot navigate away or minimize the window.
            </div>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
    // ============================================================
    // STATE
    // ============================================================
    const totalQuestions   = {{ $questions->count() }};
    let currentIndex       = 0;
    let timeRemaining      = {{ $timeRemaining }};
    const quizId           = {{ $quiz->quiz_id }};
    const csrfToken        = '{{ csrf_token() }}';
    const saveAnswerUrl    = '{{ route('quizzes.answer', $quiz->quiz_id) }}';
    const submitUrl        = '{{ route('quizzes.submit', $quiz->quiz_id) }}';
    const autoSubmitUrl    = '{{ route('quizzes.auto-submit', $quiz->quiz_id) }}';

    // Track which questions have been answered (by question_id -> bool)
    const answeredState    = {};

    // ============================================================
    // TIMER
    // ============================================================
    const timerInterval = setInterval(() => {
        timeRemaining--;

        // Update display
        const h = Math.floor(timeRemaining / 3600);
        const m = Math.floor((timeRemaining % 3600) / 60);
        const s = timeRemaining % 60;
        document.getElementById('timerDisplay').textContent =
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');

        // Auto-submit when time expires
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            triggerAutoSubmit();
        }
    }, 1000);

    // ============================================================
    // QUESTION NAVIGATION
    // ============================================================
    function showQuestion(index) {
        const cards = document.querySelectorAll('.question-card');
        const dots  = document.querySelectorAll('.question-dot');

        cards.forEach((card, i) => {
            card.style.display = i === index ? 'block' : 'none';
            card.classList.toggle('current', i === index);
        });

        dots.forEach((dot, i) => {
            dot.classList.toggle('current', i === index);
        });

        document.getElementById('currentQuestionNum').textContent = index + 1;

        // Toggle nav buttons
        document.getElementById('prevBtn').style.display  = index === 0 ? 'none' : 'inline-flex';
        document.getElementById('nextBtn').style.display  = index === totalQuestions - 1 ? 'none' : 'inline-flex';
        document.getElementById('submitBtn').style.display = index === totalQuestions - 1 ? 'inline-flex' : 'none';

        currentIndex = index;
    }

    function nextQuestion() {
        if (currentIndex < totalQuestions - 1) {
            showQuestion(currentIndex + 1);
        }
    }

    function prevQuestion() {
        if (currentIndex > 0) {
            showQuestion(currentIndex - 1);
        }
    }

    function goToQuestion(index) {
        if (index >= 0 && index < totalQuestions) {
            showQuestion(index);
        }
    }

    // ============================================================
    // ANSWER SELECTION (Auto-save on click)
    // ============================================================
    function selectAnswer(questionId, answerId, labelEl) {
        // Update visual state for this question's options
        const card = labelEl.closest('.question-card');
        card.querySelectorAll('.answer-option').forEach(opt => {
            opt.classList.remove('selected');
            opt.querySelector('input[type="radio"]').checked = false;
        });
        labelEl.classList.add('selected');
        labelEl.querySelector('input[type="radio"]').checked = true;

        // Mark the dot as answered
        const questionIndex = Array.from(document.querySelectorAll('.question-card'))
            .findIndex(c => parseInt(c.dataset.questionId) === questionId);
        if (questionIndex !== -1) {
            const dots = document.querySelectorAll('.question-dot');
            dots[questionIndex].classList.add('answered');
        }

        answeredState[questionId] = true;

        // Save to server via AJAX
        fetch(saveAnswerUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                question_id: questionId,
                answer_id: answerId,
            }),
        }).catch(err => console.error('Failed to save answer:', err));
    }

    // ============================================================
    // MANUAL SUBMIT
    // ============================================================
    function confirmSubmit() {
        const unanswered = totalQuestions - Object.keys(answeredState).length;
        let message = 'Are you sure you want to submit your quiz? You cannot change your answers after this.';
        if (unanswered > 0) {
            message = `You have ${unanswered} unanswered question(s). ${message}`;
        }

        if (confirm(message)) {
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = 'Submitting...';

            fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Submission failed.');
                    document.getElementById('submitBtn').disabled = false;
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                document.getElementById('submitBtn').disabled = false;
            });
        }
    }

    // ============================================================
    // AUTO-SUBMIT (Timer expired)
    // ============================================================
    function triggerAutoSubmit() {
        alert('Time is up! Your quiz is being submitted.');

        fetch(autoSubmitUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(() => {
            window.location.href = '{{ route('quizzes.result', $quiz->quiz_id) }}';
        })
        .catch(() => {
            window.location.href = '{{ route('quizzes.result', $quiz->quiz_id) }}';
        });
    }

    // ============================================================
    // LOCK SCREEN: Prevent navigating away
    // ============================================================
    @if ($quiz->configuration && $quiz->configuration->lock_screen_on_start)
        window.addEventListener('beforeunload', (e) => {
            e.preventDefault();
            e.returnValue = 'Quiz is in progress. Leaving will cause auto-submission.';
        });

        // Block keyboard shortcuts that navigate away
        document.addEventListener('keydown', (e) => {
            // Alt+Left/Right, Ctrl+Tab, Ctrl+W, F5, Ctrl+R, Cmd+R
            if (
                (e.altKey && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) ||
                (e.ctrlKey && e.key === 'Tab') ||
                (e.ctrlKey && e.key === 'w') ||
                (e.key === 'F5') ||
                (e.ctrlKey && e.key === 'r')
            ) {
                e.preventDefault();
            }
        });
    @endif

    // ============================================================
    // KEYBOARD SHORTCUTS FOR NAVIGATION
    // ============================================================
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            nextQuestion();
        }
        else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prevQuestion();
        }
    });

    // ============================================================
    // INIT — mark initially answered questions and set button states
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.question-card');
        const dots  = document.querySelectorAll('.question-dot');

        cards.forEach((card, index) => {
            const checked = card.querySelector('input[type="radio"]:checked');
            if (checked) {
                const qId = parseInt(card.dataset.questionId);
                answeredState[qId] = true;
                dots[index].classList.add('answered');
            }
        });

        // Initialize button states for the first question
        showQuestion(0);
    });
</script>
@endpush
