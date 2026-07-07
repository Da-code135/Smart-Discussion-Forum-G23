<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\GroupStatisticsController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\IpWhitelistController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\SystemConfigController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WarningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\WarningAcknowledgementController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SharedTopicController;
use App\Http\Controllers\StudentQuizController;
use App\Models\Topic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ============================================
// GUEST ROUTES (No authentication needed)
// ============================================

Route::get('/', function () {
    return redirect()->route('login');
});

// LOGIN ROUTES
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================
// PROTECTED ROUTES (Authentication required)
// ============================================

Route::middleware('auth')->group(function () {
    // DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'show'])
        ->name('dashboard');

    // RECOMMENDATIONS
    Route::get('/recommendations', [DashboardController::class, 'showRecommendations'])
        ->name('recommendations.index');

    // WARNING ACKNOWLEDGEMENT
    Route::get('/warning-acknowledgement', [
        WarningAcknowledgementController::class,
        'show',
    ])->name('warning-acknowledgement');
    Route::post('/warning-acknowledgement', [
        WarningAcknowledgementController::class,
        'acknowledge',
    ])->name('warning-acknowledgement.acknowledge');

    // PROFILE ROUTES (#66-#71)
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name(
        'profile.edit',
    );
    Route::put('/profile/edit', [ProfileController::class, 'update'])->name(
        'profile.update',
    );
    Route::get('/profile/picture', [
        ProfileController::class,
        'showPictureUpload',
    ])->name('profile.picture');
    Route::post('/profile/picture', [
        ProfileController::class,
        'uploadPicture',
    ])->name('profile.picture.upload');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name(
        'profile.destroy',
    );

    // ============================================
    // FORUM ROUTES (Task 2a — Topic creation & feed; Task 2b — Topic detail & replies)
    // ============================================

    Route::prefix('forum')
        ->name('forum.')
        ->group(function () {
            // prefix('forum') prepends the name forum to all urls inside the group and name prepends forum to all route names
            // Task 2a.2 & 2a.3: Create topic form, store, and feed
            Route::get('/', [
                ForumController::class,
                'index',
            ])->name('index');
            Route::get('/create', [
                ForumController::class,
                'create',
            ])->name('create');
            Route::post('/', [
                ForumController::class,
                'store',
            ])
                ->middleware('throttle.posts:topic')
                ->name('store');

            // Task 2b.1 & 2b.2: Topic detail with replies & reply form
            Route::get('/{topic}', [
                ForumController::class,
                'show',
            ])->name('show');
            Route::post('/{topic}/reply', [
                ForumController::class,
                'replyStore',
            ])
                ->middleware('throttle.posts:reply')
                ->name('reply.store');

            // Task 6.27: Edit & update topic
            Route::get('/{topic}/edit', [
                ForumController::class,
                'edit',
            ])->name('edit');
            Route::put('/{topic}', [
                ForumController::class,
                'update',
            ])->name('update');

            // Task 4.1: Exclude user from post visibility

            Route::post('/post/{post}/visibility/exclude', [
                ForumController::class,
                'excludeUser',
            ])->name('visibility.exclude');

            // Task 5.1: Export topic thread as PDF (throttled: 5 requests/minute to prevent DoS)
            Route::get('/{topic}/export-pdf', [
                ForumController::class,
                'exportPDF',
            ])
                ->middleware('throttle:5,1')
                ->name('export-pdf');
        });

    // NOTIFICATIONS
    Route::get('/notifications', [
        ForumController::class,
        'notifications',
    ])->name('notifications');
    Route::post('/notifications/{notificationId}/read', [
        ForumController::class,
        'markNotificationAsRead',
    ])->name('notifications.read');
});

// Topic sharing route (outside auth middleware)
Route::prefix('topics')->group(function () {
    Route::post('/{topic}/share', [
        ForumController::class,
        'shareTopic',
    ])->name('topics.share');
});

// Route for accessing shared topics with signed URL (?expires=...&signature=... appended by Laravel)
Route::get('/shared/topic/{topic}/{signedUserId}', [
    SharedTopicController::class,
    'show',
])->name('shared.topic.show');

// Email Verification Routes
Route::get('/verify-email', [
    EmailVerificationController::class,
    'show',
])->name('verify-email');
Route::get('/verify-email/verify', [
    EmailVerificationController::class,
    'verify',
])->name('verify-email.verify');

Route::middleware('auth')->group(function () {
    Route::post('/verify-email/resend', [
        EmailVerificationController::class,
        'resend',
    ])->name('verify-email.resend');
});

Route::middleware('guest')->group(function () {
    // ==================== REGISTRATION & ONBOARDING ROUTES (Task #49) ====================

    /**
     * GET /register
     * Task #41: Show registration form
     * Route name: 'register'
     */
    Route::get('/register', [RegisterController::class, 'showRegister'])->name(
        'register',
    );

    /**
     * POST /register
     * Task #43, #44, #45: Store and validate registration data
     * Route name: 'register.store'
     * Rate limited: 3 registrations per minute to prevent spam
     */
    Route::post('/register', [RegisterController::class, 'storeRegister'])
        ->name('register.store')
        ->middleware('throttle:3,60'); // 3 requests per 60 minutes

    /**
     * GET /onboarding
     * Task #46: Show onboarding/platform rules view
     * Route name: 'onboarding'
     */
    Route::get('/onboarding', [
        RegisterController::class,
        'showOnboarding',
    ])->name('onboarding');

    /**
     * POST /onboarding/agree
     * Task #47: Accept terms and create user
     * Route name: 'onboarding.agree'
     */
    Route::post('/onboarding/agree', [
        RegisterController::class,
        'agreeOnboarding',
    ])->name('onboarding.agree');

    /**
     * POST /onboarding/decline
     * Task #48: Decline terms, don't create user
     * Route name: 'onboarding.decline'
     */
    Route::post('/onboarding/decline', [
        RegisterController::class,
        'declineOnboarding',
    ])->name('onboarding.decline');

    // ==================== PASSWORD RESET ROUTES (Task #61) ====================

    /**
     * GET /forgot-password
     * Task #51: Show forgot password form
     * Route name: 'password.request'
     */
    Route::get('/forgot-password', [
        PasswordController::class,
        'showForgotPassword',
    ])->name('password.request');

    /**
     * POST /forgot-password
     * Task #53: Send password reset link
     * Route name: 'password.email'
     */
    Route::post('/forgot-password', [
        PasswordController::class,
        'sendResetLink',
    ])->name('password.email');

    /**
     * GET /reset-password/{token}
     * Task #55: Show password reset form
     * Route name: 'password.reset'
     */
    Route::get('/reset-password/{token}', [
        PasswordController::class,
        'showResetPassword',
    ])->name('password.reset');

    /**
     * POST /reset-password
     * Task #56: Process password reset
     * Route name: 'password.update'
     */
    Route::post('/reset-password', [
        PasswordController::class,
        'resetPassword',
    ])->name('password.update');
});

// Authenticated routes (logged-in users only)
Route::middleware('auth')->group(function () {
    // ==================== CHANGE PASSWORD ROUTES (Task #62) ====================

    /**
     * GET /change-password
     * Task #57, #58: Show change password form
     * Route name: 'password.change'
     */
    Route::get('/change-password', [
        PasswordController::class,
        'showChangePassword',
    ])->name('password.change');

    /**
     * POST /change-password
     * Task #59: Process password change
     * Route name: 'password.change.update'
     */
    Route::post('/change-password', [
        PasswordController::class,
        'updatePassword',
    ])->name('password.change.update');

    // Reports
    Route::post('/report', [ReportController::class, 'store'])->name(
        'report.store',
    );
});

// ============================================
// STUDENT QUIZ ROUTES (Quiz execution & timer — Person 3)
// ============================================

Route::middleware('auth')->group(function () {
    // Student: Dashboard listing all available quizzes
    // Separate path to avoid clashing with the lecturer's GET /quizzes
    Route::get('/my-quizzes', [StudentQuizController::class, 'index'])->name('quizzes.my-quizzes');

    Route::prefix('quizzes')->name('quizzes.')->group(function () {
        // Student: Quiz announcement page (shown BEFORE quiz starts)
        Route::get('/{quiz}/announcement', [
            StudentQuizController::class,
            'showAnnouncement',
        ])->name('announcement');

        // Student: Active quiz interface (countdown timer + questions)
        Route::get('/{quiz}/attempt', [
            StudentQuizController::class,
            'showQuiz',
        ])->name('attempt');

        // Student: Save a single answer (AJAX — called on each selection)
        Route::post('/{quiz}/answer', [
            StudentQuizController::class,
            'saveAnswer',
        ])->name('answer');

        // Student: Manual submit button
        Route::post('/{quiz}/submit', [
            StudentQuizController::class,
            'submitQuiz',
        ])->name('submit');

        // Student: Auto-submit when timer expires (JS-driven)
        Route::post('/{quiz}/auto-submit', [
            StudentQuizController::class,
            'autoSubmit',
        ])->name('auto-submit');

        // Student: Real-time quiz status (JSON — polled by JS every second)
        Route::get('/{quiz}/status', [
            StudentQuizController::class,
            'getStatus',
        ])->name('status');

        // Result page (stub — Person 4/5 will flesh out grading & results)
        Route::get('/{quiz}/result', [
            StudentQuizController::class,
            'showResult',
        ])->name('result');
    });

    // ============================================
    // QUIZ ROUTES (Lecturer quiz management)
    // ============================================

    Route::prefix('quizzes')->name('quizzes.')->group(function () {
        Route::get('/', [QuizController::class, 'index'])->name('index');
        Route::get('/create', [QuizController::class, 'create'])->name('create');
        Route::post('/', [QuizController::class, 'store'])->name('store');
        Route::get('/{quiz}/edit', [QuizController::class, 'edit'])->name('edit');
        Route::put('/{quiz}', [QuizController::class, 'update'])->name('update');
        Route::delete('/{quiz}', [QuizController::class, 'destroy'])->name('destroy');
        Route::post('/{quiz}/publish', [QuizController::class, 'publish'])->name('publish');

        // Performance report (lecturer/admin only)
        Route::get('/{quiz}/report', [QuizController::class, 'showPerformanceReport'])->name('report');

        // Nested: questions under quiz
        Route::post('/{quiz}/questions', [QuestionController::class, 'store'])->name('questions.store');
    });

    // Flat: question/answer delete routes (no nesting needed)
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::post('/questions/{question}/answers', [AnswerController::class, 'store'])->name('answers.store');
    Route::delete('/answers/{answer}', [AnswerController::class, 'destroy'])->name('answers.destroy');
});

// ============================================
// ADMIN ROUTES (Authentication + Admin check required)
// ============================================

Route::prefix('admin')
    ->middleware('admin')
    ->group(function () {
        // User Management (#88-#91) - All admins can view, but actions are controlled by policies
        Route::get('/users', [
            UserManagementController::class,
            'index',
        ])->name('admin.users.index');

        // User creation & deletion - System Admin only (must be before {user} route to avoid route conflict)
        Route::middleware(['system-admin'])->group(function () {
            Route::get('/users/create', [
                UserManagementController::class,
                'create',
            ])->name('admin.users.create');
            Route::post('/users', [
                UserManagementController::class,
                'store',
            ])->name('admin.users.store');
            Route::delete('/users/{user}', [
                UserManagementController::class,
                'destroy',
            ])->name('admin.users.destroy');
            Route::get('/users/{user}/reset-password', [
                UserManagementController::class,
                'showResetPassword',
            ])->name('admin.users.reset-password');
            Route::post('/users/{user}/reset-password', [
                UserManagementController::class,
                'resetPassword',
            ])->name('admin.users.reset-password.store');
            Route::get('/users/{user}/blacklist', [
                UserManagementController::class,
                'showBlacklist',
            ])->name('admin.users.blacklist');
            Route::post('/users/{user}/blacklist', [
                UserManagementController::class,
                'blacklist',
            ])->name('admin.users.blacklist.store');
            Route::post('/warnings/{warning}/resolve', [
                UserManagementController::class,
                'resolveWarning',
            ])->name('admin.warnings.resolve');
        });

        Route::get('/users/{user}', [
            UserManagementController::class,
            'show',
        ])->name('admin.users.show');
        Route::get('/users/{user}/edit', [
            UserManagementController::class,
            'edit',
        ])->name('admin.users.edit');
        Route::put('/users/{user}', [
            UserManagementController::class,
            'update',
        ])->name('admin.users.update');
        Route::post('/users/{user}/lift-blacklist', [
            UserManagementController::class,
            'liftBlacklist',
        ])->name('admin.users.lift-blacklist');
        Route::post('/users/{user}/change-role', [
            UserManagementController::class,
            'changeRole',
        ])->name('admin.users.change-role');

        // System Config (#92) - System Admin only
        Route::middleware(['system-admin'])->group(function () {
            Route::get('/system-config', [
                SystemConfigController::class,
                'index',
            ])->name('admin.system-config.index');
            Route::put('/system-config', [
                SystemConfigController::class,
                'update',
            ])->name('admin.system-config.update');

            // Group Statistics - System Admin only
            Route::get('/group-statistics', [
                GroupStatisticsController::class,
                'index',
            ])->name('admin.group-statistics.index');
            Route::get('/group-statistics/{group}', [
                GroupStatisticsController::class,
                'show',
            ])->name('admin.group-statistics.show');
        });

        // Admin Dashboard - All admins
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('admin.dashboard');

        // Moderation Panel - All admins (group-scoped in controller queries)
        Route::get('/moderation', [
            ModerationController::class,
            'index',
        ])->name('admin.moderation.index');
        Route::post('/moderation/{post}/remove', [
            ModerationController::class,
            'removePost',
        ])->name('admin.moderation.remove');
        Route::post('/moderation/{post}/ignore', [
            ModerationController::class,
            'ignoreReport',
        ])->name('admin.moderation.ignore');

        // Audit Logs - All admins
        Route::get('/audit-logs', [
            AuditLogController::class,
            'index',
        ])->name('admin.audit-logs.index');
        Route::get('/audit-logs/{log}', [
            AuditLogController::class,
            'show',
        ])->name('admin.audit-logs.show');
        Route::get('/audit-logs/export/{format?}', [
            AuditLogController::class,
            'export',
        ])->name('admin.audit-logs.export');

        // IP Whitelist - System Admin only
        Route::middleware(['system-admin'])->group(function () {
            Route::get('/ip-whitelist', [
                IpWhitelistController::class,
                'index',
            ])->name('admin.ip-whitelist.index');
            Route::get('/ip-whitelist/create', [
                IpWhitelistController::class,
                'create',
            ])->name('admin.ip-whitelist.create');
            Route::post('/ip-whitelist', [
                IpWhitelistController::class,
                'store',
            ])->name('admin.ip-whitelist.store');
            Route::get('/ip-whitelist/{ipWhitelist}/edit', [
                IpWhitelistController::class,
                'edit',
            ])->name('admin.ip-whitelist.edit');
            Route::put('/ip-whitelist/{ipWhitelist}', [
                IpWhitelistController::class,
                'update',
            ])->name('admin.ip-whitelist.update');
            Route::delete('/ip-whitelist/{ipWhitelist}', [
                IpWhitelistController::class,
                'destroy',
            ])->name('admin.ip-whitelist.destroy');
            Route::post('/ip-whitelist/{ipWhitelist}/activate', [
                IpWhitelistController::class,
                'activate',
            ])->name('admin.ip-whitelist.activate');
            Route::post('/ip-whitelist/{ipWhitelist}/deactivate', [
                IpWhitelistController::class,
                'deactivate',
            ])->name('admin.ip-whitelist.deactivate');
        });

        // Statistics Dashboard (Analytics module — Tasks 1 & 2)
        Route::get('/statistics', [
            StatisticsController::class,
            'index',
        ])->name('admin.statistics.index');
        Route::post('/statistics/{group}/recalculate', [
            StatisticsController::class,
            'recalculate',
        ])->name('admin.statistics.recalculate');

        // Group Management - All admins can view their groups, but actions are controlled by policies
        Route::get('/groups', [
            GroupController::class,
            'index',
        ])->name('admin.groups.index');

        // Group creation - System Admin only
        Route::middleware(['system-admin'])->group(function () {
            Route::get('/groups/create', [
                GroupController::class,
                'create',
            ])->name('admin.groups.create');
            Route::post('/groups', [
                GroupController::class,
                'store',
            ])->name('admin.groups.store');
            Route::post('/groups/bulk-assign', [
                GroupController::class,
                'bulkAssign',
            ])->name('admin.groups.bulk-assign');
        });

        // Group-specific actions - controlled by can-admin-group middleware
        Route::middleware(['can-admin-group'])->group(function () {
            Route::get('/groups/{group}/edit', [
                GroupController::class,
                'edit',
            ])->name('admin.groups.edit');
            Route::put('/groups/{group}', [
                GroupController::class,
                'update',
            ])->name('admin.groups.update');
            Route::delete('/groups/{group}', [
                GroupController::class,
                'destroy',
            ])->name('admin.groups.destroy');
            Route::get('/groups/{group}/members', [
                GroupController::class,
                'showMembers',
            ])->name('admin.groups.members');
            Route::put('/groups/{group}/members', [
                GroupController::class,
                'updateMembers',
            ])->name('admin.groups.update-members');
        });

        // Warning routes
        Route::prefix('warnings')->group(function () {
            Route::get('/', [WarningController::class, 'index'])->name(
                'admin.warnings.index',
            );
            Route::get('/{warning}', [WarningController::class, 'show'])->name(
                'admin.warnings.show',
            );
            Route::post('/', [WarningController::class, 'store'])->name(
                'admin.warnings.store',
            );
            Route::post('/{warning}', [
                WarningController::class,
                'update',
            ])->name('admin.warnings.update');
        });

        // Blacklist routes
        Route::prefix('blacklist')->group(function () {
            Route::get('/', [BlacklistController::class, 'index'])->name(
                'admin.blacklist.index',
            );
            Route::get('/{blacklistRecord}', [
                BlacklistController::class,
                'show',
            ])->name('admin.blacklist.show');
            Route::post('/', [BlacklistController::class, 'store'])->name(
                'admin.blacklist.store',
            );
            Route::post('/{blacklistRecord}', [
                BlacklistController::class,
                'update',
            ])->name('admin.blacklist.update');
        });
    });
