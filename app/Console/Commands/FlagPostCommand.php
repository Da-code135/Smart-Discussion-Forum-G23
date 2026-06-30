<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class FlagPostCommand extends Command
{
    protected $signature = 'posts:flag {post : The ID of the post to flag}';

    protected $description = 'Flag a post for moderation review (MVP admin-only flagging)';

    public function handle(): int
    {
        $post = Post::find($this->argument('post'));

        if (! $post) {
            $this->error('Post not found.');

            return self::FAILURE;
        }

        if ($post->is_reported) {
            $this->warn("Post #{$post->id} is already flagged.");

            return self::SUCCESS;
        }

        $post->update(['is_reported' => true]);

        $this->info("Post #{$post->id} flagged for moderation.");

        return self::SUCCESS;
    }
}
