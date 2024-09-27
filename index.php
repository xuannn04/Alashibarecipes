<?php 
session_start(); 
require_once 'connection.php'; 

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

        ");
        $stmt->execute(['searchTerm' => $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}


try {
    $stmt = $pdo->prepare("
        SELECT m.MealID, m.MealName, m.MealDescription, i.ImagePath, AVG(r.Rating) as avg_rating,
               CASE 
                   WHEN m.MealID IN (SELECT BreakfastID FROM Breakfast) THEN 'breakfast'
                   WHEN m.MealID IN (SELECT LunchID FROM Lunch) THEN 'lunch'
                   WHEN m.MealID IN (SELECT DinnerID FROM Dinner) THEN 'dinner'
                   WHEN m.MealID IN (SELECT SideID FROM Side) THEN 'side'
                   WHEN m.MealID IN (SELECT DessertID FROM Dessert) THEN 'dessert'
                   ELSE 'meal'
               END as meal_type
        FROM Meal m
        JOIN Images i ON m.ImageID = i.ImageID
        LEFT JOIN Reviews r ON m.MealID = r.MealID
        GROUP BY m.MealID
        HAVING avg_rating IS NOT NULL
        ORDER BY avg_rating DESC
        LIMIT 9
    ");
    $stmt->execute();
    $popularRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $popularRecipes = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Homepage</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .popular-recipes {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .popular-recipe-card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease-in-out;
        }
        .popular-recipe-card:hover {
            transform: translateY(-5px);
        }
        .popular-recipe-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .rating {
            color: #ffc107;
        }
    </style>
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

<header>
    <div class="header-content text-center"></div>
</header>

<main>
    <div class="container">
        <!-- Featured Content Section -->
        <div id="featuredCarousel" class="carousel slide" data-ride="carousel" data-interval="3000">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="images/food1.jpeg" class="d-block w-100" alt="Featured Image 1">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Featured Recipe 1</h5>
                        <p>A Feast of Traditional Indian Delicacies.</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="images/food3.jpeg" class="d-block w-100" alt="Featured Image 2">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>Featured Recipe 2</h5>
                        <p>Gourmet Loaded Sweet Potato Fries.</p>
                    </div>
                </div>
            </div>
            <!-- Carousel controls -->
            <a class="carousel-control-prev" href="#featuredCarousel" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#featuredCarousel" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>
        </div>

        <!-- Most Popular Recipes Section -->
        <div class="popular-recipes">
            <h2 class="text-center mb-4">Most Popular Recipes</h2>
            <div class="row">
                <?php foreach ($popularRecipes as $recipe): ?>
                    <div class="col-md-4">
                        <div class="card popular-recipe-card">
                            <img src="<?php echo htmlspecialchars($recipe['ImagePath']); ?>" class="card-img-top popular-recipe-image" alt="<?php echo htmlspecialchars($recipe['MealName']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($recipe['MealName']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($recipe['MealDescription'], 0, 100)) . '...'; ?></p>
                                <div class="rating">
                                    <?php
                                    $rating = round($recipe['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="ml-2"><?php echo number_format($recipe['avg_rating'], 1); ?></span>
                                </div>
                                <a href="recipe.php?id=<?php echo $recipe['MealID']; ?>&type=<?php echo $recipe['meal_type']; ?>" class="btn btn-primary mt-2">View Recipe</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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