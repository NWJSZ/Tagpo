<?php
// footer.php — shared footer for all pages
// Usage: <?php include 'footer.php'; ?>

<footer>
  <div class="container">
    <div class="row g-5">

      <!-- Brand col -->
      <div class="col-lg-4 col-md-6">
        <div class="footer-brand">Tagpo<span>.</span></div>
        <p class="mt-2 mb-4">
          Discover and book unforgettable venues for weddings, birthdays, proms, and corporate events across the Philippines.
        </p>
      </div>

      <!-- Explore -->
      <div class="col-lg-2 col-md-3 col-6">
        <h6>Explore</h6>
        <ul class="list-unstyled">
          <li><a href="search.php">All Venues</a></li>
          <li><a href="search.php">Wedding Venues</a></li>
          <li><a href="search.php">Birthday Venues</a></li>
          <li><a href="search.php">Prom Venues</a></li>
          <li><a href="search.php">Corporate Events</a></li>
        </ul>
      </div>

      <!-- Company -->
      <div class="col-lg-2 col-md-3 col-6">
        <h6>Company</h6>
        <ul class="list-unstyled">
          <li><a href="#">About Us</a></li>
          <li><a href="#">List Your Venue</a></li>
          <li><a href="#">Contact Us</a></li>
        </ul>
      </div>

      <!-- Contact -->
      <div class="col-lg-3 col-md-6">
        <h6>Get In Touch</h6>
        <ul class="list-unstyled">
          <li class="mb-2">
            <i class="bi bi-envelope me-2" style="color:var(--gold-light)"></i>
            <a href="mailto:hello@tagpo.ph">hello@tagpo.ph</a>
          </li>
          <li class="mb-2">
            <i class="bi bi-telephone me-2" style="color:var(--gold-light)"></i>
            <a href="tel:+63281234567">+63 (2) 8123-4567</a>
          </li>
          <li>
            <i class="bi bi-geo-alt me-2" style="color:var(--gold-light)"></i>
            <span style="color:#94a3b8">Metro Manila, Philippines</span>
          </li>
        </ul>
      </div>

    </div>

    <hr/>

    <div class="row footer-bottom align-items-center">
      <div class="col-md-6 mb-2 mb-md-0">
        &copy; <?php echo date('Y'); ?> Tagpo. All rights reserved.
      </div>
      <div class="col-md-6 text-md-end">
        <a href="#" class="me-3">Privacy Policy</a>
        <a href="#" class="me-3">Terms of Service</a>
        <a href="#">Cookie Policy</a>
      </div>
    </div>

  </div>
</footer>
