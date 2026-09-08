<?php
// admin_dashboard.php - Operational Admin Control Center
require_auth('admin');

$authUser = auth_user();

// Core KPIs
$teacherCount = R::count('teacher');
$verifiedTeacherCount = R::count('teacher', 'verification_status = ?', ['verified']);

// Queue items: Pending manual document audits + Unresolved automated TSC audits
$pendingDocVerifications = R::count('teacher', "verification_status = 'pending' OR (good_conduct_doc IS NOT NULL AND (verification_status IS NULL OR verification_status = 'pending'))");
$pendingTscAudits = R::count('tscverificationlog', 'is_resolved IS NULL OR is_resolved = 0');
$pendingVerificationCount = $pendingDocVerifications + $pendingTscAudits;

$schoolCount = R::count('school');
$proSchoolCount = R::count('school', 'status = ? AND subscription_expiry >= NOW()', ['active']);

$jobCount = R::count('job');
$publicationCount = R::count('publications');

// Recent Registered Teachers (Top 5)
$recentTeachers = R::find('teacher', 'ORDER BY id DESC LIMIT 5');

// Recent Registered Schools (Top 5)
$recentSchools = R::find('school', 'ORDER BY id DESC LIMIT 5');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1100px; margin: 0 auto;">
            
            <!-- Dashboard Header -->
            <div style="margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 1.4rem; color: #0f172a; font-weight: 800;">Administrator Control Center</h1>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                        System overview and real-time operational metrics &bull; Logged in as <strong><?= h($authUser['email']) ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/admin/verifications" class="btn-primary" style="padding: 8px 18px; font-size: 0.85rem;">
                        <i class="fa fa-shield-check"></i> Clearance Queue (<?= $pendingVerificationCount ?>)
                    </a>
                </div>
            </div>

            <!-- KPI Metric Badges -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                
                <div class="metric-card" style="border-left: 4px solid #0f766e;">
                    <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Educators</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 4px 0;"><?= number_format($teacherCount) ?></div>
                    <div style="font-size: 0.78rem; color: #16a34a; font-weight: 600;">
                        <i class="fa fa-check-circle"></i> <?= $verifiedTeacherCount ?> Verified
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #2563eb;">
                    <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Registered Schools</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 4px 0;"><?= number_format($schoolCount) ?></div>
                    <div style="font-size: 0.78rem; color: #2563eb; font-weight: 600;">
                        <i class="fa fa-crown"></i> <?= $proSchoolCount ?> Pro Subscribers
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #f59e0b;">
                    <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Clearance Queue</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 4px 0;"><?= $pendingVerificationCount ?></div>
                    <div style="font-size: 0.78rem; color: #d97706; font-weight: 600;">
                        <a href="/admin/verifications" style="color: #d97706; text-decoration: none;">
                            <?= $pendingTscAudits ?> automated &bull; <?= $pendingDocVerifications ?> manual &rarr;
                        </a>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #8b5cf6;">
                    <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active Job Vacancies</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 4px 0;"><?= number_format($jobCount) ?></div>
                    <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">
                        <a href="/admin/jobs" style="color: #8b5cf6; text-decoration: none;">Manage vacancies &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Quick Action Shortcuts -->
            <div style="margin-bottom: 2rem;">
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; color: #0f172a;">Administrative Operations</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    
                    <a href="/admin/teachers" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #f0fdfa; color: #0f766e;">
                            <i class="fa fa-user-graduate"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Manage Teachers</span>
                    </a>

                    <a href="/admin/schools" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="fa fa-school"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Manage Schools</span>
                    </a>

                    <a href="/admin/verifications" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #fffbeb; color: #d97706;">
                            <i class="fa fa-shield-check"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Clearance Queue</span>
                    </a>

                    <a href="/admin/jobs" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #f5f3ff; color: #7c3aed;">
                            <i class="fa fa-briefcase"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Job Vacancies</span>
                    </a>

                    <a href="/admin/publications" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #fdf2f8; color: #db2777;">
                            <i class="fa fa-book-open"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Publications (<?= $publicationCount ?>)</span>
                    </a>

                    <a href="/admin/audit" class="quick-action-tile">
                        <div class="quick-action-icon" style="background: #f0fdf4; color: #166534;">
                            <i class="fa fa-clipboard-list"></i>
                        </div>
                        <span style="font-size: 0.9rem; font-weight: 700; color: #1e293b;">Audit Trail</span>
                    </a>
                </div>
            </div>

            <!-- Two-Column Recent Activity Feeds -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem;">
                
                <!-- Recent Teachers -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a;">Recent Teacher Sign-ups</h4>
                        <a href="/admin/teachers" style="font-size: 0.8rem; font-weight: 700; color: #0f766e;">View All &rarr;</a>
                    </div>

                    <?php if (empty($recentTeachers)): ?>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No educators registered yet.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($recentTeachers as $t): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= h($t->name ?: 'Educator #' . $t->id) ?></div>
                                        <div style="font-size: 0.76rem; color: #64748b;"><?= h($t->email) ?> &bull; <?= h($t->county ?: 'Kenya') ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if ($t->verification_status === 'verified'): ?>
                                            <span style="color: #16a34a; font-size: 0.74rem; font-weight: 700;"><i class="fa fa-check-circle"></i> Verified</span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.74rem;"><i class="fa fa-circle-notch"></i> <?= ucfirst($t->verification_status ?? 'unverified') ?></span>
                                        <?php endif; ?>
                                        <a href="/teacher/profile?teacher_id=<?= $t->id ?>" target="_blank" style="display: block; font-size: 0.72rem; color: #0f766e; font-weight: 600; margin-top: 2px;">Inspect Profile &rarr;</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Schools -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h4 style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0f172a;">Recent School Sign-ups</h4>
                        <a href="/admin/schools" style="font-size: 0.8rem; font-weight: 700; color: #0f766e;">View All &rarr;</a>
                    </div>

                    <?php if (empty($recentSchools)): ?>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No schools registered yet.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($recentSchools as $s): 
                                $isSchoolPro = ($s->status === 'active' && !empty($s->subscription_expiry) && strtotime($s->subscription_expiry) >= time());
                            ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.88rem; color: #0f172a;"><?= h($s->name ?: 'School #' . $s->id) ?></div>
                                        <div style="font-size: 0.76rem; color: #64748b;"><?= h($s->email) ?> &bull; <?= h($s->county ?: 'Kenya') ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if ($isSchoolPro): ?>
                                            <span style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">Pro Active</span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px;">Freemium</span>
                                        <?php endif; ?>
                                        <a href="/admin/schools?id=<?= $s->id ?>" style="display: block; font-size: 0.72rem; color: #0f766e; font-weight: 600; margin-top: 2px;">Manage School &rarr;</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </main>
</div>

