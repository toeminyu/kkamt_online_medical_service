<?php
// index.php or home.php - Homepage
session_start();

// Link logic per your requirement:
// - Guest  -> register.php
// - Logged-in -> patient.php
$isLoggedIn = isset($_SESSION['user_id']);
$ctaHref    = $isLoggedIn ? 'patient.php' : 'patient_register.php';

// If you ALSO want the bottom "Create Account" to adapt:
$createHref = $isLoggedIn ? 'patient.php' : 'patient_register.php';

// (Optional) Role-aware routing (commented out).
// If you later prefer role-based destinations, replace above with this:
// $role = $_SESSION['role'] ?? null;
// if (!$role) {
//   $ctaHref = 'register.php';
//   $createHref = 'patient_register.php';
// } elseif ($role === 'patient') {
//   $ctaHref = $createHref = 'patient.php';
// } elseif ($role === 'doctor') {
//   $ctaHref = $createHref = 'doctor.php';
// } else {
//   $ctaHref = $createHref = 'admin.php';
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - 24/7 Online Medical Service</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; flex-direction: column; min-height: 100vh; background: #f5f7fa;
        }
        .main-content { flex: 1; width: 100%; }

        /* Hero Section */
        .hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 80px 20px; color: white; }
        .hero-container {
            max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 300px;
            gap: 50px; align-items: center;
        }
        .hero-content h1 { font-size: 42px; margin-bottom: 20px; line-height: 1.2; }
        .hero-content p { font-size: 18px; line-height: 1.8; margin-bottom: 30px; opacity: 0.95; }
        .hero-logo-box {
            background: white; border-radius: 20px; padding: 40px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); min-height: 300px;
        }
        .hero-logo-box img { max-width: 100%; height: auto; max-height: 250px; object-fit: contain; }
        .cta-button {
            display: inline-block; background: white; color: #667eea; padding: 15px 40px; border-radius: 30px;
            text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .cta-button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }

        /* Features Section */
        .features-section { padding: 60px 20px; background: white; }
        .features-container { max-width: 1200px; margin: 0 auto; }
        .section-title { text-align: center; font-size: 36px; color: #2d3748; margin-bottom: 50px; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .feature-card {
            background: #f8f9fa; padding: 30px; border-radius: 15px; text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease; border: 2px solid transparent;
        }
        .feature-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: #667eea; }
        .feature-icon { font-size: 48px; margin-bottom: 20px; }
        .feature-card h3 { font-size: 22px; color: #2d3748; margin-bottom: 15px; }
        .feature-card p { color: #718096; line-height: 1.6; font-size: 15px; }

        /* About/Services Section */
        .about-section { padding: 60px 20px; background: linear-gradient(to bottom, #f8f9fa, white); }
        .about-container { max-width: 1000px; margin: 0 auto; text-align: center; }
        .about-container h2 { font-size: 36px; color: #2d3748; margin-bottom: 30px; }
        .about-container p { font-size: 18px; line-height: 1.8; color: #4a5568; margin-bottom: 20px; }
        .benefits-list {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 40px; text-align: left;
        }
        .benefit-item {
            background: white; padding: 20px; border-radius: 10px; border-left: 4px solid #667eea; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .benefit-item strong { color: #2d3748; display: block; margin-bottom: 8px; font-size: 16px; }
        .benefit-item p { font-size: 14px; color: #718096; margin: 0; }

        /* CTA Section */
        .cta-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 60px 20px; text-align: center; color: white; }
        .cta-section h2 { font-size: 36px; margin-bottom: 20px; }
        .cta-section p { font-size: 18px; margin-bottom: 30px; opacity: 0.95; }
        .cta-buttons { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
        .btn-primary {
            background: white; color: #667eea; padding: 15px 40px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        .btn-secondary {
            background: transparent; color: white; padding: 15px 40px; border: 2px solid white; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s ease;
        }
        .btn-secondary:hover { background: white; color: #667eea; }

        /* Responsive */
        @media (max-width: 968px) {
            .hero-container { grid-template-columns: 1fr; gap: 30px; }
            .hero-content h1 { font-size: 32px; }
            .hero-logo-box { max-width: 300px; margin: 0 auto; }
            .section-title { font-size: 28px; }
        }
        @media (max-width: 768px) {
            .hero-section { padding: 50px 20px; }
            .hero-content h1 { font-size: 28px; }
            .hero-content p { font-size: 16px; }
            .features-section, .about-section, .cta-section { padding: 40px 20px; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .btn-primary, .btn-secondary { width: 100%; max-width: 300px; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-container">
                <div class="hero-content">
                    <h1>24/7 Online Medical Service</h1>
                    <p>
                        Our 24/7 Online Medical Service provides around-the-clock healthcare support anytime, anywhere.
                        With professional doctors available online, patients can access consultations, prescriptions, and
                        medical advice instantly through their devices. Combining advanced technology with trusted medical
                        expertise, we ensure fast, reliable, and convenient care whenever you need it.
                    </p>
                    <a href="<?= htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8') ?>" class="cta-button">Get Started Now</a>
                </div>
                <div class="hero-logo-box">
                    <img src="image/logo.jpg" alt="24/7 Medical Service Logo">
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="features-container">
                <h2 class="section-title">Why Choose Our Service?</h2>
                <div class="benefits-list">
                    <div class="benefit-item">
                        <strong>Available Anytime</strong>
                        <p>Consult with doctors 24/7 whenever you need medical assistance.</p>
                    </div>
                    <div class="benefit-item">
                        <strong>Trusted Specialists</strong>
                        <p>Connect with certified professionals in multiple fields of medicine.</p>
                    </div>
                    <div class="benefit-item">
                        <strong>E-Prescriptions</strong>
                        <p>Get digital prescriptions instantly after your consultation.</p>
                    </div>
                    <div class="benefit-item">
                        <strong>Secure & Confidential</strong>
                        <p>Your health data is private and protected with encryption.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section class="about-section">
            <div class="about-container">
                <h2>Our Medical Services</h2>
                <p>We provide comprehensive healthcare services designed to meet your needs anytime, anywhere.</p>

                <div class="features-grid" style="margin-top: 40px;">
                    <div class="feature-card">
                        <div class="feature-icon">                    </div>
                        <h3>Online Consultations</h3>
                        <p>Connect with experienced doctors through video calls for immediate medical advice and diagnosis.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"></div>
                        <h3>Digital Prescriptions</h3>
                        <p>Receive electronic prescriptions that can be used at any pharmacy nationwide.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"></div>
                        <h3>Medical Records</h3>
                        <p>Access your complete medical history and health records securely online.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"></div>
                        <h3>Appointment Reminders</h3>
                        <p>Never miss a consultation with automated reminders and notifications.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="cta-section">
            <h2>Ready to Experience Better & Accessible Healthcare?</h2><br>
            <div class="cta-buttons">
                <a href="service.php" class="btn-secondary">Learn More</a>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
