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
    die("Error connecting to the database: " . $e->getMessage());
}

// Initialize variables
$instructions = isset($_POST['instructions']) ? $_POST['instructions'] : '';
$recipeName = isset($_POST['recipe-name']) ? $_POST['recipe-name'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$prepTime = isset($_POST['prep-time']) ? $_POST['prep-time'] : '';
$cookTime = isset($_POST['cook-time']) ? $_POST['cook-time'] : '';
$ingredients = isset($_POST['ingredients']) ? $_POST['ingredients'] : '';
$mealType = isset($_POST['meal-type']) ? $_POST['meal-type'] : '';
$cuisineType = isset($_POST['cuisine-type']) ? $_POST['cuisine-type'] : '';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    if (empty($recipeName) || empty($description) || empty($prepTime) || empty($cookTime) || empty($instructions) || empty($mealType) || empty($cuisineType) || empty($ingredients)) {
        $message = "Please fill in all fields.";
    } else {
        // Process ingredients
        $ingredientList = explode("\n", trim($ingredients));
        $ingredientList = array_map('trim', $ingredientList);
        $ingredientList = array_filter($ingredientList); // Remove empty lines

        // Fetch all ingredients from the database
        $stmt = $pdo->query("SELECT IngredientID, IngredientName FROM Ingredients");
        $dbIngredients = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $matchedIngredients = [];
        $unmatchedIngredients = [];

        foreach ($ingredientList as $ingredient) {
            $found = false;
            foreach ($dbIngredients as $id => $name) {
                if (stripos($ingredient, $name) !== false) {
                    $matchedIngredients[] = $id;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $unmatchedIngredients[] = $ingredient;
            }
        }

        // Insert into the Images table
        $imageID = null;
        if (isset($_FILES['recipe-image']) && $_FILES['recipe-image']['error'] == 0) {
            $imageFileName = $_FILES['recipe-image']['name'];
            $imageTempPath = $_FILES['recipe-image']['tmp_name'];
            $imageUploadPath = "uploads/" . basename($imageFileName);

            if (move_uploaded_file($imageTempPath, $imageUploadPath)) {
                $stmt = $pdo->prepare("INSERT INTO Images (ImagePath) VALUES (:ImagePath)");
                $stmt->execute(['ImagePath' => $imageUploadPath]);
                $imageID = $pdo->lastInsertId();
            } else {
                $message = "Failed to upload recipe image.";
            }
        }

        if (empty($message)) {
            try {
                $pdo->beginTransaction();

                // Insert into the Meal table
                $stmt = $pdo->prepare("INSERT INTO Meal (MealName, MealDescription, ImageID) VALUES (:MealName, :MealDescription, :ImageID)");
                $stmt->execute([
                    'MealName' => $recipeName,
                    'MealDescription' => $description,
                    'ImageID' => $imageID
                ]);
                $mealID = $pdo->lastInsertId();

                // Insert matched ingredients
                if (!empty($matchedIngredients)) {
                    $stmt = $pdo->prepare("INSERT INTO MealIngredients (MealID, IngredientID) VALUES (:MealID, :IngredientID)");
                    foreach ($matchedIngredients as $ingredientID) {
                        $stmt->execute([
                            'MealID' => $mealID,
                            'IngredientID' => $ingredientID
                        ]);
                    }
                }
                if (!empty($unmatchedIngredients)) {
                    $stmt = $pdo->prepare("INSERT INTO CustomIngredients (MealID, IngredientName) VALUES (:MealID, :IngredientName)");
                    foreach ($unmatchedIngredients as $ingredient) {
                        $stmt->execute([
                            'MealID' => $mealID,
                            'IngredientName' => $ingredient
                        ]);
                    }
                }
                // Insert into the respective meal table
                $mealTable = '';
                $mealIDCol = '';
                $mealNameCol = '';
                $mealDescCol = '';

                switch ($mealType) {
                    case 'Breakfast':
                        $mealTable = 'Breakfast';
                        $mealIDCol = 'BreakfastID';
                        $mealNameCol = 'BreakfastName';
                        $mealDescCol = 'BreakfastDescription';
                        break;
                    case 'Lunch':
                        $mealTable = 'Lunch';
                        $mealIDCol = 'LunchID';
                        $mealNameCol = 'LunchName';
                        $mealDescCol = 'LunchDescription';
                        break;
                    case 'Dinner':
                        $mealTable = 'Dinner';
                        $mealIDCol = 'DinnerID';
                        $mealNameCol = 'DinnerName';
                        $mealDescCol = 'DinnerDescription';
                        break;
                    case 'Side':
                        $mealTable = 'Side';
                        $mealIDCol = 'SideID';
                        $mealNameCol = 'SideName';
                        $mealDescCol = 'SideDescription';
                        break;
                    case 'Dessert':
                        $mealTable = 'Dessert';
                        $mealIDCol = 'DessertID';
                        $mealNameCol = 'DessertName';
                        $mealDescCol = 'DessertDescription';
                        break;
                }

                if ($mealTable) {
                    $stmt = $pdo->prepare("INSERT INTO $mealTable ($mealIDCol, $mealNameCol, $mealDescCol, ImageID, PrepTime, CookTime, Instructions, UserID) 
                                       VALUES (:MealID, :MealName, :MealDescription, :ImageID, :PrepTime, :CookTime, :Instructions, :UserID)");
                $stmt->execute([
                    'MealID' => $mealID,
                    'MealName' => $recipeName,
                    'MealDescription' => $description,
                    'ImageID' => $imageID,
                    'PrepTime' => $prepTime,
                    'CookTime' => $cookTime,
                    'Instructions' => $instructions,
                    'UserID' => $_SESSION['UserID'] 
                ]);

                    // Insert into MealCategory table
                    $stmt = $pdo->prepare("INSERT INTO MealCategory (MealID, CuisineID) VALUES (:MealID, :CuisineID)");
                    $stmt->execute([
                        'MealID' => $mealID,
                        'CuisineID' => $cuisineType
                    ]);

                    $pdo->commit();
                    $message = "Recipe submitted successfully!";
                    if (!empty($unmatchedIngredients)) {
                        $message .= " However, the following ingredients were not found in our database: " . implode(", ", $unmatchedIngredients);
                    }
                } else {
                    $pdo->rollBack();
                    $message = "Invalid meal type.";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Error submitting recipe: " . $e->getMessage();
            }
        }
    }
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
    <title>Alashibarecipes - Add a Recipe</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

<div class="bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <h1 class="text-4xl font-bold mb-8 flex items-center">
            <span class="text-red-600 text-5xl mr-2">+</span>
            Add a Recipe
        </h1>
        <p class="mb-8 text-gray-600">
            Uploading personal recipes is easy! Add yours to your favorites, share with friends, family, or the Allrecipes community.
        </p>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="recipe-name" class="block text-sm font-medium text-gray-700 mb-2">Recipe Title</label>
                    <input type="text" id="recipe-name" name="recipe-name" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary" placeholder="Give your recipe a title">
                </div>
                <div>
                    <label for="recipe-image" class="block text-sm font-medium text-gray-700 mb-2">Photo (required)</label>
                    <input type="file" id="recipe-image" name="recipe-image" accept="image/*" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-sm text-gray-500">Use JPEG or PNG. Must be at least 960 x 960. Max file size: 30MB</p>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea id="description" name="description" rows="4" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary" placeholder="Share the story behind your recipe and what makes it special."></textarea>
            </div>

            <div>
                <label for="ingredients" class="block text-sm font-medium text-gray-700 mb-2">Ingredients</label>
                <textarea id="ingredients" name="ingredients" rows="5" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary" placeholder="Enter each ingredient on a new line, e.g.:
2 cups all-purpose flour
1 tsp baking powder
1/2 tsp salt"></textarea>
            </div>

            <div>
                <label for="instructions" class="block text-sm font-medium text-gray-700 mb-2">Instructions</label>
                <textarea id="instructions" name="instructions" rows="5" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary" placeholder="Enter the recipe instructions step by step. Each step should be on a new line."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="prep-time" class="block text-sm font-medium text-gray-700 mb-2">Preparation Time (minutes)</label>
                    <input type="number" id="prep-time" name="prep-time" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="cook-time" class="block text-sm font-medium text-gray-700 mb-2">Cooking Time (minutes)</label>
                    <input type="number" id="cook-time" name="cook-time" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Meal Type</label>
                    <select id="meal-type" name="meal-type" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="Breakfast">Breakfast</option>
                        <option value="Lunch">Lunch</option>
                        <option value="Dinner">Dinner</option>
                        <option value="Side">Side Dish</option>
                        <option value="Dessert">Dessert</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cuisine Type</label>
                    <select id="cuisine-type" name="cuisine-type" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="1">Chinese</option>
                        <option value="2">Japanese</option>
                        <option value="3">American</option>
                        <option value="4">Korean</option>
                        <option value="5">French</option>
                    </select>
                </div>
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Recipe
                </button>
            </div>
        </form>
    </div>
</div>

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
    <?php if (!empty($message)): ?>
    Swal.fire({
        title: 'Recipe Submission',
        text: <?php echo json_encode($message); ?>,
        icon: <?php echo strpos($message, "submitted successfully") !== false ? "'success'" : "'warning'"; ?>,
        confirmButtonText: 'OK'
    });
    <?php endif; ?>
</script>
</body>
</html>