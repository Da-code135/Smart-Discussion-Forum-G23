# Changes & Decisions Log

## July 7, 2026

This document explains every change made during the session. The goal was simple: make the platform actually **testable** — from creating a quiz, to a student receiving a notification, to submitting answers and seeing results.

---

## Change 1: Rewrote the API Documentation

**File:** `docs/API_DOCUMENTATION.md`

**The problem:** The original documentation was 3,600+ lines of tables, JSON examples, and status codes. It was technically correct but read like a machine spec. A developer building a desktop client had to hunt through scattered sections to understand how anything connected. Group isolation, the quiz lifecycle, warning escalation — none of it was explained in context.

**What we did:** Rewrote the entire thing from scratch. It's now about 1,530 lines but explains everything in plain English.

**Key decisions:**
- We explain **concepts first** (groups, roles, auth) before showing endpoints — because everything else depends on them.
- The quiz system is split into **lecturer side** (creating quizzes) and **student side** (taking them) so you're not jumping between sections.
- We added an **end-to-end flow section** that walks through launching the app for the first time, registering, and navigating by role.
- The TypeScript class example was replaced with raw HTTP requests — works for any language.
- Endpoint tables are kept as a **quick-reference appendix** at the end, not the main content.

---

## Change 2: Fixed the Date-Parsing Bug That Broke Quiz Publishing

**Files changed:** `Quiz.php`, `QuizController.php`, `Api/QuizController.php`, `StudentQuizController.php`, `Api/StudentQuizController.php`, `ActivateQuizzes.php`, `SendQuizReminders.php`

**The problem:** Ten different places in the code were doing this:

```
Carbon::parse($quiz->scheduled_date . ' ' . $quiz->start_time)
```

The problem is that Laravel's date casting makes `scheduled_date` return `"2026-07-07 00:00:00"` and `start_time` return `"2026-07-07 10:05:00"`. When you join them with a space, you get:

```
2026-07-07 00:00:00 2026-07-07 10:05:00
```

That's **two dates in one string**. Carbon can't parse it and throws "Double date specification". This made the **Publish button unusable** — it always crashed.

**What we did:** Added a helper method to the `Quiz` model:

```php
public function getScheduledDateTime(): Carbon
{
    $dateStr = $this->scheduled_date->format('Y-m-d');
    $timeStr = $this->start_time->format('H:i:s');
    return Carbon::parse($dateStr . ' ' . $timeStr);
}
```

Then replaced all ten broken call sites with `$quiz->getScheduledDateTime()`.

**Why this way:** Putting the fix in the model means the bug can't reappear if someone writes new code that concatenates these fields. Any future developer who needs the scheduled time just calls one method and gets the right answer.

---

## Change 3: Made Notifications Work Without a Queue Worker

**Files changed:** `app/Listeners/SendQuizAnnouncement.php`, `app/Listeners/NotifyQuizLive.php`

**The problem:** When a lecturer published a quiz, the `SendQuizAnnouncement` listener was supposed to create a notification record for every student. But it used `ShouldQueue` — meaning the work was pushed onto a queue to be processed later by a background worker. On a local development machine, no queue worker is running. So the notification records were **never created**. The lecturer saw "Published!" but students never got notified.

**What we did:** Removed `implements ShouldQueue` from both quiz listeners. They now run immediately when the event fires — notification records are created in the same HTTP request.

**Why this way:** For local development, this is simpler and more predictable. A group typically has dozens of students, not thousands, so the performance impact is negligible. If the app grows to the point where publishing a quiz means creating 10,000 notification records, the queue can be added back. But for now, instant feedback during testing wins.

---

## Change 4: Created a Student Quiz Dashboard

**Files created/changed:**
- `app/Http/Controllers/StudentQuizController.php` — added `index()` method
- `resources/views/quizzes/student-index.blade.php` — new page
- `routes/web.php` — added `/my-quizzes` route

**The problem:** There was no page where a student could see their quizzes. The only way to reach a quiz was to know the exact URL (`/quizzes/3/announcement`). A student would get a notification but have no way to act on it.

**What we did:** Created a student quiz dashboard at `/my-quizzes` that shows all published quizzes for the student's group. Each quiz is displayed as a card with:
- A **colour-coded badge**: "Upcoming", "Live Now", "Completed", or "Missed"
- The quiz title, description, lecturer, duration, question count, and scheduled time
- A **context-sensitive button**: "View Details" for upcoming, "Join Quiz" for live, "View Result" for completed

**Why a separate URL:** Both the student dashboard and the lecturer management page respond to GET at `/quizzes`. Laravel registers routes in order, so whichever is defined last wins. Putting the student page at `/my-quizzes` avoids the clash entirely.

---

## Change 5: Added Quizzes Link for Students in the Navbar

**File changed:** `resources/views/components/navbar.blade.php`

**The problem:** The navigation bar only showed a "Quizzes" link if you were an Admin or Lecturer. Students — the people who actually take quizzes — couldn't see it.

**What we did:** Removed the `@if` guard and replaced it with dynamic route selection:
- **Admins and Lecturers** get a link to the management page
- **Everyone else** gets a link to the student dashboard

The Quizzes link now appears for every logged-in user.

---

## Change 6: Made Notifications Clickable

**File changed:** `resources/views/notifications/index.blade.php`

**The problem:** Notifications were just text. You could see "Quiz announcement" but tapping it did nothing. The only action was "Mark as read". To actually reach the quiz, you had to type the URL manually.

**What we did:** Wrapped each notification in a link. The destination depends on the notification type:
- **Quiz announcement** and **Quiz reminder** → links to the announcement page
- **Quiz live** → links directly to the quiz attempt page

We also added a preview line showing the quiz title and duration so you see more useful info at a glance. The "Mark as read" button still works — clicking it doesn't follow the link.

**Why this way:** A notification that does nothing when tapped is a dead end. Tapping the notification should take you where you need to go.

---

## July 25, 2026

## Change 7: Closed the SDD Gaps — Classification Confidence, Export Logging, Recommendation Relevance

**Files changed:** `app/Services/TopicClassificationService.php`, `app/Services/RecommendationService.php`, `app/Models/Topic.php`, `app/Models/ExportLog.php` (new), `app/Models/RecommendationLog.php`, `app/Http/Controllers/ForumController.php`, `app/Http/Controllers/Api/TopicController.php`, `app/Http/Controllers/Api/RecommendationController.php`, `resources/views/recommendations/index.blade.php`, three new migrations, plus feature tests.

**The problem:** The SDD required three things the code didn't do: the classifier gave no confidence score and no way to catch bad classifications; PDF exports left no dedicated trace; recommendations carried no relevance score or explanation.

**What we did:**
1. **Classifier confidence + review flag.** `classifyTopic()` now computes `classification_confidence` (winner's keyword matches ÷ total matches × 100) and sets `classification_needs_review = true` when confidence falls below the `classification_review_threshold` config (default 40%). The `Topic::needsClassificationReview()` scope surfaces the review queue. Admins can extend the keyword map per category via the `keyword_hints` column on `topic_categories`.
2. **Export logging.** New `export_logs` table + `ExportLog` model. Both the web and API `exportPDF` methods now record `topic_id`, `user_id`, and `file_type` on every export, alongside the existing `topic.exported` audit log entry.
3. **Recommendation relevance.** `generateRecommendations()` attaches a `relevance_score` (share of the user's engagement in the topic's category, 0–100) and a `recommendation_reason` to every pick. Popular fallbacks are capped at 50% with reason "Popular in your group". The score is persisted to `recommendation_log`, shown as a "% match" badge on the recommendations page, and returned by `GET /api/v1/recommendations`.

**Verification:** Full test suite passes (343 tests, 888 assertions), including new tests for classification confidence/review, keyword hints, export logging, and relevance scoring.

---

## Change 8: Documentation Unification Pass

**Files changed:** `docs/API_DOCUMENTATION.md`, `docs/DOCUMENTATION.md`, `docs/ANALYTICS_MODULE.md`, `docs/FORUM_MODULE.md`, `docs/api-layer-overview.md`, `docs/Presentation-Web-Interface.md`, `docs/presentation-qa-guide.md`, `docs/Full-Demo-Script.md`.

**What we did:** Brought every document that describes classification, recommendations, or PDF export in line with the Change 7 implementation — new topic JSON fields (`classification_confidence`, `classification_needs_review`) in the API examples, a dedicated Recommendations API section with `relevance_score`/`reason` in the response example, `export_logs`/`recommendation_log`/`topic_categories` added to the database-table references, the ANALYTICS_MODULE deep-dive code walkthroughs updated to match the current services, and the presentation/demo scripts updated so the demo talks match what the app actually shows ("% match" badges, reason lines, review flags).
