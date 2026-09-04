<?php
// views/tp_hub.php - Teaching Practice (TP) & Student Placement Hub
$authUser = auth_user();

$countyFilter = trim($_GET['county'] ?? '');
$subjectFilter = trim($_GET['subject'] ?? '');

// 1. Fetch any specific TP job postings (using exact columns from MySQL job table)
$tpOpenings = R::find('job', 'title LIKE ? OR description LIKE ? ORDER BY id DESC LIMIT 6', [
    '%TP%', '%Intern%'
]);

// 2. Fetch schools accepting TP interns (Case-insensitive matching for uppercase database values)
$featuredSchools = [];
if ($countyFilter !== '') {
    $countyUpper = strtoupper($countyFilter);
    $featuredSchools = R::find('public_school', 'UPPER(county) = ? AND level LIKE ? LIMIT 18', [$countyUpper, '%SECONDARY%']);
    if (empty($featuredSchools)) {
        $featuredSchools = R::find('public_school', 'UPPER(county) = ? LIMIT 18', [$countyUpper]);
    }
    if (empty($featuredSchools)) {
        $featuredSchools = R::find('school', 'UPPER(county) LIKE ? LIMIT 18', ["%$countyUpper%"]);
    }
} else {
    // Default featured secondary schools across Kenya
    $featuredSchools = R::find('public_school', 'level LIKE ? ORDER BY id DESC LIMIT 18', ['%SECONDARY%']);
    if (empty($featuredSchools)) {
        $featuredSchools = R::find('public_school', 'ORDER BY id DESC LIMIT 18');
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Content Pane -->
    <main class="content-pane" style="padding-top: 1.75rem !important; <?= !$authUser ? 'max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem;' : '' ?>">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="margin: 0 0 6px; font-size: 1.45rem; color: #0f172a; font-weight: 800;">🎓 Teaching Practice (TP) & Student Placement Hub</h2>
            <p style="margin: 0; font-size: 0.88rem; color: #64748b; line-height: 1.5;">
                Connect university & college student educators with secondary and junior schools accepting Teaching Practice (TP) placements across Kenya.
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <form method="GET" action="/tp-hub" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block;">County Placement</label>
                        <select name="county" style="width: 100%; height: 42px; box-sizing: border-box; margin: 0; background: #ffffff;">
                            <option value="">All 47 Counties</option>
                            <?php foreach (kenyan_counties() as $c): ?>
                                <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block;">Subject Combination</label>
                        <input type="text" name="subject" value="<?= h($subjectFilter) ?>" placeholder="e.g. Kiswahili / CRE, Math / Physics" style="width: 100%; height: 42px; box-sizing: border-box; margin: 0; background: #ffffff;">
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn-primary" style="height: 42px; flex: 1; margin: 0; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            <i class="fa fa-search"></i> Search Placements
                        </button>
                        <?php if ($countyFilter || $subjectFilter): ?>
                            <a href="/tp-hub" style="height: 42px; padding: 0 14px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; text-decoration: none;">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- TP Guidelines for Student Teachers -->
        <div style="background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 6px; font-size: 0.95rem; font-weight: 700; color: #0f766e;">
                <i class="fa fa-info-circle"></i> Teaching Practice (TP) Placement Guidelines
            </h4>
            <p style="margin: 0; font-size: 0.84rem; color: #115e59; line-height: 1.5;">
                When applying for TP slots, prepare your university introduction letter and national ID. School principals and heads of department can view your subject combinations, academic qualifications, and contact details directly.
            </p>
        </div>

        <!-- Schools Offering Placement & Active TP Vacancies Grid -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1.25rem; color: #0f172a;">
            Schools Accepting Placement Requests <?= $countyFilter ? 'in ' . h($countyFilter) : '' ?>
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <?php if (!empty($featuredSchools)): ?>
                <?php foreach ($featuredSchools as $sc): ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; gap: 8px;">
                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                    <?= h($sc->name) ?>
                                </h4>
                                <span style="background: #dcfce7; color: #15803d; font-size: 0.68rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; white-space: nowrap; border: 1px solid #bbf7d0;">
                                    Accepting TP
                                </span>
                            </div>
                            <p style="margin: 0 0 8px; font-size: 0.8rem; color: #64748b;">
                                <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($sc->county ?: 'Kenya') ?> &bull; <?= h($sc->constituency ?? $sc->level ?? 'Secondary') ?>
                            </p>
                            <?php if (!empty($sc->phone_number) || !empty($sc->phone)): ?>
                                <p style="margin: 0 0 12px; font-size: 0.8rem; color: #475569;">
                                    <i class="fa fa-phone"></i> <?= h($sc->phone_number ?? $sc->phone) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <?php if (!empty($sc->email)): ?>
                                <a href="mailto:<?= h($sc->email) ?>?subject=<?= urlencode('Teaching Practice Placement Inquiry - ' . $sc->name) ?>" style="background: #f0fdfa; color: #0f766e !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #ccfbf1;">
                                    <i class="fa fa-envelope"></i> Email Institution
                                </a>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: #94a3b8;"><i class="fa fa-building"></i> Verified Institution</span>
                            <?php endif; ?>
                            <a href="/schools/public/detail?id=<?= $sc->id ?>" style="font-size: 0.82rem; font-weight: 700; color: #0f766e; text-decoration: none;">
                                View School &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; background: white; padding: 2.5rem; text-align: center; border-radius: 8px; border: 1px solid #e2e8f0; color: #94a3b8;">
                    No TP institutions found matching your current filter. Try selecting "All 47 Counties".
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>