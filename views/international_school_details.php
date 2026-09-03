<?php
// Assumes RedBeanPHP is already set up and connected.

if (!function_exists('h')) {
    function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}
$id = intval($_GET['id'] ?? 0);
$rec = R::load('international_school', $id);

if (!$rec->id): ?>
    <p>International school not found.</p>
<?php else: ?>
    <h2><?=h($rec->name)?></h2>
    <table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; max-width:600px;">
        <tr><th>Name</th><td><?=h($rec->name)?></td></tr>
        <tr><th>City</th><td><?=h($rec->city)?></td></tr>
        <tr><th>Country</th><td><?=h($rec->country)?></td></tr>
        <tr><th>Address</th><td><?=h($rec->address)?></td></tr>
        <tr><th>Contacts</th><td><?=h($rec->address)?></td></tr>
    </table>
    <p><a href="?action=international_school_search<?=isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : ''?>">Back to search</a></p>
<?php endif; ?>
