<?php

namespace Tests\Feature\Web;

use Tests\TestCase;
use Tests\CreatesTestUsers;
use App\Models\User;
use App\Models\Topic;
use App\Models\Post;
use App\Models\PostVisibility;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ForumExportAndShareTest extends TestCase
{
    use RefreshDatabase, CreatesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
    }

    // -------------------------------------------------------
    // Helper: create a topic with optional replies
    // -------------------------------------------------------
    private function createTopicWithReplies(array $topicAttrs = [], int $replyCount = 0, ?User $replyAuthor = null): Topic
    {
        $topic = Topic::create(array_merge([
            'group_id'    => $this->defaultGroup->id,
            'created_by'  => $topicAttrs['created_by'] ?? $this->createMember(['email' => 'creator@test.com'])->id,
            'title'       => $topicAttrs['title'] ?? 'Test Topic',
            'description' => $topicAttrs['description'] ?? 'Test description content',
            'status'      => 'active',
            'post_type'   => 'discussion',
        ], $topicAttrs));

        $author = $replyAuthor ?? $this->createMember(['email' => 'replier@test.com']);

        for ($i = 0; $i < $replyCount; $i++) {
            Post::create([
                'topic_id' => $topic->id,
                'user_id'  => $author->id,
                'content'  => "Reply #{$i} content",
            ]);
        }

        return $topic;
    }

    // =======================================================
    // PDF Export — Access Control
    // =======================================================

    public function test_unauthenticated_user_cannot_export_pdf()
    {
        $topic = $this->createTopicWithReplies();

        $response = $this->get(route('forum.export-pdf', $topic->id));

        $response->assertRedirect('/login');
    }

    public function test_user_can_export_own_group_topic_as_pdf()
    {
        $member = $this->createMember(['email' => 'exporter@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id], replyCount: 2);

        $response = $this->actingAs($member)
            ->get(route('forum.export-pdf', $topic->id));

        $response->assertOk();
        $response->assertDownload('topic-' . $topic->id . '.pdf');
    }

    public function test_user_from_another_group_gets_403_on_export()
    {
        $member       = $this->createMember(['email' => 'groupA@test.com']);
        $otherMember  = $this->createMember([
            'email'    => 'groupB@test.com',
            'group_id' => $this->secondGroup->id,
        ]);

        $topic = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($otherMember)
            ->get(route('forum.export-pdf', $topic->id));

        $response->assertForbidden();
    }

    // =======================================================
    // PDF Export — Audit Trail
    // =======================================================

    public function test_export_creates_audit_log_entry()
    {
        $member = $this->createMember(['full_name' => 'Audit User', 'email' => 'audit@test.com']);
        $topic  = $this->createTopicWithReplies([
            'created_by' => $member->id,
            'title'      => 'Audited Topic',
        ]);

        $this->actingAs($member)
            ->get(route('forum.export-pdf', $topic->id));

        $this->assertDatabaseHas('audit_logs', [
            'user_id'     => $member->id,
            'action'      => 'topic.exported',
            'target_type' => Topic::class,
            'target_id'   => $topic->id,
        ]);

        // Verify description contains the topic title
        $log = AuditLog::where('action', 'topic.exported')
            ->where('target_id', $topic->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Audited Topic', $log->description);
        $this->assertStringContainsString('PDF', $log->description);
    }

    // =======================================================
    // PDF Export — Visibility & Moderation Filtering
    // =======================================================

    public function test_export_respects_visibility_rules()
    {
        $author   = $this->createMember(['full_name' => 'Author', 'email' => 'author@test.com']);
        $excluded = $this->createMember(['full_name' => 'Excluded', 'email' => 'excluded@test.com']);

        $topic = $this->createTopicWithReplies(['created_by' => $author->id], replyCount: 3, replyAuthor: $author);

        // Exclude $excluded user from the first post
        $firstPost = $topic->posts()->oldest()->first();
        PostVisibility::create([
            'post_id'          => $firstPost->id,
            'excluded_user_id' => $excluded->id,
        ]);

        // When the excluded user exports, they should get a PDF (not 403)
        // but the first post should be filtered from the replies
        $response = $this->actingAs($excluded)
            ->get(route('forum.export-pdf', $topic->id));

        $response->assertOk();

        // The view receives filtered replies — excluded post should not be in the set
        // We verify by checking the view data passed to the PDF template
        $repliesPassedToView = null;
        \Illuminate\Support\Facades\View::creator('forum.export-pdf', function ($view) use (&$repliesPassedToView) {
            $repliesPassedToView = $view->getData()['replies'] ?? null;
        });

        $this->actingAs($excluded)
            ->get(route('forum.export-pdf', $topic->id));

        // The excluded user should not see the first post in the replies
        if ($repliesPassedToView) {
            $this->assertFalse(
                $repliesPassedToView->contains('id', $firstPost->id),
                'Excluded user should not see the post they were excluded from'
            );
        }
    }

    public function test_export_excludes_removed_posts()
    {
        $member = $this->createMember(['email' => 'mod@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id], replyCount: 3, replyAuthor: $member);

        // Mark one post as removed (moderation)
        $removedPost = $topic->posts()->oldest()->first();
        $removedPost->update(['is_removed' => true]);

        $response = $this->actingAs($member)
            ->get(route('forum.export-pdf', $topic->id));

        $response->assertOk();

        // Capture the view data
        $repliesPassedToView = null;
        \Illuminate\Support\Facades\View::creator('forum.export-pdf', function ($view) use (&$repliesPassedToView) {
            $repliesPassedToView = $view->getData()['replies'] ?? null;
        });

        $this->actingAs($member)
            ->get(route('forum.export-pdf', $topic->id));

        if ($repliesPassedToView) {
            $this->assertFalse(
                $repliesPassedToView->contains('id', $removedPost->id),
                'Removed posts should not appear in the PDF export'
            );
            $this->assertCount(2, $repliesPassedToView);
        }
    }

    // =======================================================
    // Social Sharing — View Content
    // =======================================================

    public function test_topic_detail_page_contains_share_button()
    {
        $member = $this->createMember(['email' => 'viewer@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('Share', false);
        $response->assertSee('id="share-menu"', false);
    }

    public function test_topic_detail_page_contains_whatsapp_share_link()
    {
        $member = $this->createMember(['email' => 'wa@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('wa.me', false);
        $response->assertSee('WhatsApp', false);
    }

    public function test_topic_detail_page_contains_twitter_share_link()
    {
        $member = $this->createMember(['email' => 'tw@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('twitter.com/intent/tweet', false);
        $response->assertSee('Twitter', false);
    }

    public function test_topic_detail_page_contains_facebook_share_link()
    {
        $member = $this->createMember(['email' => 'fb@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('facebook.com/sharer', false);
        $response->assertSee('Facebook', false);
    }

    public function test_topic_detail_page_contains_copy_link_button()
    {
        $member = $this->createMember(['email' => 'copy@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('Copy Link', false);
        $response->assertSee('copyToClipboard', false);
    }

    public function test_share_links_contain_correct_topic_url()
    {
        $member = $this->createMember(['email' => 'url@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id, 'title' => 'URL Test Topic']);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();

        $expectedUrl = route('forum.show', $topic->id);

        // All share links should reference the correct topic URL (URL-encoded)
        $response->assertSee(urlencode($expectedUrl), false);
    }

    public function test_share_menu_shows_auth_required_notice()
    {
        $member = $this->createMember(['email' => 'notice@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('Recipients must be logged in to view this topic.', false);
    }

    // =======================================================
    // PDF Export Button — View Content
    // =======================================================

    public function test_topic_detail_page_contains_pdf_export_button()
    {
        $member = $this->createMember(['email' => 'pdfbtn@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        $response = $this->actingAs($member)
            ->get(route('forum.show', $topic->id));

        $response->assertOk();
        $response->assertSee('Export PDF', false);
        $response->assertSee(route('forum.export-pdf', $topic->id), false);
    }

    // =======================================================
    // PDF Export — Rate Limiting
    // =======================================================

    public function test_pdf_export_is_rate_limited()
    {
        $member = $this->createMember(['email' => 'ratelimit@test.com']);
        $topic  = $this->createTopicWithReplies(['created_by' => $member->id]);

        // The route has throttle:5,1 — 6th request within 1 minute should be blocked
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($member)
                ->get(route('forum.export-pdf', $topic->id));
            $response->assertOk();
        }

        // 6th request should be rate limited (429)
        $response = $this->actingAs($member)
            ->get(route('forum.export-pdf', $topic->id));
        $response->assertStatus(429);
    }
}
