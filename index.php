<?php
$page_title = "POP!Work Sabah - Gold Edition";
include 'includes/header.php';
?>
<style>
/* ================================================
   POP!WORK LANDING PAGE - MOBILE OPTIMIZED
   Gold Edition with Full Responsive Design
   ================================================ */

:root {
  --gold-accent: #d4af37; 
  --gold-gradient: linear-gradient(to right, #bf953f, #fcf6ba, #b38728, #fbf5b7);
  --dark-bg: #8a1538;
  --card-bg: rgba(104, 11, 47, 0.95);
  --text-gray: #a0a0a0;
}

/* ===== BASE STYLES ===== */
body {
  font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background-color: #f1ecedff;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  padding: 0;
  margin: 0;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Main content wrapper */
.main-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  position: relative;
  min-height: 100vh;
}

/* ===== BACKGROUND ===== */
.bg-fixed {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: url('uploads/photos/Mount Kinabalu.jpg');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  z-index: -2;
  filter: brightness(0.6) contrast(1.1);
}

/* Performance: Disable fixed attachment on mobile */
@media (max-width: 768px) {
  .bg-fixed {
    background-attachment: scroll;
  }
}

/* ===== THE GOLD CARD ===== */
.gold-card {
  width: 100%;
  max-width: 1100px;
  min-height: 600px; /* Changed from fixed height */
  background: var(--card-bg);
  border-radius: 8px;
  display: flex;
  position: relative;
  box-shadow: 0 0 60px rgba(212, 175, 55, 0.15), 
              0 20px 40px rgba(0,0,0,0.8);
  border: 1px solid rgba(255, 215, 0, 0.15);
  overflow: hidden;
  animation: floatUp 1s cubic-bezier(0.2, 0.8, 0.2, 1);
  backdrop-filter: blur(10px);
}

@keyframes floatUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ===== LEFT SIDE (Content) ===== */
.card-left {
  flex: 1.3;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  z-index: 2;
  min-height: 600px;
}

/* ===== RIGHT SIDE (Image) ===== */
.card-right {
  flex: 0.7;
  background: url('uploads/photos/Mount Kinabalu.jpg');
  background-size: cover;
  background-position: center;
  position: relative;
  clip-path: polygon(15% 0, 100% 0, 100% 100%, 0% 100%);
  min-height: 600px;
}

.card-right::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(45deg, rgba(0,0,0,0.5), rgba(212, 175, 55, 0.15));
}

/* ===== HEADER ===== */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 15px;
  text-decoration: none;
}

.brand img {
  width: 110px;
  height: auto;
  max-height: 80px;
  border: none;
  border-radius: 0;
  object-fit: contain;
  background: transparent;
}

.brand span {
  font-weight: 700;
  color: white;
  font-size: 18px;
  letter-spacing: 1px;
}

.nav-btn {
  color: var(--gold-accent);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1px;
  text-decoration: none;
  border-bottom: 1px solid transparent;
  transition: 0.3s;
}

.nav-btn:hover {
  border-bottom-color: var(--gold-accent);
}

/* ===== HERO TEXT ===== */
.hero-text {
  margin-top: 10px;
  margin-bottom: 30px;
}

.tagline {
  font-size: 12px;
  font-weight: 700;
  color: var(--gold-accent);
  text-transform: uppercase;
  letter-spacing: 3px;
  margin-bottom: 15px;
  display: block;
}

h1 {
  font-size: 52px;
  line-height: 1;
  font-weight: 900;
  color: white;
  margin-bottom: 25px;
  letter-spacing: -1px;
}

.gold-gradient-text {
  background: var(--gold-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  display: block;
}

p.subtitle {
  font-size: 15px;
  color: var(--text-gray);
  line-height: 1.6;
  max-width: 90%;
  font-weight: 300;
}

/* ===== LUXURY TILES ===== */
.grid-actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
  margin-bottom: 30px;
}

.luxury-tile {
  text-decoration: none;
  background: linear-gradient(180deg, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0.2) 100%);
  border: 1px solid rgba(255,255,255,0.08);
  padding: 25px 20px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  position: relative;
  transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
  overflow: hidden;
  border-bottom: 2px solid transparent;
  border-radius: 4px;
}

.luxury-tile::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0%;
  height: 3px;
  background: var(--gold-gradient);
  transition: 0.4s ease;
}

.luxury-tile:hover {
  background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0.4) 100%);
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  border-color: rgba(212, 175, 55, 0.3);
}

.luxury-tile:hover::after {
  width: 100%;
}

/* Disable transform on touch devices to prevent issues */
@media (hover: none) {
  .luxury-tile:active {
    transform: scale(0.98);
  }
}

.icon-circle {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 15px;
  transition: 0.4s;
  font-size: 20px;
  color: #888;
}

.luxury-tile:hover .icon-circle {
  background: var(--gold-accent);
  border-color: var(--gold-accent);
  color: #000;
  transform: scale(1.1);
  box-shadow: 0 0 15px rgba(212, 175, 55, 0.4);
}

.tile-info {
  flex-grow: 1;
}

.btn-title {
  color: white;
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 4px;
  display: block;
  transition: 0.3s;
}

.luxury-tile:hover .btn-title {
  color: var(--gold-accent);
}

.btn-sub {
  color: #666;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: block;
}

.slide-arrow {
  position: absolute;
  bottom: 20px;
  right: 20px;
  opacity: 0;
  transform: translateX(-10px);
  color: var(--gold-accent);
  font-size: 16px;
  transition: 0.4s;
}

.luxury-tile:hover .slide-arrow {
  opacity: 1;
  transform: translateX(0);
}

/* ===== FOOTER ===== */
.footer-bar {
  font-size: 11px;
  color: #666;
  border-top: 1px solid rgba(255,255,255,0.1);
  padding-top: 20px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.footer-bar span {
  cursor: default;
  white-space: nowrap;
}

/* ================================================
   RESPONSIVE DESIGN - MOBILE OPTIMIZED
   ================================================ */

/* Large Tablet (1024px and below) */
@media (max-width: 1024px) {
  .gold-card {
    min-height: auto;
    flex-direction: column;
  }

  .card-left {
    padding: 40px;
    min-height: auto;
  }

  .card-right {
    height: 250px;
    min-height: auto;
    flex: none;
    clip-path: none;
  }

  h1 {
    font-size: 44px;
  }

  .grid-actions {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Tablet (768px and below) */
@media (max-width: 768px) {
  .main-wrapper {
    padding: 16px;
    min-height: auto;
    align-items: flex-start;
    padding-top: 80px; /* Space for header */
  }

  .gold-card {
    border-radius: 12px;
  }

  .card-left {
    padding: 32px 24px;
  }

  .card-right {
    height: 200px;
  }

  /* Header */
  .brand img {
    width: 80px;
    max-height: 60px;
  }

  .brand span {
    font-size: 16px;
  }

  /* Hero Text */
  .tagline {
    font-size: 11px;
    letter-spacing: 2px;
  }

  h1 {
    font-size: 36px;
    margin-bottom: 20px;
  }

  p.subtitle {
    font-size: 14px;
    max-width: 100%;
  }

  /* Grid Actions - Single Column on Mobile */
  .grid-actions {
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 24px;
  }

  /* Luxury Tiles - Horizontal Layout */
  .luxury-tile {
    flex-direction: row;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
  }

  .icon-circle {
    margin-bottom: 0;
    flex-shrink: 0;
  }

  .luxury-tile::after {
    height: 0;
    width: 3px;
    top: 0;
    left: 0;
    bottom: auto;
  }

  .luxury-tile:hover::after,
  .luxury-tile:active::after {
    height: 100%;
    width: 3px;
  }

  .slide-arrow {
    position: relative;
    bottom: auto;
    right: auto;
    opacity: 1;
    transform: none;
    color: #555;
    margin-left: auto;
  }

  .luxury-tile:hover .slide-arrow,
  .luxury-tile:active .slide-arrow {
    color: var(--gold-accent);
  }

  /* Footer */
  .footer-bar {
    font-size: 10px;
    gap: 12px;
    padding-top: 16px;
  }
}

/* Mobile (576px and below) */
@media (max-width: 576px) {
  .main-wrapper {
    padding: 12px;
    padding-top: 70px;
  }

  .gold-card {
    border-radius: 8px;
  }

  .card-left {
    padding: 24px 20px;
  }

  .card-right {
    height: 160px;
  }

  /* Header */
  .brand {
    gap: 10px;
  }

  .brand img {
    width: 70px;
    max-height: 50px;
  }

  .brand span {
    font-size: 14px;
  }

  /* Hero */
  .tagline {
    font-size: 10px;
    letter-spacing: 1.5px;
    margin-bottom: 12px;
  }

  h1 {
    font-size: 30px;
    line-height: 1.1;
    margin-bottom: 16px;
  }

  p.subtitle {
    font-size: 13px;
    line-height: 1.5;
  }

  /* Tiles */
  .luxury-tile {
    padding: 16px 18px;
    gap: 12px;
  }

  .icon-circle {
    width: 40px;
    height: 40px;
    font-size: 18px;
  }

  .btn-title {
    font-size: 13px;
  }

  .btn-sub {
    font-size: 9px;
  }

  .slide-arrow {
    font-size: 14px;
  }

  /* Footer */
  .footer-bar {
    flex-direction: column;
    gap: 8px;
    font-size: 9px;
  }
}

/* Extra Small Mobile (480px and below) */
@media (max-width: 480px) {
  .main-wrapper {
    padding: 10px;
    padding-top: 60px;
  }

  .card-left {
    padding: 20px 16px;
  }

  .card-right {
    height: 140px;
  }

  h1 {
    font-size: 26px;
  }

  .luxury-tile {
    padding: 14px 16px;
  }

  .icon-circle {
    width: 36px;
    height: 36px;
    font-size: 16px;
  }
}

/* Landscape Phone */
@media (max-height: 500px) and (orientation: landscape) {
  .main-wrapper {
    min-height: auto;
    padding: 10px;
  }

  .gold-card {
    flex-direction: row;
  }

  .card-right {
    display: none; /* Hide image on landscape to save space */
  }

  .card-left {
    padding: 20px;
    min-height: auto;
  }

  h1 {
    font-size: 28px;
    margin-bottom: 12px;
  }

  .hero-text {
    margin-top: 5px;
    margin-bottom: 15px;
  }

  .grid-actions {
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 15px;
  }

  .luxury-tile {
    flex-direction: column;
    padding: 12px;
  }

  .icon-circle {
    margin-bottom: 8px;
  }
}
</style>

  <div class="bg-fixed"></div>

  <div class="main-wrapper">
    <div class="gold-card">
      
      <div class="card-left">
        
        <div class="card-header">
          <a href="#" class="brand">
            <img src="uploads/photos/logo popwork.png" alt="Logo">
            <span>POP!WORK</span>
          </a>
        </div>

        <div class="hero-text">
          <span class="tagline">The Premium Standard</span>
          <h1>
            Sabah's Gig
            <span class="gold-gradient-text">MARKETPLACE.</span>
          </h1>
          <p class="subtitle">
            Secure. Fast. Local. Connecting professionals and businesses from Kota Kinabalu to Tawau with elite efficiency.
          </p>
        </div>

        <div class="grid-actions">
          
          <a href="register.html" class="luxury-tile">
            <div class="icon-circle">⚡</div>
            <div class="tile-info">
              <span class="btn-title">Start Earning</span>
              <span class="btn-sub">Sign Up Now</span>
            </div>
            <div class="slide-arrow">→</div>
          </a>

          <a href="login.html" class="luxury-tile">
            <div class="icon-circle">🔑</div>
            <div class="tile-info">
              <span class="btn-title">Member Login</span>
              <span class="btn-sub">Dashboard</span>
            </div>
            <div class="slide-arrow">→</div>
          </a>

          <a href="jobs.php" class="luxury-tile">
            <div class="icon-circle">💎</div>
            <div class="tile-info">
              <span class="btn-title">Browse Jobs</span>
              <span class="btn-sub">Opportunities</span>
            </div>
            <div class="slide-arrow">→</div>
          </a>

        </div>

        <div class="footer-bar">
          <span>© 2024 POP!Work</span>
          <span>Secured by SSL</span>
          <span>Local Verified</span>
        </div>

      </div>

      <div class="card-right"></div>

    </div>
  </div>

</body>
</html>