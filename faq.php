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
    <title>Alashibarecipes - FAQ</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .faq-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .faq-item {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 20px;
        }
        .faq-question {
            font-weight: bold;
            color: #007bff;
            cursor: pointer;
        }
        .faq-answer {
            display: none;
            margin-top: 10px;
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

<main>
    <div class="container">
        <div class="faq-section">
            <h1 class="text-center mb-4">Frequently Asked Questions</h1>
            
            <div class="faq-item">
                <div class="faq-question">How do I create an account?</div>
                <div class="faq-answer">To create an account, click on the "My Account" dropdown in the top right corner and select "Sign Up". Fill in the required information and follow the prompts to complete your registration.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How can I submit my own recipe?</div>
                <div class="faq-answer">Once you're logged in, go to "My Account" and select "Add a Recipe". Fill out the recipe form with all the necessary details, including ingredients, instructions, and a photo if possible.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Can I save my favorite recipes?</div>
                <div class="faq-answer">Yes! When viewing a recipe, look for the "Save" or "Favorite" button. Click it to add the recipe to your saved collection, which you can access from your user profile.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How do I rate a recipe?</div>
                <div class="faq-answer">On each recipe page, you'll find a rating section. Simply click on the number of stars you'd like to give the recipe. You must be logged in to rate recipes.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How can I contact customer support?</div>
                <div class="faq-answer">You can reach our customer support team by emailing support@alashibarecipes.com</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Are the recipes on Alashibarecipes tested?</div>
                <div class="faq-answer">Yes, all recipes submitted by our team are thoroughly tested before being published. User-submitted recipes are reviewed for completeness and clarity, but we encourage users to provide feedback and ratings to help others.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">How often are new recipes added?</div>
                <div class="faq-answer">We add new recipes to our collection several times a week. Be sure to check our homepage or follow us on social media to stay updated on the latest additions.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Is there a mobile app for Alashibarecipes?</div>
                <div class="faq-answer">Currently, we don't have a mobile app, but our website is fully responsive and works great on mobile devices. We're considering developing an app in the future, so stay tuned!</div>
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

<script>
$(document).ready(function() {
    $('.faq-question').click(function() {
        $(this).next('.faq-answer').slideToggle();
    });
});
</script>

</body>
</html>