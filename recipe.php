<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'Assignment';
$username = 'root';
$password = '';

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['UserID'])) {
    handleSaveUnsave($pdo);
    exit;
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
// Get the recipe ID and type from the URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Prepare the SQL query based on the recipe type
switch ($type) {
    case 'breakfast':
        $table = 'Breakfast';
        $idColumn = 'BreakfastID';
        $nameColumn = 'BreakfastName';
        $descColumn = 'BreakfastDescription';
        break;
    case 'lunch':
        $table = 'Lunch';
        $idColumn = 'LunchID';
        $nameColumn = 'LunchName';
        $descColumn = 'LunchDescription';
        break;
    case 'dinner':
        $table = 'Dinner';
        $idColumn = 'DinnerID';
        $nameColumn = 'DinnerName';
        $descColumn = 'DinnerDescription';
        break;
    case 'side':
        $table = 'Side';
        $idColumn = 'SideID';
        $nameColumn = 'SideName';
        $descColumn = 'SideDescription';
        break;
    case 'dessert':
        $table = 'Dessert';
        $idColumn = 'DessertID';
        $nameColumn = 'DessertName';
        $descColumn = 'DessertDescription';
        break;
    default:
        die("Invalid recipe type");
}

// Fetch the recipe details
$stmt = $pdo->prepare("
    SELECT $table.$nameColumn, $table.$descColumn, $table.PrepTime, $table.CookTime, $table.Instructions, Images.ImagePath, User.Username as SubmittedBy, Meal.MealID
    FROM $table 
    JOIN Meal ON $table.$idColumn = Meal.MealID
    JOIN Images ON $table.ImageID = Images.ImageID 
    LEFT JOIN User ON $table.UserID = User.UserID
    WHERE $table.$idColumn = :id
");
$stmt->execute(['id' => $id]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) {
    die("Recipe not found");
}

// Fetch ingredients for the recipe
$stmt = $pdo->prepare("
    SELECT i.IngredientName
    FROM Ingredients i
    JOIN MealIngredients mi ON i.IngredientID = mi.IngredientID
    WHERE mi.MealID = :id
    UNION ALL
    SELECT ci.IngredientName
    FROM CustomIngredients ci
    WHERE ci.MealID = :id
");
$stmt->execute(['id' => $id]);
$ingredients = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    $rating = intval($_POST['rating']);
    $review = $_POST['review'];
    $user_id = $_SESSION['UserID']; // Make sure the user is logged in

    $stmt = $pdo->prepare("INSERT INTO Reviews (UserID, MealID, Rating, ReviewText, ReviewDate) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $id, $rating, $review]);

    // Redirect to prevent form resubmission
    header("Location: recipe.php?id=$id&type=$type&rated=1");
    exit();
}

// Fetch existing ratings
$stmt = $pdo->prepare("SELECT AVG(Rating) as avg_rating, COUNT(*) as rating_count FROM Reviews WHERE MealID = ?");
$stmt->execute([$id]);
$rating_data = $stmt->fetch(PDO::FETCH_ASSOC);

$avg_rating = $rating_data['avg_rating'] !== null ? number_format($rating_data['avg_rating'], 1) : 'N/A';
$rating_count = $rating_data['rating_count'];

$is_saved = false;
if (isset($_SESSION['UserID'])) {
    $stmt = $pdo->prepare("SELECT * FROM SavedRecipes WHERE UserID = ? AND MealID = ?");
    $stmt->execute([$_SESSION['UserID'], $id]);
    $is_saved = $stmt->fetch() !== false;
}
error_log("Recipe page loaded. GET parameters: " . print_r($_GET, true));

// Handle save/unsave action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['UserID'])) {
    error_log("Save/Unsave action triggered. POST data: " . print_r($_POST, true));
    error_log("User ID: " . $_SESSION['UserID']);
    
    $response = ['success' => false, 'message' => ''];
    $mealID = isset($_POST['mealID']) ? intval($_POST['mealID']) : 0;

    error_log("Meal ID: " . $mealID);

    if ($mealID === 0) {
        $response['message'] = 'Invalid meal ID';
    } else {
        try {
            if ($_POST['action'] === 'save') {
                $stmt = $pdo->prepare("INSERT IGNORE INTO SavedRecipes (UserID, MealID) VALUES (?, ?)");
                $stmt->execute([$_SESSION['UserID'], $mealID]);
                $response['success'] = true;
                $response['message'] = 'Recipe saved successfully';
                error_log("Save query executed. Affected rows: " . $stmt->rowCount());
            } elseif ($_POST['action'] === 'unsave') {
                $stmt = $pdo->prepare("DELETE FROM SavedRecipes WHERE UserID = ? AND MealID = ?");
                $stmt->execute([$_SESSION['UserID'], $mealID]);
                $response['success'] = true;
                $response['message'] = 'Recipe unsaved successfully';
                error_log("Unsave query executed. Affected rows: " . $stmt->rowCount());
            } else {
                $response['message'] = 'Invalid action';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
    
    error_log("Response: " . print_r($response, true));
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
function handleSaveUnsave($pdo) {
    error_log("Save/Unsave action triggered. POST data: " . print_r($_POST, true));
    error_log("User ID: " . $_SESSION['UserID']);
    
    $response = ['success' => false, 'message' => ''];
    $mealID = isset($_POST['mealID']) ? intval($_POST['mealID']) : 0;

    error_log("Meal ID: " . $mealID);

    if ($mealID === 0) {
        $response['message'] = 'Invalid meal ID';
    } else {
        try {
            if ($_POST['action'] === 'save') {
                $stmt = $pdo->prepare("INSERT IGNORE INTO SavedRecipes (UserID, MealID) VALUES (?, ?)");
                $stmt->execute([$_SESSION['UserID'], $mealID]);
                $response['success'] = true;
                $response['message'] = 'Recipe saved successfully';
                error_log("Save query executed. Affected rows: " . $stmt->rowCount());
            } elseif ($_POST['action'] === 'unsave') {
                $stmt = $pdo->prepare("DELETE FROM SavedRecipes WHERE UserID = ? AND MealID = ?");
                $stmt->execute([$_SESSION['UserID'], $mealID]);
                $response['success'] = true;
                $response['message'] = 'Recipe unsaved successfully';
                error_log("Unsave query executed. Affected rows: " . $stmt->rowCount());
            } else {
                $response['message'] = 'Invalid action';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error: ' . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
    
    error_log("Response: " . print_r($response, true));
    header('Content-Type: application/json');
    echo json_encode($response);
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe[$nameColumn]); ?> - Alashibarecipes - Recipe</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="stylesbreakfast.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .rating {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .stars {
            color: #f8ce0b;
            margin-right: 10px;
        }
        .reviews, .photos {
            color: #0066cc;
            text-decoration: none;
            margin-right: 15px;
        }
        .meta {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 20px;
        }
        .actions {
            margin-bottom: 20px;
        }
        .actions button {
            margin-right: 10px;
            padding: 5px 10px;
            cursor: pointer;
        }
        .save-btn {
            background-color: #d32f2f;
            color: white;
            border: none;
        }
        .main-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .recipe-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            background-color: #fff3e0;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .recipe-info div {
            display: flex;
            flex-direction: column;
        }
        .recipe-info h3 {
            margin: 0;
            font-size: 1em;
        }
        .recipe-info p {
            margin: 5px 0 0;
        }
        .nutrition-link {
            color: #0066cc;
            text-decoration: none;
            display: block;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 1.8em;
            margin-top: 30px;
        }
        ul, ol {
            padding-left: 20px;
        }
        li {
            margin-bottom: 10px;
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
     

<main class="container mt-5">
    <h1><?php echo htmlspecialchars($recipe[$nameColumn]); ?></h1>
    <div class="rating">
        <div class="stars">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $avg_rating) {
                    echo '<i class="fas fa-star"></i>';
                } elseif ($i - 0.5 <= $avg_rating) {
                    echo '<i class="fas fa-star-half-alt"></i>';
                } else {
                    echo '<i class="far fa-star"></i>';
                }
            }
            ?>
        </div>
        <span><?php echo $avg_rating !== 'N/A' ? "$avg_rating stars" : 'No ratings yet'; ?> (<?php echo $rating_count; ?> reviews)</span>
    </div>
        <p><?php echo htmlspecialchars($recipe[$descColumn]); ?></p>
        <div class="meta">
            Submitted by <?php echo htmlspecialchars($recipe['SubmittedBy'] ?? 'Unknown User'); ?> | Updated on <?php echo date('F j, Y'); ?>
        </div>
    <div class="actions">
    <button id="saveBtn" class="save-btn" onclick="toggleSave()" <?php echo isset($_SESSION['UserID']) ? '' : 'disabled'; ?>>
    <?php echo $is_saved ? 'Unsave' : 'Save'; ?>
</button>

    <button onclick="scrollToReviews()">Rate</button>
</div>
    <img src="<?php echo htmlspecialchars($recipe['ImagePath']); ?>" alt="<?php echo htmlspecialchars($recipe[$nameColumn]); ?>" class="main-image">
    <div class="recipe-info">
        <div>
            <h3>Prep Time:</h3>
            <p><?php echo $recipe['PrepTime']; ?> mins</p>
        </div>
        <div>
            <h3>Cook Time:</h3>
            <p><?php echo $recipe['CookTime']; ?> mins</p>
        </div>
        <div>
            <h3>Total Time:</h3>
            <p><?php echo $recipe['PrepTime'] + $recipe['CookTime']; ?> mins</p>
        </div>
    </div>
    <h2>Ingredients</h2>
    <ul>
        <?php foreach ($ingredients as $ingredient): ?>
            <li><?php echo htmlspecialchars(trim($ingredient)); ?></li>
        <?php endforeach; ?>
    </ul>
    <h2>Instructions</h2>
    <ol>
        <?php 
        $instructions = explode("\n", $recipe['Instructions']);
        foreach ($instructions as $step) {
            if (!empty(trim($step))) {
                echo "<li>" . htmlspecialchars($step) . "</li>";
            }
        }
        ?>
    </ol>
    <h2 id="reviews">Reviews</h2>
    <?php if (isset($_SESSION['UserID'])): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="rating">Your Rating:</label>
                <select name="rating" id="rating" class="form-control" required>
                    <option value="">Select a rating</option>
                    <option value="5">5 Stars</option>
                    <option value="4">4 Stars</option>
                    <option value="3">3 Stars</option>
                    <option value="2">2 Stars</option>
                    <option value="1">1 Star</option>
                </select>
            </div>
            <div class="form-group">
                <label for="review">Your Review:</label>
                <textarea name="review" id="review" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    <?php else: ?>
        <p>Please <a href="login.php">log in</a> to leave a review.</p>
    <?php endif; ?>

    <?php
    // Fetch and display existing reviews
    $stmt = $pdo->prepare("SELECT r.Rating, r.ReviewText, r.ReviewDate, u.Username 
                           FROM Reviews r 
                           JOIN User u ON r.UserID = u.UserID 
                           WHERE r.MealID = ? 
                           ORDER BY r.ReviewDate DESC");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reviews as $review):
    ?>
        <div class="review">
            <div class="stars">
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $review['Rating']) {
                        echo '<i class="fas fa-star"></i>';
                    } else {
                        echo '<i class="far fa-star"></i>';
                    }
                }
                ?>
            </div>
            <p><?php echo htmlspecialchars($review['ReviewText']); ?></p>
            <p class="text-muted">By <?php echo htmlspecialchars($review['Username']); ?> on <?php echo date('F j, Y', strtotime($review['ReviewDate'])); ?></p>
        </div>
    <?php endforeach; ?>
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
    <script>
function scrollToReviews() {
    const reviewsSection = document.getElementById('reviews');
    if (reviewsSection) {
        reviewsSection.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>
<script>
function toggleSave() {
    const saveBtn = document.getElementById('saveBtn');
    const action = saveBtn.textContent.trim().toLowerCase() === 'save' ? 'save' : 'unsave';
    const mealId = <?php echo json_encode($recipe['MealID']); ?>; // Make sure to use the correct MealID

    console.log('Toggling save. Action:', action, 'Meal ID:', mealId);

    fetch('recipe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&mealID=${mealId}`,
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('Raw response:', data);
        try {
            const jsonData = JSON.parse(data);
            console.log('Parsed JSON:', jsonData);
            if (jsonData.success) {
                saveBtn.textContent = action === 'save' ? 'Unsave' : 'Save';
                alert(jsonData.message);
            } else {
                alert('Error: ' + (jsonData.message || 'Unknown error occurred'));
            }
        } catch (error) {
            console.error('JSON parsing error:', error);
            alert('Error processing the response. Please try again.');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>
</body>
</html>