<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Reports table ===\n";
$reports = App\Models\Report::all();
foreach ($reports as $r) {
    echo "ID:{$r->id} | User:{$r->user_id} | Reason:{$r->reason} | Type:{$r->reportable_type} | ID:{$r->reportable_id}\n";
}

echo "\n=== Posts table ===\n";
$posts = App\Models\Post::all();
foreach ($posts as $p) {
    $reported = $p->is_reported ? 'true' : 'false';
    $removed = $p->is_removed ? 'true' : 'false';
    echo "ID:{$p->id} | is_reported:{$reported} | is_removed:{$removed} | Body:" . substr($p->content, 0, 50) . "\n";
}

echo "\n=== Moderation controller query ===\n";
$user = App\Models\User::find(4); // superadmin
echo "User: {$user->full_name} (ID:{$user->id})\n";
echo "Is System Admin: " . ($user->isSystemAdmin() ? 'YES' : 'NO') . "\n";

$reportedPosts = App\Models\Post::where('is_reported', true)->with(['topic', 'user'])->get();
echo "Reported posts found: " . $reportedPosts->count() . "\n";
foreach ($reportedPosts as $p) {
    echo "  ID:{$p->id} | Topic:{$p->topic->title} | User:{$p->user->full_name}\n";
}
