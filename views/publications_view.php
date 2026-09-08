<?php
// views/publications_view.php
$id = (int)($_GET['id'] ?? 0);
$publication = null;

if ($id > 0) {
    $publication = R::load('publications', $id);
    if ($publication && $publication->id) {
        $publication->views_count = ((int)$publication->views_count) + 1;
        R::store($publication);
    }
}

if (!$publication || !$publication->id) {
    // Fallback: load latest
    $publication = R::findOne('publications', 'ORDER BY created_at DESC');
}

$authUser = auth_user();
$relatedPubs = [];
if ($publication && $publication->id) {
    $relatedPubs = R::findAll('publications', 'id != ? ORDER BY created_at DESC LIMIT 3', [$publication->id]);
}

$pdfSrc = $publication->pdf_url ?? ($publication->pdf_path ?? null);
?>

<div class="pub-reader-wrapper">
  <div class="pub-reader-container">

    <!-- Top Breadcrumb & Actions Bar -->
    <div class="reader-top-bar">
      <a href="/publications" class="btn-back-pubs">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Publications Hub</span>
      </a>

      <div class="reader-actions">
        <?php if ($pdfSrc): ?>
          <a href="<?= htmlspecialchars($pdfSrc) ?>" target="_blank" download class="btn-reader-download">
            <i class="fa-solid fa-download"></i>
            <span>Download Paper</span>
          </a>
        <?php endif; ?>

        <button type="button" class="btn-reader-share" onclick="sharePublication()" title="Share Publication">
          <i class="fa-solid fa-share-nodes"></i>
        </button>
      </div>
    </div>

    <!-- Main Article Article Card -->
    <article class="pub-article-card">
      <!-- Article Header -->
      <header class="pub-article-header">
        <div class="header-tags">
          <span class="pub-cat-tag"><?= h($publication->category ?? 'Educational Research') ?></span>
          <span class="pub-date-tag"><i class="fa-regular fa-calendar"></i> <?= date('F d, Y', strtotime($publication->created_at ?? 'now')) ?></span>
          <span class="pub-views-tag"><i class="fa-solid fa-eye"></i> <?= number_format($publication->views_count ?? 1) ?> Views</span>
        </div>

        <h1 class="pub-article-title"><?= h($publication->title ?? 'Untitled Publication') ?></h1>

        <div class="pub-author-strip">
          <div class="author-circle"><i class="fa-solid fa-user-graduate"></i></div>
          <div class="author-details">
            <div class="author-name-lg"><?= h($publication->author ?? 'Educator Author') ?></div>
            <div class="author-role-lg"><?= h($publication->author_role ?? 'Senior Educator / Researcher') ?></div>
          </div>
        </div>
      </header>

      <!-- Executive Summary Callout Box -->
      <div class="pub-exec-summary-box">
        <div class="exec-header">
          <i class="fa-solid fa-lightbulb"></i>
          <h4>Executive Summary / Abstract</h4>
        </div>
        <p><?= nl2br(h($publication->summary ?? '')) ?></p>
      </div>

      <!-- Academia-Style PDF Document Viewer -->
      <?php if ($pdfSrc): ?>
        <section class="academia-viewer-section">
          <div class="academia-viewer-toolbar">
            <div class="toolbar-info">
              <div class="pdf-icon-badge"><i class="fa-solid fa-file-pdf"></i></div>
              <div>
                <strong>Academia Research Paper Viewer</strong>
                <span>Empirical PDF Document Reader</span>
              </div>
            </div>

            <div class="toolbar-actions">
              <a href="<?= htmlspecialchars($pdfSrc) ?>" target="_blank" class="btn-pdf-fullscreen" title="Open PDF in Full Tab">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in New Tab
              </a>
              <a href="<?= htmlspecialchars($pdfSrc) ?>" download class="btn-pdf-download-sm">
                <i class="fa-solid fa-arrow-down-to-line"></i> Download PDF
              </a>
            </div>
          </div>

          <div class="pdf-iframe-container">
            <object data="<?= htmlspecialchars($pdfSrc) ?>#toolbar=1&navpanes=0" type="application/pdf" class="pdf-iframe">
              <iframe src="<?= htmlspecialchars($pdfSrc) ?>#toolbar=1&navpanes=0" class="pdf-iframe" title="PDF Document Viewer">
                <div class="pdf-fallback-banner">
                  <i class="fa-solid fa-file-pdf fa-3x" style="color: #ef4444; margin-bottom: 12px;"></i>
                  <h4>Preview Not Supported in This Browser Window</h4>
                  <p>Your browser requires direct viewing for this research paper format.</p>
                  <a href="<?= htmlspecialchars($pdfSrc) ?>" target="_blank" class="btn-fallback-open">
                    <i class="fa-solid fa-eye"></i> View Full Document Here
                  </a>
                </div>
              </iframe>
            </object>
          </div>
        </section>
      <?php endif; ?>

      <!-- Full Article Content Body -->
      <?php if (!empty($publication->content) && $publication->content !== $publication->summary): ?>
        <section class="pub-body-section">
          <h3>Full Article Findings & Practical Adaptations</h3>
          <div class="pub-content-text">
            <?= nl2br(h($publication->content)) ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- Social Share & Educator Attribution Footer -->
      <footer class="pub-article-footer">
        <div class="share-box">
          <span>Share this research with fellow educators:</span>
          <div class="share-buttons">
            <a href="https://wa.me/?text=<?= urlencode(($publication->title ?? '') . ' - Read on MwalimuLink: ' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/publications/view?id=' . ($publication->id ?? 1)) ?>" target="_blank" class="share-btn share-wa"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode(($publication->title ?? '') . ' via @MwalimuLink') ?>&url=<?= urlencode((isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/publications/view?id=' . ($publication->id ?? 1)) ?>" target="_blank" class="share-btn share-tw"><i class="fa-brands fa-x-twitter"></i> X / Twitter</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode((isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/publications/view?id=' . ($publication->id ?? 1)) ?>" target="_blank" class="share-btn share-in"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
            <button type="button" class="share-btn share-copy" onclick="copyPubLink(this)"><i class="fa-regular fa-copy"></i> Copy Link</button>
          </div>
        </div>
      </footer>
    </article>

    <!-- Related Publications -->
    <?php if (!empty($relatedPubs)): ?>
      <section class="related-pubs-section">
        <h3><i class="fa-solid fa-book-bookmark" style="color: #0f766e;"></i> Related Educational Publications</h3>
        <div class="related-grid">
          <?php foreach ($relatedPubs as $rel): ?>
            <div class="related-card">
              <span class="rel-category"><?= h($rel->category) ?></span>
              <h4><a href="/publications/view?id=<?= $rel->id ?>"><?= h($rel->title) ?></a></h4>
              <p><?= h(mb_strimwidth($rel->summary, 0, 110, '...')) ?></p>
              <div class="rel-footer">
                <span class="rel-author"><?= h($rel->author) ?></span>
                <a href="/publications/view?id=<?= $rel->id ?>" class="rel-link">Read Paper <i class="fa-solid fa-arrow-right"></i></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  </div>
</div>

<style>
.pub-reader-wrapper {
  background: #f8fafc;
  color: #1e293b;
  font-family: inherit;
  padding: 3rem 0 5rem;
}

.pub-reader-container {
  max-width: 960px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Top Bar */
.reader-top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.btn-back-pubs {
  color: #0f766e;
  font-weight: 700;
  font-size: 0.92rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: transform 0.15s ease;
}

.btn-back-pubs:hover {
  transform: translateX(-3px);
  text-decoration: underline;
}

.reader-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-reader-download {
  background: #0f766e;
  color: #ffffff;
  padding: 8px 18px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.88rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background 0.15s ease;
}

.btn-reader-download:hover {
  background: #115e59;
}

.btn-reader-share {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-reader-share:hover {
  background: #f1f5f9;
  color: #0f766e;
}

/* Main Article Card */
.pub-article-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 3rem 2.5rem;
  box-shadow: 0 4px 25px rgba(0,0,0,0.03);
  margin-bottom: 3rem;
}

@media (max-width: 600px) {
  .pub-article-card {
    padding: 2rem 1.25rem;
  }
}

/* Header */
.pub-article-header {
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 2rem;
  margin-bottom: 2rem;
}

.header-tags {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 1.25rem;
}

.pub-cat-tag {
  background: #f0fdfa;
  color: #0f766e;
  border: 1px solid #ccfbf1;
  font-size: 0.8rem;
  font-weight: 800;
  padding: 4px 14px;
  border-radius: 999px;
}

.pub-date-tag, .pub-views-tag {
  font-size: 0.84rem;
  color: #64748b;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pub-article-title {
  font-size: clamp(1.8rem, 3.5vw, 2.5rem);
  font-weight: 800;
  line-height: 1.25;
  color: #0f172a;
  margin: 0 0 1.5rem 0;
}

.pub-author-strip {
  display: flex;
  align-items: center;
  gap: 14px;
}

.author-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #f0fdfa;
  color: #0f766e;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  border: 2px solid #ccfbf1;
}

.author-name-lg {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
}

.author-role-lg {
  font-size: 0.86rem;
  color: #64748b;
}

/* Executive Summary Box */
.pub-exec-summary-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.75rem;
  margin-bottom: 2.5rem;
}

.exec-header {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #0f766e;
  margin-bottom: 0.75rem;
}

.exec-header h4 {
  font-size: 1rem;
  font-weight: 800;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.pub-exec-summary-box p {
  color: #334155;
  font-size: 0.98rem;
  line-height: 1.7;
  margin: 0;
  font-weight: 500;
}

/* Academia Viewer */
.academia-viewer-section {
  margin-bottom: 2.5rem;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0,0,0,0.06);
  border: 1px solid #e2e8f0;
}

.academia-viewer-toolbar {
  background: #0f172a;
  color: #ffffff;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.toolbar-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.pdf-icon-badge {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
}

.toolbar-info strong {
  display: block;
  font-size: 0.95rem;
  color: #ffffff;
}

.toolbar-info span {
  font-size: 0.78rem;
  color: #94a3b8;
}

.toolbar-actions {
  display: flex;
  gap: 8px;
}

.btn-pdf-fullscreen {
  background: #1e293b;
  color: #cbd5e1;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-pdf-fullscreen:hover {
  background: #334155;
  color: #ffffff;
}

.btn-pdf-download-sm {
  background: #0f766e;
  color: #ffffff;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-pdf-download-sm:hover {
  background: #115e59;
}

.pdf-iframe-container {
  width: 100%;
  height: 650px;
  background: #f1f5f9;
  position: relative;
}

.pdf-iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

.pdf-fallback-banner {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  text-align: center;
  background: #f8fafc;
}

.pdf-fallback-banner h4 {
  font-size: 1.15rem;
  color: #0f172a;
  margin: 0 0 8px 0;
  font-weight: 700;
}

.pdf-fallback-banner p {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0 0 18px 0;
}

.btn-fallback-open {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #0f766e;
  color: #ffffff !important;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 22px;
  border-radius: 8px;
  text-decoration: none;
  box-shadow: 0 2px 4px rgba(15,118,110,0.2);
}

.btn-fallback-open:hover {
  background: #115e59;
}

/* Body Text */
.pub-body-section {
  margin-bottom: 2.5rem;
}

.pub-body-section h3 {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 1rem 0;
}

.pub-content-text {
  color: #334155;
  font-size: 1rem;
  line-height: 1.8;
}

/* Footer & Share */
.pub-article-footer {
  border-top: 1px solid #e2e8f0;
  padding-top: 2rem;
  margin-top: 2rem;
}

.share-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.share-box span {
  font-size: 0.9rem;
  font-weight: 700;
  color: #475569;
}

.share-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.share-btn {
  padding: 7px 14px;
  border-radius: 8px;
  font-size: 0.84rem;
  font-weight: 700;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  border: none;
}

.share-wa { background: #dcfce7; color: #15803d; }
.share-tw { background: #f1f5f9; color: #0f172a; }
.share-in { background: #e0f2fe; color: #0369a1; }
.share-copy { background: #f1f5f9; color: #475569; }

/* Related */
.related-pubs-section h3 {
  font-size: 1.3rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 10px;
}

.related-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.25rem;
}

.related-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  transition: all 0.2s ease;
}

.related-card:hover {
  transform: translateY(-3px);
  border-color: #0f766e;
}

.rel-category {
  font-size: 0.72rem;
  font-weight: 800;
  color: #0f766e;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.related-card h4 {
  font-size: 1rem;
  font-weight: 800;
  margin: 0 0 8px 0;
  line-height: 1.4;
}

.related-card h4 a {
  color: #0f172a;
  text-decoration: none;
}

.related-card h4 a:hover {
  color: #0f766e;
}

.related-card p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.5;
  margin: 0 0 1rem 0;
}

.rel-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #f1f5f9;
  padding-top: 0.75rem;
}

.rel-author {
  font-size: 0.78rem;
  color: #94a3b8;
  font-weight: 600;
}

.rel-link {
  color: #0f766e;
  font-weight: 800;
  font-size: 0.82rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

@media (max-width: 768px) {
  .pub-view-wrapper {
    padding-bottom: 3rem;
  }
  .pub-view-header {
    margin-bottom: 1.5rem;
  }
  .pub-title-main {
    font-size: 1.6rem !important;
    line-height: 1.3 !important;
  }
  .pub-author-strip {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
  }
  .pub-share-bar {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }
  .pub-pdf-frame {
    height: 400px !important;
  }
  .pdf-viewer-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 10px 14px;
  }
  .pdf-controls {
    width: 100%;
    justify-content: space-between;
  }
  .related-grid {
    grid-template-columns: 1fr !important;
  }
}

@media (max-width: 480px) {
  .pub-view-container {
    padding: 0 1rem;
  }
  .pub-title-main {
    font-size: 1.35rem !important;
  }
  .pub-exec-summary-box {
    padding: 1.25rem 1rem !important;
  }
  .pub-body-card {
    padding: 1.5rem 1rem !important;
  }
  .pub-pdf-frame {
    height: 320px !important;
  }
  .btn-download-pdf,
  .btn-fullscreen-pdf {
    font-size: 0.78rem;
    padding: 6px 10px;
  }
}
</style>

<script>
function copyPubLink(btn) {
  navigator.clipboard.writeText(window.location.href).then(function() {
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color: #0f766e;"></i> Copied!';
    setTimeout(() => { btn.innerHTML = original; }, 2000);
  });
}

function sharePublication() {
  if (navigator.share) {
    navigator.share({
      title: document.title,
      url: window.location.href
    }).catch(console.error);
  } else {
    copyPubLink(document.querySelector('.share-copy'));
  }
}
</script>
