<?php
// Modern Professional Landing Page for MwalimuLink
// Fetch live real counts from the school directories
$publicCount = 0;
$privateCount = 0;
$intlCount = 0;
$sampleSchools = [];

try {
  $publicCount = R::count('public_school');
  $privateCount = R::count('private_school');
  $intlCount = R::count('international_school');

  // Fetch 2 random public schools
  $pubOffset = ($publicCount > 2) ? mt_rand(0, $publicCount - 2) : 0;
  $pubSamples = R::find('public_school', 'LIMIT 2 OFFSET ?', [$pubOffset]);
  foreach ($pubSamples as $s) {
    $sampleSchools[] = [
      'id' => $s->id,
      'name' => $s->name,
      'location' => $s->county ? $s->county . ' County' : ($s->constituency ?: 'Kenya'),
      'type' => 'Public School',
      'badge_class' => 'badge-public',
      'detail_url' => '/schools/public/detail?id=' . $s->id,
      'level' => $s->level ?: 'Primary & Secondary'
    ];
  }

  // Fetch 2 random private schools
  $privOffset = ($privateCount > 2) ? mt_rand(0, $privateCount - 2) : 0;
  $privSamples = R::find('private_school', 'LIMIT 2 OFFSET ?', [$privOffset]);
  foreach ($privSamples as $s) {
    $sampleSchools[] = [
      'id' => $s->id,
      'name' => $s->name,
      'location' => $s->county ? $s->county . ' County' : ($s->constituency ?: 'Kenya'),
      'type' => 'Private Academy',
      'badge_class' => 'badge-private',
      'detail_url' => '/schools/private/detail?id=' . $s->id,
      'level' => $s->level ?: 'Primary & Secondary'
    ];
  }

  // Fetch 2 random international schools
  $intlOffset = ($intlCount > 2) ? mt_rand(0, $intlCount - 2) : 0;
  $intlSamples = R::find('international_school', 'LIMIT 2 OFFSET ?', [$intlOffset]);
  foreach ($intlSamples as $s) {
    $sampleSchools[] = [
      'id' => $s->id,
      'name' => $s->name,
      'location' => $s->city ? $s->city . ($s->country ? ', ' . $s->country : '') : ($s->country ?: 'Global'),
      'type' => 'International School',
      'badge_class' => 'badge-intl',
      'detail_url' => '/schools/international/detail?id=' . $s->id,
      'level' => 'IGCSE / IB / American'
    ];
  }
} catch (Exception $e) {
  // Fallback numbers if database is unreachable
  $publicCount = 28900;
  $privateCount = 8790;
  $intlCount = 6180;
}
?>

<style>
  /* ==========================================================================
   MWALIMULINK MODERN EDUCATION LANDING PAGE (EXPANDED WIDE LAYOUT)
   ========================================================================== */

  :root {
    --ml-primary: #0f766e;
    --ml-primary-dark: #115e59;
    --ml-primary-light: #ccfbf1;
    --ml-primary-glow: rgba(15, 118, 110, 0.25);
    --ml-accent: #f59e0b;
    --ml-slate-900: #0f172a;
    --ml-slate-800: #1e293b;
    --ml-slate-600: #475569;
    --ml-slate-500: #64748b;
    --ml-slate-200: #e2e8f0;
    --ml-slate-100: #f1f5f9;
    --ml-slate-50: #f8fafc;
    --ml-white: #ffffff;
  }

  .landing-wrapper {
    width: 100%;
    max-width: 100%;
    background-color: var(--ml-slate-50);
    color: var(--ml-slate-800);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
  }

  /* Expanded Wide Container */
  .landing-container {
    max-width: 1440px;
    margin: 0 auto;
    padding: 0 2.5rem;
  }

  /* --------------------------------------------------------------------------
   1. FULL-BLEED ASYMMETRICAL HERO (WIDE 1560px VIEWPORT)
   -------------------------------------------------------------------------- */
  .landing-hero {
    position: relative;
    width: 100%;
    min-height: 620px;
    background-color: var(--ml-slate-900);
    background-image:
      linear-gradient(to right,
        rgba(15, 23, 42, 0.98) 0%,
        rgba(15, 23, 42, 0.94) 38%,
        rgba(15, 23, 42, 0.65) 54%,
        rgba(15, 23, 42, 0.15) 72%,
        rgba(15, 23, 42, 0.00) 100%),
      url('/hero-teacher.jpg');
    background-size: cover;
    background-position: 80% center;
    background-repeat: no-repeat;
    display: flex;
    align-items: center;
    padding: 5.5rem 0;
    border-bottom: 3px solid var(--ml-primary);
    box-shadow: inset 0 -20px 40px rgba(15, 23, 42, 0.35);
  }

  .hero-inner-wrapper {
    width: 100%;
    max-width: 1560px;
    margin: 0 auto;
    padding: 0 3rem;
    display: grid;
    grid-template-columns: minmax(360px, 620px) 1fr;
    gap: 2.5rem;
    align-items: center;
  }

  .hero-box {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    z-index: 2;
  }

  .hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(15, 118, 110, 0.55);
    border: 1px solid rgba(45, 212, 191, 0.7);
    color: #ccfbf1;
    padding: 7px 16px;
    border-radius: 9999px;
    font-size: 0.84rem;
    font-weight: 700;
    margin-bottom: 1.35rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    backdrop-filter: blur(8px);
  }

  .hero-pill i {
    color: #2dd4bf;
    font-size: 0.95rem;
  }

  .hero-title {
    font-size: 3.1rem;
    line-height: 1.15;
    font-weight: 900;
    color: #ffffff !important;
    margin: 0 0 1.35rem;
    letter-spacing: -0.025em;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.6);
  }

  .hero-title .highlight {
    color: #2dd4bf;
    position: relative;
    display: inline-block;
  }

  .hero-subtitle {
    font-size: 1.14rem;
    line-height: 1.65;
    color: #f1f5f9;
    margin: 0 0 2rem;
    max-width: 580px;
    text-shadow: 0 1px 5px rgba(0, 0, 0, 0.5);
  }

  .hero-cta-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    width: 100%;
  }

  .btn-landing {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    border-radius: 8px;
    font-size: 0.98rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
  }

  .btn-landing-primary {
    background: var(--ml-primary);
    color: #ffffff !important;
    border: 1.5px solid #2dd4bf;
  }

  .btn-landing-primary:hover {
    background: #115e59;
    border-color: #5eead4;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(15, 118, 110, 0.5);
    color: #ffffff !important;
  }

  .btn-landing-white {
    background: #ffffff;
    color: var(--ml-slate-900) !important;
    border: 1.5px solid #ffffff;
  }

  .btn-landing-white:hover {
    background: #f8fafc;
    color: var(--ml-primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
  }

  .hero-tp-sublink {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.92rem;
    font-weight: 700;
    color: #5eead4;
    text-decoration: none;
    padding: 4px 0;
    transition: all 0.15s ease;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
  }

  .hero-tp-sublink:hover {
    color: #ffffff;
    text-decoration: underline;
    transform: translateX(3px);
  }

  /* Quick Hero Highlights Bar */
  .hero-highlights-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    width: 100%;
  }

  .hero-highlight-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #e2e8f0;
    font-size: 0.88rem;
    font-weight: 600;
  }

  .hero-highlight-pill i {
    color: #2dd4bf;
    font-size: 1rem;
  }

  /* --------------------------------------------------------------------------
   2. STATS STRIP (Real School Types Only)
   -------------------------------------------------------------------------- */
  .landing-stats-bar {
    background: var(--ml-white);
    border-bottom: 1px solid var(--ml-slate-200);
    padding: 2.75rem 0;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2.25rem;
  }

  .stat-item {
    display: flex;
    align-items: center;
    gap: 1.35rem;
    padding: 1.35rem 1.75rem;
    background: #f8fafc;
    border: 1px solid var(--ml-slate-200);
    border-radius: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
    color: inherit;
  }

  .stat-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    border-color: var(--ml-primary);
  }

  .stat-icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.55rem;
    flex-shrink: 0;
  }

  .stat-icon-circle.public {
    background: #f0fdfa;
    color: var(--ml-primary);
    border: 1px solid #99f6e4;
  }

  .stat-icon-circle.private {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
  }

  .stat-icon-circle.intl {
    background: #fdf4ff;
    color: #a855f7;
    border: 1px solid #f5d0fe;
  }

  .stat-content {
    display: flex;
    flex-direction: column;
  }

  .stat-number {
    font-size: 2.1rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    line-height: 1.1;
    letter-spacing: -0.02em;
  }

  .stat-label {
    font-size: 1rem;
    font-weight: 700;
    color: var(--ml-slate-800);
    margin-top: 3px;
  }

  .stat-hint {
    font-size: 0.8rem;
    color: var(--ml-slate-500);
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  /* --------------------------------------------------------------------------
   3. AUDIENCE SECTION (Who Is MwalimuLink For?)
   -------------------------------------------------------------------------- */
  .landing-section {
    padding: 5.5rem 0;
  }

  .section-header {
    text-align: center;
    max-width: 860px;
    margin: 0 auto 3.5rem;
  }

  .section-tag {
    display: inline-block;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ml-primary);
    background: #f0fdfa;
    padding: 5px 14px;
    border-radius: 9999px;
    margin-bottom: 0.85rem;
    border: 1px solid #ccfbf1;
  }

  .section-title {
    font-size: 2.35rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    margin: 0 0 0.85rem;
    letter-spacing: -0.02em;
  }

  .section-desc {
    font-size: 1.08rem;
    line-height: 1.65;
    color: var(--ml-slate-600);
    margin: 0;
  }

  .audience-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
  }

  .audience-card {
    background: var(--ml-white);
    border: 1px solid var(--ml-slate-200);
    border-radius: 16px;
    padding: 2.25rem 2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.25s ease;
    position: relative;
    overflow: hidden;
  }

  .audience-card:hover {
    transform: translateY(-5px);
    border-color: var(--ml-primary);
    box-shadow: 0 16px 32px rgba(15, 118, 110, 0.08);
  }

  .audience-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--ml-primary);
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  .audience-card:hover::before {
    opacity: 1;
  }

  .audience-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: #f0fdfa;
    color: var(--ml-primary);
    border: 1px solid #ccfbf1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.75rem;
  }

  .audience-title {
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    margin: 0 0 0.85rem;
  }

  .audience-text {
    font-size: 0.95rem;
    line-height: 1.65;
    color: var(--ml-slate-600);
    margin: 0 0 1.5rem;
  }

  .audience-feature-list {
    list-style: none;
    padding: 0;
    margin: 0 0 2rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .audience-feature-list li {
    font-size: 0.9rem;
    color: var(--ml-slate-800);
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }

  .audience-feature-list li i {
    color: var(--ml-primary);
    font-size: 0.9rem;
    margin-top: 3px;
    flex-shrink: 0;
  }

  .audience-btn {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 0.94rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid var(--ml-slate-200);
    background: var(--ml-slate-50);
    color: var(--ml-slate-800) !important;
    transition: all 0.2s ease;
  }

  .audience-btn:hover {
    background: var(--ml-primary);
    color: var(--ml-white) !important;
    border-color: var(--ml-primary);
  }

  /* --------------------------------------------------------------------------
   4. HOW IT WORKS (3 Simple Steps)
   -------------------------------------------------------------------------- */
  .landing-steps {
    background: #ffffff;
    border-top: 1px solid var(--ml-slate-200);
    border-bottom: 1px solid var(--ml-slate-200);
  }

  .steps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2.25rem;
    position: relative;
  }

  .step-card {
    background: var(--ml-slate-50);
    border: 1px solid var(--ml-slate-200);
    border-radius: 14px;
    padding: 2.25rem 1.75rem;
    text-align: left;
    position: relative;
    transition: transform 0.2s ease;
  }

  .step-card:hover {
    transform: translateY(-3px);
    border-color: #cbd5e1;
  }

  .step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--ml-slate-900);
    color: #ffffff;
    font-weight: 800;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
  }

  .step-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    margin: 0 0 0.65rem;
  }

  .step-desc {
    font-size: 0.92rem;
    line-height: 1.65;
    color: var(--ml-slate-600);
    margin: 0;
  }

  /* --------------------------------------------------------------------------
   5. REAL SCHOOL DIRECTORY PREVIEW
   -------------------------------------------------------------------------- */
  .schools-preview-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.75rem;
    margin-bottom: 3rem;
  }

  .school-card-item {
    background: var(--ml-white);
    border: 1px solid var(--ml-slate-200);
    border-radius: 14px;
    padding: 1.5rem 1.4rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.02);
  }

  .school-card-item:hover {
    border-color: var(--ml-primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
  }

  .school-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 0.85rem;
  }

  .school-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--ml-slate-900);
    line-height: 1.35;
    margin: 0;
  }

  .school-badge {
    font-size: 0.7rem;
    font-weight: 800;
    padding: 4px 9px;
    border-radius: 5px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .badge-public {
    background: #f0fdfa;
    color: #0f766e;
    border: 1px solid #99f6e4;
  }

  .badge-private {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
  }

  .badge-intl {
    background: #fdf4ff;
    color: #9333ea;
    border: 1px solid #f5d0fe;
  }

  .school-meta {
    font-size: 0.86rem;
    color: var(--ml-slate-600);
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 1.25rem;
  }

  .school-meta-line {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .school-meta-line i {
    color: var(--ml-slate-500);
    font-size: 0.84rem;
    width: 14px;
  }

  .school-card-footer {
    border-top: 1px solid var(--ml-slate-100);
    padding-top: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ml-primary);
  }

  /* --------------------------------------------------------------------------
   6. CURATED VACANCIES (Realistic Preview)
   -------------------------------------------------------------------------- */
  .landing-jobs {
    background: #ffffff;
    border-top: 1px solid var(--ml-slate-200);
    border-bottom: 1px solid var(--ml-slate-200);
  }

  .jobs-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.75rem;
    margin-bottom: 2.5rem;
  }

  .job-card-item {
    background: #f8fafc;
    border: 1px solid var(--ml-slate-200);
    border-radius: 14px;
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
  }

  .job-card-item:hover {
    background: #ffffff;
    border-color: var(--ml-primary);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
  }

  .job-role-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 0.75rem;
  }

  .job-role-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    margin: 0;
    line-height: 1.35;
  }

  .job-type-pill {
    background: #f1f5f9;
    color: var(--ml-slate-800);
    font-size: 0.74rem;
    font-weight: 700;
    padding: 4px 9px;
    border-radius: 5px;
    white-space: nowrap;
  }

  .job-school-info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    font-size: 0.88rem;
    color: var(--ml-slate-600);
    margin-bottom: 1rem;
  }

  .job-school-info span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .job-req-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 1.5rem;
  }

  .job-req-pill {
    background: #ffffff;
    border: 1px solid var(--ml-slate-200);
    color: var(--ml-slate-800);
    font-size: 0.76rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 5px;
  }

  .job-card-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--ml-slate-200);
    padding-top: 1rem;
  }

  .job-deadline {
    font-size: 0.8rem;
    font-weight: 700;
    color: #d97706;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .job-apply-link {
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--ml-primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }

  .job-apply-link:hover {
    text-decoration: underline;
    color: var(--ml-primary-dark);
  }

  .jobs-notice-box {
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 12px;
    padding: 1.25rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
  }

  .jobs-notice-text {
    font-size: 0.92rem;
    color: var(--ml-primary-dark);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* --------------------------------------------------------------------------
   7. NATIONAL REACH & TRUST PILLARS
   -------------------------------------------------------------------------- */
  .trust-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.75rem;
  }

  .trust-item {
    background: var(--ml-white);
    border: 1px solid var(--ml-slate-200);
    border-radius: 12px;
    padding: 1.75rem 1.4rem;
    text-align: center;
  }

  .trust-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 1.15rem;
    border-radius: 50%;
    background: #f0fdfa;
    color: var(--ml-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
  }

  .trust-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ml-slate-900);
    margin: 0 0 0.45rem;
  }

  .trust-desc {
    font-size: 0.85rem;
    color: var(--ml-slate-600);
    line-height: 1.55;
    margin: 0;
  }

  /* --------------------------------------------------------------------------
   8. BOTTOM CALL TO ACTION (Teal Gradient)
   -------------------------------------------------------------------------- */
  .landing-cta-banner {
    background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
    color: #ffffff;
    padding: 5.5rem 0;
    text-align: center;
    position: relative;
  }

  .cta-box {
    max-width: 900px;
    margin: 0 auto;
  }

  .cta-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #ffffff !important;
    margin: 0 0 1.15rem;
    letter-spacing: -0.02em;
  }

  .cta-desc {
    font-size: 1.15rem;
    line-height: 1.65;
    color: #ccfbf1;
    margin: 0 0 2.25rem;
  }

  .cta-btn-group {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.25rem;
    flex-wrap: wrap;
  }

  .btn-cta-white {
    background: #ffffff;
    color: var(--ml-primary-dark) !important;
    border: 1px solid #ffffff;
    font-weight: 800;
    padding: 15px 30px;
  }

  .btn-cta-white:hover {
    background: #f1f5f9;
    color: #0f172a !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  }

  .btn-cta-trans {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff !important;
    border: 1.5px solid rgba(255, 255, 255, 0.4);
    font-weight: 700;
    padding: 15px 30px;
  }

  .btn-cta-trans:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: #ffffff;
    transform: translateY(-2px);
  }

  /* --------------------------------------------------------------------------
   RESPONSIVE BREAKPOINTS
   -------------------------------------------------------------------------- */
  @media (max-width: 1200px) {
    .hero-inner-wrapper {
      grid-template-columns: 1fr;
      padding: 0 2.5rem;
    }

    .landing-hero {
      background-position: 75% center;
      background-image:
        linear-gradient(to right,
          rgba(15, 23, 42, 0.96) 0%,
          rgba(15, 23, 42, 0.90) 60%,
          rgba(15, 23, 42, 0.40) 100%),
        url('/hero-teacher.jpg');
    }

    .hero-title {
      font-size: 2.6rem;
    }
  }

  @media (max-width: 992px) {
    .landing-container {
      padding: 0 1.5rem;
    }

    .stats-grid {
      grid-template-columns: 1fr;
      gap: 1rem;
    }

    .audience-grid {
      grid-template-columns: 1fr;
    }

    .steps-grid {
      grid-template-columns: 1fr;
    }

    .schools-preview-grid {
      grid-template-columns: repeat(2, 1fr);
    }

    .jobs-grid {
      grid-template-columns: 1fr;
    }

    .trust-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 640px) {
    .landing-hero {
      padding: 3.5rem 0 3rem;
    }

    .hero-inner-wrapper {
      padding: 0 1.25rem;
    }

    .hero-title {
      font-size: 2.1rem;
    }

    .hero-cta-group {
      flex-direction: column;
      width: 100%;
    }

    .btn-landing {
      width: 100%;
    }

    .schools-preview-grid {
      grid-template-columns: 1fr;
    }

    .trust-grid {
      grid-template-columns: 1fr;
    }

    .hero-highlights-strip {
      flex-direction: column;
      gap: 0.75rem;
    }
  }
</style>

<div class="landing-wrapper">

  <!-- ========================================================================
       1. FULL-BLEED ASYMMETRICAL HERO (EXPANDED WIDE VIEWPORT)
       ======================================================================== -->
  <section class="landing-hero">
    <div class="hero-inner-wrapper">
      <div class="hero-box">

        <h1 class="hero-title">
          Connecting Outstanding Educators with Leading Schools Across <span class="highlight">Kenya</span>
        </h1>
        <p class="hero-subtitle">
          Whether you are an institution recruiting vetted teaching talent, an educator advancing your career, or a
          student teacher seeking Teaching Practice (TP) placement — MwalimuLink bridges the gap.
        </p>
        <div class="hero-cta-group">
          <a href="/register/school" class="btn-landing btn-landing-primary">
            <i class="fa-solid fa-school"></i> Register as a School
          </a>
          <a href="/register/teacher" class="btn-landing btn-landing-white">
            <i class="fa-solid fa-user-plus"></i> Join as a Teacher (Free)
          </a>
        </div>
        <a href="/tp-hub" class="hero-tp-sublink">
          <i class="fa-solid fa-graduation-cap"></i> Student Teacher? Apply for Teaching Practice (TP) Placements &rarr;
        </a>

        <div class="hero-highlights-strip">
          <div class="hero-highlight-pill">
            <i class="fa-solid fa-shield-halved"></i> TSC & Ministry Standards Aligned
          </div>
          <div class="hero-highlight-pill">
            <i class="fa-solid fa-building-columns"></i> 43,000+ Catalogued Schools
          </div>
          <div class="hero-highlight-pill">
            <i class="fa-solid fa-map-location-dot"></i> All 47 Counties
          </div>
        </div>
      </div>
      <!-- Right column intentionally open for background educator visual -->
      <div class="hero-visual-space"></div>
    </div>
  </section>

  <!-- ========================================================================
       2. STATS STRIP (Real School Types Only)
       ======================================================================== -->
  <section class="landing-stats-bar">
    <div class="landing-container">
      <div class="stats-grid">
        <a href="/schools/public" class="stat-item">
          <div class="stat-icon-circle public">
            <i class="fa-solid fa-landmark"></i>
          </div>
          <div class="stat-content">
            <span class="stat-number"><?= number_format($publicCount) ?>+</span>
            <span class="stat-label">Public Schools</span>
            <span class="stat-hint">Primary & Secondary across 47 Counties <i class="fa-solid fa-chevron-right"
                style="font-size:10px;"></i></span>
          </div>
        </a>

        <a href="/schools/private" class="stat-item">
          <div class="stat-icon-circle private">
            <i class="fa-solid fa-building"></i>
          </div>
          <div class="stat-content">
            <span class="stat-number"><?= number_format($privateCount) ?>+</span>
            <span class="stat-label">Private Academies</span>
            <span class="stat-hint">Top-tier preparatory & high schools <i class="fa-solid fa-chevron-right"
                style="font-size:10px;"></i></span>
          </div>
        </a>

        <a href="/schools/international" class="stat-item">
          <div class="stat-icon-circle intl">
            <i class="fa-solid fa-globe"></i>
          </div>
          <div class="stat-content">
            <span class="stat-number"><?= number_format($intlCount) ?>+</span>
            <span class="stat-label">International Schools</span>
            <span class="stat-hint">British, IB & American curricula <i class="fa-solid fa-chevron-right"
                style="font-size:10px;"></i></span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       3. AUDIENCE SECTOR PORTALS
       ======================================================================== -->
  <section class="landing-section">
    <div class="landing-container">
      <div class="section-header">
        <span class="section-tag">Designed for Kenyan Education</span>
        <h2 class="section-title">One Platform for Every Stakeholder</h2>
        <p class="section-desc">Tailored recruitment workflows, candidate discovery, and placement solutions built
          specifically for schools and educators.</p>
      </div>

      <div class="audience-grid">
        <!-- School Card -->
        <div class="audience-card">
          <div>
            <div class="audience-icon">
              <i class="fa-solid fa-school-flag"></i>
            </div>
            <h3 class="audience-title">Schools & Institutions</h3>
            <p class="audience-text">
              Streamline faculty hiring with automated applicant tracking, verified subject credentials, and interview
              management.
            </p>
            <ul class="audience-feature-list">
              <li><i class="fa-solid fa-circle-check"></i> Post teaching vacancies with deadlines</li>
              <li><i class="fa-solid fa-circle-check"></i> Filter verified teacher talent by subject</li>
              <li><i class="fa-solid fa-circle-check"></i> Coordinate virtual or in-person interviews</li>
              <li><i class="fa-solid fa-circle-check"></i> Invite faculty & department heads to panels</li>
            </ul>
          </div>
          <a href="/register/school" class="audience-btn">
            <span>Register School Portal</span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>

        <!-- Teacher Card -->
        <div class="audience-card">
          <div>
            <div class="audience-icon">
              <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <h3 class="audience-title">Teachers & Educators</h3>
            <p class="audience-text">
              100% free lifetime membership for teachers. Get discovered by leading public and private schools across
              Kenya.
            </p>
            <ul class="audience-feature-list">
              <li><i class="fa-solid fa-circle-check"></i> Create a professional educator profile</li>
              <li><i class="fa-solid fa-circle-check"></i> Apply to verified vacancies with 1-click</li>
              <li><i class="fa-solid fa-circle-check"></i> Track application status milestones</li>
              <li><i class="fa-solid fa-circle-check"></i> Direct inquiries with school leadership</li>
            </ul>
          </div>
          <a href="/register/teacher" class="audience-btn">
            <span>Create Free Profile</span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>

        <!-- Student Teacher / TP Card -->
        <div class="audience-card">
          <div>
            <div class="audience-icon">
              <i class="fa-solid fa-user-graduate"></i>
            </div>
            <h3 class="audience-title">Student Teachers (TP)</h3>
            <p class="audience-text">
              Accelerate your Teaching Practice. Connect directly with partner schools and secure formal placement
              without hassle.
            </p>
            <ul class="audience-feature-list">
              <li><i class="fa-solid fa-circle-check"></i> Access dedicated national TP Hub</li>
              <li><i class="fa-solid fa-circle-check"></i> Upload official university intro letters</li>
              <li><i class="fa-solid fa-circle-check"></i> Real-time clearance and approval alerts</li>
              <li><i class="fa-solid fa-circle-check"></i> Generate official printable placement slips</li>
            </ul>
          </div>
          <a href="/tp-hub" class="audience-btn">
            <span>Explore TP Hub</span>
            <i class="fa-solid fa-arrow-right"></i>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       4. HOW IT WORKS (3 Simple Steps)
       ======================================================================== -->
  <section class="landing-section landing-steps">
    <div class="landing-container">
      <div class="section-header">
        <span class="section-tag">Streamlined Process</span>
        <h2 class="section-title">How MwalimuLink Works</h2>
        <p class="section-desc">From account creation to hiring and placement in three transparent steps.</p>
      </div>

      <div class="steps-grid">
        <div class="step-card">
          <div class="step-number">1</div>
          <h3 class="step-title">Create & Verify Account</h3>
          <p class="step-desc">
            Institutions register their school profile and recruitment roles. Educators create a free account
            highlighting their subject combinations, TSC status, and academic experience.
          </p>
        </div>

        <div class="step-card">
          <div class="step-number">2</div>
          <h3 class="step-title">Post & Discover Openings</h3>
          <p class="step-desc">
            Schools publish active job vacancies or TP placement capacities. Qualified educators browse listings and
            submit instant, secure applications directly to school administration.
          </p>
        </div>

        <div class="step-card">
          <div class="step-number">3</div>
          <h3 class="step-title">Interview & Secure Placement</h3>
          <p class="step-desc">
            Schools review candidate credentials, schedule in-person or virtual interviews, send real-time notification
            updates, and onboard educators with confidence.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       5. FEATURED SCHOOL DIRECTORY PREVIEW (Real Data from Database)
       ======================================================================== -->
  <section class="landing-section">
    <div class="landing-container">
      <div class="section-header">
        <span class="section-tag">Institutional Network</span>
        <h2 class="section-title">Featured Schools in Our Directory</h2>
        <p class="section-desc">A snapshot of accredited public, private, and international institutions listed on
          MwalimuLink.</p>
      </div>

      <div class="schools-preview-grid">
        <?php foreach ($sampleSchools as $school): ?>
          <a href="<?= h($school['detail_url']) ?>" class="school-card-item">
            <div>
              <div class="school-card-header">
                <h4 class="school-name"><?= h($school['name']) ?></h4>
                <span class="school-badge <?= h($school['badge_class']) ?>"><?= h($school['type']) ?></span>
              </div>
              <div class="school-meta">
                <div class="school-meta-line">
                  <i class="fa-solid fa-location-dot"></i>
                  <span><?= h($school['location']) ?></span>
                </div>
                <div class="school-meta-line">
                  <i class="fa-solid fa-graduation-cap"></i>
                  <span><?= h($school['level']) ?></span>
                </div>
              </div>
            </div>
            <div class="school-card-footer">
              <span>View School Profile</span>
              <i class="fa-solid fa-arrow-right"></i>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div style="text-align: center;">
        <a href="/schools/public" class="btn-landing btn-landing-white"
          style="border: 1.5px solid var(--ml-primary); color: var(--ml-primary) !important;">
          <i class="fa-solid fa-magnifying-glass"></i> Explore All 43,000+ Schools in National Directory
        </a>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       6. CURATED VACANCY OPENINGS (Realistic Kenyan Education Roles)
       ======================================================================== -->
  <section class="landing-section landing-jobs">
    <div class="landing-container">
      <div class="section-header">
        <span class="section-tag">Educator Opportunities</span>
        <h2 class="section-title">Latest Teaching Vacancies</h2>
        <p class="section-desc">Openings from registered institutions looking for qualified Kenyan teaching
          talent.</p>
      </div>

      <div class="jobs-grid">
        <!-- Job 1 -->
        <div class="job-card-item">
          <div>
            <div class="job-role-header">
              <h4 class="job-role-title">Junior Secondary Mathematics & Integrated Science Teacher</h4>
              <span class="job-type-pill">Full-Time</span>
            </div>
            <div class="job-school-info">
              <span><i class="fa-solid fa-school"></i> St. Austin's Senior Academy</span>
              <span><i class="fa-solid fa-location-dot"></i> Nairobi County</span>
              <span><i class="fa-solid fa-award"></i> CBC Junior Secondary</span>
            </div>
            <div class="job-req-pills">
              <span class="job-req-pill">B.Ed / Dip. Ed</span>
              <span class="job-req-pill">TSC Registered</span>
              <span class="job-req-pill">Competency-Based Assessment</span>
            </div>
          </div>
          <div class="job-card-bottom">
            <span class="job-deadline"><i class="fa-regular fa-clock"></i> Deadline: 14 Days Left</span>
            <a href="/login/teacher" class="job-apply-link">Login to Apply <i class="fa-solid fa-chevron-right"></i></a>
          </div>
        </div>

        <!-- Job 2 -->
        <div class="job-card-item">
          <div>
            <div class="job-role-header">
              <h4 class="job-role-title">CBC Lead Educator (Grade 4–6 English & Social Studies)</h4>
              <span class="job-type-pill">Full-Time</span>
            </div>
            <div class="job-school-info">
              <span><i class="fa-solid fa-school"></i> Riara Springs Primary</span>
              <span><i class="fa-solid fa-location-dot"></i> Kiambu County</span>
              <span><i class="fa-solid fa-award"></i> Senior Primary</span>
            </div>
            <div class="job-req-pills">
              <span class="job-req-pill">P1 / B.Ed Primary</span>
              <span class="job-req-pill">CBC Facilitator Trained</span>
              <span class="job-req-pill">2+ Yrs Experience</span>
            </div>
          </div>
          <div class="job-card-bottom">
            <span class="job-deadline"><i class="fa-regular fa-clock"></i> Deadline: 9 Days Left</span>
            <a href="/login/teacher" class="job-apply-link">Login to Apply <i class="fa-solid fa-chevron-right"></i></a>
          </div>
        </div>

        <!-- Job 3 -->
        <div class="job-card-item">
          <div>
            <div class="job-role-header">
              <h4 class="job-role-title">Physics & Chemistry Senior High School Teacher</h4>
              <span class="job-type-pill">Contract</span>
            </div>
            <div class="job-school-info">
              <span><i class="fa-solid fa-school"></i> Pioneer Extra-County High School</span>
              <span><i class="fa-solid fa-location-dot"></i> Murang'a County</span>
              <span><i class="fa-solid fa-award"></i> 8-4-4 / KCSE Form 1-4</span>
            </div>
            <div class="job-req-pills">
              <span class="job-req-pill">B.Ed Science</span>
              <span class="job-req-pill">TSC Certified</span>
              <span class="job-req-pill">Strong Practical Lab Track Record</span>
            </div>
          </div>
          <div class="job-card-bottom">
            <span class="job-deadline"><i class="fa-regular fa-clock"></i> Deadline: 21 Days Left</span>
            <a href="/login/teacher" class="job-apply-link">Login to Apply <i class="fa-solid fa-chevron-right"></i></a>
          </div>
        </div>

        <!-- Job 4 -->
        <div class="job-card-item">
          <div>
            <div class="job-role-header">
              <h4 class="job-role-title">French & Humanities Specialist (IGCSE / British Curriculum)</h4>
              <span class="job-type-pill">Full-Time</span>
            </div>
            <div class="job-school-info">
              <span><i class="fa-solid fa-school"></i> Coast International Academy</span>
              <span><i class="fa-solid fa-location-dot"></i> Mombasa County</span>
              <span><i class="fa-solid fa-award"></i> Cambridge / IGCSE</span>
            </div>
            <div class="job-req-pills">
              <span class="job-req-pill">DELF / DALF B2+</span>
              <span class="job-req-pill">Cambridge Assessment Experience</span>
              <span class="job-req-pill">International Schooling</span>
            </div>
          </div>
          <div class="job-card-bottom">
            <span class="job-deadline"><i class="fa-regular fa-clock"></i> Deadline: 12 Days Left</span>
            <a href="/login/teacher" class="job-apply-link">Login to Apply <i class="fa-solid fa-chevron-right"></i></a>
          </div>
        </div>
      </div>

      <div class="jobs-notice-box">
        <div class="jobs-notice-text">
          <i class="fa-solid fa-circle-info"></i>
          <span>Sample vacancies shown. Register or sign in to your Educator account to view all live postings and apply
            directly.</span>
        </div>
        <div style="display: flex; gap: 0.75rem;">
          <a href="/login/teacher" class="btn-landing btn-landing-primary"
            style="padding: 8px 16px; font-size: 0.86rem;">
            Teacher Login
          </a>
          <a href="/register/teacher" class="btn-landing btn-landing-white"
            style="padding: 8px 16px; font-size: 0.86rem; border: 1px solid var(--ml-slate-200);">
            Register Free
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       7. TRUST & REGIONAL REACH
       ======================================================================== -->
  <section class="landing-section">
    <div class="landing-container">
      <div class="section-header">
        <span class="section-tag">National Coverage</span>
        <h2 class="section-title">Built for the Kenyan Education System</h2>
        <p class="section-desc">Reliable institutional infrastructure connecting counties, curriculums, and teaching
          colleges.</p>
      </div>

      <div class="trust-grid">
        <div class="trust-item">
          <div class="trust-icon">
            <i class="fa-solid fa-map-location-dot"></i>
          </div>
          <h4 class="trust-title">All 47 Counties</h4>
          <p class="trust-desc">Comprehensive database spanning rural and urban institutions across Kenya.</p>
        </div>

        <div class="trust-item">
          <div class="trust-icon">
            <i class="fa-solid fa-book-open-reader"></i>
          </div>
          <h4 class="trust-title">CBC, 8-4-4 & IGCSE</h4>
          <p class="trust-desc">Tailored to the evolving Kenyan curriculum transitions and international standards.</p>
        </div>

        <div class="trust-item">
          <div class="trust-icon">
            <i class="fa-solid fa-file-signature"></i>
          </div>
          <h4 class="trust-title">Verified TP Processing</h4>
          <p class="trust-desc">Direct linkage between teacher trainee students, university faculties, and schools.</p>
        </div>

        <div class="trust-item">
          <div class="trust-icon">
            <i class="fa-solid fa-envelope-circle-check"></i>
          </div>
          <h4 class="trust-title">Direct Communication</h4>
          <p class="trust-desc">Connect directly via official institutional email with zero recruiter intermediary fees.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================================================================
       8. BOTTOM CALL TO ACTION
       ======================================================================== -->
  <section class="landing-cta-banner">
    <div class="landing-container">
      <div class="cta-box">
        <h2 class="cta-title">Ready to Elevate Your School's Faculty or Teaching Career?</h2>
        <p class="cta-desc">
          Join thousands of educators and accredited schools already collaborating on Kenya's dedicated recruitment
          platform.
        </p>
        <div class="cta-btn-group">
          <a href="/register/school" class="btn-landing btn-cta-white">
            <i class="fa-solid fa-school"></i> Register as an Institution
          </a>
          <a href="/register/teacher" class="btn-landing btn-cta-trans">
            <i class="fa-solid fa-user-plus"></i> Join as a Teacher — It's Free
          </a>
        </div>
      </div>
    </div>
  </section>

</div>