<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Tests\CreatesTestUsers;
use App\Models\TopicCategory;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use App\Models\Topic;
use Database\Seeders\TopicCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TopicCategoryModelTest extends TestCase
{
    use RefreshDatabase, CreatesTestUsers;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndGroups();
        $this->author = $this->createMember([
            'full_name' => 'Category Test User',
            'email' => 'category@test.com',
        ]);
    }

    public function test_category_belongs_to_group()
    {
        $category = TopicCategory::create([
            'group_id' => $this->defaultGroup->id,
            'category_name' => 'Test Category',
            'keyword_hints' => 'test, example, sample',
        ]);

        $this->assertInstanceOf(Group::class, $category->group);
        $this->assertEquals($this->defaultGroup->id, $category->group->id);
        $this->assertEquals($this->defaultGroup->group_name, $category->group->group_name);
    }

    public function test_category_has_many_posts()
    {
        $topic = Topic::create([
            'group_id' => $this->defaultGroup->id,
            'created_by' => $this->author->id,
            'title' => 'Category Posts Test',
            'description' => 'Testing hasMany posts on category',
            'status' => 'active',
            'post_type' => 'discussion',
        ]);

        $category = TopicCategory::create([
            'group_id' => $this->defaultGroup->id,
            'category_name' => 'Programming',
            'keyword_hints' => 'code, function, loop',
        ]);

        Post::create([
            'topic_id' => $topic->id,
            'user_id' => $this->author->id,
            'content' => 'Post in category',
            'category_id' => $category->id,
        ]);

        Post::create([
            'topic_id' => $topic->id,
            'user_id' => $this->author->id,
            'content' => 'Another post in category',
            'category_id' => $category->id,
        ]);

        Post::create([
            'topic_id' => $topic->id,
            'user_id' => $this->author->id,
            'content' => 'Uncategorized post',
        ]);

        $this->assertCount(2, $category->posts);
        foreach ($category->posts as $post) {
            $this->assertEquals($category->id, $post->category_id);
        }
    }

    public function test_seeder_creates_categories_for_all_groups()
    {
        $this->seed(TopicCategorySeeder::class);

        $groupCount = Group::count();
        $totalCategories = TopicCategory::count();

        $this->assertEquals($groupCount * 4, $totalCategories);

        foreach (Group::all() as $group) {
            $categoriesInGroup = TopicCategory::forGroup($group->id)->get();
            $this->assertCount(4, $categoriesInGroup);
        }
    }

    public function test_seeder_is_idempotent()
    {
        $this->seed(TopicCategorySeeder::class);
        $firstCount = TopicCategory::count();

        $this->seed(TopicCategorySeeder::class);
        $secondCount = TopicCategory::count();

        $this->assertEquals($firstCount, $secondCount);
    }

    public function test_seeder_creates_correct_category_names()
    {
        $this->seed(TopicCategorySeeder::class);

        $expectedNames = ['Mathematics', 'Programming', 'Science', 'General'];

        $categories = TopicCategory::forGroup($this->defaultGroup->id)
                                   ->pluck('category_name')
                                   ->toArray();

        foreach ($expectedNames as $name) {
            $this->assertContains($name, $categories);
        }
    }

    public function test_seeded_categories_have_keyword_hints()
    {
        $this->seed(TopicCategorySeeder::class);

        $categories = TopicCategory::forGroup($this->defaultGroup->id)->get();

        foreach ($categories as $category) {
            $this->assertNotNull($category->keyword_hints);
            $this->assertNotEmpty($category->keyword_hints);
        }
    }

    public function test_different_groups_can_have_same_category_name()
    {
        TopicCategory::create([
            'group_id' => $this->defaultGroup->id,
            'category_name' => 'Mathematics',
        ]);

        TopicCategory::create([
            'group_id' => $this->secondGroup->id,
            'category_name' => 'Mathematics',
        ]);

        $this->assertEquals(2, TopicCategory::where('category_name', 'Mathematics')->count());
    }

    public function test_scope_for_group_filters_correctly()
    {
        TopicCategory::create(['group_id' => $this->defaultGroup->id, 'category_name' => 'G1-Cat']);
        TopicCategory::create(['group_id' => $this->secondGroup->id, 'category_name' => 'G2-Cat']);

        $group1Categories = TopicCategory::forGroup($this->defaultGroup->id)->get();
        $group2Categories = TopicCategory::forGroup($this->secondGroup->id)->get();

        $this->assertCount(1, $group1Categories);
        $this->assertEquals('G1-Cat', $group1Categories->first()->category_name);
        $this->assertCount(1, $group2Categories);
        $this->assertEquals('G2-Cat', $group2Categories->first()->category_name);
    }
}
