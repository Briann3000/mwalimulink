<?php
// views/international_school_details.php - Modern High-Contrast Detail View
$authUser = auth_user();

$id = intval($_GET['id'] ?? 0);
$rec = R::load('international_school', $id);

if (!$rec->id) {
    echo '<div style="padding: 3rem; text-align: center;"><h2>International school not found.</h2><a href="/schools/international" class="btn-primary">Back to Directory</a></div>';
    return;
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="<?= !$authUser ? 'max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem;' : '' ?>">
        
        <!-- Breadcrumb & Back Action -->
        <div style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: #64748b;">
                <a href="/schools/international" style="color: #0f766e; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to International Schools
                </a>
                <span>/</span>
                <span style="color: #94a3b8;"><?= h($rec->country ?: 'Global') ?></span>
            </div>
            
            <a href="https://wa.me/254725788400?text=<?= urlencode('Inquiring about international school: ' . $rec->name . ' (' . ($rec->country ?? 'Global') . ')') ?>" target="_blank" style="background: #25d366; color: white !important; font-size: 0.8rem; font-weight: 600; padding: 7px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-brands fa-whatsapp"></i> Inquire via WhatsApp
            </a>
        </div>

        <!-- School Header Card -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0 0 6px; font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">
                        <?= h($rec->name) ?>
                    </h2>
                    <p style="margin: 0; font-size: 0.9rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-globe" style="color: #0f766e;"></i>
                        <span><?= h($rec->city ?: 'City') ?>, <?= h($rec->country ?: 'Country') ?></span>
                    </p>
                </div>
                <div>
                    <span style="background: #ede9fe; color: #6d28d9; font-size: 0.8rem; font-weight: 700; padding: 6px 12px; border-radius: 6px; border: 1px solid #ddd6fe;">
                        International School
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- School Specifications Table Card -->
            <div class="mwalimu-table-card">
                <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.9rem; color: #0f172a;">
                    <i class="fa fa-info-circle" style="color: #0f766e; margin-right: 6px;"></i> Institution Details
                </div>
                <table class="mwalimu-table">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background: white !important; color: #64748b !important;">Institution Name</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($rec->name) ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Category</th>
                            <td><span style="background: #ede9fe; color: #6d28d9; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">INTERNATIONAL</span></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Country</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($rec->country ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">City / Town</th>
                            <td><?= h($rec->city ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Address / Location</th>
                            <td><?= h($rec->address ?: 'N/A') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Global Placement Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h4 style="margin: 0 0 8px; font-size: 1rem; font-weight: 700; color: #0f172a;">
                        <i class="fa fa-plane-departure" style="color: #0f766e; margin-right: 6px;"></i> International Career Opportunities
                    </h4>
                    <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; margin: 0 0 1rem;">
                        Educators looking for global placements or international curriculum positions (IB, Cambridge IGCSE, British, American) can inquire directly or check our overseas guides.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/faqs-overseas" style="background: #f1f5f9; color: #0f766e; font-weight: 600; font-size: 0.82rem; padding: 8px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-book"></i> Overseas Guide
                    </a>
                    <a href="/teacher/jobs" class="btn-primary" style="font-size: 0.82rem; padding: 8px 14px;">
                        <i class="fa fa-search"></i> Browse International Openings
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>
