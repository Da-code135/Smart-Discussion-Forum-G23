<div class="post-actions">
    @can('update', $post)
        <a href="{{ route('posts.edit', $post) }}" class="btn btn-primary">Edit Post</a>
    @endcan
    
    @auth
        <x-report-button type="post" :id="$post->id" />
    @endauth
</div>