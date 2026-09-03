<?php
// blog_admin.php using PDO

// Database connection (SQLite)
$db = new PDO('sqlite:./mwalimu.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Users array for authentication
$users = [
    'admin' => ['password' => 'Kenya@254', 'role' => 'admin'],
    'staff' => ['password' => 'Staff@123', 'role' => 'staff'],
];

$username = $_POST['username'] ?? $_GET['username'] ?? '';
$password = $_POST['password'] ?? $_GET['password'] ?? '';
$role = '';

// Authentication
if (!isset($users[$username]) || $users[$username]['password'] !== $password) {
    echo <<<HTML
<form method="post">
    <h2>Login</h2>
    <label>Username: <input type="text" name="username" required></label><br>
    <label>Password: <input type="password" name="password" required></label><br>
    <button type="submit">Login</button>
</form>
HTML;
    exit();
}

$role = $users[$username]['role'];

function create_slug($text) {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['content'])) {
    $id = (int)($_POST['id'] ?? 0);

    if (isset($_POST['delete']) && $role === 'admin') {
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Post deleted';
    } else {
        $title = $_POST['title'];
        $slug = create_slug($title);
        $content = $_POST['content'];
        $author = $username;
        $is_published = isset($_POST['is_published']) ? 1 : 0;
        $updated_at = date('Y-m-d H:i:s');
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($id > 0 && $role === 'admin') {
            $stmt = $db->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, author = ?, is_published = ?, category_id = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $author, $is_published, $category_id, $updated_at, $id]);
            $message = 'Post updated';
        } elseif ($role === 'admin' || $role === 'staff') {
            $created_at = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO posts (title, slug, content, author, is_published, category_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $author, $is_published, $category_id, $created_at, $updated_at]);
            $message = 'Post created';
        } else {
            $message = 'You are not allowed to post';
        }
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = ['id' => '', 'title' => '', 'slug' => '', 'content' => '', 'author' => '', 'is_published' => 0, 'category_id' => ''];
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC) ?: $post;
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$posts = $db->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

function include_wysiwyg() {
    echo <<<HTML
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/ui/trumbowyg.min.css">
<script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.28.0/dist/trumbowyg.min.js"></script>
<script>
\$(function(){
    \$('#content').trumbowyg({
        btns: [['viewHTML'], ['undo','redo'], ['strong','em','del'],
        ['link','insertImage'], ['unorderedList','orderedList']]
    });
});
</script>
HTML;
}

?><!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <?php include_wysiwyg(); ?>
</head>
<body>
<h1>Welcome, <?= htmlspecialchars($username) ?>!</h1>
<p>Role: <?= htmlspecialchars($role) ?></p>

<?php if (!empty($message)): ?>
    <div style="background: #cfc; padding: 0.5rem; margin: 1rem 0;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Post Form -->
<form method="post">
    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($post['id']) ?>">

    <label>Title:
        <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
    </label><br>

    <label>Category:
        <select name="category_id" required>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label><br>

    <label>Publish?
        <input type="checkbox" name="is_published" <?= ($post['is_published'] ?? 0) ? 'checked' : '' ?>>
    </label><br>

    <label>Content:<br>
        <textarea id="content" name="content" rows="10" cols="80"><?= htmlspecialchars($post['content']) ?></textarea>
    </label><br>

    <button type="submit">Save Post</button>

    <?php if (!empty($post['id']) && $role === 'admin'): ?>
        <button type="submit" name="delete" onclick="return confirm('Delete this post?')">Delete</button>
    <?php endif; ?>
</form>

<h2>Recent Posts</h2>
<ul>
<?php foreach ($posts as $p): ?>
    <li>
        <strong><?= htmlspecialchars($p['title']) ?></strong> -
        <a href="?action=blog_admin&id=<?= $p['id'] ?>&username=<?= urlencode($username) ?>&password=<?= urlencode($password) ?>">Edit</a>
    </li>
<?php endforeach; ?>
</ul>
</body>
</html>
