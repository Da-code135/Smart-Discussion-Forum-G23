<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\Post;
use App\Models\PostVisibility;
use App\Models\User;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends Controller
{
    /**
     * ============================================
     * Task 2a.3 — Forum Feed (Topic List)
     * ============================================
     *
     * Display all active topics in the authenticated user's group,
     * paginated and ordered by most recent first.
     *
     * Security: group_id is hard-filtered so topics from other groups
     * never leak into this view (defense in depth).
     */
    public function index()//this fetches all topics from the user's group and displays them in a list
    {
        $topics = Topic::where('group_id', Auth::user()->group_id)//this queries the Topic model to fetch topics where groupId matches the logged-in user's group. This prevents cross-group data leaks
                        ->where('status', 'active')//only show topics whose status is active
                        ->with('creator') // Eager load creator (avoids N+1) -> this line tells laravel "When you fetch the topics, also fetch the creator (user) for each topic"
                        ->withCount('posts')// // Add posts_count property (total replies) without extra database queries> Adds a count of related records to each topic
                        ->latest()//Sorts the results by the created_at column in descending order (newest first) It's a shortcut for ->orderBy('created_at', 'desc')
                        ->paginate(10);//Split the results into pages of 10 items each

        return view('forum.index', compact('topics')); //compact passes data to the template compact('topics') is shorthand for ['topics' => $topics] It takes the variable $topics and makes it available in the view
    }

    /**
     * ============================================
     * Task 2a.2 — Show Create Topic Form
     * ============================================
     *
     * Display the form where a member types a title and description
     * for a new discussion topic.
     */
    public function create()
    {
        return view('forum.create-topic');
    }

    /**
     * ============================================
     * Task 2a.2 — Store a New Topic
     * ============================================
     *
     * Validate input, create the Topic record scoped to the user's group,
     * then redirect to the forum feed with a success message.
     *
     * Security: group_id is taken from auth()->user() — the user cannot
     * override it via the form payload. This prevents cross-group topic creation.
     */
    public function store(Request $request)//validates the from data, saves the topic to the database, and redirects to the forum feed
    {
        $request->validate([
            'title'       => 'required|max:255|unique:topics,title',
            'description' => 'required|string|max:10000',
            'post_type'   => 'sometimes|in:discussion,question',//sometimes means the field is optional and validation can pass without it being submitted
        ]);                                                     //in:discussion,question means the value must be on of these two

        Topic::create([
            'title'       => $request->title,
            'description' => $request->description,
            'post_type'   => $request->post_type ?? 'discussion',
            'created_by'  => Auth::id(), //sets the creator to the logged in user's ID
            'group_id'    => Auth::user()->group_id,   // Critical: scoped to user's group
            'status'      => 'active',
        ]);

        return redirect()->route('forum.index')
                         ->with('success', 'Topic created successfully!');
    }

    /**
     * ============================================
     * Task 2b.1 — Topic Detail with Threading
     * ============================================
     *
     * Display a single topic with all its replies (posts).
     *
     * Security — Group isolation check:
     * Even if a user guesses a topic ID from another group,
     * they get a 403 Forbidden. This is the second line of defense
     * (the first is the group_id filter in index()).
     *
     * Performance — Nested eager loading:
     * Loads the topic + all posts + all post authors in exactly
     * 3 queries total, regardless of how many replies exist.
     */
    public function show(Topic $topic)
    {
        // === GROUP ISOLATION CHECK (Defense in depth) ===
        if ($topic->group_id !== Auth::user()->group_id) {//checks whether the groupId for the topic is the same as that for the logged in user
            abort(403, 'You do not have access to this topic.');
        }

        // === NESTED EAGER LOADING ===
        // Load all replies (posts) for this topic, filtered:
        //   1. Only non-removed posts (moderation soft-delete)
        //   2. Only posts the current user is not excluded from (visibility rules)
        //   3. Order by oldest first (chronological thread)
        //   4. Eager load the author to avoid N+1
        $topic->load(['posts' => function ($query) {
            $query->notRemoved()
                  ->visibleToUser(Auth::id())
                  ->orderBy('created_at', 'asc')//will list the replies in order of creation, newest appears at the bottom
                  ->with('user'); //this means "When loading posts, also load the related users immediately."
        }]);

        // Pre-load users eligible for exclusion (same group, not current user)
        // to avoid N+1 queries inside the Blade loop
        $excludableUsers = User::where('group_id', Auth::user()->group_id)
            ->where('id', '!=', Auth::id())
            ->get();

        /*Get all users in the same group as the logged-in user,
           excluding the logged-in user themselves.
           These users will be displayed as options that the author
           can choose to exclude from viewing individual posts.*/

        return view('forum.show', compact('topic', 'excludableUsers'));//this just makes the topic and excludableUsers available in the blade template
    }

    /**
     * ============================================
     * Task 2b.2 — Store a Reply
     * ============================================
     *
     * Create a new Post record as a reply to the given topic.
     *
     * Security checks:
     *   1. Topic must belong to the user's group (group isolation)
     *   2. Topic must be active (not archived)
     *
     * Design note: There is no separate 'replies' table.
     * Posts ARE the replies — each Post record with a topic_id
     * is a reply to that topic. The topic's own 'description'
     * field serves as the opening post content.
     */
    public function replyStore(Request $request, Topic $topic)
    {
        // === GROUP ISOLATION CHECK ===
        if ($topic->group_id !== Auth::user()->group_id) {
            abort(403, 'You do not have access to this topic.');
        }

        // === STATUS CHECK — Only active topics accept replies ===
        if ($topic->status !== 'active') {
            return back()->with('error', 'This topic is closed for replies.');
        }

        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        Post::create([
            'topic_id' => $topic->id,
            'user_id'  => Auth::id(),
            'content'  => $request->content,
        ]);

        return redirect()->route('forum.show', $topic->id)
                         ->with('success', 'Reply posted successfully!');
    }

    /**
     * ============================================
     * Task 4.1 — Exclude User from Post Visibility
     * ============================================
     *
     * Allow the post author to exclude specific users from seeing their post.
     * This creates a record in the post_visibility table to hide the post from the excluded user.
     * 
     * Security: Users can only exclude others in their own group
     */
    public function excludeUser(Request $request, Post $post)
    {
        // Only the post author can exclude users
        if ($post->user_id !== Auth::id()) {
            abort(403, 'Only the post author can exclude users.');
        }

        // Validate the user_id input
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        // Get the user to be excluded
        $userToExclude = User::findOrFail($request->user_id);

        // Ensure the user being excluded belongs to the same group as the post author
        if ($userToExclude->group_id !== Auth::user()->group_id) {
            abort(403, 'You can only exclude users in your own group.');
        }

        // Check if rule already exists
        $existing = PostVisibility::where('post_id', $post->id)
                                  ->where('excluded_user_id', $request->user_id)
                                  ->first();

        if (!$existing) {
            PostVisibility::create([
                'post_id' => $post->id,
                'excluded_user_id' => $request->user_id,
            ]);
        }

        return redirect()->back()->with([
            'success' => 'User excluded from this post.',
            'post_id' => $post->id
        ]);
    }

    /**
     * ============================================
     * Task 5.1 — Export Topic Thread as PDF
     * ============================================
     *
     * Generate a formatted PDF of the topic thread including the opening
     * post (topic description) and all visible replies.
     *
     * Security:
     *   1. Group isolation check — topic must belong to user's group
     *   2. Visibility rules — excluded posts are filtered from the PDF
     *   3. Moderation — removed posts are excluded
     */
    public function exportPDF(Topic $topic)
    {
        // === GROUP ISOLATION CHECK ===
        if ($topic->group_id !== Auth::user()->group_id) {
            abort(403, 'You do not have access to this topic.');
        }

        // Load visible, non-removed replies with their authors
        $replies = Post::where('topic_id', $topic->id)
            ->notRemoved()
            ->visibleToUser(Auth::id())
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // Load the topic creator and group for the PDF
        $topic->load(['creator', 'group']);

        // Log the export for audit trail
        app(AuditLogService::class)->log(
            action: 'topic.exported',
            target: $topic,
            newValues: ['format' => 'pdf'],
            description: Auth::user()->full_name . ' exported topic "' . $topic->title . '" as PDF'
        );

        $pdf = Pdf::loadView('forum.export-pdf', [
            'topic' => $topic,
            'replies' => $replies,
        ]);

        return $pdf->download('topic-' . $topic->id . '.pdf');
    }
}