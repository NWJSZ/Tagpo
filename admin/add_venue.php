<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = '../';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}

// Security check - Admin only
if (!isAdmin()) {
    header("Location: ../index.php?expired=true");
    exit();
}

// 1. Dito mo ilagay ang Logic (Walang HTML dapat sa taas nito)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['venues'])) {
        $_SESSION['venues'] = [];
    }

    $uploadedImage = 'images/default-venue.jpg';

    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0){
        $targetDir = "../assets/images/";
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;

        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);

        $uploadedImage = $targetFile;
    }

    $newVenue = [
        'id'        => time(), 
        'name'      => $_POST['name'] ?? 'Unnamed Venue',
        'location'  => $_POST['location'] ?? 'Unknown Location',
        'price'     => (int)($_POST['price'] ?? 0),
        'cap'       => (int)($_POST['cap'] ?? 0),
        'standing'  => (int)($_POST['cap'] ?? 0) + 50,
        'catering'  => isset($_POST['catering']),
        'tag'       => $_POST['tag'] ?? 'General',
        'image' => $uploadedImage,
        'gallery' => [
            ['label' => 'Main View', 'src' => $uploadedImage],
            ['label' => 'Venue Space', 'src' => $uploadedImage],
            ['label' => 'Exterior', 'src' => $uploadedImage],
            ['label' => 'Exterior', 'src' => $uploadedImage],
            ['label' => 'Exterior', 'src' => $uploadedImage]
        ],
        'desc'      => $_POST['desc'] ?? 'No description provided.',
        'response'  => '24 hrs',
        'rating'    => 5.0,
        'reviews'   => 0,
        'why'       => [
            'Newly listed premium property',
            'Flexible space for various events',
            'Verified by Tagpo Admin'
        ],
        'amenities' => [
            ['icon' => '🅿️', 'label' => 'Parking Available'],
            ['icon' => '📶', 'label' => 'Wi-Fi Ready'],
            ['icon' => '❄️', 'label' => 'Fully Air-conditioned']
        ],
        'reviews_list' => []
    ];

    $_SESSION['venues'][] = $newVenue;

    // Redirect agad pagkatapos i-save
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Venue | TAGPO</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

    <div class="breadcrumb-bar">
        <div class="container">
            <a href="../index.php">Home</a>
            <span class="mx-2" style="color:#d1d5db;">/</span>
            <a href="add_venue.php">Add New Venue</a>
        </div>
    </div>

    <main class="main-wrap">
        <div class="content-side">
            <h1 class="venue-title">List your property</h1>
            <p class="section-sub mb-4">Fill in the details below to add a new venue to the TAGPO collection. Your venue will be visible for the current session.</p>
            
            <div class="why-card">
                <h4>Why list with TAGPO?</h4>
                <ul class="why-list">
                    <li><span class="check-icon"></span> Premium audience of event planners</li>
                    <li><span class="check-icon"></span> Refined, editorial-style presentation</li>
                    <li><span class="check-icon"></span> Easy management tools</li>
                </ul>
            </div>

            <div class="section">
                <h3 class="section-title">Venue Details</h3>
                <p>Provide a clear name and location to help users find your space easily. Aesthetic descriptions and tags like "Vintage" or "Minimalist" perform best.</p>
            </div>
        </div>

        <div class="form-side">
            <div class="booking-card">
                <h3>Venue Info</h3>
                <p class="price-sub">Enter the baseline information.</p>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Venue Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. The Glass Garden" required>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Makati, Metro Manila" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Base Price</label>
                            <input type="number" name="price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Max Capacity</label>
                            <input type="number" name="cap" class="form-control" placeholder="Max Pax" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Category Tag</label>
                        <input type="text" name="tag" class="form-control" placeholder="Wedding · Corporate · Birthday">
                    </div>

                    <div class="form-group mb-3">
                        <label>Venue Description</label>
                        <textarea name="desc" class="form-control" rows="4" placeholder="Tell us more about the ambiance and history..." required></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label>Upload Venue Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="catering" id="cateringCheck">
                        <label class="form-check-label" for="cateringCheck">
                            Allow External Catering?
                        </label>
                    </div>

                    <button type="submit" class="btn-enquire">Save Venue</button>
                    
                    <p class="free-note">This will immediately update your session data.</p>
                </form>
            </div>
        </div>
    </main>

</body>
</html>