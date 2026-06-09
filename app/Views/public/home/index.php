<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<?php
$homeStats = $stats ?? [];
$labCount = (int) ($homeStats['lab_count'] ?? 0);
$bookingCount = (int) ($homeStats['total_bookings'] ?? 0);
$approvedCount = (int) ($homeStats['approved'] ?? 0);
$heroPoster = base_url('images/fkmp/FKMP.jpeg');
?>

<!-- ============================================================
     HERO SECTION WITH AERIAL FOOTAGE
     ============================================================ -->
<div class="home-page">
<section class="hero-section">
    <!-- Video Background -->
    <div class="video-background">
        <video id="uthmVideo" autoplay muted loop playsinline preload="metadata" poster="<?= esc($heroPoster) ?>">
            <source src="<?= base_url('images/night-time-aerial-compressed.mp4') ?>" type="video/mp4">
        </video>
    </div>
    
    <!-- Overlay for better text readability -->
    <div class="hero-overlay"></div>
    
    <!-- Video Controls -->
    <div class="video-controls">
        <button class="video-toggle" id="videoPauseBtn" title="Pause Video">
            <i class="bi bi-pause-fill"></i>
        </button>
    </div>
    
    <!-- Hero Content -->
    <div class="container">
        <div class="hero-content">
            <div class="home-hero-panel">
                <div class="hero-eyebrow">
                    <i class="bi bi-stars"></i>
                    FKMP UTHM Laboratory Access
                </div>
                <h1>Smart laboratory booking with clearer approval control.</h1>
                <p class="subtitle">
                    Discover FKMP laboratories, check available resources, submit booking requests,
                    and track approvals through one coordinated SLAMS workspace.
                </p>
                <div class="hero-buttons">
                    <a href="<?= site_url('/laboratories') ?>" class="hero-btn hero-btn-primary">
                        <i class="bi bi-building"></i>
                        Explore Laboratories
                    </a>
                    <a href="<?= site_url('/login') ?>" class="hero-btn hero-btn-secondary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login to SLAMS
                    </a>
                </div>
            </div>
        </div>
    </div>
    
        <!-- Campus Info -->
    <div class="campus-info">
        <i class="bi bi-camera-video-fill"></i>
        <span>UTHM Campus Night Aerial Footage</span>
    </div>
</section>


<div class="container">

    <section class="home-stats-section" aria-label="SLAMS public summary">
        <div class="home-stat-grid">
            <div class="home-stat">
                <span class="home-stat-value"><?= esc($labCount) ?></span>
                <span class="home-stat-label">Laboratories listed</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-value"><?= esc($bookingCount) ?></span>
                <span class="home-stat-label">Booking records</span>
            </div>
            <div class="home-stat">
                <span class="home-stat-value"><?= esc($approvedCount) ?></span>
                <span class="home-stat-label">Approved requests</span>
            </div>
        </div>
    </section>

    <!-- ============================================================
         FEATURE SECTION
         ============================================================ -->
    <div class="home-section-header">
        <div class="home-section-kicker">
            <i class="bi bi-lightning-charge"></i>
            Core Experience
        </div>
        <h3 class="feature-section-title">A cleaner way to manage lab access</h3>
        <p class="text-muted">
            SLAMS keeps requests, equipment availability, approval stages, and notifications connected
            so each role can work from the same source of truth.
        </p>
    </div>

    <div class="row g-4 mb-5 home-feature-grid">

        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h5 class="fw-semibold">Fast & Easy Booking</h5>
                <p class="small mb-0">
                    A streamlined wizard helps UTHM students and staff submit lab bookings with ease.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-tools"></i>
                </div>
                <h5 class="fw-semibold">Equipment Availability</h5>
                <p class="small mb-0">
                    View real-time equipment availability and select items required for your task.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h5 class="fw-semibold">Secure Approval Flow</h5>
                <p class="small mb-0">
                    Built-in PIC and Manager approval ensures compliance and lab safety.
                </p>
            </div>
        </div>

    </div>

    <!-- ============================================================
         WORKFLOW SECTION
         ============================================================ -->
    <section class="home-flow-section">
        <div class="home-section-header">
            <div class="home-section-kicker">
                <i class="bi bi-diagram-3"></i>
                Booking Workflow
            </div>
            <h3>From discovery to approval</h3>
            <p class="text-muted">
                The public journey stays simple while PIC and manager checks remain structured behind the scenes.
            </p>
        </div>

        <div class="home-flow-grid">
            <div class="home-flow-card">
                <div class="home-flow-step">1</div>
                <h5>Choose a laboratory</h5>
                <p class="small mb-0">Browse available FKMP facilities and review room, PIC, and equipment information.</p>
            </div>
            <div class="home-flow-card">
                <div class="home-flow-step">2</div>
                <h5>Select resources</h5>
                <p class="small mb-0">Request the required assets and booking slot with built-in availability checks.</p>
            </div>
            <div class="home-flow-card">
                <div class="home-flow-step">3</div>
                <h5>Track the decision</h5>
                <p class="small mb-0">Receive status updates as requests move through PIC and manager approval.</p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         CTA SECTION
         ============================================================ -->
    <section class="cta-section">
        <div class="home-cta-content">
            <div class="home-cta-copy">
                <h4 class="fw-bold mb-2">Ready to submit a booking request?</h4>
                <p class="mb-0">
                    Log in to continue with your SLAMS dashboard, or browse laboratories first if you are still planning.
                </p>
            </div>
            <div class="cta-buttons mt-0">
                <a href="<?= site_url('/login') ?>" class="btn btn-glow px-4 py-2">
                    <i class="bi bi-person-circle me-1"></i> Login Now
                </a>
                <a href="<?= site_url('/laboratories') ?>" class="btn btn-glass px-4 py-2">
                    <i class="bi bi-search me-1"></i> Browse Labs
                </a>
            </div>
        </div>
    </section>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('uthmVideo');
    const videoBtn = document.getElementById('videoPauseBtn');
    const videoBackground = document.querySelector('.video-background');
    let isPlaying = true;

    function setVideoIcon(iconClass) {
        const icon = videoBtn ? videoBtn.querySelector('i') : null;
        if (icon) {
            icon.className = iconClass;
        }
    }

    function activateVideoFallback() {
        if (videoBackground) {
            videoBackground.classList.add('is-video-fallback');
        }

        if (video) {
            video.pause();
            video.removeAttribute('autoplay');
        }

        if (videoBtn) {
            videoBtn.hidden = true;
        }

        isPlaying = false;
    }
    
    // Optimize video for night footage
    if (video && videoBtn) {
        // Ensure video plays
        video.play().catch(error => {
            console.log('Autoplay prevented, showing play button');
            setVideoIcon('bi bi-play-fill');
            isPlaying = false;
        });

        video.addEventListener('error', activateVideoFallback);
        
        // Video controls
        videoBtn.addEventListener('click', function() {
            if (isPlaying) {
                video.pause();
                setVideoIcon('bi bi-play-fill');
                videoBtn.title = 'Play Video';
                isPlaying = false;
            } else {
                video.play();
                setVideoIcon('bi bi-pause-fill');
                videoBtn.title = 'Pause Video';
                isPlaying = true;
            }
        });
        
        // Handle video loading states
        video.addEventListener('waiting', function() {
            video.style.opacity = '0.8';
        });
        
        video.addEventListener('canplay', function() {
            video.style.opacity = '1';
        });
        
        // Restart video when it ends
        video.addEventListener('ended', function() {
            this.currentTime = 0;
            this.play();
        });

        window.setTimeout(function() {
            if (video.readyState === 0 || video.networkState === HTMLMediaElement.NETWORK_NO_SOURCE) {
                activateVideoFallback();
            }
        }, 3500);
    }
    
    // Add smooth scroll for navigation
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
});
</script>

<?= $this->endSection() ?>
