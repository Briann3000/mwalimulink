<div class="overseas-faq-wrapper">
  <!-- Hero Section -->
  <section class="overseas-hero">
    <div class="overseas-container">
      <div class="overseas-hero-badge"><i class="fa-solid fa-globe"></i> Global Educator Placement Program</div>
      <h1 class="overseas-hero-title">Teaching Overseas FAQ & Placement Guide</h1>
      <p class="overseas-hero-subtitle">
        Everything you need to know about qualifying for international schools, visa sponsorship, remuneration packages, and global relocation through MwalimuLink.
      </p>

      <!-- Live Search Box -->
      <div class="faq-search-wrapper">
        <i class="fa-solid fa-search search-icon"></i>
        <input type="text" id="overseasSearchInput" placeholder="Search keywords e.g. visa, qualifications, salaries, Middle East, UK..." oninput="filterOverseasFaqs()">
        <button type="button" id="overseasClearBtn" class="clear-search-btn" onclick="clearOverseasSearch()" style="display: none;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
    </div>
  </section>

  <div class="overseas-container" style="padding-top: 2.5rem;">
    <!-- Destination Highlights -->
    <div class="destination-grid">
      <div class="dest-card">
        <div class="dest-icon" style="background: #fef3c7; color: #d97706;"><i class="fa-solid fa-sun"></i></div>
        <h4>Middle East & Gulf</h4>
        <p>UAE, Qatar, Saudi Arabia, Oman & Kuwait. Tax-free salaries, furnished accommodation & annual flights.</p>
      </div>
      <div class="dest-card">
        <div class="dest-icon" style="background: #e0f2fe; color: #0284c7;"><i class="fa-solid fa-landmark"></i></div>
        <h4>UK & Europe</h4>
        <p>Qualified Teacher Status (QTS) pathways for STEM, Mathematics, Physics & Special Needs teachers.</p>
      </div>
      <div class="dest-card">
        <div class="dest-icon" style="background: #dcfce7; color: #16a34a;"><i class="fa-solid fa-earth-asia"></i></div>
        <h4>Asia & Far East</h4>
        <p>China, Vietnam, Thailand & Japan. High demand for IB / Cambridge educators and ESL specialists.</p>
      </div>
      <div class="dest-card">
        <div class="dest-icon" style="background: #ccfbf1; color: #0f766e;"><i class="fa-solid fa-laptop-code"></i></div>
        <h4>Remote & Online</h4>
        <p>Global online tutoring and curriculum authoring with flexible hours from anywhere in Kenya.</p>
      </div>
    </div>

    <!-- Controls Bar -->
    <div class="faq-controls-bar">
      <div class="faq-category-pills">
        <button class="cat-pill active" onclick="setOverseasCategory('all', this)">All Questions (17)</button>
        <button class="cat-pill" onclick="setOverseasCategory('eligibility', this)"><i class="fa-solid fa-user-graduate"></i> Qualifications & Eligibility</button>
        <button class="cat-pill" onclick="setOverseasCategory('visa', this)"><i class="fa-solid fa-passport"></i> Visas & Relocation</button>
        <button class="cat-pill" onclick="setOverseasCategory('compensation', this)"><i class="fa-solid fa-money-bill-wave"></i> Salaries & Family</button>
        <button class="cat-pill" onclick="setOverseasCategory('application', this)"><i class="fa-solid fa-file-signature"></i> Application Timing</button>
      </div>

      <div class="faq-toggle-all">
        <button type="button" class="btn-toggle-accordion" onclick="toggleAllOverseas(true)">
          <i class="fa-solid fa-angles-down"></i> Expand All
        </button>
        <button type="button" class="btn-toggle-accordion" onclick="toggleAllOverseas(false)">
          <i class="fa-solid fa-angles-up"></i> Collapse All
        </button>
      </div>
    </div>

    <!-- No Results -->
    <div id="overseasNoResults" class="faq-no-results" style="display: none;">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>No matching questions found</h3>
      <p>Try searching for a different keyword or browse questions across all categories.</p>
      <button type="button" class="btn-clear-filter" onclick="clearOverseasSearch()">Reset Search</button>
    </div>

    <!-- Accordion List -->
    <div class="faq-accordion-list" id="overseasAccordionList">

      <!-- Eligibility -->
      <div class="faq-item" data-category="eligibility">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Eligibility</span>
          <span class="faq-question">1. How do I begin the process of teaching overseas through MwalimuLink?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>
            Once registered on MwalimuLink, you gain two major advantages:<br>
            • <strong>Direct Access:</strong> Explore our curated directory of over 6,000 verified international schools worldwide, including direct headteacher/HR contact channels.<br>
            • <strong>Agency Placement:</strong> Access vetted recruitment partners collaborating with MwalimuLink to place Kenyan teachers in global institutions.<br><br>
            Ensure your profile is updated with your degree transcripts, TSC certificate, teaching subject mastery, and a professional resume.
          </p>
        </div>
      </div>

      <div class="faq-item" data-category="eligibility">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Eligibility</span>
          <span class="faq-question">2. Who can apply for overseas teaching jobs via MwalimuLink?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Any qualified teacher with recognized academic credentials — regardless of nationality or current county of residence — can apply, provided they meet international school accreditation requirements.</p>
        </div>
      </div>

      <div class="faq-item" data-category="eligibility">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Eligibility</span>
          <span class="faq-question">3. What are the basic qualifications to teach overseas?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>
            Standard institutional requirements across most international schools include:<br>
            • <strong>Academic Degree:</strong> Bachelor of Education (B.Ed) or Bachelor's in a teaching subject + PGDE.<br>
            • <strong>Professional License:</strong> Active TSC registration or recognized international teaching license.<br>
            • <strong>Experience:</strong> Minimum 1–2 years full-time classroom teaching experience.<br>
            • <strong>Language Proficiency:</strong> High English fluency.<br>
            • <strong>Background:</strong> Clean Certificate of Good Conduct (Police Clearance).<br><br>
            <em>Advantageous credentials:</em> IB (PYP/MYP/DP), Cambridge IGCSE, or American curriculum experience, and TEFL/TESOL certifications.
          </p>
        </div>
      </div>

      <div class="faq-item" data-category="eligibility">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Eligibility</span>
          <span class="faq-question">4. Which countries and regions offer opportunities for Kenyan educators?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Opportunities exist across the Middle East (UAE, Qatar, Oman, Saudi Arabia), East & West Africa, the United Kingdom, Western Europe, Southeast Asia (China, Vietnam, Thailand), South America, and remote international online academies.</p>
        </div>
      </div>

      <div class="faq-item" data-category="eligibility">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-teacher">Eligibility</span>
          <span class="faq-question">5. What types of teaching positions are in highest demand globally?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Classroom Teachers, STEM Specialists (Mathematics, Physics, Chemistry, Biology), ICT & Computer Science Educators, English/Literature Instructors, Special Needs & Inclusive Education Specialists, and Curriculum / Department Heads.</p>
        </div>
      </div>

      <!-- Visas & Relocation -->
      <div class="faq-item" data-category="visa">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Visas & Relocation</span>
          <span class="faq-question">6. How does the visa and work permit process work for teaching abroad?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>In almost all established international schools, the hiring institution fully sponsors and processes your residency and work visa after formal contract signing. Standard requirements include certified degree transcripts, attested police clearance, medical fitness check, and valid passport.</p>
        </div>
      </div>

      <div class="faq-item" data-category="visa">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Visas & Relocation</span>
          <span class="faq-question">7. Are there support services to assist with relocation and interview prep?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. MwalimuLink collaborates with verified relocation consultants and agency partners who provide mock panel interviews, document attestation guidance, and pre-departure cultural orientation.</p>
        </div>
      </div>

      <div class="faq-item" data-category="visa">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-school">Visas & Relocation</span>
          <span class="faq-question">8. What cultural and environmental challenges should I be prepared for?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Relocating involves adapting to different cultural norms, diverse student body demographics, international curricula (such as IB or Cambridge), and weather adjustments. We recommend thorough research on your destination country's expatriate laws before contract signing.</p>
        </div>
      </div>

      <!-- Compensation & Family -->
      <div class="faq-item" data-category="compensation">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Salaries & Benefits</span>
          <span class="faq-question">9. What are typical salaries and compensation packages for international teachers?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>
            International packages typically range from <strong>$1,200 to $4,500 USD per month</strong> (approx. KES 155,000 – KES 580,000) depending on experience, subject specialty, and country tier.<br>
            Standard international packages also include:<br>
            • Free furnished accommodation or generous monthly housing allowance<br>
            • Annual return flight tickets to Kenya<br>
            • Comprehensive international medical insurance<br>
            • End-of-service gratuity bonus.
          </p>
        </div>
      </div>

      <div class="faq-item" data-category="compensation">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Salaries & Benefits</span>
          <span class="faq-question">10. Can I relocate with my spouse and children?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. Tier-1 and Tier-2 international schools routinely sponsor spousal residency visas and provide free or heavily subsidized school tuition for up to 2 dependent children. Always disclose your family status early in the interview stage.</p>
        </div>
      </div>

      <div class="faq-item" data-category="compensation">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-pay">Salaries & Benefits</span>
          <span class="faq-question">11. Do teachers have to pay upfront placement fees to get a job?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p><strong>CAUTION:</strong> Legitimate international schools never charge teachers for interviews or job offers. On MwalimuLink, browsing and applying is free. Beware of external fraudulent agencies that demand large sums for "guaranteed visas".</p>
        </div>
      </div>

      <!-- Application & Timing -->
      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Application & Timing</span>
          <span class="faq-question">12. When is the peak recruitment season for international teaching contracts?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>
            • <strong>Primary Cycle (August/September Starts):</strong> Peak hiring is between <strong>January and May</strong>.<br>
            • <strong>Secondary Cycle (January Starts):</strong> Hiring takes place between <strong>September and November</strong>.<br>
            Urgent replacement roles and mid-term maternity covers are posted continuously year-round.
          </p>
        </div>
      </div>

      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Application & Timing</span>
          <span class="faq-question">13. What documents should I prepare for international teaching applications?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>A comprehensive international teaching portfolio should include: a 2-page concise CV, tailored cover letter, certified university degree transcripts, TSC certificate, 2 written supervisor reference letters, passport bio-data copy, and a 2-3 minute recorded video lesson introduction.</p>
        </div>
      </div>

      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Application & Timing</span>
          <span class="faq-question">14. Can I choose the specific country or city I want to teach in?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Yes. You can filter our International Schools directory by country and apply directly to schools in your target destination. Flexibility across 2 or 3 countries significantly increases your placement speed.</p>
        </div>
      </div>

      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-jobs">Application & Timing</span>
          <span class="faq-question">15. Is MwalimuLink a recruitment agency or a platform?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>MwalimuLink is an educational career platform and directory network. We connect teachers directly with school administrators and vetted recruitment partners, providing directory access and profile verification.</p>
        </div>
      </div>

      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-sec">Security</span>
          <span class="faq-question">16. How does MwalimuLink safeguard educator data in international transmissions?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Your resume and contact information are only made available to vetted, verified institutional representatives. You maintain complete control over your profile visibility from your privacy settings.</p>
        </div>
      </div>

      <div class="faq-item" data-category="application">
        <button class="faq-trigger" onclick="toggleFaq(this)">
          <span class="faq-badge badge-sec">Support</span>
          <span class="faq-question">17. Where can I get direct advice or report a suspicious listing?</span>
          <i class="fa-solid fa-chevron-down faq-chevron"></i>
        </button>
        <div class="faq-answer">
          <p>Reach out directly to our international liaison desk at <a href="mailto:infomwalimulink@gmail.com" style="color: #0f766e; font-weight: 700;">infomwalimulink@gmail.com</a> or submit an inquiry via our <a href="/contacts" style="color: #0f766e; font-weight: 700;">Contact Support Desk</a>.</p>
        </div>
      </div>

    </div>

    <!-- Bottom Action Banner -->
    <div class="faq-support-banner">
      <div class="banner-left">
        <div class="banner-icon"><i class="fa-solid fa-earth-americas"></i></div>
        <div>
          <h3>Explore 6,000+ Global International Schools</h3>
          <p>Browse our curated directory of accredited international schools across Africa, the Middle East, Asia, and Europe.</p>
        </div>
      </div>
      <div class="banner-actions">
        <a href="/schools/international" class="btn-ask-support"><i class="fa-solid fa-search"></i> Browse International Schools</a>
        <a href="/faqs" class="btn-overseas-faq"><i class="fa-solid fa-question-circle"></i> General Educator FAQs</a>
      </div>
    </div>
  </div>
</div>

<style>
.overseas-faq-wrapper {
  background: #f8fafc;
  color: #1e293b;
  font-family: inherit;
  padding-bottom: 4rem;
}

.overseas-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero */
.overseas-hero {
  background: linear-gradient(135deg, #0f172a 0%, #0369a1 100%);
  color: #ffffff !important;
  padding: 4.5rem 0 3.5rem;
  text-align: center;
}

.overseas-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(56, 189, 248, 0.15);
  border: 1px solid rgba(56, 189, 248, 0.35);
  color: #38bdf8 !important;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 1.25rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.overseas-hero-title,
.overseas-hero h1 {
  font-size: clamp(2rem, 4vw, 3rem) !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
  max-width: 850px;
  margin: 0 auto 1rem !important;
  color: #ffffff !important;
}

.overseas-hero-subtitle,
.overseas-hero p {
  font-size: 1.05rem !important;
  line-height: 1.6 !important;
  max-width: 720px;
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
  border-color: #38bdf8;
  box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.25);
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

/* Destination Highlights */
.destination-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2.5rem;
}

.dest-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1.75rem 1.25rem;
  box-shadow: 0 4px 15px rgba(0,0,0,0.03);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dest-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.dest-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  margin-bottom: 1rem;
}

.dest-card h4 {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px 0;
}

.dest-card p {
  color: #64748b;
  font-size: 0.85rem;
  line-height: 1.5;
  margin: 0;
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
  color: #0284c7;
  border-color: #94a3b8;
}

.cat-pill.active {
  background: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
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
  color: #0284c7;
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
  border-color: #0284c7;
  box-shadow: 0 6px 20px rgba(2, 132, 199, 0.08);
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
  color: #0284c7;
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
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8 !important;
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
  background: #38bdf8;
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

.btn-overseas-faq {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #ffffff !important;
  font-weight: 700;
  font-size: 0.92rem;
  padding: 11px 20px;
  border-radius: 10px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

@media (max-width: 768px) {
  .overseas-hero {
    padding: 3rem 1rem 2.5rem;
  }
  .destination-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
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
  .overseas-container {
    padding: 0 1rem;
  }
  .overseas-hero-title,
  .overseas-hero h1 {
    font-size: 1.75rem !important;
  }
  .overseas-hero-subtitle,
  .overseas-hero p {
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
    font-size: 16px !important;
    padding: 12px 40px 12px 42px;
  }
}
</style>

<script>
let currentOverseasCategory = 'all';

function toggleFaq(triggerBtn) {
  const item = triggerBtn.closest('.faq-item');
  item.classList.toggle('open');
}

function toggleAllOverseas(expand) {
  document.querySelectorAll('#overseasAccordionList .faq-item').forEach(item => {
    if (item.style.display !== 'none') {
      if (expand) item.classList.add('open');
      else item.classList.remove('open');
    }
  });
}

function setOverseasCategory(cat, btn) {
  currentOverseasCategory = cat;
  document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  filterOverseasFaqs();
}

function filterOverseasFaqs() {
  const search = document.getElementById('overseasSearchInput').value.toLowerCase().trim();
  const clearBtn = document.getElementById('overseasClearBtn');
  if (clearBtn) clearBtn.style.display = search ? 'flex' : 'none';

  const items = document.querySelectorAll('#overseasAccordionList .faq-item');
  let matchCount = 0;

  items.forEach(item => {
    const cat = item.getAttribute('data-category');
    const qText = item.querySelector('.faq-question').textContent.toLowerCase();
    const aText = item.querySelector('.faq-answer').textContent.toLowerCase();

    const matchesCategory = (currentOverseasCategory === 'all' || cat === currentOverseasCategory);
    const matchesSearch = !search || qText.includes(search) || aText.includes(search);

    if (matchesCategory && matchesSearch) {
      item.style.display = 'block';
      matchCount++;
      if (search) item.classList.add('open');
    } else {
      item.style.display = 'none';
    }
  });

  const noRes = document.getElementById('overseasNoResults');
  if (noRes) noRes.style.display = matchCount === 0 ? 'block' : 'none';
}

function clearOverseasSearch() {
  document.getElementById('overseasSearchInput').value = '';
  document.getElementById('overseasClearBtn').style.display = 'none';
  currentOverseasCategory = 'all';
  document.querySelectorAll('.cat-pill').forEach((p, idx) => {
    if (idx === 0) p.classList.add('active');
    else p.classList.remove('active');
  });
  filterOverseasFaqs();
}
</script>