<?php
session_start();

$host = 'localhost';
$db = 'Assignment';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
if (isset($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT m.MealID, m.MealName, 
                   CASE 
                       WHEN b.BreakfastID IS NOT NULL THEN 'breakfast'
                       WHEN l.LunchID IS NOT NULL THEN 'lunch'
                       WHEN d.DinnerID IS NOT NULL THEN 'dinner'
                       WHEN s.SideID IS NOT NULL THEN 'side'
                       WHEN ds.DessertID IS NOT NULL THEN 'dessert'
                       ELSE 'meal'
                   END as meal_type
            FROM Meal m
            LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID AND b.Status = 'approved'
            LEFT JOIN Lunch l ON m.MealID = l.LunchID AND l.Status = 'approved'
            LEFT JOIN Dinner d ON m.MealID = d.DinnerID AND d.Status = 'approved'
            LEFT JOIN Side s ON m.MealID = s.SideID AND s.Status = 'approved'
            LEFT JOIN Dessert ds ON m.MealID = ds.DessertID AND ds.Status = 'approved'
            LEFT JOIN MealIngredients mi ON m.MealID = mi.MealID
            LEFT JOIN Ingredients i ON mi.IngredientID = i.IngredientID
            WHERE (m.MealName LIKE :searchTerm 
               OR m.MealDescription LIKE :searchTerm
               OR i.IngredientName LIKE :searchTerm)
            AND (b.Status = 'approved' OR l.Status = 'approved' OR d.Status = 'approved' 
                 OR s.Status = 'approved' OR ds.Status = 'approved')
            LIMIT 10
        ");
        $stmt->execute(['searchTerm' => $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
// Fetch all ingredients
$stmt = $pdo->query("SELECT * FROM Ingredients ORDER BY IngredientName");
$ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle ingredient selection
$selectedIngredient = isset($_GET['ingredient']) ? $_GET['ingredient'] : null;
$recipes = [];

if ($selectedIngredient) {
    // Fetch only approved recipes containing the selected ingredient
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.MealID, m.MealName, m.MealDescription, i.ImagePath, 
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN 'Breakfast'
                   WHEN l.LunchID IS NOT NULL THEN 'Lunch'
                   WHEN d.DinnerID IS NOT NULL THEN 'Dinner'
                   WHEN s.SideID IS NOT NULL THEN 'Side'
                   WHEN ds.DessertID IS NOT NULL THEN 'Dessert'
               END AS MealType
        FROM Meal m
        JOIN MealIngredients mi ON m.MealID = mi.MealID
        JOIN Images i ON m.ImageID = i.ImageID
        LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID
        LEFT JOIN Lunch l ON m.MealID = l.LunchID
        LEFT JOIN Dinner d ON m.MealID = d.DinnerID
        LEFT JOIN Side s ON m.MealID = s.SideID
        LEFT JOIN Dessert ds ON m.MealID = ds.DessertID
        WHERE mi.IngredientID = :ingredientId
        AND (
            (b.BreakfastID IS NOT NULL AND b.Status = 'approved') OR
            (l.LunchID IS NOT NULL AND l.Status = 'approved') OR
            (d.DinnerID IS NOT NULL AND d.Status = 'approved') OR
            (s.SideID IS NOT NULL AND s.Status = 'approved') OR
            (ds.DessertID IS NOT NULL AND ds.Status = 'approved')
        )
    ");
    $stmt->execute(['ingredientId' => $selectedIngredient]);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Ingredients</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<style>

.card-img-top {
    width: 100%;
    height: 200px; 
    object-fit: cover; 
}

.card {
    height: 100%; 
    display: flex;
    flex-direction: column;
}

.card-body {
    flex-grow: 1; 
    display: flex;
    flex-direction: column;
}

.card-title {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.card-text {
    font-size: 0.9rem;
    flex-grow: 1; 
}

.text-muted {
    font-size: 0.8rem;
}

.col-md-6 {
    margin-bottom: 20px; 
}

@media (min-width: 768px) {
    .row {
        display: flex;
        flex-wrap: wrap;
    }
    .col-md-6 {
        display: flex;
    }
}
</style>
<body>
<!-- navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container d-flex justify-content-between align-items-center">
        <!-- Brand logo -->
        <a class="navbar-brand" href="index.php" id="brand-logo">Alashibarecipes</a>

        <!-- Centered Search bar -->
        <div class="search-container mx-auto">
            <input type="text" id="search-input" class="form-control" placeholder="Find a recipe or ingredient">
            <div id="search-results" class="search-results"></div>
        </div>

        <!-- My Account dropdown with user image -->
        <div class="dropdown d-flex align-items-center">
            <!-- User Image -->
            <img src="images/user.png" alt="User Image" class="user-image mr-2">

            <!-- My Account dropdown -->
            <a>
                <span class="mr-1">My Account</span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="dropdown-menu" aria-labelledby="accountDropdown">
                <a class="dropdown-item" href="add.php">Add a Recipe</a>
                <a class="dropdown-item" href="userprofile.php">Profile</a>
                <a class="dropdown-item" href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>

<!-- Separate navigation links section -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <div class="collapse navbar-collapse justify-content-center">
            <ul class="navbar-nav">
                <!-- Meals dropdown menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle no-arrow" href="#" id="mealsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Meals
                    </a>
                    <div class="dropdown-menu" aria-labelledby="mealsDropdown">
                        <a class="dropdown-item" href="breakfast.php">Breakfast</a>
                        <a class="dropdown-item" href="lunch.php">Lunch</a>
                        <a class="dropdown-item" href="dinner.php">Dinner</a>
                        <a class="dropdown-item" href="sidedishes.php">Side Dishes</a>
                        <a class="dropdown-item" href="desserts.php">Desserts</a>
                    </div>
                </li>
                
                <!-- Cuisines dropdown menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle no-arrow" href="#" id="cuisinesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Cuisines
                    </a>
                    <div class="dropdown-menu" aria-labelledby="cuisinesDropdown">
                        <a class="dropdown-item" href="chinese.php">Chinese</a>
                        <a class="dropdown-item" href="japanese.php">Japanese</a>
                        <a class="dropdown-item" href="korean.php">Korean</a>
                        <a class="dropdown-item" href="american.php">American</a>
                        <a class="dropdown-item" href="french.php">French</a>
                    </div>
                </li>
                <!-- Ingredients menu  -->
                <li class="nav-item">
                    <a class="nav-link dropdown-toggle no-arrow" href="ingredients.php">
                        Ingredients
                    </a>
                </li>
                
                <!-- About us and FAQ -->
                <li class="nav-item">
                    <a class="nav-link" href="aboutus.php">About us</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="faq.php">FAQ</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

    <main class="container mt-5">
        <h1 class="mb-4">Ingredients</h1>

        <div class="row">
            <div class="col-md-4">
                <h2>Select an Ingredient</h2>
                <ul class="list-group">
                    <?php foreach ($ingredients as $ingredient): ?>
                        <li class="list-group-item">
                            <a href="?ingredient=<?= urlencode($ingredient['IngredientID']) ?>">
                                <?= htmlspecialchars($ingredient['IngredientName']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-md-8">
    <?php if ($selectedIngredient): ?>
        <?php
        $selectedIngredientName = '';
        foreach ($ingredients as $ingredient) {
            if ($ingredient['IngredientID'] == $selectedIngredient) {
                $selectedIngredientName = $ingredient['IngredientName'];
                break;
            }
        }
        ?>
        <h2>Recipes with <?= htmlspecialchars($selectedIngredientName) ?></h2>
        <?php if (empty($recipes)): ?>
            <p>No recipes found with this ingredient.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($recipes as $recipe): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <a href="recipe.php?id=<?= urlencode($recipe['MealID']) ?>&type=<?= strtolower(urlencode($recipe['MealType'])) ?>" class="text-decoration-none">
                                <img src="<?= htmlspecialchars($recipe['ImagePath']) ?>" class="card-img-top" alt="<?= htmlspecialchars($recipe['MealName']) ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($recipe['MealName']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($recipe['MealDescription']) ?></p>
                                    <p class="card-text"><small class="text-muted">Meal Type: <?= htmlspecialchars($recipe['MealType']) ?></small></p>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>Select an ingredient to see matching recipes.</p>
    <?php endif; ?>
</div>
        </div>
    </main>

    <footer id="footer">
        <div class="container text-center">
            <p>&copy; 2024 ALASHIBARECIPES. All Rights Reserved.</p>
        </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="indexjs.js"></script>
</body>
</html>