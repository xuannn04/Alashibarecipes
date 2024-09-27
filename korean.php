<?php 
session_start();

$host = 'localhost';
$dbname = 'Assignment';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Korean Recipes</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="styleskorean.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
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

<main>
    <div class="container-fluid p-0">
        <!-- Image Header Section -->
        <div class="recipe-header w-100 position-relative">
            <img src="images/Kimchi.jpg" alt="Korean Cuisine Image" class="img-fluid w-100" style="height: 400px; object-fit: cover;">
            <div class="image-overlay"></div>
        </div>
    </div>

    <div class="container mt-5">
        <div class="recipe-content mt-4">
            <p>Explore the vibrant flavors of Korean cuisine. From spicy kimchi to savory bulgogi, discover the rich culinary traditions of Korea.</p>

            <!-- Fetch and display Korean recipes from the database -->
            <div class="row">
                <?php
                // Modified query to fetch Korean cuisine recipes with their specific meal type
                $stmt = $pdo->query('
    SELECT 
        CASE
            WHEN Breakfast.BreakfastID IS NOT NULL THEN "breakfast"
            WHEN Lunch.LunchID IS NOT NULL THEN "lunch"
            WHEN Dinner.DinnerID IS NOT NULL THEN "dinner"
            WHEN Side.SideID IS NOT NULL THEN "side"
            WHEN Dessert.DessertID IS NOT NULL THEN "dessert"
        END AS meal_type,
        COALESCE(Breakfast.BreakfastID, Lunch.LunchID, Dinner.DinnerID, Side.SideID, Dessert.DessertID) AS meal_id,
        Meal.MealName, 
        Meal.MealDescription, 
        Images.ImagePath 
    FROM Meal
    JOIN MealCategory ON Meal.MealID = MealCategory.MealID
    JOIN Cuisine ON MealCategory.CuisineID = Cuisine.CuisineID
    JOIN Images ON Meal.ImageID = Images.ImageID
    LEFT JOIN Breakfast ON Meal.MealID = Breakfast.BreakfastID AND Breakfast.Status = "approved"
    LEFT JOIN Lunch ON Meal.MealID = Lunch.LunchID AND Lunch.Status = "approved"
    LEFT JOIN Dinner ON Meal.MealID = Dinner.DinnerID AND Dinner.Status = "approved"
    LEFT JOIN Side ON Meal.MealID = Side.SideID AND Side.Status = "approved"
    LEFT JOIN Dessert ON Meal.MealID = Dessert.DessertID AND Dessert.Status = "approved"
    WHERE Cuisine.CuisineName = "Korean"
    AND (Breakfast.Status = "approved" OR Lunch.Status = "approved" OR Dinner.Status = "approved" OR Side.Status = "approved" OR Dessert.Status = "approved")
');

                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <div class="col-md-4">
                        <div class="card">
                            <a href="recipe.php?id=<?php echo $row['meal_id']; ?>&type=<?php echo $row['meal_type']; ?>">
                                <img src="<?php echo htmlspecialchars($row['ImagePath']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['MealName']); ?>">
                            </a>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="recipe.php?id=<?php echo $row['meal_id']; ?>&type=<?php echo $row['meal_type']; ?>">
                                        <?php echo htmlspecialchars($row['MealName']); ?>
                                    </a>
                                </h5>
                                <p class="card-text"><?php echo htmlspecialchars($row['MealDescription']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</main>

<footer id="footer">
    <div class="container text-center">
        <p>&copy; 2024 ALASHIBARECIPES. All Rights Reserved.</p>
    </div>
</footer>

<!-- jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- External JavaScript file -->
<script src="indexjs.js"></script>
</body>
</html>