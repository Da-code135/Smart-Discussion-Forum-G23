<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\WarningAcknowledgementController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Admin\WarningController;
use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\ReportController;
use App\Models\Topic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ============================================
// GUEST ROUTES (No authentication needed)
// ============================================

Route::get("/", function () {
    return redirect()->route("login");
});

// LOGIN ROUTES
Route::get("/login", [LoginController::class, "showLoginForm"])->name("login");
Route::post("/login", [LoginController::class, "authenticate"]);
Route::post("/logout", [LoginController::class, "logout"])->name("logout");

// ============================================
// PROTECTED ROUTES (Authentication required)
// ============================================

Route::middleware("auth")->group(function () {
    // DASHBOARD
    Route::get("/dashboard", function () {
        $user = Auth::user();

        $recentTopics = collect();
        $recommendedTopics = collect();

        if ($user->group_id) {
            $recentTopics = Topic::where("group_id", $user->group_id)
                ->where("status", "active")
                ->with("creator")
                ->withCount("posts")
                ->latest()
                ->take(5)
                ->get()
                ->map(
                    fn(Topic $topic) => [
                        "id" => $topic->id,
                        "title" => $topic->title,
                        "creator_name" => optional($topic->creator)->full_name ?? 'Deleted User',
                        "reply_count" => $topic->posts_count,
                        "created_at" => $topic->created_at,
                    ],
                );

            $recommendedTopics = Topic::where("group_id", $user->group_id)
                ->where("status", "active")
                ->withCount("posts")
                ->orderByDesc("posts_count")
                ->take(2)
                ->get()
                ->map(
                    fn(Topic $topic) => [
                        "id" => $topic->id,
                        "title" => $topic->title,
                        "member_count" => $topic->posts_count,
                    ],
                );
        }

        return view(
            "auth.dashboard",
            compact("recentTopics", "recommendedTopics"),
        );
    })->name("dashboard");

    // WARNING ACKNOWLEDGEMENT
    Route::get("/warning-acknowledgement", [
        WarningAcknowledgementController::class,
        "show",
    ])->name("warning-acknowledgement");
    Route::post("/warning-acknowledgement", [
        WarningAcknowledgementController::class,
        "acknowledge",
    ])->name("warning-acknowledgement.acknowledge");

    // PROFILE ROUTES (#66-#71)
    Route::get("/profile/edit", [ProfileController::class, "edit"])->name(
        "profile.edit",
    );
    Route::put("/profile/edit", [ProfileController::class, "update"])->name(
        "profile.update",
    );
    Route::get("/profile/picture", [
        ProfileController::class,
        "showPictureUpload",
    ])->name("profile.picture");
    Route::post("/profile/picture", [
        ProfileController::class,
        "uploadPicture",
    ])->name("profile.picture.upload");
    Route::delete("/profile", [ProfileController::class, "destroy"])->name(
        "profile.destroy",
    );

    // ============================================
    // FORUM ROUTES (Task 2a — Topic creation & feed; Task 2b — Topic detail & replies)
    // ============================================

    Route::prefix("forum")
        ->name("forum.")
        ->group(function () {
            //prefix('forum') prepends the name forum to all urls inside the group and name prepends forum to all route names
            // Task 2a.2 & 2a.3: Create topic form, store, and feed
            Route::get("/", [
                \App\Http\Controllers\ForumController::class,
                "index",
            ])->name("index");
            Route::get("/create", [
                \App\Http\Controllers\ForumController::class,
                "create",
            ])->name("create");
            Route::post("/", [
                \App\Http\Controllers\ForumController::class,
                "store",
            ])
                ->middleware("throttle.posts:topic")
                ->name("store");

            // Task 2b.1 & 2b.2: Topic detail with replies & reply form
            Route::get("/{topic}", [
                \App\Http\Controllers\ForumController::class,
                "show",
            ])->name("show");
            Route::post("/{topic}/reply", [
                \App\Http\Controllers\ForumController::class,
                "replyStore",
            ])
                ->middleware("throttle.posts:reply")
                ->name("reply.store");

            // Task 6.27: Edit & update topic
            Route::get("/{topic}/edit", [
                \App\Http\Controllers\ForumController::class,
                "edit",
            ])->name("edit");
            Route::put("/{topic}", [
                \App\Http\Controllers\ForumController::class,
                "update",
            ])->name("update");

            // Task 4.1: Exclude user from post visibility

            Route::post("/post/{post}/visibility/exclude", [
                \App\Http\Controllers\ForumController::class,
                "excludeUser",
            ])->name("visibility.exclude");

            // Task 5.1: Export topic thread as PDF (throttled: 5 requests/minute to prevent DoS)
            Route::get("/{topic}/export-pdf", [
                \App\Http\Controllers\ForumController::class,
                "exportPDF",
            ])
                ->middleware("throttle:5,1")
                ->name("export-pdf");
        });

    // NOTIFICATIONS
    Route::get("/notifications", [
        \App\Http\Controllers\ForumController::class,
        "notifications",
    ])->name("notifications");
    Route::post("/notifications/{notificationId}/read", [
        \App\Http\Controllers\ForumController::class,
        "markNotificationAsRead",
    ])->name("notifications.read");
});

// Topic sharing route (outside auth middleware)
Route::prefix("topics")->group(function () {
    Route::post("/{topic}/share", [
        \App\Http\Controllers\ForumController::class,
        "shareTopic",
    ])->name("topics.share");
});

// Route for accessing shared topics with signed URL (?expires=...&signature=... appended by Laravel)
Route::get("/shared/topic/{topic}/{signedUserId}", [
    App\Http\Controllers\SharedTopicController::class,
    "show",
])->name("shared.topic.show");

// Email Verification Routes
Route::get("/verify-email", [
    \App\Http\Controllers\Auth\EmailVerificationController::class,
    "show",
])->name("verify-email");
Route::get("/verify-email/verify", [
    \App\Http\Controllers\Auth\EmailVerificationController::class,
    "verify",
])->name("verify-email.verify");

Route::middleware("auth")->group(function () {
    Route::post("/verify-email/resend", [
        \App\Http\Controllers\Auth\EmailVerificationController::class,
        "resend",
    ])->name("verify-email.resend");
});

Route::middleware("guest")->group(function () {
    // ==================== REGISTRATION & ONBOARDING ROUTES (Task #49) ====================

    /**
     * GET /register
     * Task #41: Show registration form
     * Route name: 'register'
     */
    Route::get("/register", [RegisterController::class, "showRegister"])->name(
        "register",
    );

    /**
     * POST /register
     * Task #43, #44, #45: Store and validate registration data
     * Route name: 'register.store'
     * Rate limited: 3 registrations per minute to prevent spam
     */
    Route::post("/register", [RegisterController::class, "storeRegister"])
        ->name("register.store")
        ->middleware("throttle:3,60"); // 3 requests per 60 minutes

    /**
     * GET /onboarding
     * Task #46: Show onboarding/platform rules view
     * Route name: 'onboarding'
     */
    Route::get("/onboarding", [
        RegisterController::class,
        "showOnboarding",
    ])->name("onboarding");

    /**
     * POST /onboarding/agree
     * Task #47: Accept terms and create user
     * Route name: 'onboarding.agree'
     */
    Route::post("/onboarding/agree", [
        RegisterController::class,
        "agreeOnboarding",
    ])->name("onboarding.agree");

    /**
     * POST /onboarding/decline
     * Task #48: Decline terms, don't create user
     * Route name: 'onboarding.decline'
     */
    Route::post("/onboarding/decline", [
        RegisterController::class,
        "declineOnboarding",
    ])->name("onboarding.decline");

    // ==================== PASSWORD RESET ROUTES (Task #61) ====================

    /**
     * GET /forgot-password
     * Task #51: Show forgot password form
     * Route name: 'password.request'
     */
    Route::get("/forgot-password", [
        PasswordController::class,
        "showForgotPassword",
    ])->name("password.request");

    /**
     * POST /forgot-password
     * Task #53: Send password reset link
     * Route name: 'password.email'
     */
    Route::post("/forgot-password", [
        PasswordController::class,
        "sendResetLink",
    ])->name("password.email");

    /**
     * GET /reset-password/{token}
     * Task #55: Show password reset form
     * Route name: 'password.reset'
     */
    Route::get("/reset-password/{token}", [
        PasswordController::class,
        "showResetPassword",
    ])->name("password.reset");

    /**
     * POST /reset-password
     * Task #56: Process password reset
     * Route name: 'password.update'
     */
    Route::post("/reset-password", [
        PasswordController::class,
        "resetPassword",
    ])->name("password.update");
});

// Authenticated routes (logged-in users only)
Route::middleware("auth")->group(function () {
    // ==================== CHANGE PASSWORD ROUTES (Task #62) ====================

    /**
     * GET /change-password
     * Task #57, #58: Show change password form
     * Route name: 'password.change'
     */
    Route::get("/change-password", [
        PasswordController::class,
        "showChangePassword",
    ])->name("password.change");

    /**
     * POST /change-password
     * Task #59: Process password change
     * Route name: 'password.change.update'
     */
    Route::post("/change-password", [
        PasswordController::class,
        "updatePassword",
    ])->name("password.change.update");

    // Reports
    Route::post("/report", [ReportController::class, "store"])->name(
        "report.store",
    );
});

// ============================================
// ADMIN ROUTES (Authentication + Admin check required)
// ============================================

Route::prefix("admin")
    ->middleware("admin")
    ->group(function () {
        // User Management (#88-#91) - All admins can view, but actions are controlled by policies
        Route::get("/users", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "index",
        ])->name("admin.users.index");

        // User creation & deletion - System Admin only (must be before {user} route to avoid route conflict)
        Route::middleware(["system-admin"])->group(function () {
            Route::get("/users/create", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "create",
            ])->name("admin.users.create");
            Route::post("/users", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "store",
            ])->name("admin.users.store");
            Route::delete("/users/{user}", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "destroy",
            ])->name("admin.users.destroy");
            Route::get("/users/{user}/reset-password", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "showResetPassword",
            ])->name("admin.users.reset-password");
            Route::post("/users/{user}/reset-password", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "resetPassword",
            ])->name("admin.users.reset-password.store");
            Route::get("/users/{user}/blacklist", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "showBlacklist",
            ])->name("admin.users.blacklist");
            Route::post("/users/{user}/blacklist", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "blacklist",
            ])->name("admin.users.blacklist.store");
            Route::post("/warnings/{warning}/resolve", [
                \App\Http\Controllers\Admin\UserManagementController::class,
                "resolveWarning",
            ])->name("admin.warnings.resolve");
        });

        Route::get("/users/{user}", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "show",
        ])->name("admin.users.show");
        Route::get("/users/{user}/edit", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "edit",
        ])->name("admin.users.edit");
        Route::put("/users/{user}", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "update",
        ])->name("admin.users.update");
        Route::post("/users/{user}/lift-blacklist", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "liftBlacklist",
        ])->name("admin.users.lift-blacklist");
        Route::post("/users/{user}/change-role", [
            \App\Http\Controllers\Admin\UserManagementController::class,
            "changeRole",
        ])->name("admin.users.change-role");

        // System Config (#92) - System Admin only
        Route::middleware(["system-admin"])->group(function () {
            Route::get("/system-config", [
                \App\Http\Controllers\Admin\SystemConfigController::class,
                "index",
            ])->name("admin.system-config.index");
            Route::put("/system-config", [
                \App\Http\Controllers\Admin\SystemConfigController::class,
                "update",
            ])->name("admin.system-config.update");

            // Group Statistics - System Admin only
            Route::get("/group-statistics", [
                \App\Http\Controllers\Admin\GroupStatisticsController::class,
                "index",
            ])->name("admin.group-statistics.index");
            Route::get("/group-statistics/{group}", [
                \App\Http\Controllers\Admin\GroupStatisticsController::class,
                "show",
            ])->name("admin.group-statistics.show");
        });

        // Admin Dashboard - All admins
        Route::get("/dashboard", function () {
            return view("admin.dashboard");
        })->name("admin.dashboard");

        // Moderation Panel - All admins (group-scoped in controller queries)
        Route::get("/moderation", [
            \App\Http\Controllers\Admin\ModerationController::class,
            "index",
        ])->name("admin.moderation.index");
        Route::post("/moderation/{post}/remove", [
            \App\Http\Controllers\Admin\ModerationController::class,
            "removePost",
        ])->name("admin.moderation.remove");
        Route::post("/moderation/{post}/ignore", [
            \App\Http\Controllers\Admin\ModerationController::class,
            "ignoreReport",
        ])->name("admin.moderation.ignore");

        // Audit Logs - All admins
        Route::get("/audit-logs", [
            \App\Http\Controllers\Admin\AuditLogController::class,
            "index",
        ])->name("admin.audit-logs.index");
        Route::get("/audit-logs/{log}", [
            \App\Http\Controllers\Admin\AuditLogController::class,
            "show",
        ])->name("admin.audit-logs.show");
        Route::get("/audit-logs/export/{format?}", [
            \App\Http\Controllers\Admin\AuditLogController::class,
            "export",
        ])->name("admin.audit-logs.export");

        // IP Whitelist - System Admin only
        Route::middleware(["system-admin"])->group(function () {
            Route::get("/ip-whitelist", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "index",
            ])->name("admin.ip-whitelist.index");
            Route::get("/ip-whitelist/create", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "create",
            ])->name("admin.ip-whitelist.create");
            Route::post("/ip-whitelist", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "store",
            ])->name("admin.ip-whitelist.store");
            Route::get("/ip-whitelist/{ipWhitelist}/edit", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "edit",
            ])->name("admin.ip-whitelist.edit");
            Route::put("/ip-whitelist/{ipWhitelist}", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "update",
            ])->name("admin.ip-whitelist.update");
            Route::delete("/ip-whitelist/{ipWhitelist}", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "destroy",
            ])->name("admin.ip-whitelist.destroy");
            Route::post("/ip-whitelist/{ipWhitelist}/activate", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "activate",
            ])->name("admin.ip-whitelist.activate");
            Route::post("/ip-whitelist/{ipWhitelist}/deactivate", [
                \App\Http\Controllers\Admin\IpWhitelistController::class,
                "deactivate",
            ])->name("admin.ip-whitelist.deactivate");
        });

        // Group Management - All admins can view their groups, but actions are controlled by policies
        Route::get("/groups", [
            \App\Http\Controllers\Admin\GroupController::class,
            "index",
        ])->name("admin.groups.index");

        // Group creation - System Admin only
        Route::middleware(["system-admin"])->group(function () {
            Route::get("/groups/create", [
                \App\Http\Controllers\Admin\GroupController::class,
                "create",
            ])->name("admin.groups.create");
            Route::post("/groups", [
                \App\Http\Controllers\Admin\GroupController::class,
                "store",
            ])->name("admin.groups.store");
            Route::post("/groups/bulk-assign", [
                \App\Http\Controllers\Admin\GroupController::class,
                "bulkAssign",
            ])->name("admin.groups.bulk-assign");
        });

        // Group-specific actions - controlled by can-admin-group middleware
        Route::middleware(["can-admin-group"])->group(function () {
            Route::get("/groups/{group}/edit", [
                \App\Http\Controllers\Admin\GroupController::class,
                "edit",
            ])->name("admin.groups.edit");
            Route::put("/groups/{group}", [
                \App\Http\Controllers\Admin\GroupController::class,
                "update",
            ])->name("admin.groups.update");
            Route::delete("/groups/{group}", [
                \App\Http\Controllers\Admin\GroupController::class,
                "destroy",
            ])->name("admin.groups.destroy");
            Route::get("/groups/{group}/members", [
                \App\Http\Controllers\Admin\GroupController::class,
                "showMembers",
            ])->name("admin.groups.members");
            Route::put("/groups/{group}/members", [
                \App\Http\Controllers\Admin\GroupController::class,
                "updateMembers",
            ])->name("admin.groups.update-members");
        });

        // Warning routes
        Route::prefix("warnings")->group(function () {
            Route::get("/", [WarningController::class, "index"])->name(
                "admin.warnings.index",
            );
            Route::get("/{warning}", [WarningController::class, "show"])->name(
                "admin.warnings.show",
            );
            Route::post("/", [WarningController::class, "store"])->name(
                "admin.warnings.store",
            );
            Route::post("/{warning}", [
                WarningController::class,
                "update",
            ])->name("admin.warnings.update");
        });

        // Blacklist routes
        Route::prefix("blacklist")->group(function () {
            Route::get("/", [BlacklistController::class, "index"])->name(
                "admin.blacklist.index",
            );
            Route::get("/{blacklistRecord}", [
                BlacklistController::class,
                "show",
            ])->name("admin.blacklist.show");
            Route::post("/", [BlacklistController::class, "store"])->name(
                "admin.blacklist.store",
            );
            Route::post("/{blacklistRecord}", [
                BlacklistController::class,
                "update",
            ])->name("admin.blacklist.update");
        });
    });
