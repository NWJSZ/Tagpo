<?php
// info.php — Premium Shared Corporate Template for Tagpo
$page = isset($_GET['page']) ? $_GET['page'] : 'about';

// Premium copywriting setup para magmukhang mamahalin ang platform
$title = "About Our House";
$subtitle = "The story behind the Philippines' premier bespoke venue matrix.";
$content = "Tagpo was born out of a singular, refined vision: to eliminate the chaotic friction of event curation. We operate as an architectural bridge between visionary clients and the country's most breathtaking, historically profound, and structurally elegant event spaces.";

switch ($page) {
    case 'list-venue':
        $title = "List Your Venue";
        $subtitle = "Propel your property into the exclusive luxury booking ecosystem.";
        $content = "Are you a proprietor of an exceptional space? Partner with Tagpo to open your doors to discerning individuals. Our platform offers a seamless digital interface designed to preserve the exclusivity of your venue while streamlining scheduling, reservation mechanics, and transactional security.";
        break;
    case 'contact':
        $title = "Get In Touch";
        $subtitle = "We are at your service for any bespoke curation requirements.";
        $content = "Whether you are planning an intimate wedding or a corporate gala, our concierge desk is available to assist you. Connect with our principal officers directly via email at hello@tagpo.ph or through our dedicated corporate line at +63 (2) 8123-4567.";
        break;
    case 'privacy':
        $title = "Privacy Policy";
        $subtitle = "Your data dignity and confidentiality choices are absolute.";
        $content = "At Tagpo, we hold your personal information in strict confidence. We employ high-grade cryptographic frameworks solely to host account variables, track preference vectors, and simulate secure transactional states. No third-party data distribution is ever permitted.";
        break;
    case 'terms':
        $title = "Terms of Service";
        $subtitle = "The institutional governance framework of the Tagpo ecosystem.";
        $content = "By engaging with our platform, you assent to our professional codes of conduct. This includes maintaining accurate registration data, respecting simulated billing schedules, and conforming to venue-specific capacity protocols to ensure optimal event safety.";
        break;
    case 'cookie':
        $title = "Cookie Declaration";
        $subtitle = "Tracking variables aligned with professional browser diagnostics.";
        $content = "We utilize core technical session states solely to authenticate user identity across dashboards and cache current user search vectors. No persistent tracking mechanisms are deployed.";
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | Tagpo Premium</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --tagpo-dark-emerald: #0d231d;
            --tagpo-luxury-gold: #d4af37;
            --tagpo-soft-cream: #fafcfb;
            --tagpo-text-muted: #5c6f68;
        }

        body { 
            background-color: var(--tagpo-soft-cream); 
            color: var(--tagpo-dark-emerald); 
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Hero Header Editorial Styling */
        .editorial-header {
            padding: 90px 0 50px 0;
            border-bottom: 1px solid rgba(13, 35, 29, 0.08);
            margin-bottom: 60px;
        }

        .editorial-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 400;
            letter-spacing: -0.03em;
            color: var(--tagpo-dark-emerald);
            margin-bottom: 15px;
        }

        .editorial-subtitle {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.35rem;
            color: var(--tagpo-luxury-gold);
            font-weight: 400;
            letter-spacing: 0.02em;
        }

        /* Content Text Area Layout */
        .editorial-body {
            font-size: 1.15rem;
            line-height: 1.85;
            color: #2c3e35;
            text-align: justify;
            letter-spacing: -0.005em;
            font-weight: 300;
        }

        /* Minimal Breadcrumbs */
        .premium-breadcrumb {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 25px;
        }
        .premium-breadcrumb a {
            color: var(--tagpo-text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .premium-breadcrumb a:hover {
            color: var(--tagpo-luxury-gold);
        }

        /* Fine Line Minimal Button */
        .btn-luxury-outline {
            border: 1px solid var(--tagpo-dark-emerald);
            background: transparent;
            color: var(--tagpo-dark-emerald);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            padding: 12px 35px;
            border-radius: 0px; /* Sharp corners provide architectural structural premium feel */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-luxury-outline:hover {
            background-color: var(--tagpo-dark-emerald);
            color: var(--tagpo-soft-cream);
            transform: translateY(-2px);
        }

        .decorative-accent-line {
            width: 60px;
            height: 1px;
            background-color: var(--tagpo-luxury-gold);
            margin: 40px 0;
        }
    </style>
</head>
<body>

<?php // include 'includes/navbar.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">
            
            <header class="editorial-header">
                <div class="premium-breadcrumb">
                    <a href="index.php">Home</a> &nbsp;/&nbsp; <span class="text-dark"><?= ($page === 'about') ? 'Company' : 'Legal & Operations' ?></span>
                </div>
                <h1 class="editorial-title"><?= $title ?></h1>
                <div class="editorial-subtitle"><?= $subtitle ?></div>
            </header>

            <article class="row pb-5 mb-5">
                <div class="col-md-11">
                    <div class="decorative-accent-line"></div>
                    <p class="editorial-body mb-5">
                        <?= $content ?>
                    </p>
                    <div class="pt-4">
                        <a href="index.php" class="btn btn-luxury-outline">
                            <i class="bi bi-arrow-left me-2"></i> Return to Main House
                        </a>
                    </div>
                </div>
            </article>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>