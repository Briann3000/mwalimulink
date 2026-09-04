<?php
// blog.php - Partial Template for Blog Content
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = htmlspecialchars($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);

// Base Query
$posts = R::find('posts', 
    'WHERE is_published = 1 
    AND (title LIKE :search OR content LIKE :search)
    AND (:category = 0 OR category_id = :category)
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset', 
    [
        ':search' => "%$search%",
        ':category' => $category_id,
        ':limit' => $per_page,
        ':offset' => $offset
    ]
);

// Categories
$categories = R::find('categories');
?>
<div class="container" style="max-width: 800px; margin: 3rem auto;">
    <article class="card">
<!-- Search Form -->
<form method="get" class="mb-4">
    <input type="hidden" name="action" value="blog">
    <input type="text" name="search" placeholder="Search articles..." value="<?= $search ?>">
    <select name="category">
        <option value="0">All Categories</option>
        <?php foreach($categories as $cat): ?>
            <option value="<?= $cat->id ?>" <?= ($category_id == $cat->id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat->name) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
</form>

<!-- Articles List -->
<div class="articles">
    <?php if(empty($posts)): ?>
        <p>No articles found.</p>
    <?php else: ?>
        <?php foreach($posts as $post): ?>
            <article>
                <h2>
                    <a href="/blog/view?id=<?= $post->id ?>">
                        <?= htmlspecialchars($post->title) ?>
                    </a>
                </h2>
                <p class="meta">
                    <?= date('M j, Y', strtotime($post->created_at)) ?> | 
                   
                    Category: <?= ($post->category && $post->category->name) ? htmlspecialchars($post->category->name) : 'Uncategorized' ?>
                </p>
                <div class="excerpt">
                    <?= htmlspecialchars(substr(strip_tags($post->content), 0, 200)) ?>...
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<div class="pagination">
    <?php if($page > 1): ?>
        <a href="/blog?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_id ?>">Previous</a>
    <?php endif; ?>
    
    <span>Page <?= $page ?></span>
    
    <?php if(count($posts) == $per_page): ?>
        <a href="/blog?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_id ?>">Next</a>
    <?php endif; ?>
</div>
</article>
</div>