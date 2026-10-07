# MwalimuLink

MwalimuLink is a recruitment, networking, and institutional directory platform built for Kenyan educators, private/international school recruiters, and educational stakeholders nationwide.

---

## Key Features

### 1. Teacher Portal & Professional Tools
- **Profile & Application Management**: Manage teaching subjects, curriculum competencies (CBC, 8-4-4, Cambridge/IGCSE, IB), TSC verification status, and direct job applications.
- **Dynamic CV Builder**: Interactive resume editor with live formatting, PDF export, and qualification highlights.
- **Vacancy Discovery**: Filter active Kenyan teaching vacancies by county, subject combination, education level, and compensation.

### 2. School Recruitment Dashboard
- **Candidate Search**: Multi-parameter search with synonym-aware subject matching (e.g., Biology / BIO / Integrated Science).
- **Application Pipeline**: Track applicants through recruitment stages (Pending, Reviewed, Shortlisted, Interview Scheduled, Hired).
- **School Staff Collaboration**: Multi-user staff access with role-based permissions and invitation links.

### 3. Institutional Directory
- Comprehensive national directory covering **Public Schools**, **Private Schools**, and **International Schools**.
- Categorized county filtering, curriculum indicators, contact verification, and paywalled full directory access options.

### 4. Educational Publications & Research
- Peer-reviewed research papers, CBC pedagogical insights, and international teaching guide repository with PDF viewing and direct downloads.

### 5. Community & Direct Communication
- **Discussion Forums**: Categorized subject boards, CBC implementation discussion, and career advice.
- **Direct Messaging**: In-platform messaging between teachers and verified schools.

### 6. Payments & Ingestion
- **IntaSend Integration**: Seamless M-Pesa STK push and Card checkouts for school subscriptions and premium directory passes.
- **Automated Job Scrapers**: Background workers for aggregating teaching vacancies across Kenyan career portals.

---

## Tech Stack

- **Backend**: PHP 8.x (Vanilla architecture, fast and lightweight)
- **Database / ORM**: RedBeanPHP with support for both SQLite (local/fallback) and MySQL/MariaDB (production)
- **Frontend**: Alpine.js, Pico CSS, FontAwesome 6, Inter typography
- **Payments**: IntaSend PHP SDK & REST API (M-Pesa Express & Card Processing)
- **Email Delivery**: PHPMailer (SMTP) & Resend API integration

---

## Local Development Setup

### Prerequisites
- PHP 8.1 or higher (with `pdo_sqlite`, `pdo_mysql`, `curl`, and `fileinfo` extensions enabled)
- MySQL / MariaDB (optional; SQLite works out of the box for development)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/Briann3000/mwalimulink.git
   cd mwalimulink
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   ```
   Open `.env` and configure your database settings, IntaSend credentials, and mail settings.

3. **Start the development server**:
   ```bash
   php -S localhost:8000 router.php
   ```
   Visit `http://localhost:8000` in your web browser.

---

## Automated Test Suite

To run the full suite of authentication, security, and integration tests:

```bash
php tests/test_auth_security.php
```

---

## License

All rights reserved &copy; MwalimuLink.
