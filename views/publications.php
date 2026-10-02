<?php
// views/publications.php
$authUser = auth_user();
$actionSuccess = '';
$actionError = '';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  $deleteId = (int) ($_POST['id'] ?? 0);
  if ($deleteId > 0) {
    $pubToDelete = R::load('publications', $deleteId);
    if ($pubToDelete && $pubToDelete->id) {
      $canDelete = ($authUser && ($authUser['role'] === 'admin' || $authUser['name'] === $pubToDelete->author || ($authUser['id'] == $pubToDelete->teacher_id)));
      if ($canDelete) {
        R::trash($pubToDelete);
        $actionSuccess = "Publication deleted successfully.";
      } else {
        $actionError = "You do not have permission to delete this publication.";
      }
    }
  }
}

// Handle Submit New Paper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_paper') {
  $title = trim($_POST['title'] ?? '');
  $category = trim($_POST['category'] ?? 'CBC Pedagogical Research');
  $author = trim($_POST['author'] ?? '');
  $authorRole = trim($_POST['author_role'] ?? 'Educator');
  $summary = trim($_POST['summary'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $pdfUrl = trim($_POST['pdf_url'] ?? '');

  if (empty($title) || empty($summary)) {
    $actionError = "Please provide both a publication title and an executive summary.";
  } else {
    $pub = R::dispense('publications');
    $pub->title = $title;
    $pub->category = $category;
    $pub->author = $author ?: ($authUser['name'] ?? 'Guest Educator');
    $pub->author_role = $authorRole ?: 'Senior Educator';
    $pub->teacher_id = $authUser['id'] ?? null;
    $pub->summary = $summary;
    $pub->content = $content ?: $summary;
    $pub->pdf_url = $pdfUrl;
    $pub->views_count = 1;
    $pub->created_at = date('Y-m-d H:i:s');
    $pub->is_published = 1;

    // Handle PDF File Upload
    if (!empty($_FILES['pdf_file']['name'])) {
      $pdfUpload = secure_validate_and_upload($_FILES['pdf_file'], 'uploads/publications/', ['pdf'], 15 * 1024 * 1024);
      if ($pdfUpload['success']) {
        $pub->pdf_path = '/' . ltrim($pdfUpload['relative_path'], '/');
        if (empty($pub->pdf_url)) {
          $pub->pdf_url = '/' . ltrim($pdfUpload['relative_path'], '/');
        }
      }
    }

    R::store($pub);
    $actionSuccess = "Publication submitted successfully! It is now published in the Educator Publications Hub.";
  }
}

// Seed initial research papers if table is empty
$count = R::count('publications');
if ($count === 0) {
  $seedPapers = [
    [
      'title' => 'Empirical Study on CBC Junior Secondary School Transition in Rural Kenya',
      'category' => 'CBC Pedagogical Research',
      'author' => 'Dr. Elizabeth Mwangi',
      'author_role' => 'Senior Curriculum Specialist, Nairobi',
      'summary' => 'An evaluation of classroom readiness, laboratory apparatus adaptations, and teacher formative assessment practices in Junior Secondary Schools across 12 rural sub-counties.',
      'content' => "This empirical research study examines the pedagogical transitions under the Competency Based Curriculum (CBC) in Grade 7 and Grade 8 classrooms.\n\nKey Findings:\n1. 78% of rural schools have improvised STEM experimental kits using locally available materials.\n2. Formative rubric assessments have increased student engagement by 42% compared to summative rote learning.\n3. Continuous teacher professional development remains critical in integrated science and pre-technical studies.",
      'pdf_url' => '/uploads/publications/cbc_jss_transition_study.pdf',
      'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
      'views_count' => 142
    ],
    [
      'title' => 'Integrating Digital Literacy and Micro-Learning in High School STEM Classrooms',
      'category' => 'STEM & Digital Learning',
      'author' => 'Mwalimu Dennis Kiprop',
      'author_role' => 'Head of Physics & ICT Lead, Nakuru',
      'summary' => 'A practical framework demonstrating how offline digital simulations (PhET) and mobile micro-assessments improved student retention in Physics and Chemistry by 34%.',
      'content' => "This paper outlines a blended learning model tailored for Kenyan high school science teachers operating in low-bandwidth environments.\n\nBy leveraging localized digital simulations and 5-minute interactive quizzes, educators can significantly reinforce abstract concepts in mechanics and chemical stoichiometry without requiring constant high-speed internet connectivity.",
      'pdf_url' => '/uploads/publications/kenyan_educators_stem_guide.pdf',
      'created_at' => date('Y-m-d H:i:s', strtotime('-12 days')),
      'views_count' => 289
    ],
    [
      'title' => 'Inclusive Education Strategies: Supporting Neurodivergent Learners in Mainstream Classrooms',
      'category' => 'Special Needs & Inclusion',
      'author' => 'Grace Wambui Chege',
      'author_role' => 'Special Needs Coordinator, Kiambu',
      'summary' => 'Actionable classroom interventions, differentiated instruction templates, and peer-buddy systems to support autistic and ADHD pupils in primary and secondary schools.',
      'content' => "Inclusive pedagogy requires systematic differentiation rather than separate exclusion. This guide presents seven core classroom adaptations tested across 15 public and private schools in Central Kenya.\n\nHighlights include visual schedules, sensory quiet zones, and structured cooperative learning teams that elevate classroom belonging and academic performance.",
      'pdf_url' => '/uploads/publications/cbc_jss_transition_study.pdf',
      'created_at' => date('Y-m-d H:i:s', strtotime('-18 days')),
      'views_count' => 195
    ],
    [
      'title' => 'Navigating Global Teaching Placements: Transition Guide for Kenyan Teachers in the Middle East',
      'category' => 'Teaching Overseas Insights',
      'author' => 'Kennedy Otieno',
      'author_role' => 'International Educator & Mentor, Dubai',
      'summary' => 'A comprehensive review of curriculum cross-training (IB vs. Cambridge), licensing equivalency, visa document attestation, and cultural adaptation for teachers moving abroad.',
      'content' => "Drawing upon first-hand experiences of over 200 Kenyan teachers placed across UAE, Qatar, and Oman, this publication offers a step-by-step roadmap for educators seeking international career mobility.\n\nCovers syllabus mapping, interview simulation strategies, and contract negotiation standards for maximum career growth.",
      'pdf_url' => '/uploads/publications/kenyan_educators_stem_guide.pdf',
      'created_at' => date('Y-m-d H:i:s', strtotime('-25 days')),
      'views_count' => 512
    ]
  ];

  foreach ($seedPapers as $seed) {
    $p = R::dispense('publications');
    $p->title = $seed['title'];
    $p->category = $seed['category'];
    $p->author = $seed['author'];
    $p->author_role = $seed['author_role'];
    $p->summary = $seed['summary'];
    $p->content = $seed['content'];
    $p->pdf_url = $seed['pdf_url'];
    $p->created_at = $seed['created_at'];
    $p->views_count = $seed['views_count'];
    $p->is_published = 1;
    R::store($p);
  }
}

// Fetch all publications
$publications = R::findAll('publications', 'ORDER BY created_at DESC');
?>

<div class="publications-page-wrapper">
  <!-- Hero Section -->
  <section class="pub-hero">
    <div class="pub-container">
      <div class="pub-hero-badge"><i class="fa-solid fa-book-open-reader"></i> Educator Research & Thought Leadership
      </div>
      <h1 class="pub-hero-title">Educator Publications Hub</h1>
      <p class="pub-hero-subtitle">
        Publish, showcase, and discover empirical classroom findings, CBC pedagogical innovations, and academic papers
        authored directly by Kenya's teaching community.
      </p>

      <!-- Search & Submission Action -->
      <div class="pub-hero-action-row">
        <div class="pub-search-box">
          <i class="fa-solid fa-search"></i>
          <input type="text" id="pubSearchInput" placeholder="Search papers by title, author, or keyword..."
            oninput="filterPublications()">
        </div>

        <button type="button" class="btn-submit-paper" onclick="openPubModal()"
          style="background: #ffffff !important; background-color: #ffffff !important; color: #0f766e !important; border: 1.5px solid #ffffff !important; --pico-background-color: #ffffff !important; --pico-color: #0f766e !important; --pico-border-color: #ffffff !important; opacity: 1 !important; visibility: visible !important;">
          <i class="fa-solid fa-plus" style="color: #0f766e !important;"></i>
          <span style="color: #0f766e !important; font-weight: 800 !important;">Submit New Paper</span>
        </button>
      </div>
    </div>
  </section>

  <div class="pub-container" style="padding-top: 2.5rem;">

    <!-- Alerts -->
    <?php if ($actionSuccess): ?>
      <div class="pub-alert pub-alert-success">
        <i class="fa-solid fa-circle-check"></i>
        <span><?= h($actionSuccess) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($actionError): ?>
      <div class="pub-alert pub-alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span><?= h($actionError) ?></span>
      </div>
    <?php endif; ?>

    <!-- Category Filter Pills -->
    <div class="pub-categories-bar">
      <div class="pub-cat-pills">
        <button class="pub-pill active" onclick="setPubCategory('all', this)">All Publications
          (<?= count($publications) ?>)</button>
        <button class="pub-pill" onclick="setPubCategory('CBC Pedagogical Research', this)"><i
            class="fa-solid fa-graduation-cap"></i> CBC Research</button>
        <button class="pub-pill" onclick="setPubCategory('STEM & Digital Learning', this)"><i
            class="fa-solid fa-microchip"></i> STEM & Digital</button>
        <button class="pub-pill" onclick="setPubCategory('Special Needs & Inclusion', this)"><i
            class="fa-solid fa-heart"></i> Special Needs</button>
        <button class="pub-pill" onclick="setPubCategory('Teaching Overseas Insights', this)"><i
            class="fa-solid fa-plane"></i> Teaching Overseas</button>
      </div>

      <div class="pub-count-indicator">
        Showing <span id="visiblePubCount"><?= count($publications) ?></span> published papers
      </div>
    </div>

    <!-- No Results Message -->
    <div id="pubNoResults" class="pub-no-results" style="display: none;">
      <i class="fa-solid fa-newspaper"></i>
      <h3>No matching publications found</h3>
      <p>Try refining your search keyword or selecting a different category filter.</p>
      <button type="button" class="btn-reset-pub-search" onclick="resetPubFilter()">Reset Filters</button>
    </div>

    <!-- Publications Grid (modeled after kmsurveytool) -->
    <div class="publications-grid" id="publicationsGrid">
      <?php foreach ($publications as $pub): ?>
        <div class="pub-card" data-category="<?= h($pub->category) ?>">
          <div class="pub-card-top">
            <div class="pub-card-meta">
              <span class="pub-badge"><?= h($pub->category) ?></span>
              <div class="pub-meta-right">
                <?php if (!empty($pub->pdf_url) || !empty($pub->pdf_path)): ?>
                  <span class="pub-pdf-tag" title="Includes full PDF paper"><i class="fa-solid fa-file-pdf"></i> PDF</span>
                <?php endif; ?>
                <span class="pub-date"><?= date('M d, Y', strtotime($pub->created_at)) ?></span>
              </div>
            </div>

            <h3 class="pub-title">
              <a href="/publications/view?id=<?= $pub->id ?>"><?= h($pub->title) ?></a>
            </h3>

            <p class="pub-summary">
              <?= h(mb_strimwidth($pub->summary, 0, 220, '...')) ?>
            </p>
          </div>

          <div class="pub-card-bottom">
            <div class="pub-author-info">
              <div class="author-avatar"><i class="fa-solid fa-user"></i></div>
              <div class="author-text">
                <span class="author-name"><?= h($pub->author) ?></span>
                <?php if (!empty($pub->author_role)): ?>
                  <span class="author-role"><?= h($pub->author_role) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="pub-card-actions">
              <?php if ($authUser && ($authUser['role'] === 'admin' || $authUser['name'] === $pub->author || ($authUser['id'] == $pub->teacher_id))): ?>
                <form method="post" action=""
                  onsubmit="return confirm('Are you sure you want to delete this publication?');" style="display: inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $pub->id ?>">
                  <button type="submit" class="btn-delete-pub" title="Delete Publication">
                    <i class="fa-regular fa-trash-can"></i>
                  </button>
                </form>
              <?php endif; ?>

              <a href="/publications/view?id=<?= $pub->id ?>" class="btn-view-pub">
                <span>View Findings</span>
                <i class="fa-solid fa-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Submit Publication Modal -->
  <div id="pubModal" class="pub-modal-overlay" style="display: none;" onclick="handleModalBackdrop(event)">
    <div class="pub-modal-container">
      <div class="pub-modal-header">
        <div class="modal-title-group">
          <div class="modal-icon"><i class="fa-solid fa-file-circle-plus"></i></div>
          <div>
            <h3>Submit Research Paper or Article</h3>
            <p>Share your empirical findings, lesson adaptations, or academic publications with Kenyan educators.</p>
          </div>
        </div>
        <button type="button" class="btn-close-modal" onclick="closePubModal()"><i
            class="fa-solid fa-xmark"></i></button>
      </div>

      <form method="post" action="" enctype="multipart/form-data" class="pub-submit-form"
        onsubmit="return validatePubSubmission()">
        <input type="hidden" name="action" value="submit_paper">

        <div class="pub-form-group">
          <label for="pubTitle">Publication Title <span class="req">*</span></label>
          <input type="text" id="pubTitle" name="title" required
            placeholder="e.g. Action Research on CBC Assessment in Junior Secondary" class="pub-input">
        </div>

        <div class="pub-form-row-2">
          <div class="pub-form-group">
            <label for="pubCategory">Category <span class="req">*</span></label>
            <select id="pubCategory" name="category" required class="pub-input">
              <option value="CBC Pedagogical Research">CBC Pedagogical Research</option>
              <option value="STEM & Digital Learning">STEM & Digital Learning</option>
              <option value="Special Needs & Inclusion">Special Needs & Inclusion</option>
              <option value="Educational Leadership">Educational Leadership & Admin</option>
              <option value="Teaching Overseas Insights">Teaching Overseas Insights</option>
              <option value="Curriculum Innovations">General Curriculum Innovations</option>
            </select>
          </div>

          <div class="pub-form-group">
            <label for="pubAuthor">Author Name <span class="req">*</span></label>
            <input type="text" id="pubAuthor" name="author" required value="<?= h($authUser['name'] ?? '') ?>"
              placeholder="e.g. Mwalimu Grace Wanjiku" class="pub-input">
          </div>
        </div>

        <div class="pub-form-group">
          <label for="pubAuthorRole">Author Title / Institution</label>
          <input type="text" id="pubAuthorRole" name="author_role" placeholder="e.g. Senior Chemistry Teacher, Nairobi"
            class="pub-input">
        </div>

        <div class="pub-form-group">
          <label for="pubSummary">Executive Summary / Abstract <span class="req">*</span></label>
          <textarea id="pubSummary" name="summary" rows="3" required
            placeholder="Provide a concise 2-4 sentence summary of your key findings, methodology, and practical takeaways..."
            class="pub-input"></textarea>
        </div>

        <div class="pub-form-group">
          <label for="pubContent">Full Article Content / Notes</label>
          <textarea id="pubContent" name="content" rows="6"
            placeholder="Provide the complete body text, methodology, background, observations, and recommendations..."
            class="pub-input"></textarea>
        </div>

        <!-- PDF Document Upload Box (Academia Style) -->
        <div class="pub-pdf-upload-box">
          <label class="pdf-upload-label">
            <i class="fa-solid fa-file-pdf" style="color: #ef4444; font-size: 1.3rem;"></i>
            <span>Attach Full PDF Research Paper (Optional)</span>
          </label>
          <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" class="pdf-file-input">
          <p class="pdf-hint">Or paste an online PDF link if already hosted on Academia.edu, ResearchGate, or Google
            Drive:</p>
          <input type="url" name="pdf_url" placeholder="https://example.com/research-paper.pdf" class="pub-input"
            style="background: #ffffff;">
        </div>

        <div class="pub-modal-footer">
          <button type="button" class="btn-cancel-modal" onclick="closePubModal()">Cancel</button>
          <button type="submit" class="btn-confirm-submit">
            <i class="fa-solid fa-paper-plane"></i> Submit Publication
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .publications-page-wrapper {
    background: #f8fafc;
    color: #1e293b;
    font-family: inherit;
    padding-bottom: 4rem;
  }

  .pub-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 1.5rem;
  }

  /* Hero */
  .pub-hero {
    background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
    color: #ffffff;
    padding: 4.5rem 0 3.5rem;
    text-align: center;
  }

  .pub-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(45, 212, 191, 0.15);
    border: 1px solid rgba(45, 212, 191, 0.35);
    color: #2dd4bf;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .pub-hero-title,
  .pub-hero h1 {
    font-size: clamp(2rem, 4vw, 3.2rem) !important;
    font-weight: 800 !important;
    line-height: 1.2 !important;
    max-width: 850px;
    margin: 0 auto 1rem !important;
    color: #ffffff !important;
  }

  .pub-hero-subtitle,
  .pub-hero p {
    font-size: 1.05rem !important;
    line-height: 1.6 !important;
    max-width: 720px;
    margin: 0 auto 2rem !important;
    color: #cbd5e1 !important;
  }

  .pub-hero-action-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    max-width: 760px;
    margin: 0 auto;
    flex-wrap: wrap;
  }

  .pub-search-box {
    position: relative !important;
    flex: 1 1 320px !important;
    margin: 0 !important;
  }

  .pub-search-box i {
    position: absolute !important;
    left: 16px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: #64748b !important;
    font-size: 0.95rem !important;
    pointer-events: none !important;
    z-index: 2 !important;
  }

  .pub-search-box input,
  .pub-search-box input#pubSearchInput {
    width: 100% !important;
    height: 48px !important;
    margin: 0 !important;
    padding: 10px 16px 10px 48px !important;
    border-radius: 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 0.95rem !important;
    box-sizing: border-box !important;
    outline: none !important;
    line-height: normal !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
  }

  .pub-search-box input:focus {
    border-color: #2dd4bf !important;
    box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.3) !important;
  }

  button.btn-submit-paper,
  .btn-submit-paper,
  .pub-hero button.btn-submit-paper,
  .pub-hero .btn-submit-paper,
  .pub-hero-action-row button {
    background: #ffffff !important;
    background-color: #ffffff !important;
    color: #0f766e !important;
    border: 1.5px solid #ffffff !important;
    --pico-background-color: #ffffff !important;
    --pico-color: #0f766e !important;
    --pico-border-color: #ffffff !important;
    --pico-primary: #0f766e !important;
    --pico-primary-background: #ffffff !important;
    --pico-primary-hover-background: #f8fafc !important;
    font-weight: 800 !important;
    font-size: 0.95rem !important;
    padding: 12px 24px !important;
    height: 48px !important;
    border-radius: 12px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
    transition: all 0.15s ease !important;
    white-space: nowrap !important;
    margin: 0 !important;
    line-height: 1 !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  button.btn-submit-paper:hover,
  .btn-submit-paper:hover,
  .pub-hero button.btn-submit-paper:hover,
  .pub-hero .btn-submit-paper:hover,
  .pub-hero-action-row button:hover {
    background: #f8fafc !important;
    background-color: #f8fafc !important;
    border-color: #f1f5f9 !important;
    color: #115e59 !important;
    --pico-background-color: #f8fafc !important;
    --pico-color: #115e59 !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25) !important;
  }

  button.btn-submit-paper i,
  button.btn-submit-paper span,
  .btn-submit-paper i,
  .btn-submit-paper span,
  .pub-hero-action-row button i,
  .pub-hero-action-row button span {
    color: #0f766e !important;
    font-weight: 800 !important;
  }

  /* Alerts */
  .pub-alert {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.92rem;
    margin-bottom: 2rem;
  }

  .pub-alert-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
  }

  .pub-alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
  }

  /* Categories Bar */
  .pub-categories-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
  }

  .pub-cat-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  button.pub-pill,
  .pub-pill {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #475569 !important;
    padding: 7px 16px !important;
    border-radius: 999px !important;
    font-size: 0.85rem !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: all 0.15s ease !important;
    margin: 0 !important;
    line-height: normal !important;
    white-space: nowrap !important;
  }

  button.pub-pill:hover,
  .pub-pill:hover {
    background: #f1f5f9 !important;
    color: #0f766e !important;
    border-color: #94a3b8 !important;
  }

  button.pub-pill.active,
  .pub-pill.active {
    background: #0f766e !important;
    color: #ffffff !important;
    border-color: #0f766e !important;
  }

  .pub-count-indicator {
    font-size: 0.86rem;
    color: #64748b;
    font-weight: 600;
  }

  /* Grid & Cards */
  .publications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 1.5rem;
  }

  .pub-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 2rem 1.75rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
  }

  .pub-card:hover {
    transform: translateY(-4px);
    border-color: #0f766e;
    box-shadow: 0 12px 28px rgba(15, 118, 110, 0.1);
  }

  .pub-card-top {
    margin-bottom: 1.5rem;
  }

  .pub-card-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .pub-badge {
    background: #f0fdfa;
    color: #0f766e;
    border: 1px solid #ccfbf1;
    font-size: 0.75rem;
    font-weight: 800;
    padding: 4px 12px;
    border-radius: 999px;
  }

  .pub-meta-right {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pub-pdf-tag {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .pub-date {
    font-size: 0.8rem;
    color: #94a3b8;
    font-weight: 600;
  }

  .pub-title {
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.4;
    color: #0f172a;
    margin: 0 0 0.75rem 0;
  }

  .pub-title a {
    color: #0f172a;
    text-decoration: none;
    transition: color 0.15s ease;
  }

  .pub-title a:hover {
    color: #0f766e;
  }

  .pub-summary {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 0;
  }

  .pub-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 1rem;
  }

  .pub-author-info {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .author-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
  }

  .author-text {
    display: flex;
    flex-direction: column;
  }

  .author-name {
    font-size: 0.86rem;
    font-weight: 700;
    color: #0f172a;
  }

  .author-role {
    font-size: 0.75rem;
    color: #94a3b8;
  }

  .pub-card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .btn-delete-pub {
    background: transparent !important;
    border: none !important;
    color: #f43f5e !important;
    font-size: 0.95rem !important;
    cursor: pointer !important;
    padding: 6px !important;
    border-radius: 6px !important;
    transition: background 0.15s ease !important;
    margin: 0 !important;
  }

  .btn-delete-pub:hover {
    background: #ffe4e6 !important;
  }

  .btn-view-pub {
    color: #0f766e;
    font-weight: 800;
    font-size: 0.86rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: gap 0.15s ease;
  }

  .btn-view-pub:hover {
    gap: 10px;
    text-decoration: underline;
  }

  /* Modal */
  .pub-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }

  .pub-modal-container {
    background: #ffffff;
    border-radius: 20px;
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border: 1px solid #e2e8f0;
    padding: 2.25rem;
  }

  .pub-modal-header {
    background: transparent !important;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.75rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
  }

  .modal-title-group {
    display: flex;
    gap: 12px;
    align-items: center;
  }

  .modal-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #ccfbf1 !important;
    color: #0f766e !important;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
  }

  .modal-title-group h3 {
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin: 0 0 2px 0 !important;
  }

  .modal-title-group p {
    color: #64748b !important;
    font-size: 0.85rem !important;
    margin: 0 !important;
  }

  .btn-close-modal {
    background: transparent !important;
    border: none !important;
    color: #94a3b8 !important;
    font-size: 1.25rem !important;
    cursor: pointer !important;
    padding: 4px !important;
    margin: 0 !important;
    transition: color 0.15s ease !important;
  }

  .btn-close-modal:hover {
    color: #0f172a !important;
  }

  .pub-submit-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  .pub-form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
  }

  .pub-form-group {
    display: flex;
    flex-direction: column;
  }

  .pub-form-group label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
  }

  .req {
    color: #ef4444;
  }

  .pub-input,
  select.pub-input,
  textarea.pub-input {
    width: 100% !important;
    padding: 10px 14px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    font-size: 0.92rem !important;
    background: #f8fafc !important;
    box-sizing: border-box !important;
    margin: 0 !important;
  }

  .pub-input:focus,
  select.pub-input:focus,
  textarea.pub-input:focus {
    outline: none !important;
    border-color: #0f766e !important;
    background: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15) !important;
  }

  .pub-pdf-upload-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem;
  }

  .pdf-upload-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
  }

  .pdf-file-input {
    font-size: 0.85rem;
    margin-bottom: 8px;
  }

  .pdf-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin: 6px 0 6px 0;
  }

  .pub-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 1rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1.25rem;
  }

  button.btn-cancel-modal,
  .btn-cancel-modal {
    background: #f1f5f9 !important;
    border: 1px solid #cbd5e1 !important;
    color: #475569 !important;
    font-weight: 700 !important;
    font-size: 0.9rem !important;
    padding: 10px 20px !important;
    border-radius: 10px !important;
    cursor: pointer !important;
    margin: 0 !important;
  }

  button.btn-confirm-submit,
  .btn-confirm-submit {
    background: #0f766e !important;
    color: #ffffff !important;
    border: none !important;
    font-weight: 800 !important;
    font-size: 0.9rem !important;
    padding: 10px 22px !important;
    border-radius: 10px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    margin: 0 !important;
  }

  button.btn-confirm-submit:hover,
  .btn-confirm-submit:hover {
    background: #115e59 !important;
  }

  /* No results */
  .pub-no-results {
    text-align: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 4rem 2rem;
  }

  .pub-no-results i {
    font-size: 3rem;
    color: #94a3b8;
    margin-bottom: 1rem;
  }

  .pub-no-results h3 {
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px 0;
  }

  .pub-no-results p {
    color: #64748b;
    font-size: 0.95rem;
    margin: 0 0 1.5rem 0;
  }

  button.btn-reset-pub-search,
  .btn-reset-pub-search {
    background: #0f766e !important;
    color: #ffffff !important;
    border: none !important;
    font-weight: 700 !important;
    padding: 9px 20px !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    margin: 0 !important;
  }

  /* Responsive Media Queries */
  @media (max-width: 768px) {
    .pub-hero {
      padding: 3rem 1rem 2.5rem !important;
    }

    .pub-hero-action-row {
      flex-direction: column !important;
      width: 100% !important;
      gap: 10px !important;
    }

    .pub-search-box {
      width: 100% !important;
      flex: unset !important;
    }

    button.btn-submit-paper,
    .btn-submit-paper {
      width: 100% !important;
      justify-content: center !important;
    }

    .pub-categories-bar {
      flex-direction: column !important;
      align-items: flex-start !important;
      gap: 0.75rem !important;
      padding-bottom: 0.75rem !important;
    }

    .pub-cat-pills {
      display: flex !important;
      overflow-x: auto !important;
      flex-wrap: nowrap !important;
      width: 100% !important;
      padding-bottom: 6px !important;
      -webkit-overflow-scrolling: touch !important;
    }

    button.pub-pill,
    .pub-pill {
      flex-shrink: 0 !important;
      font-size: 0.8rem !important;
      padding: 6px 14px !important;
    }

    .publications-grid {
      grid-template-columns: 1fr !important;
      gap: 1.25rem !important;
    }

    .pub-modal-container {
      padding: 1.5rem 1.25rem !important;
      max-height: 94vh !important;
      border-radius: 16px !important;
    }

    .pub-form-row-2 {
      grid-template-columns: 1fr !important;
      gap: 1rem !important;
    }

    .pub-modal-footer {
      flex-direction: column-reverse !important;
      gap: 8px !important;
    }

    .pub-modal-footer button {
      width: 100% !important;
      justify-content: center !important;
    }
  }

  @media (max-width: 480px) {
    .pub-container {
      padding: 0 1rem !important;
    }

    .pub-hero-title,
    .pub-hero h1 {
      font-size: 1.85rem !important;
    }

    .pub-hero-subtitle,
    .pub-hero p {
      font-size: 0.92rem !important;
      margin-bottom: 1.5rem !important;
    }

    .pub-card {
      padding: 1.35rem 1.25rem 1.15rem !important;
      border-radius: 14px !important;
    }

    .pub-title {
      font-size: 1.05rem !important;
    }

    .pub-card-bottom {
      flex-direction: column !important;
      align-items: flex-start !important;
      gap: 12px !important;
    }

    .pub-card-actions {
      width: 100% !important;
      justify-content: space-between !important;
    }

    .pub-input,
    select.pub-input,
    textarea.pub-input {
      font-size: 16px !important;
      /* Prevents auto-zoom on mobile */
    }
  }
</style>

<script>
  let activePubCategory = 'all';

  function openPubModal() {
    document.getElementById('pubModal').style.display = 'flex';
  }

  function closePubModal() {
    document.getElementById('pubModal').style.display = 'none';
  }

  function handleModalBackdrop(e) {
    if (e.target.id === 'pubModal') {
      closePubModal();
    }
  }

  function setPubCategory(cat, btn) {
    activePubCategory = cat;
    document.querySelectorAll('.pub-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    filterPublications();
  }

  function filterPublications() {
    const query = document.getElementById('pubSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.pub-card');
    let matchCount = 0;

    cards.forEach(card => {
      const cat = card.getAttribute('data-category');
      const title = card.querySelector('.pub-title').textContent.toLowerCase();
      const summary = card.querySelector('.pub-summary').textContent.toLowerCase();
      const author = card.querySelector('.author-name').textContent.toLowerCase();

      const matchesCat = (activePubCategory === 'all' || cat === activePubCategory);
      const matchesQuery = !query || title.includes(query) || summary.includes(query) || author.includes(query);

      if (matchesCat && matchesQuery) {
        card.style.display = 'flex';
        matchCount++;
      } else {
        card.style.display = 'none';
      }
    });

    const countEl = document.getElementById('visiblePubCount');
    if (countEl) countEl.textContent = matchCount;

    const noRes = document.getElementById('pubNoResults');
    if (noRes) noRes.style.display = matchCount === 0 ? 'block' : 'none';
  }

  function resetPubFilter() {
    document.getElementById('pubSearchInput').value = '';
    activePubCategory = 'all';
    document.querySelectorAll('.pub-pill').forEach((p, idx) => {
      if (idx === 0) p.classList.add('active');
      else p.classList.remove('active');
    });
    filterPublications();
  }

  function validatePubSubmission() {
    const title = document.getElementById('pubTitle').value.trim();
    const summary = document.getElementById('pubSummary').value.trim();
    if (!title || !summary) {
      alert('Please enter both a title and an executive summary.');
      return false;
    }
    return true;
  }
</script>