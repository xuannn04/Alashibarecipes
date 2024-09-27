<?php
session_start();
require_once("connection.php");

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['UserID'];
$message = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'personal_info';

// Function to fetch user data
function fetchUserData($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT Username, Email FROM User WHERE UserID = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to handle personal info update
function updatePersonalInfo($pdo, $user_id, $new_username, $new_email) {
    // Check if the new username already exists (excluding the current user)
    $check_username_stmt = $pdo->prepare("SELECT UserID FROM User WHERE Username = ? AND UserID != ?");
    $check_username_stmt->execute([$new_username, $user_id]);
    $existing_username = $check_username_stmt->fetch();

    // Check if the new email already exists (excluding the current user)
    $check_email_stmt = $pdo->prepare("SELECT UserID FROM User WHERE Email = ? AND UserID != ?");
    $check_email_stmt->execute([$new_email, $user_id]);
    $existing_email = $check_email_stmt->fetch();

    if ($existing_username) {
        return "Error: This username is already taken.";
    } elseif ($existing_email) {
        return "Error: This email is already in use.";
    } else {
        $update_stmt = $pdo->prepare("UPDATE User SET Username = ?, Email = ? WHERE UserID = ?");
        if ($update_stmt->execute([$new_username, $new_email, $user_id])) {
            $_SESSION['username'] = $new_username;
            return "Personal info updated successfully!";
        } else {
            return "Error updating personal info.";
        }
    }
}

// Function to handle password change
function changePassword($pdo, $user_id, $current_password, $new_password, $confirm_password) {
    // Verify current password
    $verify_stmt = $pdo->prepare("SELECT Password FROM User WHERE UserID = ?");
    $verify_stmt->execute([$user_id]);
    $user_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($current_password, $user_data['Password'])) {
        if ($new_password === $confirm_password) {
            // Check password length and complexity
            if (strlen($new_password) < 8) {
                return "Error: Password must be at least 8 characters long.";
            } elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/", $new_password)) {
                return "Error: Password must contain at least one letter and one number.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pass_update_stmt = $pdo->prepare("UPDATE User SET Password = ? WHERE UserID = ?");
                if ($pass_update_stmt->execute([$hashed_password, $user_id])) {
                    return "Password updated successfully!";
                } else {
                    return "Error updating password.";
                }
            }
        } else {
            return "New passwords do not match.";
        }
    } else {
        return "Current password is incorrect.";
    }
}

// Function to fetch user's reviews
function fetchUserReviews($pdo, $user_id) {
    $reviews_stmt = $pdo->prepare("
        SELECT r.Rating, r.ReviewText, r.ReviewDate, m.MealName, m.MealID, 
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN 'breakfast'
                   WHEN l.LunchID IS NOT NULL THEN 'lunch'
                   WHEN d.DinnerID IS NOT NULL THEN 'dinner'
                   WHEN s.SideID IS NOT NULL THEN 'side'
                   WHEN ds.DessertID IS NOT NULL THEN 'dessert'
               END AS MealType
        FROM Reviews r
        JOIN Meal m ON r.MealID = m.MealID
        LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID
        LEFT JOIN Lunch l ON m.MealID = l.LunchID
        LEFT JOIN Dinner d ON m.MealID = d.DinnerID
        LEFT JOIN Side s ON m.MealID = s.SideID
        LEFT JOIN Dessert ds ON m.MealID = ds.DessertID
        WHERE r.UserID = ?
        ORDER BY r.ReviewDate DESC
    ");
    $reviews_stmt->execute([$user_id]);
    return $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch saved recipes
function fetchSavedRecipes($pdo, $user_id) {
    $saved_recipes_stmt = $pdo->prepare("
        SELECT m.MealID, m.MealName, i.ImagePath, 
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN 'breakfast'
                   WHEN l.LunchID IS NOT NULL THEN 'lunch'
                   WHEN d.DinnerID IS NOT NULL THEN 'dinner'
                   WHEN s.SideID IS NOT NULL THEN 'side'
                   WHEN ds.DessertID IS NOT NULL THEN 'dessert'
               END AS MealType
        FROM SavedRecipes sr
        JOIN Meal m ON sr.MealID = m.MealID
        JOIN Images i ON m.ImageID = i.ImageID
        LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID
        LEFT JOIN Lunch l ON m.MealID = l.LunchID
        LEFT JOIN Dinner d ON m.MealID = d.DinnerID
        LEFT JOIN Side s ON m.MealID = s.SideID
        LEFT JOIN Dessert ds ON m.MealID = ds.DessertID
        WHERE sr.UserID = ?
        ORDER BY sr.DateSaved DESC
    ");
    $saved_recipes_stmt->execute([$user_id]);
    return $saved_recipes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch personal recipes
function fetchPersonalRecipes($pdo, $user_id) {
    $personal_recipes_stmt = $pdo->prepare("
        SELECT m.MealID, m.MealName, m.MealDescription, i.ImagePath, 
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN 'breakfast'
                   WHEN l.LunchID IS NOT NULL THEN 'lunch'
                   WHEN d.DinnerID IS NOT NULL THEN 'dinner'
                   WHEN s.SideID IS NOT NULL THEN 'side'
                   WHEN ds.DessertID IS NOT NULL THEN 'dessert'
               END AS MealType,
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN b.Status
                   WHEN l.LunchID IS NOT NULL THEN l.Status
                   WHEN d.DinnerID IS NOT NULL THEN d.Status
                   WHEN s.SideID IS NOT NULL THEN s.Status
                   WHEN ds.DessertID IS NOT NULL THEN ds.Status
               END AS Status
        FROM Meal m
        JOIN Images i ON m.ImageID = i.ImageID
        LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID
        LEFT JOIN Lunch l ON m.MealID = l.LunchID
        LEFT JOIN Dinner d ON m.MealID = d.DinnerID
        LEFT JOIN Side s ON m.MealID = s.SideID
        LEFT JOIN Dessert ds ON m.MealID = ds.DessertID
        WHERE (
            (b.BreakfastID IS NOT NULL AND b.UserID = :user_id) OR
            (l.LunchID IS NOT NULL AND l.UserID = :user_id) OR
            (d.DinnerID IS NOT NULL AND d.UserID = :user_id) OR
            (s.SideID IS NOT NULL AND s.UserID = :user_id) OR
            (ds.DessertID IS NOT NULL AND ds.UserID = :user_id)
        )
        AND m.MealID IN (
            SELECT DISTINCT MealID FROM MealCategory WHERE CuisineID IN (
                SELECT CuisineID FROM Cuisine
            )
        )
        ORDER BY m.MealID DESC
    ");
    $personal_recipes_stmt->execute(['user_id' => $user_id]);
    return $personal_recipes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch rejection reasons
function fetchRejectionReasons($pdo, $user_id) {
    $rejection_reasons_stmt = $pdo->prepare("
        SELECT rr.Reason, rr.CreatedAt, m.MealName, m.MealID,
               CASE
                   WHEN b.BreakfastID IS NOT NULL THEN 'breakfast'
                   WHEN l.LunchID IS NOT NULL THEN 'lunch'
                   WHEN d.DinnerID IS NOT NULL THEN 'dinner'
                   WHEN s.SideID IS NOT NULL THEN 'side'
                   WHEN ds.DessertID IS NOT NULL THEN 'dessert'
               END AS MealType
        FROM RejectionReasons rr
        JOIN Meal m ON rr.MealID = m.MealID
        LEFT JOIN Breakfast b ON m.MealID = b.BreakfastID
        LEFT JOIN Lunch l ON m.MealID = l.LunchID
        LEFT JOIN Dinner d ON m.MealID = d.DinnerID
        LEFT JOIN Side s ON m.MealID = s.SideID
        LEFT JOIN Dessert ds ON m.MealID = ds.DessertID
        WHERE rr.UserID = ?
        ORDER BY rr.CreatedAt DESC
    ");
    $rejection_reasons_stmt->execute([$user_id]);
    return $rejection_reasons_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_info'])) {
        $message = updatePersonalInfo($pdo, $user_id, $_POST['username'], $_POST['email']);
        $activeTab = 'personal_info';
    } elseif (isset($_POST['change_password'])) {
        $message = changePassword($pdo, $user_id, $_POST['current_password'], $_POST['new_password'], $_POST['confirm_password']);
        $activeTab = 'change_password';
    }
}

// Fetch user data and other necessary information
$user = fetchUserData($pdo, $user_id);
$user_reviews = fetchUserReviews($pdo, $user_id);
$saved_recipes = fetchSavedRecipes($pdo, $user_id);
$personal_recipes = fetchPersonalRecipes($pdo, $user_id);
$rejection_reasons = fetchRejectionReasons($pdo, $user_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - User Profile</title>
    <link href="stylesindex.css" rel="stylesheet" type="text/css">
    <link href="stylesuserprofile.css" rel="stylesheet" type="text/css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<style>
    #footer {
    background-color: #333;
    color: #fff;
    padding: 1rem 0;
    margin-top: 3rem;
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

    <div class="container mt-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="bg-white rounded shadow-sm">
                    <div class="sidebar-item <?php echo $activeTab == 'personal_info' ? 'active' : ''; ?>" onclick="changeTab('personal_info')">
                        <i class="fas fa-user-circle mr-2"></i>
                        Personal Info
                    </div>
                    <div class="sidebar-item <?php echo $activeTab == 'change_password' ? 'active' : ''; ?>" onclick="changeTab('change_password')">
                        <i class="fa fa-key mr-2"></i>
                        Change Password
                    </div>
                    <div class="sidebar-item <?php echo $activeTab == 'saved_recipes' ? 'active' : ''; ?>" onclick="changeTab('saved_recipes')">
                        <i class="fas fa-bookmark mr-2"></i>
                        Saved Recipes 
                    </div>
                    <div class="sidebar-item <?php echo $activeTab == 'personal_recipes' ? 'active' : ''; ?>" onclick="changeTab('personal_recipes')">
                        <i class="fas fa-utensils mr-2"></i>
                        My Personal Recipes
                    </div>
                    <div class="sidebar-item <?php echo $activeTab == 'reviews' ? 'active' : ''; ?>" onclick="changeTab('reviews')">
                        <i class="fas fa-star mr-2"></i>
                        My Reviews
                    </div>
                    <div class="sidebar-item <?php echo $activeTab == 'inbox' ? 'active' : ''; ?>" onclick="changeTab('inbox')">
                        <i class="fas fa-inbox mr-2"></i>
                        Inbox
                    </div>
                </div>
            </div>
            <!-- Main content -->
            <div class="col-md-9">
                <div class="bg-white rounded shadow-sm p-4">
                    <?php if (!empty($message)): ?>
                        <?php
                        $messageClass = strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success';
                        ?>
                        <div class="alert <?php echo $messageClass; ?>"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <?php if ($activeTab == 'personal_info'): ?>
                        <h1 class="text-2xl font-bold mb-4">Personal Info</h1>
                        <p class="mb-4">Change your username and e-mail.</p>
                        <p class="mb-4 text-sm text-gray-600">
                            <i class="fas fa-lock mr-2"></i>
                            Only you can see the information on this page. It will not be displayed for other users to see.
                        </p>

                        <div class="mt-6">
                            <h2 class="text-xl font-bold mb-4">My Basic Info</h2>
                            <form method="post" action="">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['Username']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                                </div>
                                <div class="text-right mt-4">
                                    <button type="submit" name="update_info" class="btn btn-primary">SAVE CHANGES</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($activeTab == 'change_password'): ?>
                        <h1 class="text-2xl font-bold mb-4">Change Password</h1>
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Password must be at least 8 characters long and contain at least one letter and one number.</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-4">
                                <button type="submit" name="change_password" class="btn btn-primary">CHANGE PASSWORD</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($activeTab == 'reviews'): ?>
                        <h1 class="text-2xl font-bold mb-4">My Reviews</h1>
                        <?php if (empty($user_reviews)): ?>
                            <p>You haven't written any reviews yet.</p>
                        <?php else: ?>
                            <?php foreach ($user_reviews as $review): ?>
                                <div class="review mb-4 p-4 border rounded">
                                    <h3 class="font-bold">
                                        <a href="recipe.php?id=<?php echo $review['MealID']; ?>&type=<?php echo $review['MealType']; ?>">
                                            <?php echo htmlspecialchars($review['MealName']); ?>
                                        </a>
                                    </h3>
                                    <div class="stars">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $review['Rating']) {
                                                echo '<i class="fas fa-star text-yellow-400"></i>';
                                            } else {
                                                echo '<i class="far fa-star text-yellow-400"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <p><?php echo htmlspecialchars($review['ReviewText']); ?></p>
                                    <p class="text-sm text-gray-500">Reviewed on <?php echo date('F j, Y', strtotime($review['ReviewDate'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($activeTab == 'saved_recipes'): ?>
                        <h1 class="text-2xl font-bold mb-4">Saved Recipes</h1>
                        <?php if (empty($saved_recipes)): ?>
                            <p>You haven't saved any recipes yet.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($saved_recipes as $recipe): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card">
                                            <img src="<?php echo htmlspecialchars($recipe['ImagePath']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($recipe['MealName']); ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($recipe['MealName']); ?></h5>
                                                <a href="recipe.php?id=<?php echo $recipe['MealID']; ?>&type=<?php echo $recipe['MealType']; ?>" class="btn btn-primary">View Recipe</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($activeTab == 'personal_recipes'): ?>
                        <h1 class="text-2xl font-bold mb-4">My Personal Recipes</h1>
                        <?php if (empty($personal_recipes)): ?>
                            <p>You haven't posted any recipes yet.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($personal_recipes as $recipe): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card">
                                            <img src="<?php echo htmlspecialchars($recipe['ImagePath']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($recipe['MealName']); ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($recipe['MealName']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars(substr($recipe['MealDescription'], 0, 100)); ?></p>
                                                <p class="card-text"><small class="text-muted">Status: <?php echo ucfirst($recipe['Status']); ?></small></p>
                                            </div>
                                            <div class="card-footer">
                                                 <a href="recipe.php?id=<?php echo $recipe['MealID']; ?>&type=<?php echo $recipe['MealType']; ?>" class="btn btn-primary">View Recipe</a>
                                                 <?php if ($recipe['Status'] == 'pending'): ?>
                                                    <button class="btn btn-secondary" disabled>Pending Approval</button>
                                                 <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?> 

                    <?php if ($activeTab == 'inbox'): ?>
                        <h1 class="text-2xl font-bold mb-4">Inbox</h1>
                        <?php if (empty($rejection_reasons)): ?>
                            <p>You have no messages.</p>
                        <?php else: ?>
                            <?php foreach ($rejection_reasons as $reason): ?>
                                <div class="mb-4 p-4 border rounded">
                                    <h3 class="font-bold">Recipe Rejected: <?php echo htmlspecialchars($reason['MealName']); ?></h3>
                                    <p><strong>Reason:</strong> <?php echo htmlspecialchars($reason['Reason']); ?></p>
                                    <p class="text-sm text-gray-500">Received on <?php echo date('F j, Y', strtotime($reason['CreatedAt'])); ?></p>
                                    <a href="recipe.php?id=<?php echo $reason['MealID']; ?>&type=<?php echo $reason['MealType']; ?>" class="btn btn-primary btn-sm mt-2">View Recipe</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
    <footer class="bg-gray-800 text-white mt-12 py-4">
        <div class="container mx-auto text-center">
            <p>&copy; 2024 ALASHIBARECIPES. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="indexjs.js"></script>

    <script>
        function changeTab(tab) {
            window.location.href = 'userprofile.php?tab=' + tab;
        }

        $(document).ready(function() {
            $('.toggle-password').click(function() {
                var target = $(this).data('target');
                var input = $('#' + target);
                var icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>
</body>
</html>