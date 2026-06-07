<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results | Tagpo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container mt-5">
    <div class="mb-3">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Home
        </a>
    </div>

    <div class="mb-4">
        <h2 class="fw-bold text-dark">Search Results</h2>
        <p class="text-muted">Showing venues based on your filters</p>
    </div>

    <?php
    // Kunin ang data mula sa URL (GET)
    $location = $_GET['location'] ?? '';
    $type = $_GET['type'] ?? '';
    $guests = $_GET['guests'] ?? '';
    $budget = $_GET['budget'] ?? '';

    // Data Source (Lesson 1: Numeric & Associative Array)
    $venues = [
        ['id'=>1,'name'=>'Paradiso Terrestre','location'=>'Molino Cavite City','price'=>35000,'cap'=>500,'type'=>['birthday','wedding','prom', 'corporate'],'image'=>'assets/images/paradiso1.jpg'],
        ['id'=>2,'name'=>'Blue Gardens','location'=>'Makati City','price'=>60000,'cap'=>250,'type'=>['prom','wedding','birthday', 'corporate', 'reunion', 'anniversary'],'image'=>'assets/images/gardens1.jpg'],
        ['id'=>3,'name'=>'The Green Lounge Events Place','location'=>'Quezon City','price'=>45000,'cap'=>300,'type'=>['wedding','birthday','prom','corporate','reunion','anniversary'],'image'=>'assets/images/lounge1.jpg']
    ];

    $results = [];

    // Lesson 1: Foreach & Condition Test (If-Else)
    foreach($venues as $v){
        // Match location or venue name (case-insensitive partial match)
        $matchesLocation = $location === '' || 
                          stripos($v['location'], $location) !== false || 
                          stripos($v['name'], $location) !== false;
        
        $matchesType = $type === '' || in_array($type, $v['type']);

        $matchesGuests = true;
        if($guests !== ''){
            if($guests === '250'){
                $matchesGuests = $v['cap'] >= 250;
            } elseif($guests === '251-300'){
                $matchesGuests = $v['cap'] >= 251;
            } elseif($guests === '300+'){
                $matchesGuests = $v['cap'] >= 300;
            }
        }

        $matchesBudget = true;

        if($budget !== ''){
            $budgetValue = (int)$budget;

            $matchesBudget = $v['price'] <= $budgetValue;
        }

        if($matchesLocation && $matchesType && $matchesGuests && $matchesBudget){
            $results[] = $v;
        }
    }
    ?>

    <div class="row">
        <?php if(!empty($results)): ?>
            <?php foreach($results as $v): ?>
                <?php
                // SWITCH CASE: Categorize by Price Range
                $priceCategory = '';
                switch(true){
                    case $v['price'] < 40000:
                        $priceCategory = 'Budget Friendly';
                        break;
                    case $v['price'] < 60000:
                        $priceCategory = 'Mid-Range';
                        break;
                    default:
                        $priceCategory = 'Premium';
                }
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card venue-card h-100">
                        <div class="venue-img-placeholder p-0 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($v['image'] ?? 'images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($v['name']); ?>" class="img-fluid w-100" />
                        </div>
                        <div class="card-body">
                            <div class="badge bg-light text-primary mb-2 text-uppercase fw-bold" style="font-size: 0.7rem;">
                                <?php echo $priceCategory; ?>
                            </div>
                            <h5 class="fw-bold mb-1"><?php echo $v['name']; ?></h5>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-people me-1"></i> Up to <?php echo $v['cap']; ?> guests
                            </p>
                            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                <span class="text-primary fw-bold h5 mb-0">₱<?php echo number_format($v['price']); ?></span>
                                <a href="venue.php?id=<?php echo $v['id']; ?>" class="btn btn-outline-dark btn-sm px-3">View</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
                <h4 class="fw-bold">No venues found</h4>
                <p class="text-muted">Try adjusting your filters or search for another location.</p>
                <a href="index.php" class="btn btn-primary mt-2">Go Back to Home</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>