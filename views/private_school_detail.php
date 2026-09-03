<?php
// Assumes RedBeanPHP is already loaded and R::setup() called in master layout

$id = intval($_GET['id'] ?? 0);
$school = R::load('private_school', $id);

if (!$school->id) {
    echo "<h2>School not found.</h2>";
    return;
}
?>

<h2><?= htmlspecialchars($school->name) ?></h2>
<table>
    <tr><th>Level</th><td><?= htmlspecialchars($school->level) ?></td></tr>
    <tr><th>Status</th><td><?= htmlspecialchars($school->status) ?></td></tr>
    <tr><th>County</th><td><?= htmlspecialchars($school->county) ?></td></tr>
    <tr><th>Constituency</th><td><?= htmlspecialchars($school->constituency) ?></td></tr>
    <tr><th>Province</th><td><?= htmlspecialchars($school->province) ?></td></tr>
    <tr><th>District</th><td><?= htmlspecialchars($school->district) ?></td></tr>
    <tr><th>Division</th><td><?= htmlspecialchars($school->division) ?></td></tr>
    <tr><th>Location</th><td><?= htmlspecialchars($school->location) ?></td></tr>
    <tr><th>Email</th><td><?= htmlspecialchars($school->email) ?></td></tr>
    <tr><th>Latitude</th><td><?= htmlspecialchars($school->latitude) ?></td></tr>
    <tr><th>Longitude</th><td><?= htmlspecialchars($school->longitude) ?></td></tr>
   <!-- <tr><th>Created At</th><td><? // htmlspecialchars($school->created_at) ?></td></tr>-->
</table>

<?php if (!empty($school->latitude) && !empty($school->longitude)): ?>
    <h3>Location Map</h3>
    <div id="map" style="height:350px; width:100%; margin:1em 0;"></div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var lat = <?= floatval($school->latitude) ?>;
        var lon = <?= floatval($school->longitude) ?>;
        var map = L.map('map').setView([lat, lon], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);
        L.marker([lat, lon]).addTo(map)
            .bindPopup("<?= htmlspecialchars(addslashes($school->name)) ?>")
            .openPopup();
    </script>
<?php else: ?>
    <p><em>No map location available for this school.</em></p>
<?php endif; ?>
