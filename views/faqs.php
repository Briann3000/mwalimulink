<div class="faq-page-wrapper">
  <!-- Hero Section with Search -->
  <section class="faq-hero">
    <div class="faq-container">
      <div class="faq-hero-badge"><i class="fa-solid fa-circle-question"></i> Educator & Institutional Knowledge Base</div>
      <h1 class="faq-hero-title">Frequently Asked Questions</h1>
      <p class="faq-hero-subtitle">
        Instant answers to common questions regarding teacher registration, school subscriptions, TP placements, and job applications.
      </p>

      <!-- Live Search Box -->
      <div class="faq-search-wrapper">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" id="faqSearchInput" placeholder="Search keywords e.g. registration, fees, TP hub, overseas, school login..." oninput="filterFaqs()">
        <button type="button" id="faqClearBtn" class="clear-search-btn" onclick="clearFaqSearch()" style="display: none;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
  </section>

  <div class="faq-container" style="padding-top: 2.5rem;">
    <!-- Category Filter Pills & Controls -->
    <div class="faq-controls-bar">
      <div class="faq-category-pills">
        <button class="cat-pill active" onclick="setFaqCategory('all', this)">All Questions (<span id="count-all">22</span>)</button>
        <button class="cat-pill" onclick="setFaqCategory('teachers', this)"><i class="fa-solid fa-graduation-cap"></i> Teachers & Profiles</button>
        <button class="cat-pill" onclick="setFaqCategory('schools', this)"><i class="fa-solid fa-school"></i> Schools & Hiring</button>
        <button class="cat-pill" onclick="setFaqCategory('jobs', this)"><i class="fa-solid fa-briefcase"></i> Job Applications & TP</button>
        <button class="cat-pill" onclick="setFaqCategory('payments', this)"><i class="fa-solid fa-credit-card"></i> Payments & Fees</button>
        <button class="cat-pill" onclick="setFaqCategory('security', this)"><i class="fa-solid fa-shield-halved"></i> Trust & Security</button>
      </div>

      <div class="faq-toggle-all">
        <button type="button" class="btn-toggle-accordion" onclick="toggleAllAccordions(true)">
          <i class="fa-solid fa-angles-down"></i> Expand All
        </button>
        <button type="button" class="btn-toggle-accordion" onclick="toggleAllAccordions(false)">
          <i class="fa-solid fa-angles-up"></i> Collapse All
        </button>
      </div>
    </div>

    <!-- No Results Message -->
    <div id="faqNoResults" class="faq-no-results" style="display: none;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>No matching questions found</h3>
      <p>Try searching for a different keyword or browse questions across all categories.</p>
      <button type="button" class="btn-clear-filter" onclick="clearFaqSearch()">Reset Search</button>
    </div>

    <!-- Accordion List -->
    <div class="faq-accordion-list" id="faqAccordionList">

      <!-- Teacher Category -->
      <div class="faq-item" data-category="teachers">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Teachers</span>
          <span class="faq-question">Is registration free for teachers on MwalimuLink?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. Registration is 100% free for all teachers and educators. Once registered, you can build your digital resume, showcase your TSC registration, list your subject specializations, and explore job vacancies across Kenya.</p>
        </div>
      </div>

      <div class="faq-item" data-category="teachers">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Teachers</span>
          <span class="faq-question">Can I update my profile after registration?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. Log in to your educator account and navigate to <strong>Edit Profile</strong>. You can update your academic qualifications, teaching subjects, years of experience, preferred counties, and upload new certifications at any time.</p>
        </div>
      </div>

      <div class="faq-item" data-category="teachers">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Teachers</span>
          <span class="faq-question">What file formats are accepted for CV and certificate uploads?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>We accept PDF, DOC, DOCX, and high-resolution image files (JPG/PNG). We recommend PDF format for clean, professional rendering across all devices. Maximum file size per document is 5MB.</p>
        </div>
      </div>

      <div class="faq-item" data-category="teachers">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Teachers</span>
          <span class="faq-question">Will I be notified when a school reviews my profile or shortlists me?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes! You will receive an instant email notification whenever a school administrator shortlists you or invites you for an interview. You can also monitor your active applications directly from your Teacher Dashboard.</p>
        </div>
      </div>

      <!-- School Category -->
      <div class="faq-item" data-category="schools">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Schools</span>
          <span class="faq-question">How do I register our school or educational institution?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Click on the <strong>Register Institution</strong> button, enter your school's official details (MoE/KNEC code, county, institution type, and administrator contact), and submit. Your profile is reviewed and activated within a few hours.</p>
        </div>
      </div>

      <div class="faq-item" data-category="schools">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Schools</span>
          <span class="faq-question">Is registration open to ECDE centers, TVETs, and colleges?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes! MwalimuLink caters to the full spectrum of Kenyan education: Early Childhood Development (ECDE) centers, primary schools (Junior & Primary), secondary schools, TVET institutions, teacher training colleges, and international academies.</p>
        </div>
      </div>

      <div class="faq-item" data-category="schools">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Schools</span>
          <span class="faq-question">Can school administrators post multiple vacancies simultaneously?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. Subscribed school accounts have unlimited vacancy postings throughout their subscription period. You can manage multiple department listings (e.g. Sciences, Humanities, Languages) concurrently.</p>
        </div>
      </div>

      <div class="faq-item" data-category="schools">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Schools</span>
          <span class="faq-question">Can schools search and filter teachers by county and subject mastery?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. Our Candidate Search engine allows school principals to instantly filter verified teachers by TSC registration status, subject combinations (e.g. Maths/Chemistry, English/Literature), degree level, and geographic county preference.</p>
        </div>
      </div>

      <!-- Job Applications & TP -->
      <div class="faq-item" data-category="jobs">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Jobs & TP</span>
          <span class="faq-question">How does the Teaching Practice (TP) Practicum Hub work?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>The TP Hub connects university and diploma student teachers with welcoming host schools across Kenya. Student teachers can search schools that accept TP attachments, request placements, and receive institutional mentor feedback.</p>
        </div>
      </div>

      <div class="faq-item" data-category="jobs">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Jobs & TP</span>
          <span class="faq-question">Can a teacher apply to multiple school vacancies at the same time?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes, there is no restriction on the number of positions you can apply for. We encourage candidates to apply for all vacancies matching their subject specialization and target regions.</p>
        </div>
      </div>

      <div class="faq-item" data-category="jobs">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Jobs & TP</span>
          <span class="faq-question">How do I apply for international and overseas teaching opportunities?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Visit our dedicated <a href="/faqs-overseas" style="color: #0f766e; font-weight: 700;">Teaching Overseas FAQ</a> and the International Schools directory. MwalimuLink partners with vetted global placement agencies to connect Kenyan educators with positions in the Middle East, Asia, and the UK.</p>
        </div>
      </div>

      <!-- Payments & Fees -->
      <div class="faq-item" data-category="payments">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Payments</span>
          <span class="faq-question">What payment methods are supported on MwalimuLink?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>We support secure M-Pesa STK Push payments, M-Pesa Till / Paybill numbers, credit/debit cards (Visa/Mastercard), and direct bank transfers. All transactions receive instant automated receipts.</p>
        </div>
      </div>

      <div class="faq-item" data-category="payments">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Payments</span>
          <span class="faq-question">Do teachers pay any monthly subscription fees?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>No. Teacher profiles, CV hosting, and publication submissions are completely free. Optional placement contact access fees apply only when unlocking direct employer contact details for specific priority placements.</p>
        </div>
      </div>

      <div class="faq-item" data-category="payments">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Payments</span>
          <span class="faq-question">What is the subscription fee structure for schools?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Schools pay a modest annual subscription fee that grants unlimited vacancy postings, access to our candidate database of thousands of certified teachers, and priority placement support.</p>
        </div>
      </div>

      <!-- Trust & Security -->
      <div class="faq-item" data-category="security">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-sec">Security</span>
          <span class="faq-question">How does MwalimuLink protect personal teacher and school data?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>We strictly comply with the Kenya Data Protection Act 2019. Sensitive data (including contact details and national ID numbers) is encrypted in transit and at rest, and is never sold or shared with unverified third parties.</p>
        </div>
      </div>

      <div class="faq-item" data-category="security">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-sec">Security</span>
          <span class="faq-question">How are fraudulent or suspicious listings prevented?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Every school registration is cross-referenced with Ministry of Education records and verified via institutional email/phone validation before vacancy publishing is enabled. Suspicious activity can be reported immediately to our support team.</p>
        </div>
      </div>

      <div class="faq-item" data-category="security">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-sec">Security</span>
          <span class="faq-question">What should I do if I forget my account password?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Go to the login page and click <strong>"Forgot Password"</strong>. Enter your registered email address, and you will receive a secure password reset link within seconds.</p>
        </div>
      </div>

    </div>

    <!-- Contact Support Footer Banner -->
    <div class="faq-support-banner">
      <div class="banner-left">
        <div class="banner-icon"><i class="fa-solid fa-comments"></i></div>
        <div>
          <h3>Still Have Questions?</h3>
          <p>Can't find the answer you're looking for? Our educational support specialists are ready to help you.</p>
        </div>
      </div>
      <div class="banner-actions">
        <a href="/contacts" class="btn-ask-support"><i class="fa-solid fa-envelope"></i> Contact Support Desk</a>
        <a href="/faqs-overseas" class="btn-overseas-faq"><i class="fa-solid fa-plane"></i> Teaching Overseas FAQ</a>
      </div>
    </div>
  </div>
</div>

<style>
.faq-page-wrapper {
  background: #f8fafc;
  color: #1e293b;
  font-family: inherit;
  padding-bottom: 4rem;
}

.faq-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero */
.faq-hero {
  background: linear-gradient(135deg, #0f172a 0%, #115e59 100%);
  color: #ffffff !important;
  padding: 4.5rem 0 3.5rem;
  text-align: center;
}

.faq-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(45, 212, 191, 0.15);
  border: 1px solid rgba(45, 212, 191, 0.35);
  color: #2dd4bf !important;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 1.25rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.faq-hero-title,
.faq-hero h1 {
  font-size: clamp(2rem, 4vw, 3rem) !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
  max-width: 800px;
  margin: 0 auto 1rem !important;
  color: #ffffff !important;
}

.faq-hero-subtitle,
.faq-hero p {
  font-size: 1.05rem !important;
  line-height: 1.6 !important;
  max-width: 680px;
  margin: 0 auto 2rem !important;
  color: #cbd5e1 !important;
}

/* Search Box */
.faq-search-wrapper {
  position: relative;
  max-width: 620px;
  margin: 0 auto;
}

.search-icon {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 1.1rem;
}

.faq-search-wrapper input {
  width: 100%;
  padding: 15px 46px 15px 50px;
  border-radius: 14px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  background: #ffffff;
  color: #0f172a;
  font-size: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  box-sizing: border-box;
  outline: none;
  transition: all 0.2s ease;
}

.faq-search-wrapper input:focus {
  border-color: #2dd4bf;
  box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.25);
}

.clear-search-btn {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: #e2e8f0;
  border: none;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  color: #475569;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}

/* Controls Bar */
.faq-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.faq-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.cat-pill {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 7px 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.cat-pill:hover {
  background: #f1f5f9;
  color: #0f766e;
  border-color: #94a3b8;
}

.cat-pill.active {
  background: #0f766e;
  color: #ffffff;
  border-color: #0f766e;
  box-shadow: 0 2px 8px rgba(15, 118, 110, 0.25);
}

.faq-toggle-all {
  display: flex;
  gap: 8px;
}

.btn-toggle-accordion {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.btn-toggle-accordion:hover {
  color: #0f766e;
  background: #f8fafc;
}

/* Accordion */
.faq-accordion-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 3rem;
}

.faq-item {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.faq-item:hover {
  border-color: #cbd5e1;
}

.faq-item.open {
  border-color: #0f766e;
  box-shadow: 0 6px 20px rgba(15, 118, 110, 0.08);
}

.faq-trigger {
  width: 100%;
  padding: 1.25rem 1.5rem;
  background: transparent;
  border: none;
  display: flex;
  align-items: center;
  gap: 14px;
  text-align: left;
  cursor: pointer;
  color: #0f172a;
}

.faq-badge {
  font-size: 0.72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 3px 10px;
  border-radius: 6px;
  flex-shrink: 0;
}

.badge-teacher { background: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; }
.badge-school { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
.badge-jobs { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; }
.badge-pay { background: #faf5ff; color: #9333ea; border: 1px solid #e9d5ff; }
.badge-sec { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.faq-question {
  flex: 1;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}

.faq-chevron {
  color: #94a3b8;
  font-size: 0.85rem;
  transition: transform 0.25s ease;
  flex-shrink: 0;
}

.faq-item.open .faq-chevron {
  transform: rotate(180deg);
  color: #0f766e;
}

.faq-answer {
  display: none;
  padding: 0 1.5rem 1.5rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  margin-top: 0.25rem;
  padding-top: 1rem;
}

.faq-answer p {
  color: #475569;
  line-height: 1.65;
  font-size: 0.95rem;
  margin: 0;
}

.faq-item.open .faq-answer {
  display: block;
}

/* No Results */
.faq-no-results {
  text-align: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 3.5rem 2rem;
  margin-bottom: 2rem;
}

.faq-no-results i {
  font-size: 2.5rem;
  color: #94a3b8;
  margin-bottom: 1rem;
}

.faq-no-results h3 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.faq-no-results p {
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 1.5rem;
}

.btn-clear-filter {
  background: #0f766e;
  color: #ffffff;
  border: none;
  padding: 9px 20px;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
}

/* Support Banner */
.faq-support-banner {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
  color: #ffffff !important;
  border-radius: 18px;
  padding: 2.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.banner-left {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  max-width: 600px;
}

.banner-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: rgba(45, 212, 191, 0.15);
  color: #2dd4bf !important;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  flex-shrink: 0;
}

.banner-left h3,
.faq-support-banner h3 {
  font-size: 1.3rem !important;
  font-weight: 800 !important;
  color: #ffffff !important;
  margin: 0 0 4px 0 !important;
}

.banner-left p,
.faq-support-banner p {
  color: #cbd5e1 !important;
  font-size: 0.92rem !important;
  margin: 0 !important;
}

.banner-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-ask-support {
  background: #2dd4bf;
  color: #0f172a !important;
  font-weight: 800;
  font-size: 0.92rem;
  padding: 11px 22px;
  border-radius: 10px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-ask-support:hover {
  background: #14b8a6;
}

.btn-overseas-faq {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #ffffff;
  font-weight: 700;
  font-size: 0.92rem;
  padding: 11px 20px;
  border-radius: 10px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-overseas-faq:hover {
  background: rgba(255, 255, 255, 0.18);
}

@media (max-width: 768px) {
  .faq-hero {
    padding: 3rem 1rem 2.5rem;
  }
  .faq-controls-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  .faq-category-pills {
    width: 100%;
    overflow-x: auto;
    flex-wrap: nowrap;
    padding-bottom: 6px;
    -webkit-overflow-scrolling: touch;
  }
  .cat-pill {
    white-space: nowrap;
    flex-shrink: 0;
  }
  .faq-support-banner {
    flex-direction: column;
    text-align: center;
    padding: 2rem 1.25rem;
  }
  .banner-left {
    flex-direction: column;
    text-align: center;
  }
  .banner-actions {
    flex-direction: column;
    width: 100%;
  }
  .banner-actions a {
    width: 100%;
    box-sizing: border-box;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .faq-container {
    padding: 0 1rem;
  }
  .faq-hero-title,
  .faq-hero h1 {
    font-size: 1.75rem !important;
  }
  .faq-hero-subtitle,
  .faq-hero p {
    font-size: 0.95rem !important;
    margin-bottom: 1.5rem !important;
  }
  .faq-trigger {
    padding: 1rem 1.15rem;
    gap: 10px;
  }
  .faq-question {
    font-size: 0.95rem;
  }
  .faq-answer {
    padding: 0 1.15rem 1.15rem;
    font-size: 0.88rem;
  }
  .faq-search-wrapper input {
    font-size: 16px !important; /* iOS zoom prevention */
    padding: 12px 40px 12px 42px;
  }
}
</style>

<script>
let currentCategory = 'all';

function toggleFaq(triggerBtn) {
  const item = triggerBtn.closest('.faq-item');
  item.classList.toggle('open');
}

function toggleAllAccordions(expand) {
  document.querySelectorAll('.faq-item').forEach(item => {
    if (item.style.display !== 'none') {
      if (expand) item.classList.add('open');
      else item.classList.remove('open');
    }
  });
}

function setFaqCategory(cat, btn) {
  currentCategory = cat;
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  filterFaqs();
}

function filterFaqs() {
  const search = document.getElementById('faqSearchInput').value.toLowerCase().trim();
  const clearBtn = document.getElementById('faqClearBtn');
  if (clearBtn) clearBtn.style.display = search ? 'flex' : 'none';

  const items = document.querySelectorAll('.faq-item');
  let matchCount = 0;

  items.forEach(item => {
    const cat = item.getAttribute('data-category');
    const qText = item.querySelector('.faq-question').textContent.toLowerCase();
    const aText = item.querySelector('.faq-answer').textContent.toLowerCase();

    const matchesCategory = (currentCategory === 'all' || cat === currentCategory);
    const matchesSearch = !search || qText.includes(search) || aText.includes(search);

    if (matchesCategory && matchesSearch) {
      item.style.display = 'block';
      matchCount++;
      if (search) item.classList.add('open');
    } else {
      item.style.display = 'none';
    }
  });

  const noRes = document.getElementById('faqNoResults');
  if (noRes) noRes.style.display = matchCount === 0 ? 'block' : 'none';
}

function clearFaqSearch() {
  document.getElementById('faqSearchInput').value = '';
  document.getElementById('faqClearBtn').style.display = 'none';
  currentCategory = 'all';
  document.querySelectorAll('.cat-pill').forEach((p, idx) => {
    if (idx === 0) p.classList.add('active');
    else p.classList.remove('active');
  });
  filterFaqs();
}
</script>
