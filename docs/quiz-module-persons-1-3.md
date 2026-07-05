# Quiz & Assessment Module — Persons 1–3 Implementation Summary

> **Generated:** July 2026
> **Scope:** Database layer (Person 1), Lecturer interface (Person 2), Quiz execution & timer (Person 3)
> **Build order dependency:** Person 1 -> Person 2 -> Person 3

---

## Table of Contents

1. [Person 1 — Database Layer (Migrations, Models, Seeder)](#person-1--database-layer)
2. [Person 2 — Lecturer Interface (Controller, Routes, Views)](#person-2--lecturer-interface)
3. [Person 3 — Quiz Execution & Timer (Student Interface)](#person-3--quiz-execution--timer)
4. [All Files Created / Modified](#appendix-all-files-created--modified)

---

## Person 1 — Database Layer

**Role:** Foundation. Person 1 builds everything the other developers depend on. Think of it like laying the foundation and framing of a house — without it, nothing else can be built.

**What Person 1 did:**
- Created 7 database tables (the "shelves" where data lives)
- Created 7 models (the "librarians" that know how to find and connect data)
- Created 1 seeder (a script that fills the tables with sample data for testing)
- Modified 1 existing file (to register the seeder so it runs)

---

### What is a Migration?

A migration is a set of instructions that tells the database what tables to create and what columns each table should have. Think of it like a blueprint for a shelf — it says "this shelf should be 3 feet wide, have 4 slots, and be made of wood." Once the migration runs, the shelf (table) exists in the database and is ready to hold data.

---

### MIGRATION 1: The Quizzes Table

**File:** `database/migrations/2026_07_05_211218_create_quizzes_table.php`
**Table name in database:** `quizzes`
**Primary key:** `quiz_id` (every row gets a unique ID number)

**What this table stores:** One row per quiz. A row represents one quiz — like "Midterm Exam - Laravel Basics" or "Pop Quiz Week 3."

**Columns (the pieces of information stored for each quiz):**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `quiz_id` | Auto-assigned ID number (1, 2, 3...) | 1 |
| `lecturer_id` | Which teacher created this quiz? (links to the users table) | 5 |
| `title` | The name of the quiz | "Midterm - Laravel Basics" |
| `description` | A short explanation (optional) | "Test your knowledge of Laravel fundamentals" |
| `target_category` | Who should see this quiz? | "Student" or "Lecturer" |
| `scheduled_date` | What day does the quiz happen? | 2026-07-10 |
| `start_time` | What time does it start? | 10:00 |
| `duration_minutes` | How many minutes do students get? | 60 |
| `is_active` | Is the quiz currently running? (Yes/No) | false (No) |
| `published_at` | When was it announced to students? (empty until announced) | (null) |
| `created_at` | When was this row created? (auto-filled) | 2026-07-05 21:12:18 |
| `updated_at` | When was this row last changed? (auto-filled) | 2026-07-05 21:12:18 |

**Important design decisions:**

- **`is_active` vs `published_at` are two separate things.** A quiz can be announced (`published_at` has a date) but NOT yet running (`is_active = false`). This lets teachers announce a quiz a week in advance — students see it on their dashboard but can't take it until the scheduled time arrives.
- **`lecturer_id` links to the `users` table.** This is a "foreign key" — it connects the quiz to the teacher who created it. If the teacher's account is deleted, the `onDelete('cascade')` rule automatically deletes all their quizzes too (cleaning up after them).
- **Cascading deletes.** If anyone deletes a quiz from the database, everything connected to it (all its questions, answers, student attempts, and grades) also gets deleted automatically. This prevents "orphaned" data from cluttering up the system.

---

### MIGRATION 2: The Questions Table

**File:** `database/migrations/2026_07_05_211219_create_questions_table.php`
**Table name in database:** `questions`
**Primary key:** `question_id`

**What this table stores:** One row per question. A row represents a single question inside a quiz — like "What is Laravel?" or "True or False: Laravel uses the MVC pattern."

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `question_id` | Auto-assigned ID number | 1 |
| `quiz_id` | Which quiz does this question belong to? (links to quizzes table) | 1 |
| `question_text` | The actual question wording | "What is Laravel?" |
| `question_type` | What kind of question is this? | "MCQ" (multiple choice) or "TF" (true/false) |
| `marks` | How many points is this question worth? | 5 |
| `question_order` | Should this be question #1, #2, #3 in the quiz? | 1 |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **`question_type` is an "enum"** — a list of allowed values. It can only be `MCQ` (Multiple Choice), `TF` (True/False), or `Short` (Short Answer). `Short` is reserved for future use but the column is ready.
- **`marks` lets each question be worth a different number of points.** One question can be 5 marks while another is 10 marks. This lets teachers weight important questions more heavily.
- **`question_order`** determines the order students see the questions. The system automatically assigns the next number when a teacher adds a question.
- **`onDelete('cascade')` on `quiz_id`** means if a quiz is deleted, all its questions vanish too.

---

### MIGRATION 3: The Answers Table

**File:** `database/migrations/2026_07_05_211220_create_answers_table.php`
**Table name in database:** `answers`
**Primary key:** `answer_id`

**What this table stores:** One row per answer option. For a multiple choice question like "What is Laravel?", there would be 3 rows here — one for "A PHP framework" (the correct one), one for "A JavaScript library" (wrong), and one for "A database manager" (wrong).

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `answer_id` | Auto-assigned ID number | 1 |
| `question_id` | Which question does this answer belong to? (links to questions table) | 1 |
| `answer_text` | The answer option text | "A PHP framework" |
| `is_correct` | Is this the right answer? (Yes/No) | true (Yes) |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **`is_correct` is a simple Yes/No flag.** Exactly one answer per question should be marked as correct. When the system grades a quiz, it checks: "Did the student pick the answer where `is_correct = true`?"
- **For True/False questions**, there are exactly 2 answer rows: one with `is_correct = true` (e.g. "True") and one with `is_correct = false` ("False").
- **For Multiple Choice questions**, there can be 3, 4, or even 5 options — but still only one is marked correct.

---

### MIGRATION 4: The Student Attempts Table

**File:** `database/migrations/2026_07_05_211221_create_student_attempts_table.php`
**Table name in database:** `student_attempts`
**Primary key:** `attempt_id`

**What this table stores:** One row every time a student starts a quiz. Think of this as a "session" record — it tracks when the student entered, when they submitted, whether they were late, and whether the system had to force-submit when time ran out.

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `attempt_id` | Auto-assigned ID number | 1 |
| `quiz_id` | Which quiz is this attempt for? (links to quizzes) | 1 |
| `student_id` | Which student is taking it? (links to users) | 10 |
| `start_time` | When did the student click "Start Quiz"? | 2026-07-10 10:00:00 |
| `submit_time` | When did they submit? (empty until they submit) | 2026-07-10 10:45:00 |
| `is_auto_submit` | Was this auto-submitted when time expired? | false |
| `is_late` | Did this student start after the scheduled time? | false |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **`submit_time` starts empty (NULL).** As long as it's empty, the student is still taking the quiz. The moment it gets a timestamp, the attempt is closed — no more answers can be saved.
- **`is_auto_submit`** lets the system distinguish between "the student clicked Submit" (manual) and "the timer ran out and the system submitted for them" (auto). This is useful for reporting and analytics.
- **`is_late`** marks students who joined after the scheduled start time. If the lecturer disabled late joining in the quiz settings, these students can't even start.

---

### MIGRATION 5: The Student Answers Table

**File:** `database/migrations/2026_07_05_211222_create_student_answers_table.php`
**Table name in database:** `student_answers`
**Primary key:** `id` (a simple auto-increment number)

**What this table stores:** One row per answer a student gives during a quiz. It records "For question #1, the student selected answer #3." If the student changes their answer, the old row is deleted and a new one is created.

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `id` | Auto-assigned ID number | 1 |
| `attempt_id` | Which attempt does this belong to? (links to student_attempts) | 1 |
| `question_id` | Which question was answered? (links to questions) | 1 |
| `selected_answer_id` | Which answer option did the student pick? (links to answers, or NULL if skipped) | 3 |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **`selected_answer_id` can be NULL.** This means the student didn't answer the question (they skipped it). When grading happens, this counts as 0 marks.
- **`onDelete('set null')`** is a safety feature. If a teacher deletes an answer option (e.g. to fix a typo), the student's record doesn't get deleted — instead, `selected_answer_id` just becomes NULL. This prevents accidentally wiping out a student's quiz history.

---

### MIGRATION 6: The Grades Table

**File:** `database/migrations/2026_07_05_211223_create_grades_table.php`
**Table name in database:** `grades`
**Primary key:** `grade_id`

**What this table stores:** One row per completed quiz attempt, containing the final score. This is the "report card." Person 4 (Grading & Analytics) writes to this table; Person 3 (Quiz Result View) reads from it.

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `grade_id` | Auto-assigned ID number | 1 |
| `attempt_id` | Which attempt is this grade for? (links to student_attempts) | 1 |
| `student_id` | Which student earned this grade? (links to users) | 10 |
| `quiz_id` | Which quiz was this for? (links to quizzes) | 1 |
| `total_score` | Points the student earned | 45.50 |
| `max_score` | Total possible points | 100.00 |
| `percentage` | Calculated percentage (total/max * 100) | 45.50 |
| `participation_mark` | Bonus points awarded for participation | 2.50 |
| `final_grade` | Total score + participation bonus | 48.00 |
| `graded_at` | When was grading calculated? | 2026-07-10 10:45:05 |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **Scores are `decimal(5,2)`** — numbers like 45.50 or 100.00 (up to 999.99). This allows half-marks and decimal scores.
- **`final_grade`** is separate from `total_score` because it includes the participation bonus. A student who scored 45.50 might get a final grade of 48.00 because they earned a 2.50 participation bonus for attending.
- **`graded_at`** is an audit trail timestamp so teachers can see exactly when grades were calculated.

---

### MIGRATION 7: The Quiz Configuration Table

**File:** `database/migrations/2026_07_05_211224_create_quiz_configuration_table.php`
**Table name in database:** `quiz_configuration`
**Primary key:** `config_id`

**What this table stores:** Settings for each quiz — the "rules of the game." Things like: Can students join late? Should we lock the screen? Should we show answers after the quiz ends? Each quiz has exactly one configuration row.

**Columns:**

| Column Name | What It Holds | Example Value |
|---|---|---|
| `config_id` | Auto-assigned ID number | 1 |
| `quiz_id` | Which quiz is this configuration for? (links to quizzes, but UNIQUE — one per quiz) | 1 |
| `allow_late_join` | Can students start after the scheduled time? | false (No) |
| `notification_minutes_before` | How many minutes before start should we send a reminder? | 15 |
| `participation_criteria` | How should the system award participation marks? (text description) | "Full marks if score >= 80%, half marks if score >= 50%" |
| `lock_screen_on_start` | Should the quiz lock the browser so students can't navigate away? | true (Yes) |
| `show_results_after_close` | Should students see a results report after the quiz ends? | true (Yes) |
| `show_correct_answers` | Should students be shown which answers were correct? | false (No) |
| `created_at` | Auto-filled timestamp | |
| `updated_at` | Auto-filled timestamp | |

**Important design decisions:**

- **`->unique()` on `quiz_id`** enforces a rule: one quiz = one configuration. You CANNOT have two configuration rows for the same quiz. This prevents conflicting settings.
- **`lock_screen_on_start`** is a security/anti-cheating feature. When enabled, the quiz interface fills the entire screen, disables the back button, blocks keyboard shortcuts like Alt+Tab and F5, and shows a warning if the student tries to leave.
- **`show_correct_answers`** is separate from `show_results_after_close`. A teacher might want students to see their score (percentage) but NOT the correct answers — this gives them control over that.

---

### Understanding Model Relationships (The "Librarians")

Now that we have the database tables (the shelves), we need models (the librarians). A model is a PHP class that knows how to find, connect, and work with data from a specific table.

Each model has **relationships** that describe how its data connects to other tables. These are the most important part because they let us write code like `$quiz->questions` to get all questions for a quiz, instead of writing complicated database queries.

---

#### MODEL 1: `app/Models/Quiz.php`

**Connects to table:** `quizzes`

**What it represents:** A single quiz.

**Its relationships (how it connects to other data):**

```
                     ┌───────────────────────────────────────┐
                     │              QUIZ                      │
                     │  (e.g. "Midterm - Laravel Basics")     │
                     └───────────────────────────────────────┘
                              │              │
            ┌─────────────────┤              ├─────────────────┐
            │                 │              │                 │
            ▼                 ▼              ▼                 ▼
    ┌──────────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────────┐
    │  LECTURER    │  │  QUESTIONS   │  │ ATTEMPTS │  │ CONFIGURATION│
    │ (User who    │  │ (all Qs in   │  │ (students│  │ (settings    │
    │  created it) │  │  this quiz)  │  │  taking  │  │  for this    │
    │              │  │              │  │  it)     │  │  quiz)       │
    └──────────────┘  └──────────────┘  └──────────┘  └──────────────┘
                                                    (exactly ONE)
                              │
                              ▼
                      ┌──────────────┐
                      │   GRADES     │
                      │ (scores for  │
                      │  this quiz)  │
                      └──────────────┘
```

**Relationship 1: `lecturer()` — "A quiz belongs to a lecturer"**
- **Type:** BelongsTo (the quiz points to one user)
- **How it works:** The `lecturer_id` column in the quizzes table stores a user's ID. When you call `$quiz->lecturer`, Laravel automatically looks up the user with that ID and returns their full record (name, email, etc.).
- **Real-world example:** Quiz #5 has `lecturer_id = 3`. Calling `$quiz->lecturer->name` returns "Dr. Smith." This tells you who created the quiz.
- **Why it matters:** Only the person who created a quiz can edit, delete, or publish it.

**Relationship 2: `questions()` — "A quiz has many questions"**
- **Type:** HasMany (one quiz can have several questions)
- **How it works:** The `questions` table has a `quiz_id` column. Laravel finds all questions where `quiz_id` matches this quiz's ID and returns them as a collection.
- **Real-world example:** `$quiz->questions` returns a list like: [Question 1: "What is Laravel?", Question 2: "True or False: Laravel uses MVC", ...].
- **Why it matters:** The quiz interface needs to display all questions. Grading needs to check every question's correct answer. This relationship provides that connection.

**Relationship 3: `attempts()` — "A quiz has many student attempts"**
- **Type:** HasMany (one quiz can have many students attempting it)
- **How it works:** The `student_attempts` table has a `quiz_id` column. Laravel finds all attempts where `quiz_id` matches.
- **Real-world example:** If 30 students take the quiz, `$quiz->attempts` returns 30 records, each showing when a student started, submitted, and whether they were late.
- **Why it matters:** The lecturer's performance report needs this to calculate class averages and see who took the quiz.

**Relationship 4: `configuration()` — "A quiz has one configuration"**
- **Type:** HasOne (exactly one configuration per quiz)
- **How it works:** The `quiz_configuration` table has a `quiz_id` column that is marked as UNIQUE — meaning only one config row can exist per quiz.
- **Real-world example:** `$quiz->configuration->lock_screen_on_start` returns `true` or `false`, telling the interface whether to lock the browser during the quiz.
- **Why it matters:** Every part of the quiz system needs to check these settings. Should late joiners be allowed? Should results be shown? This relationship makes those checks simple.

**Relationship 5: `grades()` — "A quiz has many grades"**
- **Type:** HasMany (one quiz can have many grades — one per student who completed it)
- **How it works:** The `grades` table has a `quiz_id` column. Laravel finds all grades where `quiz_id` matches.
- **Real-world example:** `$quiz->grades` returns a list of all scores for this quiz, which the performance report uses to calculate averages, highs, and lows.
- **Why it matters:** The class statistics feature needs this to show "Average score: 72%, Highest: 95%, Lowest: 40%."

---

#### MODEL 2: `app/Models/Question.php`

**Connects to table:** `questions`

**What it represents:** A single question inside a quiz.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │                  QUESTION                   │
              │  (e.g. "What is Laravel?")                  │
              └─────────────────────────────────────────────┘
                           │                   │
                           │                   │
                           ▼                   ▼
              ┌──────────────────────┐  ┌──────────────────┐
              │        QUIZ         │  │     ANSWERS      │
              │ (the quiz this      │  │ (the options     │
              │  question lives in)  │  │  students can    │
              │                     │  │  choose from)    │
              └──────────────────────┘  └──────────────────┘
                                                 │
                                                 ▼
                                         ┌──────────────────┐
                                         │  CORRECT ANSWER  │
                                         │ (the one where   │
                                         │  is_correct=true)│
                                         └──────────────────┘
```

**Relationship 1: `quiz()` — "A question belongs to a quiz"**
- **Type:** BelongsTo
- **What it does:** Points back to the quiz that contains this question.
- **Real-world example:** `$question->quiz->title` returns the name of the quiz this question is part of.
- **Why it matters:** If you're looking at a question and need to check the quiz's settings (like "is this quiz published?"), this relationship lets you do that.

**Relationship 2: `answers()` — "A question has many answers"**
- **Type:** HasMany
- **What it does:** Returns all the answer options for this question.
- **Real-world example:** For an MCQ, `$question->answers` returns 3 or 4 options like ["A PHP framework", "A JavaScript library", "A database manager"]. For a True/False, it returns ["True", "False"].
- **Why it matters:** When displaying a question to a student, the system needs all the answer options. When grading, it needs to compare the student's choice against the correct one.

**Relationship 3: `correctAnswer()` — "A question has one correct answer"**
- **Type:** Custom method (not a standard relationship)
- **What it does:** Looks through all the question's answers and returns ONLY the one where `is_correct = true`.
- **Real-world example:** `$question->correctAnswer()->answer_text` returns "A PHP framework" — the right answer.
- **Why it matters:** The grading system calls this for every question to figure out: "Did the student pick the right one?" It's the core of the scoring algorithm.

---

#### MODEL 3: `app/Models/Answer.php`

**Connects to table:** `answers`

**What it represents:** One answer option for a question.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │                  ANSWER                     │
              │  (e.g. "A PHP framework")                   │
              └─────────────────────────────────────────────┘
                              │
                              │
                              ▼
              ┌─────────────────────────────────────────────┐
              │                 QUESTION                    │
              │  (the question this answer belongs to)      │
              └─────────────────────────────────────────────┘
```

**Relationship 1: `question()` — "An answer belongs to a question"**
- **Type:** BelongsTo
- **What it does:** Points to the question this answer is an option for.
- **Real-world example:** `$answer->question->question_text` returns "What is Laravel?" — the question this answer belongs to.
- **Why it matters:** When a student selects an answer, the system needs to know which question they're answering. This relationship provides that link.

---

#### MODEL 4: `app/Models/StudentAttempt.php`

**Connects to table:** `student_attempts`

**What it represents:** One student's attempt at taking one quiz.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │          STUDENT ATTEMPT                    │
              │  (Student #10 taking Quiz #1)               │
              └─────────────────────────────────────────────┘
                    │           │          │        │
                    │           │          │        │
                    ▼           ▼          ▼        ▼
              ┌────────┐ ┌──────────┐ ┌────────┐ ┌───────┐
              │  QUIZ  │ │ STUDENT  │ │STUDENT │ │ GRADE │
              │ (which │ │ (User)   │ │ANSWERS │ │(score)│
              │ quiz?) │ │ who took │ │(their  │ │       │
              │        │ │  it?     │ │choices)│ │       │
              └────────┘ └──────────┘ └────────┘ └───────┘
```

**Relationship 1: `quiz()` — "An attempt belongs to a quiz"**
- **Type:** BelongsTo
- **What it does:** Points to the quiz the student is taking.
- **Why it matters:** When grading an attempt, the system needs to load the quiz's questions and correct answers. This relationship connects the two.

**Relationship 2: `student()` — "An attempt belongs to a student"**
- **Type:** BelongsTo (the link goes to the `users` table via `student_id`)
- **What it does:** Points to the user record of the student who took the quiz.
- **Real-world example:** `$attempt->student->full_name` returns "John Doe."
- **Why it matters:** The performance report needs to show each student's name next to their score.

**Relationship 3: `answers()` — "An attempt has many student answers"**
- **Type:** HasMany
- **What it does:** Returns all the individual answers the student gave during this attempt.
- **Real-world example:** If a quiz has 10 questions, `$attempt->answers` returns up to 10 records showing what the student chose for each one.
- **Why it matters:** The grading algorithm loops through these to calculate the score. The result page displays them so the student can review their choices.

**Relationship 4: `grade()` — "An attempt has one grade"**
- **Type:** HasOne (at most one grade per attempt)
- **What it does:** Returns the score record for this attempt (or NULL if not yet graded).
- **Real-world example:** `$attempt->grade->final_grade` returns 48.00 — the total score after adding participation bonus.
- **Why it matters:** The result page needs to display the grade. If it's NULL, we show "Grading in progress."

---

#### MODEL 5: `app/Models/StudentAnswer.php`

**Connects to table:** `student_answers`

**What it represents:** One answer a student selected for one question during one attempt.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │            STUDENT ANSWER                   │
              │  (Student chose Answer #3 for Question #1)  │
              └─────────────────────────────────────────────┘
                          │         │            │
                          │         │            │
                          ▼         ▼            ▼
              ┌──────────────┐ ┌──────────┐ ┌───────────┐
              │   ATTEMPT    │ │ QUESTION │ │ SELECTED │
              │ (which       │ │ (which   │ │  ANSWER  │
              │  attempt?)   │ │ question?)│ │ (what    │
              │              │ │          │ │  they    │
              │              │ │          │ │  picked) │
              └──────────────┘ └──────────┘ └───────────┘
```

**Relationship 1: `attempt()` — "A student answer belongs to an attempt"**
- **Type:** BelongsTo
- **What it does:** Points to the attempt this answer is part of.
- **Why it matters:** Groups answers by attempt so the grading system knows which answers belong together.

**Relationship 2: `question()` — "A student answer references a question"**
- **Type:** BelongsTo
- **What it does:** Points to the question being answered.
- **Why it matters:** The result page needs to show the question text next to the student's answer.

**Relationship 3: `selectedAnswer()` — "A student answer references the option they chose"**
- **Type:** BelongsTo
- **What it does:** Points to the specific answer option the student picked.
- **Real-world example:** `$studentAnswer->selectedAnswer->answer_text` returns "A PHP framework" — what the student actually chose.
- **Why it matters:** The grading system compares this against the correct answer. The result page displays the student's choice.

---

#### MODEL 6: `app/Models/Grade.php`

**Connects to table:** `grades`

**What it represents:** A completed score record for one student on one quiz.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │                 GRADE                       │
              │  (Score: 45.50/100.00 = 45.50%)             │
              └─────────────────────────────────────────────┘
                        │          │           │
                        │          │           │
                        ▼          ▼           ▼
              ┌──────────────┐ ┌────────┐ ┌──────────┐
              │   ATTEMPT    │ │STUDENT │ │  QUIZ    │
              │ (which       │ │(User)  │ │ (which   │
              │  attempt?)   │ │ who    │ │  quiz?)  │
              │              │ │ earned │ │          │
              │              │ │  it?   │ │          │
              └──────────────┘ └────────┘ └──────────┘
```

**Relationship 1: `attempt()` — "A grade belongs to an attempt"**
- **Type:** BelongsTo
- **What it does:** Points to the attempt this grade is for.
- **Why it matters:** Links the score to the specific attempt so we can also see timing details (when they started, when they submitted).

**Relationship 2: `student()` — "A grade belongs to a student"**
- **Type:** BelongsTo (links to `users`)
- **What it does:** Points to the student who earned this grade.
- **Why it matters:** The performance report shows each student's name next to their grade, sorted from highest to lowest.

**Relationship 3: `quiz()` — "A grade belongs to a quiz"**
- **Type:** BelongsTo
- **What it does:** Points to the quiz this grade is for.
- **Why it matters:** Makes it easy to get all grades for a quiz (for class statistics) without going through the attempt table.

---

#### MODEL 7: `app/Models/QuizConfiguration.php`

**Connects to table:** `quiz_configuration`

**What it represents:** The settings/rules for one quiz.

**Its relationships:**

```
              ┌─────────────────────────────────────────────┐
              │         QUIZ CONFIGURATION                  │
              │  (Settings for "Midterm - Laravel Basics")  │
              └─────────────────────────────────────────────┘
                              │
                              │
                              ▼
              ┌─────────────────────────────────────────────┐
              │                  QUIZ                       │
              │  (the quiz these settings belong to)        │
              └─────────────────────────────────────────────┘
```

**Relationship 1: `quiz()` — "A configuration belongs to a quiz"**
- **Type:** BelongsTo
- **What it does:** Points to the quiz this configuration belongs to.
- **Real-world example:** `$config->quiz->title` returns the quiz name.
- **Why it matters:** When you're looking at a configuration record and need to check the quiz details (like the scheduled date), this relationship connects them.

---

### THE SEEDER: `database/seeders/QuizSeeder.php`

A seeder is a script that fills the database with sample data for testing. Think of it like a "demo mode" — it creates pretend quizzes and questions so developers can test their code without having to manually create everything.

**What this seeder creates:**

1. **A lecturer role** — Finds or creates a role called "Lecturer" in the roles table.
2. **A student group** — Finds or creates a default student group.
3. **A lecturer user** — Creates a test teacher account:
   - Email: `lecturer@example.com`
   - Password: `password`
   - Role: Lecturer
4. **One sample quiz** — "Laravel Basics Quiz":
   - Scheduled for tomorrow at 10:00 AM
   - 30 minutes long
   - Targeted at "Student" role
   - Not yet active or published
5. **Quiz configuration** — Settings for the sample quiz:
   - Late joining: OFF
   - Reminder: 15 minutes before
   - Lock screen: ON
   - Show results after close: YES
   - Show correct answers: YES
6. **Question 1** (MCQ, 5 marks): "What is Laravel?" with 3 options:
   - "A PHP framework" (CORRECT)
   - "A JavaScript library"
   - "A database manager"
7. **Question 2** (True/False, 5 marks): "Laravel uses the MVC pattern." with 2 options:
   - "True" (CORRECT)
   - "False"

**How to use it:** Run `php artisan db:seed --class=QuizSeeder` to populate the database with this sample data. All developers can then log in as `lecturer@example.com` / `password` and see a quiz ready to work with.

---

### MODIFIED FILE: `database/seeders/DatabaseSeeder.php`

This file was already the "master list" of seeders — it runs all seeders in order. We added `QuizSeeder::class` to the list so that when someone runs `php artisan db:seed` (without specifying a class), it automatically includes our quiz sample data.

**The order now is:**
1. RoleSeeder (creates roles like Admin, Student, Lecturer)
2. GroupSeeder (creates groups)
3. SuperAdminSeeder (creates the super admin account)
4. TopicCategorySeeder (creates forum topic categories)
5. **QuizSeeder** (creates sample quiz data) <-- this was added

---

## Person 2 — Lecturer Interface

**Role:** Build the screens and logic that teachers use to create, configure, and publish quizzes. Everything a lecturer sees and clicks to set up a quiz comes from Person 2's work.

**What Person 2 did:**
- Created 3 controllers (the logic that processes form submissions and decides what to do)
- Created 3 view files (the HTML pages teachers see in their browser)
- Added 11 routes to the web.php file (the URL addresses for each page)

---

### CONTROLLER: `app/Http/Controllers/QuizController.php`

This is the main controller for lecturer quiz management. It has 7 actions (methods), each handling one thing a teacher can do.

#### `index()` — Show all quizzes

**URL:** `GET /quizzes`
**Route name:** `quizzes.index`

**What it does:** When a teacher visits the "My Quizzes" page, this method:
1. Looks up all quizzes created by the currently logged-in teacher
2. Loads each quiz's configuration settings (so we can show status badges)
3. Orders them newest-first
4. Shows 10 quizzes per page (paginated)
5. Passes the data to the `quizzes.index` view

**Real-world scenario:** Dr. Smith logs in and clicks "My Quizzes." She sees a table with her 5 quizzes, their scheduled dates, status (Draft or Published), and Edit/Delete buttons.

#### `create()` — Show the quiz creation form

**URL:** `GET /quizzes/create`
**Route name:** `quizzes.create`

**What it does:** Simply displays the blank quiz creation form. No database work — just shows the HTML form.

#### `store()` — Save a new quiz to the database

**URL:** `POST /quizzes`
**Route name:** `quizzes.store`

**What it does:** When a teacher fills out the create form and clicks "Create Quiz":
1. **Validates** the input — checks that:
   - Title is provided and not too long
   - Description is optional but within 1000 characters
   - Target category is one of: Student, Lecturer, Administrator, Member
   - Scheduled date is today or in the future (can't schedule in the past)
   - Start time is in HH:MM format (e.g. 10:30)
   - Duration is between 1 and 480 minutes (8 hours max)
2. **Creates a Quiz record** with the validated data
3. **Creates a QuizConfiguration record** with sensible defaults:
   - Late joining: OFF
   - Screen lock: ON
   - Show results: ON
   - Show correct answers: OFF
4. **Redirects** the teacher to the edit page for the new quiz, with a success message: "Quiz created! Now add questions."

**Real-world scenario:** Dr. Smith fills in "Midterm Exam" with a date of tomorrow, 10:00 AM, 60 minutes, targeted at Students. She clicks "Create Quiz" and is taken to the edit page where she can add questions.

#### `edit()` — Show the quiz editing page

**URL:** `GET /quizzes/{quiz}/edit`
**Route name:** `quizzes.edit`

**What it does:**
1. **Checks authorization** — only the quiz creator can edit it (if someone else tries, they get a 403 error)
2. **Loads the quiz** along with all its questions (in order), each question's answers, and the configuration settings
3. **Displays** the edit page with a form on the left (quiz details + questions) and a sidebar on the right (quiz info + publish/delete buttons)

#### `update()` — Save changes to an existing quiz

**URL:** `PUT /quizzes/{quiz}`
**Route name:** `quizzes.update`

**What it does:**
1. **Checks authorization** — only the quiz creator
2. **Checks if published** — if the quiz has already been announced (`published_at` is set), editing is BLOCKED. This is a safety rule: once students have seen the announcement, changing the date or time would be unfair.
3. **Validates input** — same rules as create (date must be today or future, duration 1–480 min, etc.)
4. **Updates the quiz** record with new title, description, target, date, time, duration
5. **Updates the configuration** record with new checkbox settings (lock screen, show results, etc.)
6. **Redirects back** with "Quiz updated" success message

#### `destroy()` — Delete a quiz

**URL:** `DELETE /quizzes/{quiz}`
**Route name:** `quizzes.destroy`

**What it does:**
1. **Checks authorization** — only the quiz creator
2. **Checks if published** — published quizzes CANNOT be deleted (safety rule)
3. **Deletes the quiz** — because of cascading deletes, this automatically deletes all questions, answers, attempts, and grades for this quiz
4. **Redirects** to the quizzes list with "Quiz deleted" message

#### `publish()` — Announce a quiz to students

**URL:** `POST /quizzes/{quiz}/publish`
**Route name:** `quizzes.publish`

**What it does:**
1. **Checks authorization** — only the quiz creator
2. **Checks if already published** — can't publish twice
3. **Checks that the quiz has at least 1 question** — no empty quizzes allowed
4. **Checks that the date/time is in the future** — can't announce a quiz that was scheduled for yesterday
5. **Sets `published_at`** to the current timestamp — this is the moment the quiz becomes visible to students
6. **Logs the event** — currently writes to the log file. (In the future, Person 5 will add actual notifications here)
7. **Redirects back** with "Quiz published! Students have been notified."

**Important distinction:** Publishing is NOT the same as making the quiz active. Publishing = announcing to students. The quiz doesn't go live until Person 5's scheduled task activates it at the set time.

---

### CONTROLLER: `app/Http/Controllers/QuestionController.php`

This controller handles adding and removing questions from a quiz.

#### `store()` — Add a question

**URL:** `POST /quizzes/{quiz}/questions`
**Route name:** `quizzes.questions.store`

**What it does:**
1. Checks the current teacher owns the quiz and it's not published
2. Validates the question text, type (MCQ or TF), and marks
3. Auto-calculates the next order number (e.g. if the highest existing order is 3, the new question becomes #4)
4. Creates the question record
5. Redirects back with "Question added" message

#### `destroy()` — Delete a question

**URL:** `DELETE /questions/{question}`
**Route name:** `questions.destroy`

**What it does:**
1. Checks ownership and unpublished status
2. Deletes the question (cascade deletes its answer options too)
3. Redirects back with "Question deleted" message

---

### CONTROLLER: `app/Http/Controllers/AnswerController.php`

This controller handles adding and removing answer options for a question.

#### `store()` — Add an answer option

**URL:** `POST /questions/{question}/answers`
**Route name:** `answers.store`

**What it does:**
1. Checks the current teacher owns the quiz (walks through question -> quiz -> lecturer_id)
2. Validates answer text and whether it's marked as correct
3. Creates the answer record
4. Redirects back with "Answer added" message

#### `destroy()` — Delete an answer option

**URL:** `DELETE /answers/{answer}`
**Route name:** `answers.destroy`

**What it does:**
1. Checks ownership
2. Deletes the answer
3. Redirects back with "Answer deleted" message

---

### VIEW: `resources/views/quizzes/create.blade.php`

**What the teacher sees:** A clean form page with the heading "Create New Quiz."

**The form contains:**

1. **Quiz Title** — A text box. Required. Placeholder says "E.g., Midterm Exam - Laravel Basics."
2. **Description** — A multi-line text area. Optional.
3. **Who takes this quiz?** — A dropdown menu. Options: "Students Only," "Lecturers Only," "All Members." A small note appears below: "Only users with this role will see the quiz announcement."
4. **Scheduling section** — Three inputs side by side:
   - **Date** — A date picker
   - **Start Time** — A time picker with note "HH:MM (24-hour)"
   - **Duration (minutes)** — A number input with note "1 minute to 8 hours"
5. **Buttons at the bottom:** "Cancel" (takes you back to the quiz list) and "Create Quiz" (submits the form)

**If validation fails:** Error messages appear in red below the relevant field. For example, if the teacher forgets to enter a title, a message says "The title field is required."

---

### VIEW: `resources/views/quizzes/edit.blade.php`

**What the teacher sees:** A two-column page. The left column (wider) has quiz settings and questions. The right column (narrower) is a sticky sidebar with quiz info and publish/delete buttons.

**Left column — Quiz Details section:**

A form with the quiz's current values pre-filled:

1. **Title** — Text box with current title
2. **Date, Start Time, Duration** — Three fields side by side
3. **Quiz Settings** — Four checkboxes with labels:
   - "Lock screen during quiz (prevent cheating)"
   - "Show results after quiz closes"
   - "Show correct answers with results"
   - "Allow late joiners (but no extra time)"
4. **Participation Criteria** — A text area where the teacher types how participation marks should work (e.g. "Full marks if attempted and score >= 50%")
5. **"Save Changes" button**

**Left column — Questions section:**

Shows the current question count. If no questions exist, it shows "No questions yet. Add one below." Otherwise, each question appears as a card showing:
- The question number and text (e.g. "Q1: What is Laravel?")
- The type badge (e.g. "MCQ" or "TF")
- The marks
- A list of answer options with a checkmark (correct) or circle (incorrect)
- Each answer has a small "x" delete button
- An inline form to add a new answer option (text input + "Correct?" checkbox + "Add" button)
- A "Delete" button for the entire question

Below the existing questions, there's a dashed-border box titled "Add New Question" with:
- A text area for the question
- A type dropdown (MCQ / True-False)
- A marks input (default 1)
- An "Add Question" button

**Right column — Sidebar:**

A sticky card showing:
- **Scheduled:** Date and time (formatted nicely like "Jul 10, 2026 @ 10:00")
- **Duration:** In minutes
- **Target:** The role group (e.g. "Students")
- **Status:** "Published" (green badge with "Announced 2 hours ago") or "Draft" (yellow badge with "Not yet announced")
- **Publish button** — green, full-width, shown only if the quiz is a draft AND has at least one question. If it's a draft but has no questions, it shows "Add at least 1 question before publishing" in red.
- **Delete Quiz button** — red, shown only if draft

---

### VIEW: `resources/views/quizzes/index.blade.php`

**What the teacher sees:** The main "My Quizzes" dashboard page.

**If no quizzes exist:** A friendly empty state with a quiz icon, "No quizzes yet" heading, "Create your first quiz to get started" message, and a "Create Quiz" button.

**If quizzes exist:** A table with rows for each quiz:

| Column | What it shows |
|---|---|
| Title | Quiz name in bold |
| Target | A badge showing the role (e.g. "Student") |
| Scheduled | Formatted date and time (e.g. "Jul 10, 2026 @ 10:00") |
| Duration | Number of minutes (e.g. "30 min") |
| Questions | Count of questions in the quiz |
| Status | Green "Published" badge or yellow "Draft" badge |
| Actions | "Edit" button (always), "Delete" button (only if draft) |

Below the table: pagination links (e.g. "1 2 3 ... Next").

A "Create Quiz" button is at the top right of the page.

---

### ROUTES ADDED: `routes/web.php`

Routes are like the address book of the application. They tell Laravel: "When someone visits THIS URL, run THAT controller method."

**Student routes** (lines 341–384, inside `auth` middleware — must be logged in):

| URL | What it does | Controller Method |
|---|---|---|
| `GET /quizzes/{quiz}/announcement` | Shows the pre-quiz landing page | `StudentQuizController@showAnnouncement` |
| `GET /quizzes/{quiz}/attempt` | Shows the live quiz interface | `StudentQuizController@showQuiz` |
| `POST /quizzes/{quiz}/answer` | Saves one answer (AJAX) | `StudentQuizController@saveAnswer` |
| `POST /quizzes/{quiz}/submit` | Manual quiz submission | `StudentQuizController@submitQuiz` |
| `POST /quizzes/{quiz}/auto-submit` | Auto-submit on timeout | `StudentQuizController@autoSubmit` |
| `GET /quizzes/{quiz}/status` | Returns JSON quiz status (polled by JS) | `StudentQuizController@getStatus` |
| `GET /quizzes/{quiz}/result` | Shows the result page | `StudentQuizController@showResult` |

**Lecturer routes** (lines 390–406, inside `auth` middleware):

| URL | What it does | Controller Method |
|---|---|---|
| `GET /quizzes` | Lists all quizzes | `QuizController@index` |
| `GET /quizzes/create` | Shows create form | `QuizController@create` |
| `POST /quizzes` | Saves new quiz | `QuizController@store` |
| `GET /quizzes/{quiz}/edit` | Shows edit form | `QuizController@edit` |
| `PUT /quizzes/{quiz}` | Updates quiz | `QuizController@update` |
| `DELETE /quizzes/{quiz}` | Deletes quiz | `QuizController@destroy` |
| `POST /quizzes/{quiz}/publish` | Publishes/announces quiz | `QuizController@publish` |
| `POST /quizzes/{quiz}/questions` | Adds question | `QuestionController@store` |
| `DELETE /questions/{question}` | Deletes question | `QuestionController@destroy` |
| `POST /questions/{question}/answers` | Adds answer option | `AnswerController@store` |
| `DELETE /answers/{answer}` | Deletes answer option | `AnswerController@destroy` |

---

## Person 3 — Quiz Execution & Timer

**Role:** Build the student's quiz-taking experience. This is the actual exam screen — the full-screen locked interface with the countdown timer, question navigation, and auto-submit. Everything a student sees and interacts with during a quiz comes from Person 3.

**What Person 3 did:**
- Created 1 controller (the logic that manages quiz sessions, timing, and submissions)
- Created 3 view files (the HTML pages students see before, during, and after a quiz)
- Added 7 routes to web.php

---

### CONTROLLER: `app/Http/Controllers/StudentQuizController.php`

This is the heart of the quiz-taking experience. It has 7 public methods (actions the student can take) and 2 private helpers.

---

#### `showAnnouncement()` — The "Before Quiz" page

**URL:** `GET /quizzes/{quiz}/announcement`
**Route name:** `quizzes.announcement`

**What this method does — step by step:**

1. **Check who the student is.** Gets the current user's role (e.g. "Student").
2. **Check if they're allowed to see this quiz.** If the quiz is targeted at "Lecturer" but the user is a "Student," they get a 403 error — this quiz isn't for them.
3. **Check if the quiz has been published.** If the lecturer hasn't published it yet (no `published_at`), it returns a 404 — the announcement doesn't exist yet.
4. **Calculate the timing.** It figures out:
   - When the quiz is scheduled to start (date + time combined)
   - How many seconds until that time (negative number if it's already past)
   - Whether the quiz has already started
5. **Format the time** into something human-readable like "2h 30m 0s."
6. **Pass all this info to the view** so it can show: the countdown, the Join button (if started), or the "Time passed" message.

**Real-world scenario:** Jane logs in and clicks on a quiz announcement. She sees the quiz title, description, date/time, duration, and a countdown saying "Quiz starts in 00:15:30." As the timer ticks down, when it reaches zero, the page refreshes and shows a "Join Quiz Now" button.

---

#### `showQuiz()` — The Active Quiz Screen

**URL:** `GET /quizzes/{quiz}/attempt`
**Route name:** `quizzes.attempt`

**What this method does — step by step:**

1. **Role check** — Same as above. Wrong role = 403 error.
2. **Is the quiz active?** If the quiz is NOT yet active:
   - If the scheduled time hasn't arrived, redirect to announcement page with "Quiz has not started yet."
   - If the scheduled time has passed, redirect with "Quiz time has passed."
3. **Late join check** — If the student is late AND the quiz config says `allow_late_join = false`, redirect with "Late joining is not allowed."
4. **Find or create an attempt**:
   - If the student already started the quiz before (e.g. they refreshed the page), load their existing attempt record
   - If they're starting for the first time, create a new `StudentAttempt` record with:
     - Their user ID
     - The current time as `start_time`
     - Whether they're late
     - `submit_time` set to NULL (not submitted yet)
5. **Already submitted?** If the attempt already has a `submit_time`, redirect to the results page (can't retake).
6. **Load questions** — Get all questions ordered by `question_order`, each with their answer options.
7. **Load existing answers** — If the student had previously saved answers (e.g. they refreshed), load those too so their selections are restored.
8. **Calculate time remaining** — Duration in seconds minus time elapsed since `start_time`. If time has already expired, call `autoSubmit()` immediately.
9. **Return the view** with all this data, including `timeRemaining` in seconds.

**Security features:**
- Role-based access control
- Active-only gate — can't take a quiz that isn't running
- Late-join enforcement
- One attempt per student (redirects to results if already submitted)
- Auto-submit if time has expired before they even load the page

---

#### `saveAnswer()` — Save One Answer (AJAX)

**URL:** `POST /quizzes/{quiz}/answer`
**Route name:** `quizzes.answer`

**What it does:** This is called by JavaScript every time a student clicks on an answer option. It saves the selection to the database immediately — not just when they click "Submit."

**Flow:**
1. Find the student's attempt for this quiz
2. If already submitted, return an error (can't change answers after submission)
3. Validate the input: `question_id` must be a real question, `answer_id` must be a real answer (or null)
4. Delete any previous answer the student had for this question
5. If the student selected an answer (not null), create a new `StudentAnswer` record
6. Return `{"success": true}` as JSON

**Why immediate saving?** If the student's browser crashes or loses internet, their answers are already saved. They can refresh the page and their selections are restored. This prevents the heartbreak of losing all answers to a crash.

---

#### `submitQuiz()` — Manual Submission

**URL:** `POST /quizzes/{quiz}/submit`
**Route name:** `quizzes.submit`

**What it does:** Called when the student clicks the "Submit Quiz" button.

**Flow:**
1. Find the student's attempt
2. If already submitted, return error
3. Set `submit_time` to now and `is_auto_submit` to false (it was manual)
4. Call `gradeQuiz()` (currently a placeholder — Person 4 will implement grading)
5. Return JSON with `success: true`, a success message, and the URL to redirect to the results page

---

#### `autoSubmit()` — Auto-Submit on Timeout

**URL:** `POST /quizzes/{quiz}/auto-submit`
**Route name:** `quizzes.auto-submit`

**What it does:** Called when the JavaScript timer reaches 0, OR when a student tries to load the quiz page after time has expired.

**Flow:**
1. Find the student's attempt
2. If no attempt exists, redirect to announcement with error
3. If already submitted, just redirect to results (don't process twice — idempotent)
4. Set `submit_time` to now and `is_auto_submit` to TRUE
5. Call `gradeQuiz()` (placeholder)
6. Redirect to results page with "Time expired. Quiz was auto-submitted."

**Idempotency is important:** If the network is slow and the student double-clicks, or if the JavaScript fires `autoSubmit` at the same time as a manual submit, only the first one processes. The second call sees that `submit_time` is already set and just redirects.

---

#### `getStatus()` — Real-Time Quiz Status (JSON)

**URL:** `GET /quizzes/{quiz}/status`
**Route name:** `quizzes.status`

**What it does:** Returns JSON data that the JavaScript on the attempt page polls every second. This keeps the timer and UI in sync with the server.

**Three possible responses:**

**Scenario 1: No attempt yet (student is on the announcement page):**
```json
{
  "has_started": false,
  "is_submitted": false,
  "time_remaining": 0,
  "time_until_start": 900,  // 15 minutes until quiz starts
  "auto_submit_if_expired": false
}
```

**Scenario 2: In progress (took the quiz, hasn't submitted yet):**
```json
{
  "has_started": true,
  "is_submitted": false,
  "time_remaining": 1800,  // 30 minutes left
  "time_until_start": -300,
  "auto_submit_if_expired": false
}
```

**Scenario 3: Already submitted:**
```json
{
  "has_started": true,
  "is_submitted": true,
  "time_remaining": 0,
  "time_until_start": 0,
  "auto_submit_if_expired": false
}
```

---

#### `showResult()` — Show the Result Page

**URL:** `GET /quizzes/{quiz}/result`
**Route name:** `quizzes.result`

**What it does:**
1. Finds the student's attempt for this quiz
2. Loads the grade record (may be NULL if Person 4 hasn't graded yet)
3. Loads all questions with answers
4. Loads the student's selected answers as a map
5. Passes everything to the result view

---

#### `gradeQuiz()` — Private Placeholder

This is a PRIVATE method (not accessible via URL). It's called after every submission (manual or auto). Currently it just logs the event to the Laravel log file:

```
Quiz attempt submitted — ready for grading.
  attempt_id: 5
  quiz_id: 3
  student_id: 10
```

Person 4 will replace this with the actual grading algorithm that compares answers and calculates scores.

#### `formatTimeRemaining()` — Private Helper

Converts seconds into a human-readable string:
- 3661 seconds → "1h 1m 1s"
- 150 seconds → "2m 30s"
- -5 seconds → "Time expired"

---

### VIEW: `resources/views/quizzes/announcement.blade.php`

**What the student sees BEFORE the quiz starts:**

A clean, centered page (max 640px wide) with:

1. **Quiz icon** (a large quiz/test icon)
2. **Quiz title** and **description** (if one was provided)
3. **Quiz metadata card** — a light blue box with three columns:
   - "DATE & TIME" — formatted like "Jul 10, 2026 at 10:00am"
   - "DURATION" — e.g. "30 minutes"
   - "QUESTIONS" — the count of questions in the quiz
4. **Action area** — one of three things, depending on timing:
   - **Quiz is live:** A green "Join Quiz Now" button with a play icon. Clicking it takes the student to the quiz interface.
   - **Quiz hasn't started:** A large countdown timer showing "00:15:30" style. JavaScript updates it every second. When it reaches zero, the page auto-refreshes to show the Join button.
   - **Time passed:** A red "Quiz time has passed" message. The student can't take the quiz.
5. **Instructions card** — A list with checkmarks:
   - "You have 30 minutes to complete this quiz."
   - "Answer all questions within the time limit."
   - "The quiz will auto-submit when time expires."
   - (If lock screen is enabled, with a warning icon): "The quiz interface is locked — you cannot navigate away or minimize the window."
   - (If results are shown after close): "Your results will be available immediately after submission."

---

### VIEW: `resources/views/quizzes/attempt.blade.php`

**What the student sees DURING the quiz:**

This is a FULL-SCREEN overlay — it covers the entire browser window with a semi-transparent dark background and a white box in the center. The page background behind it is locked (no scrolling).

**The quiz container has:**

1. **Header** with:
   - Quiz title on the left
   - "Question 1 of 10" on the left (updates as they navigate)
   - A large red countdown timer on the right showing H:MM:SS format

2. **Question cards** — One question shown at a time:
   - The question text in bold
   - Marks in red (e.g. "(5 marks)")
   - Answer options as styled boxes the student can click
   - Selected answer is highlighted in blue
   - Answers are auto-saved to the server on every click (so no data loss)

3. **Navigation dots** — Numbered circles (1, 2, 3, 4...) at the bottom:
   - White circle = not visited
   - Blue circle = currently viewing
   - Green circle = already answered
   - Students can click any dot to jump to that question

4. **Navigation buttons:**
   - "Previous" button (hidden on question 1)
   - "Next" button (hidden on the last question)
   - "Submit Quiz" button (shown only on the last question, green)

5. **Lock notice** (if enabled in config): A red banner saying "This quiz is locked. You cannot navigate away or minimize the window."

**JavaScript that powers the experience (about 230 lines):**

| Feature | How it works |
|---|---|
| **Countdown timer** | Runs every 1 second. Updates the H:MM:SS display. When it hits 0, it alerts the user and calls the auto-submit endpoint. |
| **Question navigation** | Clicking Next/Previous or a navigation dot shows/hides question cards and updates the navigation dot colors. Arrow keys also work (Left/Right). |
| **Answer selection** | Clicking an answer option: (1) highlights it in blue, (2) marks the dot as green/answered, (3) sends an AJAX POST to save the answer to the server — all instantly. |
| **Submit confirmation** | Before submitting, shows a warning. If there are unanswered questions, it tells the student how many they missed. "Are you sure? You have 3 unanswered questions." |
| **Screen lock** | If enabled: (1) The `beforeunload` event shows a "Are you sure you want to leave?" browser warning. (2) Keyboard shortcuts like F5 (refresh), Ctrl+R (reload), Alt+Left (back), Ctrl+W (close tab) are all blocked. |
| **Auto-submit** | When the timer hits 0: alerts the student, sends the auto-submit POST, and redirects to the results page. |

---

### VIEW: `resources/views/quizzes/result.blade.php`

**What the student sees AFTER submitting:**

A centered page showing the results:

1. **Header:** A green checkmark icon and the quiz title
2. **Auto-submit notice** (if applicable): A yellow banner saying "Auto-submitted — time expired."
3. **Score summary card** (green background) — Three columns:
   - "YOUR SCORE" — large green number (e.g. "45.5")
   - "OUT OF" — large number (e.g. "100.0")
   - "PERCENTAGE" — large blue number (e.g. "45.5%")
4. **Submission info row:** "Started: 10:00am | Submitted: 10:45am | Status: Manual"
5. **Question review section** — Each question shown as a card:
   - Green background = answered correctly
   - Red background = answered incorrectly (shows the correct answer too)
   - Gray background = skipped/not answered
   - Shows: question number and text, student's answer, correct answer (if wrong), marks earned
6. **"Back to Dashboard" button** at the bottom

**If grading hasn't happened yet** (Person 4 not done yet): An amber/yellow card appears saying "Grading in progress. Your grade will appear here once grading is complete." — this gives a good user experience even while Person 4's work is pending.

---

## Appendix: All Files Created / Modified

### Files Created (25 files)

| # | File Path | Person | Brief Description |
|---|---|---|---|
| 1 | `database/migrations/2026_07_05_211218_create_quizzes_table.php` | P1 | Table storing quiz definitions |
| 2 | `database/migrations/2026_07_05_211219_create_questions_table.php` | P1 | Table storing questions within quizzes |
| 3 | `database/migrations/2026_07_05_211220_create_answers_table.php` | P1 | Table storing answer options for each question |
| 4 | `database/migrations/2026_07_05_211221_create_student_attempts_table.php` | P1 | Table tracking when students attempt a quiz |
| 5 | `database/migrations/2026_07_05_211222_create_student_answers_table.php` | P1 | Table storing each answer a student selected |
| 6 | `database/migrations/2026_07_05_211223_create_grades_table.php` | P1 | Table storing final scores and participation marks |
| 7 | `database/migrations/2026_07_05_211224_create_quiz_configuration_table.php` | P1 | Table storing lecturer-set settings for each quiz |
| 8 | `app/Models/Quiz.php` | P1 | Model for the quizzes table with 5 relationships |
| 9 | `app/Models/Question.php` | P1 | Model for the questions table with 3 relationships |
| 10 | `app/Models/Answer.php` | P1 | Model for the answers table with 1 relationship |
| 11 | `app/Models/StudentAttempt.php` | P1 | Model for the student_attempts table with 4 relationships |
| 12 | `app/Models/StudentAnswer.php` | P1 | Model for the student_answers table with 3 relationships |
| 13 | `app/Models/Grade.php` | P1 | Model for the grades table with 3 relationships |
| 14 | `app/Models/QuizConfiguration.php` | P1 | Model for the quiz_configuration table with 1 relationship |
| 15 | `database/seeders/QuizSeeder.php` | P1 | Creates sample quiz data for testing |
| 16 | `app/Http/Controllers/QuizController.php` | P2 | Manages quiz CRUD (create, read, update, delete, publish) |
| 17 | `app/Http/Controllers/QuestionController.php` | P2 | Manages adding/removing questions |
| 18 | `app/Http/Controllers/AnswerController.php` | P2 | Manages adding/removing answer options |
| 19 | `app/Http/Controllers/StudentQuizController.php` | P3 | Manages the student quiz-taking experience |
| 20 | `resources/views/quizzes/create.blade.php` | P2 | "Create New Quiz" form page |
| 21 | `resources/views/quizzes/edit.blade.php` | P2 | "Edit Quiz" page with questions management |
| 22 | `resources/views/quizzes/index.blade.php` | P2 | "My Quizzes" dashboard listing page |
| 23 | `resources/views/quizzes/announcement.blade.php` | P3 | Pre-quiz announcement page with countdown |
| 24 | `resources/views/quizzes/attempt.blade.php` | P3 | Full-screen locked quiz interface with timer |
| 25 | `resources/views/quizzes/result.blade.php` | P3 | Post-submission results page |

### Files Modified (2 files)

| # | File Path | What Changed | Person |
|---|---|---|---|
| 1 | `routes/web.php` | Added ~70 lines of quiz routes — 11 lecturer routes and 7 student routes, all inside the `auth` middleware group | P2, P3 |
| 2 | `database/seeders/DatabaseSeeder.php` | Added `QuizSeeder::class` to the seeder call list so it runs with other seeders | P1 |

---

### Database Entity-Relationship Diagram (Simplified)

```
                       ┌─────────────────────────────────────────────────────────────────────────────┐
                       │                                    QUIZZES                                  │
                       │  quiz_id | lecturer_id | title | description | target | date | time | dur   │
                       └──────────┬──────────────┬───────────────────────────────────────────────────┘
                                  │              │
          ┌───────────────────────┘              └────────────────────────────┐
          │                                                                  │
          ▼                                                                  ▼
┌─────────────────────┐                                         ┌─────────────────────────┐
│     QUESTIONS       │                                         │   QUIZ_CONFIGURATION    │
│  question_id        │                                         │  config_id              │
│  quiz_id ───────────┤                                         │  quiz_id (UNIQUE) ──────┤
│  question_text      │                                         │  allow_late_join        │
│  question_type      │                                         │  lock_screen            │
│  marks              │                                         │  show_results           │
│  question_order     │                                         │  show_correct_answers   │
└──────────┬──────────┘                                         └─────────────────────────┘
           │
           ▼
┌──────────────────────┐                ┌───────────────────────────────────────────┐
│       ANSWERS        │                │          STUDENT_ATTEMPTS                 │
│  answer_id           │                │  attempt_id                               │
│  question_id ────────┤                │  quiz_id ─────────────────────────────────┤
│  answer_text         │                │  student_id ───────────┐                  │
│  is_correct          │                │  start_time            │                  │
└──────────────────────┘                │  submit_time           │                  │
                                        │  is_auto_submit        │                  │
                                        │  is_late               │                  │
                                        └──────────┬─────────────┘                  │
                                                   │                                │
          ┌────────────────────────────────────────┼──────────────────────────────┐ │
          │                                        │                              │ │
          ▼                                        ▼                              │ │
┌──────────────────────┐              ┌────────────────────────┐                  │ │
│   STUDENT_ANSWERS    │              │        GRADES          │                  │ │
│  id                  │              │  grade_id              │                  │ │
│  attempt_id ─────────┤              │  attempt_id ───────────┤                  │ │
│  question_id ────────┤              │  student_id ───────────┼──────────────────┘ │
│  selected_answer_id ─┼──┐           │  quiz_id ──────────────┼────────────────────┘
└──────────────────────┘  │           │  total_score           │
                          │           │  max_score             │
                          │           │  percentage            │
                          │           │  participation_mark    │
                          │           │  final_grade           │
                          ▼           │  graded_at             │
                 ┌──────────────┐     └────────────────────────┘
                 │   ANSWERS    │
                 │  (correct    │
                 │   answer)    │
                 └──────────────┘
```

**Reading the diagram:** Lines connect tables that are linked by foreign keys. For example, `student_answers` connects to `student_attempts` via `attempt_id`, to `questions` via `question_id`, and to `answers` via `selected_answer_id`.

---

*End of document. Persons 4 (Grading & Analytics) and 5 (Notifications & Triggers) are not yet implemented.*
