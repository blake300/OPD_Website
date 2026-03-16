<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

require_once __DIR__ . '/../src/seo.php';

$featuredProducts = site_get_featured_products(12);
$user = site_current_user();
$isSignedIn = $user !== null;
$csrf = site_csrf_token();
$_seoTitle = opd_site_name() . ' | Oilfield Equipment, Tools & Supplies';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($_seoTitle, ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <?php opd_seo_meta([
    'title' => $_seoTitle,
    'description' => 'Oilfield equipment, tools, parts, and supplies at low prices. Nationwide shipping with Oklahoma same-day delivery. AutoBailer systems, downhole pumps, fittings, valves, and more.',
    'canonical' => '/',
    'jsonLd' => opd_organization_jsonld()
  ]); ?>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>
  <div class="notice" id="favorite-message" style="display:none;"></div>

  <main class="page home-page">
    <section class="home-hero">
      <div class="home-hero-band">
        <div class="home-hero-inner">
          <h1>Oilfield Quality - Low Prices</h1>
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
          <span class="category-label">Parts</span>
        </a>
        <a class="category-button" href="/category.php?category=Tools">
          <span class="category-circle">
            <span class="category-icon category-icon--tools" aria-hidden="true"></span>
          </span>
          <span class="category-label">Tools</span>
        </a>
        <a class="category-button" href="/category.php?category=Services">
          <span class="category-circle">
            <span class="category-icon category-icon--services" aria-hidden="true"></span>
          </span>
          <span class="category-label">Services</span>
        </a>
        <a class="category-button" href="/category.php?category=Supplies">
          <span class="category-circle">
            <span class="category-icon category-icon--supplies" aria-hidden="true"></span>
          </span>
          <span class="category-label">Supplies</span>
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
        <h2>How it Works</h2>
      </div>
      <div style="text-align: center;">
        <img src="/assets/How it works_Graphic.jpg" alt="How it Works" style="max-width: 100%; height: auto; border-radius: 12px;" />
      </div>
    </section>

    <?php if (count($featuredProducts) > 0): ?>
    <section class="panel">
      <div class="section-title">
        <h2>Featured Products</h2>
        <p class="meta">Hand-picked products for your operation.</p>
      </div>
      <div class="carousel-container">
        <button class="carousel-nav carousel-nav-prev" aria-label="Previous products">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </button>
        <div class="carousel-track-wrapper">
          <div class="carousel-track">
            <?php foreach ($featuredProducts as $product): ?>
              <?php $productId = $product['id'] ?? ''; ?>
              <div class="carousel-slide">
                <div class="card">
                  <div class="tag"><?php echo htmlspecialchars($product['status'] ?? 'available', ENT_QUOTES); ?></div>
                  <?php if (!empty($product['imageUrl'])): ?>
                    <img class="product-thumb" src="<?php echo htmlspecialchars($product['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
                  <?php else: ?>
                    <div class="image-placeholder">No image</div>
                  <?php endif; ?>
                  <h3><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h3>
                  <div class="meta"><?php echo htmlspecialchars($product['sku'] ?? '', ENT_QUOTES); ?></div>
                  <div class="price">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></div>
                  <div class="meta"><?php echo htmlspecialchars($product['category'] ?? 'General', ENT_QUOTES); ?></div>
                  <div class="product-card-actions">
                    <div class="favorite-wrap">
                      <div class="favorite-message-inline" data-favorite-message hidden>
                        Please Sign-In to Select Favorites.
                        <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>
                      </div>
                      <button
                        type="button"
                        class="favorite-btn"
                        data-favorite
                        data-product-id="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>"
                        aria-label="Add to favorites"
                      >
                        <svg class="favorite-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </button>
                      <div class="favorite-dropdown" data-favorite-menu hidden></div>
                    </div>
                    <a class="btn" href="/product.php?id=<?php echo urlencode($productId); ?>">View details</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <button class="carousel-nav carousel-nav-next" aria-label="Next products">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
          </svg>
        </button>
      </div>
      <div class="carousel-dots"></div>
    </section>
    <?php endif; ?>

    <section class="panel">
      <h2>Why <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></h2>
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
  <script>
    (function () {
      // Carousel functionality
      var carouselContainer = document.querySelector('.carousel-container')
      if (carouselContainer) {
        var track = document.querySelector('.carousel-track')
        var slides = document.querySelectorAll('.carousel-slide')
        var prevBtn = document.querySelector('.carousel-nav-prev')
        var nextBtn = document.querySelector('.carousel-nav-next')
        var dotsContainer = document.querySelector('.carousel-dots')

        var currentIndex = 0
        var slidesPerView = 3
        var autoScrollInterval = null
        var autoScrollDelay = 5000

        function updateSlidesPerView() {
          if (window.innerWidth <= 900) {
            slidesPerView = 3
          } else {
            slidesPerView = 6
          }
        }

        function getTotalPages() {
          return Math.max(1, Math.ceil(slides.length - slidesPerView + 1))
        }

        function updateCarousel() {
          var slideWidth = slides[0].offsetWidth
          var gap = window.innerWidth <= 900 ? 12 : 20
          if (slidesPerView === 1) {
            gap = 0
          }
          var offset = currentIndex * (slideWidth + gap)
          track.style.transform = 'translateX(-' + offset + 'px)'

          updateButtons()
          updateDots()
        }

        function updateButtons() {
          var totalPages = getTotalPages()
          prevBtn.disabled = currentIndex === 0
          nextBtn.disabled = currentIndex >= totalPages - 1
        }

        function updateDots() {
          var totalPages = getTotalPages()
          dotsContainer.innerHTML = ''
          for (var i = 0; i < totalPages; i++) {
            var dot = document.createElement('button')
            dot.className = 'carousel-dot' + (i === currentIndex ? ' active' : '')
            dot.setAttribute('aria-label', 'Go to slide ' + (i + 1))
            dot.addEventListener('click', (function (index) {
              return function () {
                currentIndex = index
                updateCarousel()
                resetAutoScroll()
              }
            })(i))
            dotsContainer.appendChild(dot)
          }
        }

        function nextSlide() {
          var totalPages = getTotalPages()
          if (currentIndex < totalPages - 1) {
            currentIndex++
          } else {
            currentIndex = 0
          }
          updateCarousel()
        }

        function prevSlide() {
          if (currentIndex > 0) {
            currentIndex--
            updateCarousel()
          }
        }

        function startAutoScroll() {
          autoScrollInterval = setInterval(nextSlide, autoScrollDelay)
        }

        function stopAutoScroll() {
          if (autoScrollInterval) {
            clearInterval(autoScrollInterval)
            autoScrollInterval = null
          }
        }

        function resetAutoScroll() {
          stopAutoScroll()
          startAutoScroll()
        }

        prevBtn.addEventListener('click', function () {
          prevSlide()
          resetAutoScroll()
        })

        nextBtn.addEventListener('click', function () {
          nextSlide()
          resetAutoScroll()
        })

        carouselContainer.addEventListener('mouseenter', stopAutoScroll)
        carouselContainer.addEventListener('mouseleave', startAutoScroll)

        window.addEventListener('resize', function () {
          updateSlidesPerView()
          currentIndex = Math.min(currentIndex, getTotalPages() - 1)
          updateCarousel()
        })

        updateSlidesPerView()
        updateCarousel()
        startAutoScroll()
      }

      // Initialize centralized favorites module
      if (window.Favorites && typeof Favorites.init === 'function') {
        Favorites.init({ csrfToken: <?php echo json_encode($csrf); ?>, isSignedIn: <?php echo $isSignedIn ? 'true' : 'false'; ?> })
      }
    })()
  </script>
</body>
</html>
