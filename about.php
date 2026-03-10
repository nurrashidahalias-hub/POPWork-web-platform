<?php
$page_title = "About Us - POP!Work";
include 'includes/header.php';
?>

<style>
    /* Scoped to avoid conflicts, or kept global if this page is unique */
    .about-section-wrapper {
        font-family: 'Poppins', sans-serif;
        color: #333;
        overflow-x: hidden;
    }

    /* --- Hero Section --- */
    .hero-section {
        position: relative;
        height: 30vh;
        min-height: 400px;
        background: url('uploads/photos/bac.jpeg') no-repeat center center;
        background-size: cover;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #8a1538;
    }
    
    .hero-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255, 255, 255, 0.35);
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 800px;
        padding: 20px;
    }

    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 2px;
        animation: fadeInUp 1s ease-out;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        font-weight: 300;
        margin-bottom: 30px;
        opacity: 0.9;
        animation: fadeInUp 1s ease-out 0.2s backwards;
    }

    /* --- Stats Section --- */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 30px;
        max-width: 1200px;
        margin: -50px auto 50px;
        padding: 0 20px;
        position: relative;
        z-index: 3;
    }

    .stat-box {
        background: #8a1538;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        text-align: center;
        transition: transform 0.3s ease;
    }

    .stat-box:hover {
        transform: translateY(-10px);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: white;
        margin-bottom: 10px;
    }

    .stat-label {
        font-size: 0.9rem;
        color: white;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* --- Story Section --- */
    .story-section {
        max-width: 1000px;
        margin: 0 auto;
        line-height: 1.8;
    }

    .section-title {
        font-size: 2rem;
        font-weight: 700;
        color: #8a1538;
        margin-bottom: 10px;
        text-align: center;
    }

    .story-text {
        font-size: 1.1rem;
        color: #555;
        margin-bottom: 20px;
        text-align: justify;
    }

    /* --- Values Section --- */
    .values-section {
        background: #9c1142ff;
        padding: 80px 20px;
    }

    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .value-card {
        background: white;
        padding: 40px;
        border-radius: 12px;
        border-left: 5px solid #8a1538;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }

    .value-card h3 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        color: #333;
    }

    .value-card p {
        color: #666;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="about-section-wrapper">

    <div class="hero-section">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title">We Are POP!Work</h1>
            <p class="hero-subtitle">Connecting Sabah's Best Talent with Premier Opportunities</p>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-number number">850+</div>
            <div class="stat-label">Active Workers</div>
        </div>
        <div class="stat-box">
            <div class="stat-number number">320+</div>
            <div class="stat-label">Companies</div>
        </div>
        <div class="stat-box">
            <div class="stat-number number">580+</div>
            <div class="stat-label">Jobs Completed</div>
        </div>
        <div class="stat-box">
            <div class="stat-number number">4.8</div>
            <div class="stat-label">Average Rating</div>
        </div>
    </div>

    <div class="story-section">
        <h2 class="section-title">Our Mission</h2>
        <p class="story-text">
            Founded with a vision to revolutionize the gig economy in Sabah, POP!Work acts as the bridge between local talent and businesses requiring flexible, high-quality staffing solutions. We believe that finding work shouldn't be a hassle, and finding workers shouldn't be a gamble.
        </p>
        <p class="story-text">
            From Kota Kinabalu to Tawau, we provide a secure, transparent, and efficient platform where payments are guaranteed, profiles are verified, and connections are made instantly. Whether you are a student looking for part-time income or a business manager needing urgent staff, POP!Work is your trusted partner.
        </p>
    </div>

    <div class="values-section">
        <div class="values-grid">
            <div class="value-card">
                <h3><i class="fa-solid fa-shield-halved" style="color: #8a1538; margin-right:10px;"></i> Safety First</h3>
                <p>We implement strict verification for all users and secure payment holding to ensure that every job done is a job paid.</p>
            </div>
            <div class="value-card">
                <h3><i class="fa-solid fa-bolt" style="color: #8a1538; margin-right:10px;"></i> Speed & Efficiency</h3>
                <p>Our platform is built for speed. Post a job in minutes, get applicants in seconds, and get the work started today.</p>
            </div>
            <div class="value-card">
                <h3><i class="fa-solid fa-users" style="color: #8a1538; margin-right:10px;"></i> Community</h3>
                <p>We are more than an app; we are a community of hardworking Sabahans building a better local economy together.</p>
            </div>
        </div>
    </div>

</div>

<script>
    // Counter Animation for Stats
    function animateCounter(element, target) {
        // Handle numbers with decimals (like 4.8)
        const isFloat = target % 1 !== 0;
        const suffix = element.textContent.replace(/[0-9.]/g, '');
        
        let current = 0;
        const duration = 2000; // 2 seconds
        const steps = 60;
        const increment = target / steps;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target + suffix;
                clearInterval(timer);
            } else {
                element.textContent = (isFloat ? current.toFixed(1) : Math.floor(current)) + suffix;
            }
        }, duration / steps);
    }

    // Trigger animation when scrolled into view
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const numbers = document.querySelectorAll('.stat-number');
                const targets = [850, 320, 580, 4.8];
                
                numbers.forEach((num, index) => {
                    // Slight delay for each number for a cascading effect
                    setTimeout(() => {
                        animateCounter(num, targets[index]);
                    }, index * 200);
                });
                
                // Stop observing once animated
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    const statsContainer = document.querySelector('.stats-container');
    if(statsContainer) {
        statsObserver.observe(statsContainer);
    }
</script>