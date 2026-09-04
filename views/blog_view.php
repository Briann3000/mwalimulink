<?php
// views/blog_view.php — Full blog post view

$post_id = (int)($_GET['id'] ?? 0);
$post = R::load('posts', $post_id);

// Redirect if post not found or unpublished
if (!$post->id || !$post->is_published) {
    echo "<p>Post not found or unpublished.</p>";
    return;
}

// Safely fetch and sanitize post properties
$post_title = htmlspecialchars($post->title ?? 'Untitled');
$post_content = $post->content ?? '';
$post_created = $post->created_at ?? '';

// Fetch and sanitize category
$category = $post->category;
$category_name = $category && !is_null($category->name) ? htmlspecialchars($category->name) : 'Uncategorized';
?>

<article class="blog-post">
    
    <h1><?= $post_title ?></h1>
    <p class="meta">
        <?= $post_created ? date('F j, Y', strtotime($post_created)) : 'Unknown date' ?> |
        Category: <?= $category_name ?>
    </p>
    <div class="content">
        <?= $post_content ?>
    </div>
</article>


<p><a href="/blog">← Back to Resources</a></p>
