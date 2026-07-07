# Quiz & Assessment Module — Person 5 Implementation Summary

> **Generated:** July 2026
> **Person 5 Role:** Notifications & Trigger System — the "nerve system" of the quiz module

---

## Table of Contents

1. [What Person 5 Does (The Big Picture)](#what-person-5-does-the-big-picture)
2. [How Person 5 Connects to Everyone Else](#how-person-5-connects-to-everyone-else)
3. [Files Created (8 files)](#files-created)
4. [Files Modified (2 files)](#files-modified)
5. [The Complete Flow, End to End](#the-complete-flow-end-to-end)

---

## What Person 5 Does (The Big Picture)

Person 5 makes things happen **automatically**. Before Person 5, the quiz system was "manual" — a lecturer could create a quiz and publish it, but nothing actually told students about it. No reminders were sent. The quiz never flipped from "waiting" to "active" on its own.

Person 5 adds three pieces of automation:

1. **When a lecturer publishes a quiz → students get notified immediately.** You don't need to email them or announce it in class. The system creates a notification for every eligible student on the spot.

2. **A few minutes before the quiz starts → students get a reminder.** The system checks every minute whether any quiz is about to start. If one is, it sends a "heads up, 15 minutes to go" notification.

3. **At the scheduled start time → the quiz goes live automatically.** The system flips the switch from "not active" to "active" at the exact right moment and tells students "It's time, go now!"

Think of it like an alarm clock with three alarms:
- **Alarm 1:** "A quiz has been scheduled!" (fires when the lecturer publishes)
- **Alarm 2:** "The quiz starts in 15 minutes!" (fires automatically before start time)
- **Alarm 3:** "The quiz is starting NOW!" (fires at the scheduled time and activates the quiz)

---

## How Person 5 Connects to Everyone Else

Person 5 doesn't build anything from scratch — it plugs into what Persons 1, 2, and 3 already built.

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        HOW IT ALL CONNECTS                              │
│                                                                         │
│  P1 (Database)                                                          │
│  ┌──────────────────┐                                                   │
│  │  quizzes table   │◄──── Person 5 reads quiz dates, times, settings  │
│  │  users table     │◄──── Person 5 reads student emails/roles         │
│  │  notifications   │◄──── Person 5 WRITES new notification rows       │
│  │  table           │                                                   │
│  └──────────────────┘                                                   │
│         ▲                                                               │
│         │                                                               │
│  ┌──────┴──────┐                                                        │
│  │   Person 5  │────────┐                                               │
│  │  (You!)     │        │                                               │
│  └─────────────┘        │                                               │
│         │               │                                               │
│         │               ▼                                               │
│  ┌──────┴──────┐  ┌──────────┐                                         │
│  │   Person 2  │  │ Person 3 │                                         │
│  │ (Lecturer   │  │ (Student │                                         │
│  │  Interface) │  │  Quiz)   │                                         │
│  └─────────────┘  └──────────┘                                         │
│         │               │                                               │
│         │               │                                               │
│         ▼               ▼                                               │
│  When lecturer      When Person 5 activates the quiz,                   │
│  clicks Publish,    Person 3's interface allows                         │
│  Person 5 sends     the student to enter and                            │
│  notifications      start taking it                                     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Person 5 + Person 1 (Database)

Person 1 created the tables. Person 5 reads from them:

| Table | What Person 5 Reads | Why |
|---|---|---|
| `quizzes` | `scheduled_date`, `start_time`, `is_active`, `published_at`, `target_category` | To know when to send reminders and when to activate |
| `users` | Role, group, account status | To know who should receive notifications |
| `quiz_configuration` | `notification_minutes_before` | To know how many minutes before start to send the reminder |

Person 5 also **writes** to the existing `notifications` table that was already in the system (created long before the quiz module). It creates new rows with `type` set to `quiz_announcement`, `quiz_reminder`, or `quiz_live`.

### Person 5 + Person 2 (Lecturer Interface)

Person 2 built the lecturer's "Publish" button in `QuizController@publish()`. But when that button was clicked, the controller just wrote to a log file — it didn't actually tell anyone.

Person 5 changed that. Now, right after Person 2's code marks the quiz as published, Person 5's event fires and sends notifications to all eligible students. The lecturer still clicks the same button and sees the same success message — but now it's genuine when it says "Students have been notified."

### Person 5 + Person 3 (Student Quiz Interface)

Person 3 built the student quiz experience — the announcement page, the locked quiz interface, the countdown timer. But Person 3's code checks `is_active` before it lets a student in. Without Person 5, that flag never flips from `false` to `true` automatically.

Person 5's `ActivateQuizzes` command flips `is_active` to `true` at the scheduled time. Once that happens, Person 3's `showQuiz()` method allows the student to enter and start taking the quiz.

---

## Files Created

---

### FILE 1: `app/Events/QuizPublished.php`

**What it is:** An "event" — think of it as a public announcement inside the code. When a lecturer publishes a quiz, this event is "fired" (like pressing a doorbell). Any listener that's waiting for that doorbell will then do its job.

**Full code with explanation:**

```php
<?php

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizPublished
{
    use Dispatchable, SerializesModels;

    /**
     * The quiz that was just published.
     * 
     * This is the "package" the event carries. Any listener
     * that receives this event can access $event->quiz to
     * get the quiz's title, lecturer, target_category, etc.
     */
    public function __construct(public Quiz $quiz)
    {
        //
    }
}
```

**What the code does:**
- `use Dispatchable` — allows this event to be "dispatched" (fired) by calling `QuizPublished::dispatch($quiz)`
- `public Quiz $quiz` — the event carries the quiz object with it, so the listener can read its details without having to look them up again
- The constructor is simple — it just stores the quiz for later use

**Real-world analogy:** This is a mailman's envelope. The envelope itself is simple (just holds the mail), but without it, the mail inside can't be delivered. The "mail" here is the quiz data that the listener needs.

---

### FILE 2: `app/Events/QuizWentLive.php`

**What it is:** The second event — fired when a quiz is activated at its scheduled time.

```php
<?php

namespace App\Events;

use App\Models\Quiz;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizWentLive
{
    use Dispatchable, SerializesModels;

    public function __construct(public Quiz $quiz)
    {
        //
    }
}
```

**What the code does:** Identical structure to `QuizPublished`. It's a separate event because:
1. They happen at different times (publish is when the lecturer clicks the button; live is when the scheduled time arrives)
2. They trigger different notifications (one says "scheduled," the other says "available now")
3. Different parts of the system might need to listen for one but not the other

**Real-world analogy:** Think of `QuizPublished` as a "Save the Date" card and `QuizWentLive` as the "It's starting now!" text message.

---

### FILE 3: `app/Listeners/SendQuizAnnouncement.php`

**What it is:** A "listener" — it sits and waits for the `QuizPublished` doorbell to ring. When it does, it runs and creates notification records for every student who should know about the quiz.

```php
<?php

namespace App\Listeners;

use App\Events\QuizPublished;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendQuizAnnouncement implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event — this is where the real work happens.
     */
    public function handle(QuizPublished $event): void
    {
        $quiz = $event->quiz;  // Get the quiz from the event envelope

        // Find all USERS whose role matches the quiz's target_category
        // AND who belong to the same group as the lecturer
        // AND whose account is active (not suspended)
        $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
                $query->where('role_name', $quiz->target_category);
            })
            ->where('group_id', $quiz->lecturer->group_id)
            ->where('account_status', 'active')
            ->get();

        $count = 0;

        // Create one notification record per user
        foreach ($targetUsers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'quiz_announcement',
                'data' => [
                    'quiz_id' => $quiz->quiz_id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'scheduled_date' => $quiz->scheduled_date,
                    'start_time' => $quiz->start_time,
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                ],
                'read_at' => null,  // Mark as unread
            ]);

            $count++;
        }

        // Log how many notifications were sent
        logger("Quiz {$quiz->quiz_id} announcement sent to {$count} user(s).");
    }
}
```

**Step-by-step breakdown of what happens when this listener runs:**

1. **Gets the quiz** from the event (`$event->quiz`)
2. **Finds the right audience** — it looks for users who:
   - Have a role name matching `target_category` (e.g. if the quiz targets "Student", it finds all users with the "Student" role)
   - Are in the same group as the lecturer (so students in Group A don't see announcements meant for Group B)
   - Have an active account (suspended users get nothing)
3. **Creates a notification for each user** — each notification stores:
   - `user_id` — who gets it
   - `type` — `quiz_announcement` (labels it as a quiz announcement)
   - `data` — a JSON object with all the details (quiz title, date, time, duration, lecturer name) so the view can display rich content without extra database queries
   - `read_at` — set to `null` (unread)
4. **Logs the result** — writes to the log file how many notifications were sent

**The `ShouldQueue` interface** (on line 9) is important. It means this listener runs in the **background**. The lecturer clicks "Publish" and gets an instant success response. The notifications are created a moment later, off to the side, without making the lecturer wait.

**Real-world analogy:** This is like a mailing house. When you place an order (the lecturer clicks Publish), the order goes into a queue. A machine in the back (the listener) picks it up, looks up the addresses (finds target users), stuffs envelopes (creates notifications), and drops them in the mail. You don't stand at the counter waiting for each envelope to be stuffed.

---

### FILE 4: `app/Listeners/NotifyQuizLive.php`

**What it is:** The listener for `QuizWentLive`. When the quiz becomes active, this creates "The quiz is live NOW!" notifications.

```php
<?php

namespace App\Listeners;

use App\Events\QuizWentLive;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyQuizLive implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(QuizWentLive $event): void
    {
        $quiz = $event->quiz;

        // Find target users (same logic as the announcement listener)
        $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
                $query->where('role_name', $quiz->target_category);
            })
            ->where('group_id', $quiz->lecturer->group_id)
            ->where('account_status', 'active')
            ->get();

        $count = 0;

        foreach ($targetUsers as $user) {
            // IMPORTANT: Check if we already sent a "live" notification
            // to this user for this quiz. This prevents duplicates if
            // the quiz somehow gets reactivated.
            $alreadyNotified = Notification::where('user_id', $user->id)
                ->where('type', 'quiz_live')
                ->where('data->quiz_id', $quiz->quiz_id)
                ->exists();

            if ($alreadyNotified) {
                continue;  // Skip — already told them
            }

            Notification::create([
                'user_id' => $user->id,
                'type' => 'quiz_live',
                'data' => [
                    'quiz_id' => $quiz->quiz_id,
                    'title' => $quiz->title,
                    'duration_minutes' => $quiz->duration_minutes,
                    'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                ],
                'read_at' => null,
            ]);

            $count++;
        }

        logger("Quiz {$quiz->quiz_id} is now live! Notified {$count} user(s).");
    }
}
```

**What's different from the announcement listener:**

1. **Notification type is `quiz_live`** instead of `quiz_announcement` — the view displays it differently (red card instead of blue)
2. **Less data is stored** — only quiz_id, title, duration, and lecturer name (students don't need the scheduled date because it's happening NOW)
3. **Deduplication check** — before creating a notification, it checks if one already exists for this user+quiz combination. This prevents sending the same "Quiz is live!" message twice if the quiz gets reactivated for any reason

---

### FILE 5: `app/Console/Commands/SendQuizReminders.php`

**What it is:** A scheduled command — a script that runs automatically every minute and checks if any quizzes need reminders sent.

```php
<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Quiz;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendQuizReminders extends Command
{
    // The command name used in the terminal: "php artisan quiz:send-reminders"
    protected $signature = 'quiz:send-reminders';

    // Description shown when listing commands
    protected $description = 'Send reminder notifications for quizzes starting soon';

    public function handle(): int
    {
        $now = now();

        // Step 1: Find all published, not-yet-active quizzes
        // (No point reminding students about a quiz that's already running
        // or one that hasn't been announced yet.)
        $quizzes = Quiz::where('is_active', false)
            ->whereNotNull('published_at')
            ->with('configuration', 'lecturer')  // Load related data
            ->get();

        $totalRemindersSent = 0;

        foreach ($quizzes as $quiz) {
            // Step 2: Calculate the scheduled start time as a real date/time
            $scheduledTime = Carbon::parse(
                $quiz->scheduled_date.' '.$quiz->start_time,
            );

            // Step 3: How many minutes from NOW until the quiz starts?
            // A positive number = future. Negative = already past.
            $minutesUntilStart = $now->diffInMinutes($scheduledTime, false);

            // Step 4: What's the notification window? (Default 15 minutes)
            $notificationWindow = $quiz->configuration?->notification_minutes_before ?? 15;

            // Step 5: Should we send a reminder NOW?
            // We only send when minutesUntilStart is within the window.
            // Example: window is 15, minutesUntilStart is 14.5 to 15
            // This creates about a 60-second window to catch it once.
            if ($minutesUntilStart <= 0 || $minutesUntilStart > $notificationWindow) {
                continue;  // Not in the window — skip
            }

            $inWindow = $minutesUntilStart <= $notificationWindow
                && $minutesUntilStart > ($notificationWindow - 1);

            if (! $inWindow) {
                continue;
            }

            // Step 6: Find target users (same logic as other listeners)
            $targetUsers = User::whereHas('role', function ($query) use ($quiz) {
                    $query->where('role_name', $quiz->target_category);
                })
                ->where('group_id', $quiz->lecturer->group_id)
                ->where('account_status', 'active')
                ->get();

            $remindersSent = 0;

            foreach ($targetUsers as $user) {
                // Step 7: Deduplicate — don't send a second reminder
                $alreadyReminded = Notification::where('user_id', $user->id)
                    ->where('type', 'quiz_reminder')
                    ->where('data->quiz_id', $quiz->quiz_id)
                    ->exists();

                if ($alreadyReminded) {
                    continue;
                }

                // Step 8: Create the reminder notification
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'quiz_reminder',
                    'data' => [
                        'quiz_id' => $quiz->quiz_id,
                        'title' => $quiz->title,
                        'minutes_until_start' => $minutesUntilStart,
                        'scheduled_date' => $quiz->scheduled_date,
                        'start_time' => $quiz->start_time,
                        'duration_minutes' => $quiz->duration_minutes,
                        'lecturer_name' => $quiz->lecturer->full_name ?? 'Lecturer',
                    ],
                    'read_at' => null,
                ]);

                $remindersSent++;
            }

            if ($remindersSent > 0) {
                // Print to the console so the schedule log shows activity
                $this->info(
                    "Quiz '{$quiz->title}' (ID: {$quiz->quiz_id}): "
                    ."{$remindersSent} reminder(s) sent ({$minutesUntilStart} min before start)."
                );
            }

            $totalRemindersSent += $remindersSent;
        }

        if ($totalRemindersSent === 0) {
            $this->info('No reminders needed at this time.');
        }

        return self::SUCCESS;  // Exit code 0 = success
    }
}
```

**Step-by-step breakdown:**

1. **Load all published, not-yet-active quizzes** — it only checks quizzes that have been announced but aren't running yet. If a quiz is already active, it's too late for a reminder.
2. **Calculate time until start** — it figures out how many minutes from now until the quiz's scheduled date+time.
3. **Check the notification window** — if the quiz is configured to remind 15 minutes before, it checks whether now is approximately 15 minutes before start.
4. **The "one-minute window" trick** — since this runs every minute, the command uses a ~60-second window: it fires when `minutesUntilStart` is between 14 and 15. This means each quiz gets reminded exactly once, not on every run.
5. **Find target users** — same audience selection as the other listeners (matching role, same group, active account).
6. **Deduplicate** — checks if this user already received a reminder for this quiz. If yes, skip.
7. **Create the notification** — stores the reminder with all the details the view needs.

**Real-world analogy:** This is like a security guard who walks the halls every minute checking a clipboard. The clipboard lists all the events scheduled for today. When the guard sees "Staff meeting in 15 minutes," they radio the staff. Once announced, they cross it off the list so they don't announce it again next minute.

---

### FILE 6: `app/Console/Commands/ActivateQuizzes.php`

**What it is:** The second scheduled command — it flips the switch that makes quizzes "live" at the right time.

```php
<?php

namespace App\Console\Commands;

use App\Events\QuizWentLive;
use App\Models\Quiz;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActivateQuizzes extends Command
{
    protected $signature = 'quiz:activate';

    protected $description = 'Activate quizzes at their scheduled start time';

    public function handle(): int
    {
        $now = now();

        // Find all published, not-yet-active quizzes
        $quizzes = Quiz::where('is_active', false)
            ->whereNotNull('published_at')
            ->get();

        $activated = 0;

        foreach ($quizzes as $quiz) {
            $scheduledTime = Carbon::parse(
                $quiz->scheduled_date.' '.$quiz->start_time,
            );

            // Has the scheduled time arrived or passed?
            if ($now->isAfter($scheduledTime)) {
                // Step 1: Flip the switch — quiz is now active
                $quiz->update(['is_active' => true]);

                // Step 2: Fire the event — this triggers the
                // NotifyQuizLive listener to send notifications
                QuizWentLive::dispatch($quiz);

                $this->info(
                    "Quiz '{$quiz->title}' (ID: {$quiz->quiz_id}) activated."
                );

                $activated++;
            }
        }

        if ($activated === 0) {
            $this->info('No quizzes to activate at this time.');
        }

        return self::SUCCESS;
    }
}
```

**What happens when this runs:**

1. **Find quizzes to activate** — queries for published, not-yet-active quizzes
2. **Check the time** — for each one, combines `scheduled_date` and `start_time` into a single timestamp and compares it to the current time
3. **If the time has come:**
   - **Flips `is_active` from `false` to `true`** — this is the moment the quiz becomes available. Before this, Person 3's `showQuiz()` would refuse entry with "Quiz has not started yet." After this, students can enter.
   - **Fires `QuizWentLive`** — this triggers `NotifyQuizLive`, which sends "Quiz is live NOW!" notifications
4. **Logs the result** — prints to the console for the schedule log

**This is the most important of Person 5's files.** Without it, no quiz would ever become available. Students would see the announcement, wait for the scheduled time, refresh the page, and still see "Quiz has not started yet" because `is_active` was never flipped.

**Real-world analogy:** This is the stage manager at a theater. When the clock hits 8:00 PM, they flip the "Performance" sign from OFF to ON, unlock the doors (activation), and announce "The show is starting!" (event dispatch). The audience (students) can now enter.

---

### FILE 7: `resources/views/notifications/center.blade.php`

**What it is:** A "partial" view — a reusable piece of HTML that can be dropped into any page. It displays quiz notifications (announcements, reminders, live alerts) to the currently logged-in user.

```blade
@php
    // How many notifications to show (default 5)
    $limit = $limit ?? 5;

    // Get the current user's quiz notifications, newest first
    $quizNotifications = auth()->user()->notifications()
        ->whereIn('type', ['quiz_announcement', 'quiz_reminder', 'quiz_live'])
        ->latest()
        ->limit($limit)
        ->get();
@endphp

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0; font-size: 1rem;">Quiz Updates</h3>
    </div>
    <div class="card-body page-stack" style="gap: 0.5rem;">

        @forelse ($quizNotifications as $notif)
            @php
                // Pick an icon and colors based on notification type
                $data = $notif->data;
                $icon = match ($notif->type) {
                    'quiz_announcement' => '📢',   // Megaphone
                    'quiz_reminder'     => '⏰',   // Alarm clock
                    'quiz_live'         => '🔴',   // Red circle
                    default             => '📌',
                };
                $bgColor = match ($notif->type) {
                    'quiz_announcement' => '#f0f5ff',  // Light blue
                    'quiz_reminder'     => '#fefce8',  // Light yellow
                    'quiz_live'         => '#fef2f2',  // Light red
                    default             => '#f9fafb',
                };
                $borderColor = match ($notif->type) {
                    'quiz_announcement' => '#3b82f6',  // Blue
                    'quiz_reminder'     => '#eab308',  // Yellow
                    'quiz_live'         => '#dc2626',  // Red
                    default             => '#e5e7eb',
                };
            @endphp

            {{-- One notification card --}}
            <div style="padding: 0.75rem 1rem; background: {{ $bgColor }}; border-left: 4px solid {{ $borderColor }}; border-radius: 6px;">
                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <span style="font-size: 1.25rem; line-height: 1.4;">{{ $icon }}</span>
                    <div style="flex: 1; min-width: 0;">
                        {{-- Title line --}}
                        <p style="margin: 0 0 0.25rem 0; font-weight: 600; font-size: 0.9rem;">
                            @if ($notif->type === 'quiz_announcement')
                                New Quiz: {{ $data['title'] ?? 'Untitled' }}
                            @elseif ($notif->type === 'quiz_reminder')
                                Reminder: {{ $data['title'] ?? 'Untitled' }}
                            @elseif ($notif->type === 'quiz_live')
                                Live Now: {{ $data['title'] ?? 'Untitled' }}
                            @endif
                        </p>

                        {{-- Body text --}}
                        <p style="margin: 0 0 0.25rem 0; font-size: 0.8rem; color: #4b5563;">
                            @if ($notif->type === 'quiz_announcement')
                                Scheduled for
                                <strong>{{ $data['scheduled_date'] ?? '?' }}</strong>
                                at
                                <strong>{{ $data['start_time'] ?? '?' }}</strong>
                                ({{ $data['duration_minutes'] ?? '?' }} min)
                            @elseif ($notif->type === 'quiz_reminder')
                                Starts in
                                <strong>{{ $data['minutes_until_start'] ?? '?' }}</strong>
                                minute(s) at {{ $data['start_time'] ?? '?' }}
                                — {{ $data['duration_minutes'] ?? '?' }} min duration
                            @elseif ($notif->type === 'quiz_live')
                                Available now!
                                <strong>{{ $data['duration_minutes'] ?? '?' }}</strong>
                                minutes to complete it.
                            @endif
                        </p>

                        {{-- Footer: time ago + lecturer name + link --}}
                        <p style="margin: 0; font-size: 0.7rem; color: #9ca3af;">
                            {{ $notif->created_at->diffForHumans() }}
                            @if ($data['lecturer_name'] ?? null)
                                &middot; by {{ $data['lecturer_name'] }}
                            @endif
                            @if ($data['quiz_id'] ?? null)
                                &middot;
                                <a href="{{ route('quizzes.announcement', $data['quiz_id']) }}"
                                   style="color: #3b82f6; text-decoration: none;">
                                    View Quiz
                                </a>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

        @empty
            {{-- No notifications at all --}}
            <div style="text-align: center; padding: 2rem 1rem; color: #9ca3af;">
                <p style="margin: 0; font-size: 0.9rem;">No quiz updates yet.</p>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem;">
                    When a quiz is announced, it will appear here.
                </p>
            </div>
        @endforelse

    </div>
</div>
```

**How it works:**

1. **Fetches notifications** — gets the current user's notifications where `type` is one of the three quiz types (`quiz_announcement`, `quiz_reminder`, `quiz_live`), ordered newest-first, limited to a configurable number (default 5)
2. **Color-codes each notification** — each type gets a distinct look:
   - **Blue** (📢) = Announcement — a new quiz was published
   - **Yellow** (⏰) = Reminder — a quiz is starting soon
   - **Red** (🔴) = Live — a quiz is available now
3. **Displays relevant info** — the title, body text, and footer change depending on the type:
   - Announcements show the scheduled date/time and duration
   - Reminders show "Starts in X minutes" with the start time
   - Live alerts show "Available now!" with the duration
4. **Shows a "View Quiz" link** — clicking it takes the student to the quiz's announcement page
5. **Empty state** — if no notifications exist, shows a friendly "No quiz updates yet" message

**How to use this view:** Any other page (dashboard, forum, etc.) can include it with:
```blade
@include('notifications.center', ['limit' => 5])
```

---

### FILE 8: `routes/console.php` (modified — see below for what changed)

This is actually a modified file, but I'm listing it here because it's where the scheduled commands live. See the [Modified Files section](#files-modified) for the details.

---

## Files Modified

---

### FILE 9: `routes/console.php`

**What changed:** Before Person 5, this file had only one line:

```php
Schedule::command('monitor:activity')->daily()->at('02:00');
```

After Person 5, it has three lines:

```php
Schedule::command('monitor:activity')->daily()->at('02:00');

// Send reminders for quizzes that are about to start (checks every minute)
Schedule::command('quiz:send-reminders')->everyMinute();

// Activate quizzes at their scheduled start time (checks every minute)
Schedule::command('quiz:activate')->everyMinute();
```

**What this does:** Tells Laravel to run both commands every minute. In production, someone would run `php artisan schedule:work` which starts a loop that checks this file every 60 seconds and runs whatever commands are scheduled.

**Why every minute?** Because quiz times need to be precise. If a quiz is scheduled for 10:00, we want it to activate at 10:00, not "sometime within the next hour." Every minute gives us 60-second accuracy, which is plenty for a quiz system.

---

### FILE 10: `app/Http/Controllers/QuizController.php`

**Two changes were made to this file:**

**Change 1 — Added an import at the top (line 10):**

```php
use App\Events\QuizPublished;
```

This tells the controller: "There's an event called QuizPublished, and I want to use it."

**Change 2 — Replaced the placeholder log with an actual event dispatch (lines 220-223):**

**Before (Person 2's placeholder):**
```php
// Create announcement notification
// (This triggers Person 4's Notification system)
// For now, just log it
\Log::info("Quiz {$quiz->quiz_id} published as announcement for {$quiz->target_category}");
```

**After (Person 5's real integration):**
```php
// Dispatch event — SendQuizAnnouncement listener creates
// notifications for all students in the target audience.
QuizPublished::dispatch($quiz);
```

**What changed functionally:**
- Before: The publish button would save the quiz, write a log entry saying "pretend I sent notifications," and show a success message that was technically lying
- After: The publish button saves the quiz, fires the `QuizPublished` event, the listener creates real notification records in the database, and the success message is now telling the truth

---

## The Complete Flow, End to End

Here's what happens from the moment a lecturer creates a quiz to the moment a student takes it, showing which Person handles each step:

```
┌──────────────────────────────────────────────────────────────────────────┐
│                   THE COMPLETE QUIZ LIFECYCLE                            │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  LECTURER CREATES QUIZ                                                   │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 2: QuizController@store()                                │    │
│  │ Creates quiz + configuration. Redirects to edit page.           │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  LECTURER ADDS QUESTIONS & ANSWERS                                       │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 2: QuestionController + AnswerController                  │    │
│  │ Lecturer adds questions, marks, answer options.                  │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  LECTURER CLICKS "PUBLISH"                                               │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 2: QuizController@publish()  ── sets published_at         │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: QuizPublished::dispatch($quiz)  ── fires event         │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: SendQuizAnnouncement listener runs (background)        │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: Creates "quiz_announcement" notifications              │    │
│  │     │            for every eligible student                      │    │
│  │     ▼                                                            │    │
│  │ 🎯 STUDENT SEES "New Quiz: Midterm Exam" on their dashboard      │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  TIME PASSES...                                                          │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 5: quiz:send-reminders runs every minute                  │    │
│  │     │                                                            │    │
│  │     ▼ (15 minutes before start)                                  │    │
│  │ Person 5: Detects quiz starting in 15 min                        │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: Creates "quiz_reminder" notifications                  │    │
│  │     │            for every eligible student                      │    │
│  │     ▼                                                            │    │
│  │ 🎯 STUDENT SEES "Reminder: Midterm starts in 15 minutes!"        │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  SCHEDULED TIME ARRIVES                                                  │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 5: quiz:activate runs every minute                        │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: Finds quiz where scheduled time has arrived            │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: Flips is_active from false → true                      │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: QuizWentLive::dispatch($quiz)  ── fires event          │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: NotifyQuizLive listener runs (background)              │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ Person 5: Creates "quiz_live" notifications                      │    │
│  │     │                                                            │    │
│  │     ▼                                                            │    │
│  │ 🎯 STUDENT SEES "Live Now: Midterm Exam — Available now!"        │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  STUDENT TAKES THE QUIZ                                                  │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 3: StudentQuizController                                  │    │
│  │ Student clicks "Join Quiz Now" → takes quiz → submits            │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│           │                                                              │
│           ▼                                                              │
│  GRADING HAPPENS                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │ Person 4: QuizGradingController (not yet built)                  │    │
│  │ Calculates score, creates Grade record, shows result             │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### Summary of Person 5's Place in the System

| Stage | What Happens | Who Built It |
|---|---|---|
| Quiz is created | Lecturer fills form, questions, answers | Person 2 |
| Quiz is published | `published_at` is set, event fires | Person 2 + **Person 5** |
| Notifications sent | Students see "New Quiz" on dashboard | **Person 5** |
| Reminder sent (15 min before) | Students see "Starts in 15 min" | **Person 5** |
| Quiz goes live | `is_active` flipped to true | **Person 5** |
| "Live now" notification sent | Students see "Available now!" | **Person 5** |
| Student takes quiz | Timer, questions, navigation | Person 3 |
| Quiz is graded | Score calculated, Grade record created | Person 4 (not yet built) |

### All Files Summary

| # | File | Type | What It Does |
|---|---|---|---|
| 1 | `app/Events/QuizPublished.php` | **Created** | Event fired when lecturer publishes a quiz |
| 2 | `app/Events/QuizWentLive.php` | **Created** | Event fired when quiz becomes active at scheduled time |
| 3 | `app/Listeners/SendQuizAnnouncement.php` | **Created** | Listener — creates announcement notifications for all eligible students |
| 4 | `app/Listeners/NotifyQuizLive.php` | **Created** | Listener — creates "quiz is live" notifications for all eligible students |
| 5 | `app/Console/Commands/SendQuizReminders.php` | **Created** | Scheduled command — runs every minute, sends reminders for quizzes starting within the notification window |
| 6 | `app/Console/Commands/ActivateQuizzes.php` | **Created** | Scheduled command — runs every minute, activates quizzes whose scheduled time has arrived |
| 7 | `resources/views/notifications/center.blade.php` | **Created** | View partial — displays quiz notifications to students with color-coded cards |
| 8 | `routes/console.php` | **Modified** | Added scheduling for both commands to run every minute |
| 9 | `app/Http/Controllers/QuizController.php` | **Modified** | Added import and replaced log line with `QuizPublished::dispatch($quiz)` in `publish()` method |

---

*End of document. Person 4 (Grading & Analytics) is not yet implemented.*
