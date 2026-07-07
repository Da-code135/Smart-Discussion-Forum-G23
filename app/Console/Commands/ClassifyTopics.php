<?php

namespace App\Console\Commands;

use App\Services\TopicClassificationService;
use Illuminate\Console\Command;

class ClassifyTopics extends Command
{
    protected $signature = 'app:classify-topics {groupId?}';

    protected $description = 'Classify topics in a group';

    public function handle()
    {
        $groupId = $this->argument('groupId');

        if ($groupId) {
            $count = app(TopicClassificationService::class)->classifyGroupTopics($groupId);
            $this->info("Classified {$count} topics in group {$groupId}.");
        } else {
            $this->error('Please provide a group ID');
        }
    }
}
