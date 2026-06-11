<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$_SESSION['last_activity'] = time();
if (isLoggedIn()) {
    setcookie('user_session', getCurrentUser()['email'], time() + 86400 * 7, '/');
}
if (!isAdmin()) {
    header('Location: ../index.php');
    exit();
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']     ?? '');
    $location    = trim($_POST['location'] ?? '');
    $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $capacity    = filter_input(INPUT_POST, 'cap',   FILTER_VALIDATE_INT);
    $description = trim($_POST['desc']     ?? '');

    if (empty($name))                   $errors[] = 'Venue name is required.';
    if (empty($location))               $errors[] = 'Location is required.';
    if ($price === false || $price < 0) $errors[] = 'A valid price is required.';
    if (!$capacity || $capacity < 1)    $errors[] = 'A valid capacity is required.';
    if (empty($description))            $errors[] = 'Description is required.';

    $imageUrl = 'assets/images/default-venue.jpg';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be under 5 MB.';
        } else {
            $ext  = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            $dir   = dirname(__DIR__) . '/assets/images/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                $imageUrl = 'assets/images/' . $fname;
            } else {
                $errors[] = 'Failed to upload image. Check folder permissions on /assets/images/.';
            }
        }
    }

    if (empty($errors)) {
        // Use real_escape_string for safety — no encoding artifacts in the type string
        $eName     = $conn->real_escape_string($name);
        $eLocation = $conn->real_escape_string($location);
        $eDesc     = $conn->real_escape_string($description);
        $eImage    = $conn->real_escape_string($imageUrl);
        $iCap      = (int)   $capacity;
        $fPrice    = (float) $price;

        $sql = "INSERT INTO venues (name, location, capacity, price, description, image_url)
                VALUES ('$eName', '$eLocation', $iCap, $fPrice, '$eDesc', '$eImage')";

        if ($conn->query($sql)) {
            $newVenueId = (int) $conn->insert_id;

            // Insert default amenities
            foreach (['Parking Available', 'Wi-Fi', 'Air Conditioning'] as $amenity) {
                $eAmenity = $conn->real_escape_string($amenity);
                $conn->query(
                    "INSERT INTO amenities (venue_id, amenity_name)
                     VALUES ($newVenueId, '$eAmenity')"
                );
            }
            $success = true;
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Venue | TAGPO Admin</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="breadcrumb-bar">
  <div class="container">
    <a href="../index.php">Home</a> <span class="mx-2" style="color:#d1d5db;">/</span>
    <span>Add New Venue</span>
  </div>
</div>

<main class="container my-5" style="max-width:700px;">
  <h1 class="venue-title mb-4">List a New Venue</h1>

  <?php if ($success): ?>
    <div class="alert alert-success">
      Venue added successfully! <a href="../index.php">View all venues &rarr;</a>
    </div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card p-4 shadow-sm border-0">
    <form method="POST" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label fw-semibold">Venue Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" placeholder="e.g. The Glass Garden"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Location <span class="text-danger">*</span></label>
        <input type="text" name="location" class="form-control" placeholder="e.g. Makati, Metro Manila"
               value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Base Price (&#8369;) <span class="text-danger">*</span></label>
          <input type="number" name="price" class="form-control" step="0.01" min="0"
                 placeholder="35000" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Max Capacity (pax) <span class="text-danger">*</span></label>
          <input type="number" name="cap" class="form-control" min="1"
                 placeholder="200" value="<?= htmlspecialchars($_POST['cap'] ?? '') ?>" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
        <textarea name="desc" class="form-control" rows="4"
                  placeholder="Describe the venue ambiance..." required><?= htmlspecialchars($_POST['desc'] ?? '') ?></textarea>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold">Venue Image</label>
        <input type="file" name="image" class="form-control" accept="image/*">
        <div class="form-text">Optional. Max 5 MB. JPG, PNG, WEBP, or GIF.</div>
      </div>
      <div class="d-flex gap-3">
        <button type="submit" class="btn btn-primary px-5">Save Venue</button>
        <a href="../index.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
</body>
</html>
