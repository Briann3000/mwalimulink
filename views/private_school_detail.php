<?php
// views/private_school_detail.php - Modern High-Contrast Detail View
$authUser = auth_user();

$id = intval($_GET['id'] ?? 0);
$school = R::load('private_school', $id);

if (!$school->id) {
    echo '<div style="padding: 3rem; text-align: center;"><h2>Private school not found.</h2><a href="/schools/private" class="btn-primary">Back to Directory</a></div>';
    return;
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="<?= !$authUser ? 'max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem;' : '' ?>">
        
        <!-- Breadcrumb & Actions -->
        <div style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: #64748b;">
                <a href="/schools/private" style="color: #0f766e; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to Private Academies
                </a>
                <span>/</span>
                <span style="color: #94a3b8;"><?= h($school->county) ?></span>
            </div>
            
            <?php if (!empty($school->email)): ?>
                <a href="mailto:<?= h($school->email) ?>?subject=<?= urlencode('Teaching Application - ' . $school->name) ?>" class="btn-primary" style="font-size: 0.82rem; padding: 7px 16px;">
                    <i class="fa fa-envelope"></i> Send Official Application
                </a>
            <?php else: ?>
                <a href="/teacher/jobs" class="btn-primary" style="font-size: 0.82rem; padding: 7px 16px;">
                    <i class="fa fa-briefcase"></i> Browse Teaching Jobs
                </a>
            <?php endif; ?>
        </div>

        <!-- School Header Card -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0 0 6px; font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">
                        <?= h($school->name) ?>
                    </h2>
                    <p style="margin: 0; font-size: 0.9rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i>
                        <span><?= h($school->county) ?> &bull; <?= h($school->constituency ?? $school->district ?? 'Kenya') ?></span>
                    </p>
                </div>
                <div>
                    <span style="background: #fef3c7; color: #92400e; font-size: 0.8rem; font-weight: 700; padding: 6px 12px; border-radius: 6px; border: 1px solid #fde68a;">
                        <?= h($school->level ?: 'Private Academy') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- School Specifications Table Card -->
            <div class="mwalimu-table-card">
                <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.9rem; color: #0f172a;">
                    <i class="fa fa-info-circle" style="color: #0f766e; margin-right: 6px;"></i> Administrative Details
                </div>
                <table class="mwalimu-table">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background: white !important; color: #64748b !important;">Institution Level</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($school->level ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Status</th>
                            <td><span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;"><?= h($school->status ?: 'PRIVATE') ?></span></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">County</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($school->county ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Constituency</th>
                            <td><?= h($school->constituency ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">District / Sub-County</th>
                            <td><?= h($school->district ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Division / Zone</th>
                            <td><?= h($school->division ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Location / Ward</th>
                            <td><?= h($school->location ?: 'N/A') ?></td>
                        </tr>
                        <?php if (!empty($school->email)): ?>
                            <tr>
                                <th style="background: white !important; color: #64748b !important;">Email Address</th>
                                <td><a href="mailto:<?= h($school->email) ?>" style="color: #0f766e;"><?= h($school->email) ?></a></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Geographic Location Card & Map -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column;">
                <div style="font-weight: 700; font-size: 0.9rem; color: #0f172a; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa fa-map-location-dot" style="color: #0f766e; margin-right: 6px;"></i> Geographic Location</span>
                    <?php if (!empty($school->latitude) && !empty($school->longitude)): ?>
                        <span style="font-size: 0.75rem; color: #64748b; font-weight: normal;">
                            <?= number_format($school->latitude, 4) ?>, <?= number_format($school->longitude, 4) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($school->latitude) && !empty($school->longitude) && floatval($school->latitude) != 0): ?>
                    <div id="map" style="height: 280px; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; flex: 1;"></div>
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var lat = <?= floatval($school->latitude) ?>;
                            var lon = <?= floatval($school->longitude) ?>;
                            var map = L.map('map').setView([lat, lon], 14);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; OpenStreetMap'
                            }).addTo(map);
                            L.marker([lat, lon]).addTo(map)
                                .bindPopup("<b><?= htmlspecialchars(addslashes($school->name)) ?></b><br><?= htmlspecialchars(addslashes($school->county)) ?>")
                                .openPopup();
                        });
                    </script>
                <?php else: ?>
                    <div style="height: 200px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.85rem;">
                        <i class="fa fa-map-pin" style="font-size: 1.5rem; margin-bottom: 6px; opacity: 0.5;"></i>
                        <span>GPS Coordinates not indexed for this location</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>
