<?php
// blog_setup.php - One-time database setup
///////require_once 'includes/db.php'; // Your existing RedBeanPHP connection

try {
    // Create Categories Table
    R::exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )
    ");
    
    // Create Posts Table with Foreign Keys
    R::exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            author_id INTEGER,
            category_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME,
            is_published INTEGER DEFAULT 1,
            FOREIGN KEY (author_id) REFERENCES teacher(id),
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )
    ");
    
    // Add Initial Categories
    $categories = ['News', 'Teaching Opportunities', 'Education Articles','Teaching Tips'];
    foreach($categories as $cat) {
        if(!R::findOne('categories', 'name = ?', [$cat])) {
            $category = R::dispense('categories');
            $category->name = $cat;
            R::store($category);
        }
    }
    
    echo "<h1>News and Articles Setup Complete!</h1>";
    echo "<p>These tables were created:</p>";
    echo "<ul>
            <li><strong>categories</strong> (Articles Categories)</li>
            <li><strong>posts</strong> (Articles/News Posts)</li>
          </ul>";
    echo "<p>Sample categories added: " . implode(', ', $categories) . "</p>";
    echo "<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>";

} catch(Exception $e) {
    echo "<h1>Setup Error</h1>";
    echo "<p style='color: red'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Check your database permissions and connection settings.</p>";
}
?>
