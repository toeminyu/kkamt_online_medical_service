<?php
// services.php - Services Page
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['email']);
$bookingLink = $isLoggedIn ? 'book_doctor.php' : 'patient_register.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - 24/7 Online Medical Service</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f5f7fa;
        }

        .main-content {
            flex: 1;
            width: 100%;
            padding: 40px 20px;
        }

        .services-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header Section */
        .page-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .page-header h1 {
            font-size: 42px;
            color: #2d3748;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            font-size: 18px;
            color: #718096;
            line-height: 1.6;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        /* Service Card */
        .service-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            border: 3px solid transparent;
            position: relative;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }

        .service-card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.95) 0%, rgba(118, 75, 162, 0.95) 100%);
            padding: 150px 30px 30px 30px;
            text-align: center;
            color: white;
            position: relative;
            background-size: cover;
            background-position: center;
            min-height: 250px;
        }

        .service-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.6) 0%, rgba(118, 75, 162, 0.6) 100%);
            z-index: 1;
        }

        .service-card-header > * {
            position: relative;
            z-index: 2;
        }

        .service-card-header.minor-cases {
            background-image: url('image/22.png');
        }

        .service-card-header.mental-health {
            background-image: url('image/24.jpg');
        }

        .service-icon {
            display: none;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .service-card-header h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .service-card-header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .service-card-body {
            padding: 35px 30px;
        }

        .service-description {
            font-size: 16px;
            color: #4a5568;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .service-features {
            list-style: none;
            margin-bottom: 30px;
        }

        .service-features li {
            padding: 12px 0;
            color: #2d3748;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-features li:last-child {
            border-bottom: none;
        }

        .service-features li::before {
            content: '✓';
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            font-size: 14px;
            flex-shrink: 0;
        }

        /* Book Button */
        .book-btn {
            display: block;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .book-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .book-btn:active {
            transform: scale(0.98);
        }

        /* Info Badge */
        .info-badge {
            display: inline-block;
            background: #eef2ff;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        /* Emergency Note */
        .emergency-note {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #f59e0b;
            margin-top: 40px;
            text-align: center;
        }

        .emergency-note h3 {
            color: #92400e;
            font-size: 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .emergency-note p {
            color: #78350f;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .services-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .page-header h1 {
                font-size: 32px;
            }

            .service-card-header h2 {
                font-size: 28px;
            }

            .service-icon {
                font-size: 60px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 30px 15px;
            }

            .page-header {
                margin-bottom: 40px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .page-header p {
                font-size: 16px;
            }

            .service-card-header {
                padding: 30px 20px;
            }

            .service-card-body {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="services-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Our Medical Services</h1>
                <p>Choose the service that best fits your healthcare needs. Our professional doctors are ready to assist you 24/7.</p>
            </div>

            <!-- Services Grid -->
            <div class="services-grid">
                <!-- Minor Cases Service -->
                <div class="service-card">
                    <div class="service-card-header minor-cases">
                        <h2>Minor Cases</h2>

                        <p class="subtitle">General Medical Consultation</p>
                    </div>
                    <div class="service-card-body">
                        <span class="info-badge">Most Popular</span>
                        <p class="service-description">
                            Get immediate medical attention for common health concerns. Our experienced doctors are available 24/7 to diagnose and treat minor illnesses and injuries.
                        </p>
                        <ul class="service-features">
                            <li>Cold, flu, and fever treatment</li>
                            <li>Minor injuries and wounds</li>
                            <li>Stomach issues and infections</li>
                            <li>Skin rashes and allergies</li>
                            <li>Headaches and migraines</li>
                            <li>Digital prescriptions included</li>
                        </ul>
                        <a href="<?= $bookingLink ?>" class="book-btn">
                            <?= $isLoggedIn ? 'Book Appointment Now' : 'Register to Book Appointment' ?>
                        </a>
                    </div>
                </div>

                <!-- Mental Illness Service -->
                <div class="service-card">
                    <div class="service-card-header mental-health">
                        <h2>Mental Health</h2>
                        <p class="subtitle">Professional Psychological Support</p>
                    </div>
                    <div class="service-card-body">
                        <span class="info-badge">Confidential & Private</span>
                        <p class="service-description">
                            Connect with licensed mental health professionals for confidential counseling and support. Your mental well-being is as important as your physical health.
                        </p>
                        <ul class="service-features">
                            <li>Stress and anxiety management</li>
                            <li>Depression counseling</li>
                            <li>Student mental health support</li>
                            <li>Sleep disorders and insomnia</li>
                            <li>Emotional wellness guidance</li>
                            <li>100% confidential sessions</li>
                        </ul>
                        <a href="<?= $bookingLink ?>" class="book-btn">
                            <?= $isLoggedIn ? 'Book Appointment Now' : 'Register to Book Appointment' ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Emergency Note -->
            <div class="emergency-note">
                <h3>⚠️ Important Notice</h3>
                <p>For life-threatening emergencies, please call 053-920-330 or visit Ram Hostipal emergency room immediately. Our service is designed for non-emergency consultations and mental health support.</p>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>