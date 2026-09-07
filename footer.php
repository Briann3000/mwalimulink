</main>

<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-col" style="grid-column: span 1;">
      <img src="/mwalimu-link-logo-clean.png" alt="MwalimuLink" style="max-height: 38px; filter: brightness(1.2); margin-bottom: 0.8rem;">
      <p style="color: #94a3b8; font-size: 0.82rem; line-height: 1.6;">
        Connecting qualified Kenyan educators with top public, private, and international schools nationwide.
      </p>
    </div>

    <div class="footer-col">
      <h4>Educator Hub</h4>
      <ul>
        <li><a href="/teacher/jobs"><i class="fa fa-briefcase"></i> Teaching Vacancies</a></li>
        <li><a href="/tp-hub"><i class="fa fa-graduation-cap"></i> TP & Internship Hub</a></li>
        <li><a href="/faqs"><i class="fa fa-question-circle"></i> Teacher FAQs</a></li>
        <li><a href="/faqs-overseas"><i class="fa fa-plane"></i> Teach Overseas</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>School Recruitment</h4>
      <ul>
        <li><a href="/schools/public"><i class="fa fa-landmark"></i> Public School Directory</a></li>
        <li><a href="/schools/private"><i class="fa fa-building"></i> Private Academies</a></li>
        <li><a href="/schools/international"><i class="fa fa-globe"></i> International Schools</a></li>
        <li><a href="/school/dashboard"><i class="fa fa-tachometer-alt"></i> Recruitment Portal</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Support & Contact</h4>
      <ul>
        <li><a href="/about"><i class="fa fa-info-circle"></i> About MwalimuLink</a></li>
        <li><a href="/contacts"><i class="fa fa-envelope"></i> Get in Touch</a></li>
        <li><a href="/privacy"><i class="fa fa-shield-alt"></i> Privacy Policy</a></li>
        <li><a href="/terms"><i class="fa fa-gavel"></i> Terms of Service</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    <div>
      &copy; <?= date('Y') ?> MwalimuLink&trade; &bull; A product of <a href="https://schoolsnetkenya.com" target="_blank" style="color: #2dd4bf;">Schools Net Kenya</a>.
    </div>
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
      <a href="/login/admin" style="color: #64748b;"><i class="fa fa-lock"></i> Staff Admin</a>
    </div>
  </div>
</footer>

<!-- Full-Color Affiliate & Partner Marquee at Very Bottom -->
<style>
.affiliates-bottom-bar {
  background: #ffffff;
  border-top: 2px solid #0f766e;
  padding: 10px 0;
  overflow: hidden;
  position: relative;
  box-shadow: 0 -2px 10px rgba(0,0,0,0.04);
}

.affiliate-logos-scroll {
  display: flex;
  align-items: center;
  gap: 4rem;
  width: max-content;
  animation: scroll-logos 35s linear infinite;
}

.affiliate-logos-scroll:hover {
  animation-play-state: paused;
}

.affiliate-logo {
  height: 38px;
  filter: none !important;
  opacity: 1 !important;
  transition: transform 0.25s ease;
}

.affiliate-logo:hover {
  transform: scale(1.1);
}

@keyframes scroll-logos {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.site-footer {
  background: #0f172a;
  color: #94a3b8;
  padding: 3.5rem 1.5rem 1.5rem;
  font-size: 0.85rem;
  border-top: 2px solid #0f766e;
}

.footer-container {
  max-width: 1240px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2.25rem;
  margin-bottom: 2.5rem;
}

.footer-col h4 {
  color: #f8fafc !important;
  font-size: 0.95rem !important;
  font-weight: 700;
  margin-bottom: 1rem;
  letter-spacing: 0.02em;
}

.footer-col ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.footer-col a {
  color: #94a3b8 !important;
  transition: color 0.15s ease;
}

.footer-col a:hover {
  color: #2dd4bf !important;
}

.footer-bottom {
  border-top: 1px solid #1e293b;
  padding-top: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  color: #64748b;
  font-size: 0.8rem;
  max-width: 1240px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .site-footer {
    padding: 2.5rem 1.25rem 1.25rem;
  }
  .footer-container {
    grid-template-columns: 1fr 1fr;
    gap: 2rem 1.25rem;
    margin-bottom: 2rem;
  }
  .footer-col:first-child {
    grid-column: span 2;
  }
}

@media (max-width: 480px) {
  .footer-container {
    grid-template-columns: 1fr;
    gap: 1.75rem;
  }
  .footer-col:first-child {
    grid-column: span 1;
  }
  .footer-bottom {
    flex-direction: column;
    text-align: center;
    gap: 0.75rem;
  }
}
</style>

<div class="affiliates-bottom-bar">
  <div class="affiliate-logos-scroll">
    <a href="https://schoolsnetkenya.com" target="_blank" rel="noopener"><img src="/snk.png" alt="SchoolsNetKenya" class="affiliate-logo"></a>
    <a href="https://esoma.info" target="_blank" rel="noopener"><img src="/esoma-logo.png" alt="Esoma.info" class="affiliate-logo"></a>
    <a href="https://arjess.org" target="_blank" rel="noopener"><img src="/arjess-logo.png" alt="Arjess" class="affiliate-logo"></a>
    <a href="https://kenpro.org" target="_blank" rel="noopener"><img src="/kenpro-logo.png" alt="Kenpro" class="affiliate-logo"></a>
    <!-- Seamless Loop Duplicate -->
    <a href="https://schoolsnetkenya.com" target="_blank" rel="noopener"><img src="/snk.png" alt="SchoolsNetKenya" class="affiliate-logo"></a>
    <a href="https://esoma.info" target="_blank" rel="noopener"><img src="/esoma-logo.png" alt="Esoma.info" class="affiliate-logo"></a>
    <a href="https://arjess.org" target="_blank" rel="noopener"><img src="/arjess-logo.png" alt="Arjess" class="affiliate-logo"></a>
    <a href="https://kenpro.org" target="_blank" rel="noopener"><img src="/kenpro-logo.png" alt="Kenpro" class="affiliate-logo"></a>
  </div>
</div>

</body>
</html>