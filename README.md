# MwalimuLink

MwalimuLink is an all-in-one educational platform, institutional recruitment network, and Competency-Based Curriculum (CBC) teaching resources hub built for Kenyan educators, schools, and academic stakeholders nationwide.

---

## Core Features & Modules

### 1. E-Library & Teaching Materials Hub
- **Curriculum Taxonomy**: Categorized resources spanning Pre-Primary (PP1/PP2), Primary (Grade 1–6), Junior School (Grade 7–9), and Senior Secondary (Form 1–4 / KCSE).
- **Resource Categories**: Learning notes, revision past papers with complete marking schemes, rationalised schemes of work, lesson plans, set book guides, and curriculum designs.
- **Teaser Preview & Instant Access**: Secure first-page document preview window with locked scrolling for non-downloaded items.
- **Batch Cart & 1-Click Purchase**: Support for multi-resource shopping cart (`/elibrary/cart`) and instant 1-click M-Pesa direct purchase.
- **Automated Email Delivery**: Purchased and unlocked documents are automatically emailed to the buyer's specified email address in addition to in-browser downloads.
- **Creator Incentive System**: Teachers and schools receive **3 Free Download Unlocks** automatically upon admin approval of each contributed document.
- **Privacy & Contributor Protection**: Individual teacher names are protected on public cards with a verified *"Verified Educator"* badge.
- **Copyright Safe Harbor**: Built-in takedown claim submission workflow compliant with Section 35B of the Kenya Copyright Act.

### 2. Teacher Career Portal & Professional Tools
- **Profile & Credential Management**: Teaching subjects, curriculum specializations (CBC, 8-4-4, Cambridge/IGCSE, IB), TSC number verification, and application history.
- **Interactive CV Builder**: Real-time resume editor with custom styling, work experience timeline, and PDF export.
- **Job Discovery & Direct Applications**: Live vacancy search filterable by county, school type, subject combination, and compensation.
- **Teaching Practice (TP) Hub**: Dedicated placement directory connecting student teachers with schools offering attachment opportunities across all 47 counties.

### 3. School Recruitment & Institutional Management
- **Synonym-Aware Candidate Search**: Multi-parameter teacher discovery matching alternate subject keywords (e.g., Biology / BIO / Integrated Science).
- **Recruitment Pipeline**: Application tracker across stages (Pending, Reviewed, Shortlisted, Interview Scheduled, Hired).
- **Institutional Subscriptions**: Pro recruitment tier enabling unlimited job postings, direct candidate outreach, and verified directory badges.
- **Multi-User Staff Access**: Role-based access control for administrative and departmental recruitment teams.

### 4. National Institutional Directory
- **Comprehensive Coverage**: Verified directories of **Public Schools**, **Private Academies**, and **International Schools**.
- **Location & Curriculum Filters**: Search by county, constituency, curriculum offered, boarding status, and verified contact channels.
- **Directory Access Passes**: Time-limited institutional access passes via automated M-Pesa gateway.

### 5. Publications Hub & Research Repository
- Peer-reviewed research papers, CBC pedagogical insights, and education leadership publications with PDF stream viewing.

### 6. Discussion Forums & Direct Messaging
- **Educators Forum**: Subject-specific boards, curriculum transition discussions, and career guidance.
- **Direct Messaging**: Secure communication channels between verified schools and candidate teachers.

### 7. Administration & Moderation Control Panel
- **E-Library Moderation Queue**: 1-click resource approval/rejection with auto-unlock awards and PDF stream inspection.
- **Copyright Claim Management**: Compliance ticket review and instant resource suspension tools.
- **Pricing & Currency Control**: Dynamic fee management with auto-healing currency fallbacks.

---

## Technical Architecture

- **Backend**: PHP 8.1+ (Clean MVC-inspired routing, lightweight and dependency-minimal)
- **Database / ORM**: RedBeanPHP with dual support for SQLite (local testing) and MySQL / MariaDB (production)
- **Frontend**: Inter typography, Alpine.js, Pico CSS (with scoped container isolation), FontAwesome 6
- **Payment Gateway**: IntaSend REST API & SDK (Instant M-Pesa STK Push, Card processing, and Webhook verification)
- **Transactional Mail**: Resend REST API with automatic PHPMailer SMTP fallback
- **Storage Resolution**: Multi-root storage dispatcher supporting localized and clustered storage layouts

---

## Local Development Setup

### Prerequisites
- PHP 8.1 or higher (with `pdo_sqlite`, `pdo_mysql`, `curl`, `fileinfo`, and `zip` extensions enabled)
- MySQL / MariaDB (optional; SQLite runs out of the box for local development)

### Getting Started

1. **Clone the repository**:
   ```bash
   git clone https://github.com/Briann3000/mwalimulink.git
   cd mwalimulink
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   ```
   Configure your database, IntaSend credentials, and email delivery keys in `.env`.

3. **Start the local server**:
   ```bash
   php -S localhost:8000 router.php
   ```
   Open `http://localhost:8000` in your web browser.

4. **Import E-Library Starter Data (Optional)**:
   ```bash
   php scripts/import_elibrary_sql.php library_resources.sql
   ```

---

## Automated Verification & Tests

Run the security and integration test suite:

```bash
php tests/test_auth_security.php
```

---

## License

All rights reserved &copy; MwalimuLink.
