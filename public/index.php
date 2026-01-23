<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page home-page">
    <section class="home-hero">
      <div class="home-hero-band">
        <div class="home-hero-inner">
          <h1>Oil Field Parts, Tools, &amp; Equipment For Less</h1>
        </div>
      </div>
      <div class="home-hero-buttons">
        <a class="category-button" href="/category.php?category=AutoBailer%20Artifical%20Lift">
          <span class="category-circle">
            <span class="category-icon category-icon--autobailer" aria-hidden="true"></span>
          </span>
          <span class="category-label">AutoBailer Artifical Lift</span>
        </a>
        <a class="category-button" href="/category.php?category=Parts">
          <span class="category-circle">
            <span class="category-icon category-icon--parts" aria-hidden="true"></span>
          </span>
          <span class="category-label">Specialty Parts</span>
        </a>
        <a class="category-button" href="/category.php?category=Tools">
          <span class="category-circle">
            <span class="category-icon category-icon--tools" aria-hidden="true"></span>
          </span>
          <span class="category-label">Specialty Tools</span>
        </a>
        <a class="category-button" href="/category.php?category=Services">
          <span class="category-circle">
            <span class="category-icon category-icon--services" aria-hidden="true"></span>
          </span>
          <span class="category-label">Specialty Services</span>
        </a>
        <a class="category-button" href="/category.php?category=Supplies">
          <span class="category-circle">
            <span class="category-icon category-icon--supplies" aria-hidden="true"></span>
          </span>
          <span class="category-label">Discount Supplies</span>
        </a>
        <a class="category-button" href="/category.php?category=Used%20Equipment">
          <span class="category-circle">
            <span class="category-icon category-icon--used" aria-hidden="true"></span>
          </span>
          <span class="category-label">Used Equipment</span>
        </a>
      </div>
    </section>

    <section class="panel">
      <div class="section-title">
        <h2>Quick access</h2>
        <span class="meta">Jump straight to your operational tools.</span>
      </div>
      <div class="grid cols-3">
        <a class="card" href="/dashboard-account.php">
          <h3>Account</h3>
          <div class="meta">Profile, security, and preferences.</div>
        </a>
        <a class="card" href="/dashboard-orders.php">
          <h3>Orders</h3>
          <div class="meta">Track fulfillment and approvals.</div>
        </a>
        <a class="card" href="/dashboard-accounting-codes.php">
          <h3>Accounting codes</h3>
          <div class="meta">Map expenses to the right job.</div>
        </a>
        <a class="card" href="/dashboard-favorites.php">
          <h3>Favorites</h3>
          <div class="meta">Fast reorder for repeat jobs.</div>
        </a>
        <a class="card" href="/dashboard-vendors.php">
          <h3>Vendors</h3>
          <div class="meta">Manage preferred suppliers.</div>
        </a>
        <a class="card" href="/dashboard-clients.php">
          <h3>Clients</h3>
          <div class="meta">Keep customer records ready.</div>
        </a>
        <a class="card" href="/dashboard-equipment.php">
          <h3>Your equipment</h3>
          <div class="meta">Track assets and availability.</div>
        </a>
      </div>
    </section>

    <section class="panel">
      <h2>Why Oil Patch Depot</h2>
      <div class="grid cols-3">
        <div class="card">
          <h3>Field-first workflows</h3>
          <div class="meta">Built for mobile crews and fast approvals.</div>
        </div>
        <div class="card">
          <h3>Accountable spend</h3>
          <div class="meta">Tie purchases to jobs and cost centers.</div>
        </div>
        <div class="card">
          <h3>Reliable supply</h3>
          <div class="meta">Curated inventory for operational uptime.</div>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
