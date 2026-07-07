<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\BlacklistController;
use App\Http\Controllers\Api\Admin\BulkOperationController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Api\Admin\GroupStatisticsController;
use App\Http\Controllers\Api\Admin\IpWhitelistController as AdminIpWhitelistController;
use App\Http\Controllers\Api\Admin\ModerationController;
use App\Http\Controllers\Api\Admin\SearchController;
use App\Http\Controllers\Api\Admin\SystemConfigController as AdminSystemConfigController;
use App\Http\Controllers\Api\Admin\WarningController;
use App\Http\Controllers\Api\AnswerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\GroupBrowseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostVisibilityController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\QuizNotificationController;
use App\Http\Controllers\Api\StudentQuizController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/
| Protected routes require auth:sanctum middleware
| Rate limited to 60 requests per minute
|
*/
$API_VERSION = 'v1';
Route::prefix($API_VERSION)->group(function () {
    // Apply security headers to all API routes
    Route::middleware('api.security')->group(function () {
        // ============================================
        // PUBLIC ROUTES (No authentication required)
        // ============================================

        /**
         * POST /api/v1/register
         * Register new user and return API token
         */
        Route::post('/register', [AuthController::class, 'register']);

        /**
         * POST /api/v1/login
         * Authenticate user and return API token
         */
        Route::post('/login', [AuthController::class, 'login']);

        /**
         * POST /api/v1/password/forgot
         * Send password reset link to email
         */
        Route::post('/password/forgot', [PasswordController::class, 'forgot']);

        /**
         * POST /api/v1/password/reset
         * Reset password with token
         */
        Route::post('/password/reset', [PasswordController::class, 'reset']);

        // ============================================
        // PROTECTED ROUTES (Authentication required)
        // ============================================

        Route::middleware('auth:sanctum')->group(function () {
            /**
             * POST /api/v1/logout
             * Revoke current API token
             */
            Route::post('/logout', [AuthController::class, 'logout']);

            /**
             * DELETE /api/v1/account
             * Delete user account
             */
            Route::delete('/account', [AuthController::class, 'deleteAccount']);

            /**
             * POST /api/v1/token/refresh
             * Refresh API token
             */
            Route::post('/token/refresh', [
                AuthController::class,
                'refreshToken',
            ]);

            /**
             * GET /api/v1/tokens
             * List all active tokens
             */
            Route::get('/tokens', [AuthController::class, 'listTokens']);

            /**
             * DELETE /api/v1/tokens/{tokenId}
             * Revoke specific token
             */
            Route::delete('/tokens/{tokenId}', [
                AuthController::class,
                'revokeToken',
            ]);

            /**
             * GET /api/v1/me
             * Get authenticated user data with role and group
             */
            Route::get('/me', [UserController::class, 'me']);

            /**
             * POST /api/v1/profile
             * Update user profile (full_name, email)
             */
            Route::post('/profile', [ProfileController::class, 'update']);

            /**
             * POST /api/v1/password/change
             * Change user password
             */
            Route::post('/password/change', [
                PasswordController::class,
                'change',
            ]);

            /**
             * POST /api/v1/email/verify
             * Verify email address
             */
            Route::post('/email/verify', [
                EmailVerificationController::class,
                'verify',
            ]);

            /**
             * POST /api/v1/email/resend
             * Resend verification email
             */
            Route::post('/email/resend', [
                EmailVerificationController::class,
                'resend',
            ]);

            // ============================================
            // FORUM ROUTES (Topics & Posts)
            // ============================================

            // Topics
            Route::get('/topics', [TopicController::class, 'index']); // T1: List topics
            Route::get('/topics/type/{type}', [
                TopicController::class,
                'byType',
            ]); // T7: Filter by type
            Route::post('/topics', [TopicController::class, 'store'])
                ->middleware('throttle.posts:topic'); // T3: Create topic (anti-flood)
            Route::get('/topics/{topicId}', [TopicController::class, 'show']); // T2: Topic detail
            Route::put('/topics/{topicId}', [TopicController::class, 'update']); // T4: Update topic
            Route::delete('/topics/{topicId}', [
                TopicController::class,
                'destroy',
            ]); // T5: Archive topic

            // Topic Export & Share (E1-E2)
            Route::get('/topics/{topicId}/export/pdf', [
                TopicController::class,
                'exportPDF',
            ]); // E1: Export topic as PDF
            Route::post('/topics/{topicId}/share', [
                TopicController::class,
                'share',
            ]); // E2: Generate share link

            // Share access (no auth required — validated via signed URL)
            Route::get('/topics/{topicId}/shared', [
                TopicController::class,
                'sharedAccess',
            ])
                ->name('topics.share.access')
                ->withoutMiddleware('auth:sanctum');

            // Posts within topics
            Route::get('/topics/{topicId}/posts', [
                TopicController::class,
                'posts',
            ]); // T6: List posts
            Route::post('/topics/{topicId}/posts', [
                PostController::class,
                'store',
            ])->middleware('throttle.posts:reply'); // P1: Create reply (anti-flood)

            // Standalone post operations
            Route::put('/posts/{postId}', [PostController::class, 'update']); // P2: Update post
            Route::delete('/posts/{postId}', [
                PostController::class,
                'destroy',
            ]); // P3: Delete post

            // Post Visibility (P5-P7)
            Route::get('/posts/{postId}/visibility', [
                PostVisibilityController::class,
                'index',
            ]); // P7: List excluded users
            Route::post('/posts/{postId}/visibility/exclude', [
                PostVisibilityController::class,
                'exclude',
            ]); // P5: Exclude user
            Route::delete('/posts/{postId}/visibility/{userId}', [
                PostVisibilityController::class,
                'removeExclusion',
            ]); // P6: Remove exclusion

            // Categories (C1-C2: User-facing, group-scoped)
            Route::get('/categories', [CategoryController::class, 'index']); // C1: List categories
            Route::get('/categories/{categoryId}/topics', [
                CategoryController::class,
                'topics',
            ]); // C2: Topics in category

            // Group Browsing (G1-G4: User-facing, group isolation enforced)
            Route::get('/groups', [GroupBrowseController::class, 'index']); // G1: List my groups
            Route::get('/groups/{groupId}', [
                GroupBrowseController::class,
                'show',
            ]); // G2: Group detail
            Route::get('/groups/{groupId}/topics', [
                GroupBrowseController::class,
                'topics',
            ]); // G3: Group topics
            Route::get('/groups/{groupId}/members', [
                GroupBrowseController::class,
                'members',
            ]); // G4: Group members

            // Notifications (N1-N5)
            Route::get('/me/notifications', [
                NotificationController::class,
                'index',
            ]); // N1: List my notifications
            Route::get('/me/notifications/unread-count', [
                NotificationController::class,
                'unreadCount',
            ]); // N2: Unread notification count
            Route::post('/notifications/read-all', [
                NotificationController::class,
                'readAll',
            ]); // N3: Mark all as read
            Route::post('/notifications/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]); // N4: Mark single notification as read
            Route::delete('/notifications/{id}', [
                NotificationController::class,
                'destroy',
            ]); // N5: Delete a notification

            // Topic Toggles (N3-N4)
            Route::post('/topics/{topicId}/toggle-answered', [
                TopicController::class,
                'toggleAnswered',
            ]); // N3: Toggle answered
            Route::post('/topics/{topicId}/toggle-pinned', [
                TopicController::class,
                'togglePinned',
            ]); // N4: Toggle pinned

            // ============================================
            // QUIZ NOTIFICATIONS API (Person 3 — must be before {quiz} param routes)
            // ============================================

            // Upcoming & live are static segments — they must be defined BEFORE the
            // parameterized {quiz} routes below so "upcoming" and "live" aren't
            // mistaken for quiz IDs by the route model binding.
            Route::get('/quizzes/upcoming', [QuizNotificationController::class, 'upcoming']);
            Route::get('/quizzes/live', [QuizNotificationController::class, 'live']);

            // Student result — shares the quizzes prefix with the student quiz routes
            Route::get('/quizzes/{quiz}/result', [GradeController::class, 'myResult']);

            // ============================================
            // STUDENT QUIZ ROUTES (Quiz execution & timer)
            // ============================================
            Route::prefix('quizzes')->group(function () {
                Route::get('/{quiz}/announcement', [StudentQuizController::class, 'announcement']);
                Route::get('/{quiz}/status', [StudentQuizController::class, 'status']);
                Route::post('/{quiz}/attempt', [StudentQuizController::class, 'start']);
                Route::get('/{quiz}/attempt', [StudentQuizController::class, 'showAttempt']);
                Route::post('/{quiz}/answer', [StudentQuizController::class, 'saveAnswer']);
                Route::post('/{quiz}/answers/batch', [StudentQuizController::class, 'saveAnswersBatch']);
                Route::post('/{quiz}/submit', [StudentQuizController::class, 'submit']);
                Route::post('/{quiz}/auto-submit', [StudentQuizController::class, 'autoSubmit']);
            });

            // ============================================
            // RESULTS & REPORTS API (Person 3 — Lecturer grades + export)
            // ============================================

            // Lecturer grade management — admin-scoped, uses "lecturer" prefix
            // for semantic clarity (these are the lecturer-facing grade endpoints)
            Route::middleware('admin')->prefix('lecturer')->group(function () {
                Route::get('/quizzes/{quiz}/grades', [GradeController::class, 'index']);
                Route::get('/quizzes/{quiz}/grades/export', [GradeController::class, 'exportCsv']);
                Route::get('/grades/{grade}', [GradeController::class, 'show']);
            });

            // ============================================
            // QUIZ NOTIFICATIONS API (Person 3 — student history + notifications)
            // ============================================

            Route::prefix('me')->group(function () {
                Route::get('/quiz-history', [QuizNotificationController::class, 'history']);
                Route::get('/quiz-notifications', [QuizNotificationController::class, 'quizNotifications']);
            });

            // Quiz notification mark-as-read is handled by NotificationController above (N4)

            // ============================================
            // QUIZ API (Lecturer/Admin — Quiz CRUD + Questions + Answers)
            // ============================================
            Route::middleware('admin')->group(function () {
                Route::prefix('quizzes')->name('quizzes.')->group(function () {
                    Route::get('/', [QuizController::class, 'index']);
                    Route::post('/', [QuizController::class, 'store']);
                    Route::get('/{quiz}', [QuizController::class, 'show']);
                    Route::put('/{quiz}', [QuizController::class, 'update']);
                    Route::delete('/{quiz}', [QuizController::class, 'destroy']);
                    Route::post('/{quiz}/publish', [QuizController::class, 'publish']);
                    Route::get('/{quiz}/report', [QuizController::class, 'report']);
                    // Questions (nested under quizzes)
                    Route::get('/{quiz}/questions', [QuestionController::class, 'index']);
                    Route::post('/{quiz}/questions', [QuestionController::class, 'store']);
                    Route::put('/{quiz}/questions/{question}', [QuestionController::class, 'update']);
                    Route::delete('/{quiz}/questions/{question}', [QuestionController::class, 'destroy']);
                    Route::put('/{quiz}/questions/reorder', [QuestionController::class, 'reorder']);
                });
                // Answers (not nested — uses explicit question binding)
                Route::get('/questions/{question}/answers', [AnswerController::class, 'index']);
                Route::post('/questions/{question}/answers', [AnswerController::class, 'store']);
                Route::put('/answers/{answer}', [AnswerController::class, 'update']);
                Route::delete('/answers/{answer}', [AnswerController::class, 'destroy']);
            });

            // ============================================
            // ADMIN ROUTES (Admin access required)
            // ============================================

            Route::prefix('admin')
                ->middleware('admin')
                ->group(function () {
                    // User Management (All admins)
                    Route::get('/users', [AdminUserController::class, 'index']);
                    Route::get('/users/{userId}', [
                        AdminUserController::class,
                        'show',
                    ]);
                    Route::post('/users/{userId}/lift-blacklist', [
                        AdminUserController::class,
                        'liftBlacklist',
                    ]);
                    Route::post('/users/{userId}/warn', [
                        AdminUserController::class,
                        'warn',
                    ]);

                    // User CRUD (System Admin only for create/delete — enforced in controller)
                    Route::post('/users', [AdminUserController::class, 'store']);
                    Route::put('/users/{userId}', [AdminUserController::class, 'update']);
                    Route::delete('/users/{userId}', [AdminUserController::class, 'destroy']);
                    Route::post('/users/{userId}/reset-password', [AdminUserController::class, 'resetPassword']);

                    // User role management (System Admin only - enforced in controller)
                    Route::post('/users/{userId}/change-role', [
                        AdminUserController::class,
                        'changeRole',
                    ]);

                    // Post Moderation (All admins, group-scoped — enforced in controller)
                    Route::get('/moderation', [ModerationController::class, 'index']);
                    Route::post('/moderation/{post}/remove', [ModerationController::class, 'removePost']);
                    Route::post('/moderation/{post}/ignore', [ModerationController::class, 'ignoreReport']);

                    // Warning Management (W1-W4, All admins, group-scoped)
                    Route::get('/warnings', [
                        WarningController::class,
                        'index',
                    ]); // W1: List warnings
                    Route::get('/warnings/{warningId}', [
                        WarningController::class,
                        'show',
                    ]); // W2: Show warning
                    Route::post('/users/{userId}/warnings', [
                        WarningController::class,
                        'store',
                    ]); // W3: Issue warning
                    Route::post('/warnings/{warningId}/resolve', [
                        WarningController::class,
                        'resolve',
                    ]); // W4: Resolve warning

                    // Blacklist Management (W5-W7, All admins, group-scoped)
                    Route::get('/blacklist-records', [
                        BlacklistController::class,
                        'index',
                    ]); // W5: List blacklist records
                    Route::post('/users/{userId}/blacklist', [
                        BlacklistController::class,
                        'store',
                    ]); // W6: Blacklist user
                    Route::post('/blacklist-records/{recordId}/lift', [
                        BlacklistController::class,
                        'lift',
                    ]); // W7: Lift blacklist

                    // Category Management (C3-C5: Admin only)
                    Route::post('/categories', [
                        CategoryController::class,
                        'store',
                    ]); // C3: Create category
                    Route::put('/categories/{categoryId}', [
                        CategoryController::class,
                        'update',
                    ]); // C4: Update category
                    Route::delete('/categories/{categoryId}', [
                        CategoryController::class,
                        'destroy',
                    ]); // C5: Delete category

                    // Group Management (All admins can view, actions enforced by policies)
                    Route::get('/groups', [
                        AdminGroupController::class,
                        'index',
                    ]);
                    Route::get('/groups/{groupId}', [
                        AdminGroupController::class,
                        'show',
                    ]);
                    Route::get('/groups/{groupId}/members', [
                        AdminGroupController::class,
                        'showMembers',
                    ]);
                    Route::put('/groups/{groupId}/members', [
                        AdminGroupController::class,
                        'updateMembers',
                    ]);

                    // Group CRUD (System Admin only for create/delete)
                    Route::post('/groups', [
                        AdminGroupController::class,
                        'store',
                    ]);
                    Route::put('/groups/{groupId}', [
                        AdminGroupController::class,
                        'update',
                    ]);
                    Route::delete('/groups/{groupId}', [
                        AdminGroupController::class,
                        'destroy',
                    ]);

                    // Group admin management (System Admin only)
                    Route::post('/groups/{groupId}/admins', [
                        AdminGroupController::class,
                        'addAdmin',
                    ]);
                    Route::delete('/groups/{groupId}/admins/{userId}', [
                        AdminGroupController::class,
                        'removeAdmin',
                    ]);

                    // System Configuration (System Admin only)
                    Route::get('/system-config', [
                        AdminSystemConfigController::class,
                        'index',
                    ]);
                    Route::put('/system-config', [
                        AdminSystemConfigController::class,
                        'update',
                    ]);
                    Route::get('/system-config/{key}', [
                        AdminSystemConfigController::class,
                        'show',
                    ]);

                    // Audit Logs (All admins)
                    Route::get('/audit-logs', [
                        AdminAuditLogController::class,
                        'index',
                    ]);
                    Route::get('/audit-logs/actions', [
                        AdminAuditLogController::class,
                        'getActions',
                    ]);
                    Route::get('/audit-logs/{logId}', [
                        AdminAuditLogController::class,
                        'show',
                    ]);
                    Route::get('/audit-logs/export/{format}', [
                        AdminAuditLogController::class,
                        'export',
                    ]);

                    // IP Whitelist (System Admin only)
                    Route::get('/ip-whitelist', [
                        AdminIpWhitelistController::class,
                        'index',
                    ]);
                    Route::get('/ip-whitelist/check/{ip}', [
                        AdminIpWhitelistController::class,
                        'check',
                    ]);
                    Route::get('/ip-whitelist/{ipId}', [
                        AdminIpWhitelistController::class,
                        'show',
                    ]);
                    Route::post('/ip-whitelist', [
                        AdminIpWhitelistController::class,
                        'store',
                    ]);
                    Route::put('/ip-whitelist/{ipId}', [
                        AdminIpWhitelistController::class,
                        'update',
                    ]);
                    Route::delete('/ip-whitelist/{ipId}', [
                        AdminIpWhitelistController::class,
                        'destroy',
                    ]);
                    Route::post('/ip-whitelist/{ipId}/activate', [
                        AdminIpWhitelistController::class,
                        'activate',
                    ]);
                    Route::post('/ip-whitelist/{ipId}/deactivate', [
                        AdminIpWhitelistController::class,
                        'deactivate',
                    ]);

                    // Bulk Operations (Phase 4E)
                    Route::prefix('bulk')->group(function () {
                        Route::post('/change-roles', [
                            BulkOperationController::class,
                            'changeRoles',
                        ]);
                        Route::post('/change-status', [
                            BulkOperationController::class,
                            'changeStatus',
                        ]);
                        Route::post('/assign-group', [
                            BulkOperationController::class,
                            'assignGroup',
                        ]);
                        Route::post('/blacklist', [
                            BulkOperationController::class,
                            'blacklist',
                        ]);
                        Route::post('/lift-blacklist', [
                            BulkOperationController::class,
                            'liftBlacklist',
                        ]);
                        Route::post('/warn', [
                            BulkOperationController::class,
                            'warn',
                        ]);
                        Route::post('/assign-group-admins', [
                            BulkOperationController::class,
                            'assignGroupAdmins',
                        ]);
                    });

                    // Advanced Search (Phase 4E)
                    Route::prefix('search')->group(function () {
                        Route::post('/users', [
                            SearchController::class,
                            'searchUsers',
                        ]);
                        Route::post('/groups', [
                            SearchController::class,
                            'searchGroups',
                        ]);
                        Route::post('/audit-logs', [
                            SearchController::class,
                            'searchAuditLogs',
                        ]);
                        Route::post('/warnings', [
                            SearchController::class,
                            'searchWarnings',
                        ]);
                        Route::get('/options/{model}', [
                            SearchController::class,
                            'getOptions',
                        ]);
                        Route::get('/suggestions/{type}', [
                            SearchController::class,
                            'getSuggestions',
                        ]);
                    });

                    // Dashboard & Group Statistics (P5)
                    Route::get('/dashboard', [DashboardController::class, 'index']);
                    Route::get('/group-statistics', [GroupStatisticsController::class, 'index']);
                    Route::get('/group-statistics/{group}', [GroupStatisticsController::class, 'show']);
                });
        });
    });
});
