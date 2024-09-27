<?php
session_start();
require_once 'connection.php';
define('BASE_URL', 'http://localhost/assignment/');

if (!isset($_SESSION['AdminID'])) {
    header("Location: admin_login.php");
    exit();
}

$message = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

$customIngredients = [];
$allIngredients = [];

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['recipe_id']) && isset($_POST['table'])) {
        $recipe_id = $_POST['recipe_id'];
        $action = $_POST['action'];
        $table = $_POST['table'];

        try {
            $pdo->beginTransaction();

            if ($action == 'approve') {
                $stmt = $pdo->prepare("UPDATE $table SET Status = 'approved' WHERE {$table}ID = ?");
                $stmt->execute([$recipe_id]);
                $message = "Recipe approved successfully.";
            } elseif ($action == 'reject') {
                $rejection_reason = $_POST['rejection_reason'] ?? '';
                $stmt = $pdo->prepare("UPDATE $table SET Status = 'rejected' WHERE {$table}ID = ?");
                $stmt->execute([$recipe_id]);
                
                $user_stmt = $pdo->prepare("SELECT UserID FROM $table WHERE {$table}ID = ?");
                $user_stmt->execute([$recipe_id]);
                $user_id = $user_stmt->fetchColumn();
                
                $admin_id = $_SESSION['AdminID'];
                $insert_reason_stmt = $pdo->prepare("INSERT INTO RejectionReasons (MealID, UserID, Reason, AdminID) VALUES (?, ?, ?, ?)");
                $insert_reason_stmt->execute([$recipe_id, $user_id, $rejection_reason, $admin_id]);
                
                $message = "Recipe rejected successfully.";
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error updating recipe status: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_recipe'])) {
        $table = ucfirst($type);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE $table SET 
                {$table}Name = ?, 
                {$table}Description = ?, 
                PrepTime = ?, 
                CookTime = ?, 
                Instructions = ?
                WHERE {$table}ID = ?");
            $stmt->execute([
                $_POST['recipe_name'],
                $_POST['description'],
                $_POST['prep_time'],
                $_POST['cook_time'],
                $_POST['instructions'],
                $id
            ]);
            
            // Handle ingredients
            $stmt = $pdo->prepare("DELETE FROM CustomIngredients WHERE MealID = ?");
            $stmt->execute([$id]);
            
            $ingredients = explode("\n", $_POST['ingredients']);
            $stmt = $pdo->prepare("INSERT INTO CustomIngredients (MealID, IngredientName) VALUES (?, ?)");
            foreach ($ingredients as $ingredient) {
                $ingredient = trim($ingredient);
                if (!empty($ingredient)) {
                    $stmt->execute([$id, $ingredient]);
                }
            }
            
            // Handle image upload
            if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['recipe_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $new_filename = uniqid() . "." . $ext;
                    $upload_path = "uploads/" . $new_filename;
                    if (move_uploaded_file($_FILES['recipe_image']['tmp_name'], $upload_path)) {
                        $image_stmt = $pdo->prepare("UPDATE $table SET ImagePath = ? WHERE {$table}ID = ?");
                        $image_stmt->execute([$upload_path, $id]);
                    }
                }
            }
            
            $pdo->commit();
            $message = "Recipe updated successfully.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error updating recipe: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_review'])) {
        $review_id = $_POST['review_id'];
        $stmt = $pdo->prepare("DELETE FROM Reviews WHERE ReviewID = ?");
        try {
            $stmt->execute([$review_id]);
            $message = "Review deleted successfully.";
        } catch (PDOException $e) {
            $message = "Error deleting review: " . $e->getMessage();
        }
    }
}

// Fetch recipe details
$table = ucfirst($type);
$stmt = $pdo->prepare("
    SELECT $table.*, images.ImagePath 
    FROM $table 
    LEFT JOIN images ON $table.ImageID = images.ImageID 
    WHERE {$table}ID = ?
");
$stmt->execute([$id]);
$recipe = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all ingredients 
$stmt = $pdo->prepare("
    SELECT i.IngredientName
    FROM Ingredients i
    JOIN MealIngredients mi ON i.IngredientID = mi.IngredientID
    WHERE mi.MealID = :id
    UNION
    SELECT IngredientName
    FROM CustomIngredients
    WHERE MealID = :id
");
$stmt->execute(['id' => $id]);
$allIngredients = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch reviews only if the recipe is approved
$reviews = [];
if ($recipe && $recipe['Status'] == 'approved') {
    $stmt = $pdo->prepare("
        SELECT Reviews.*, User.Username 
        FROM Reviews 
        JOIN User ON Reviews.UserID = User.UserID 
        WHERE MealID = ?
    ");
    $stmt->execute([$id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - View/Edit Recipe</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'recipe-orange': '#FF9800',
                        'recipe-green': '#4CAF50',
                        'recipe-yellow': '#FFC107',
                    },
                }
            }
        }
    </script>
    <style>
        #brand-logo {
            font-size: 2.5rem;
            color: #FF9800;
            text-decoration: none;
        }
    </style>
</head>
<body class="bg-orange-50 min-h-screen">
    <nav class="bg-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <a href="admin.php" id="brand-logo" class="font-bold hover:text-recipe-orange">Alashibarecipes</a>
            <div>
                <span class="mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['AdminUsername']); ?></span>
                <a href="admin_login.php" class="bg-recipe-orange text-white px-4 py-2 rounded hover:bg-orange-600">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-8 px-4">
        <h1 class="text-4xl font-semibold mb-6 text-gray-800">
            <i class="fas fa-utensils mr-3"></i>View/Edit Recipe
        </h1>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <?php if (!empty($recipe['ImagePath'])): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2">Recipe Image</h3>
                    <img src="<?php echo BASE_URL . htmlspecialchars($recipe['ImagePath']); ?>" alt="Recipe Image" class="max-w-md w-full h-auto mx-auto rounded-lg shadow-md">
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="recipe_id" value="<?php echo $id; ?>">
                <input type="hidden" name="table" value="<?php echo $table; ?>">
                
                <div class="mb-4">
                    <label for="recipe_name" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-tag mr-2"></i>Recipe Name
                    </label>
                    <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="recipe_name" name="recipe_name" value="<?php echo htmlspecialchars($recipe["{$table}Name"]); ?>" required>
                </div>
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Description
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="description" name="description" rows="3" required><?php echo htmlspecialchars($recipe["{$table}Description"]); ?></textarea>
                </div>
                <div class="flex flex-wrap -mx-3 mb-4">
                    <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                        <label for="prep_time" class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-clock mr-2"></i>Prep Time (minutes)
                        </label>
                        <input type="number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="prep_time" name="prep_time" value="<?php echo htmlspecialchars($recipe['PrepTime']); ?>" required>
                    </div>
                    <div class="w-full md:w-1/2 px-3">
                        <label for="cook_time" class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-hourglass-half mr-2"></i>Cook Time (minutes)
                        </label>
                        <input type="number" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="cook_time" name="cook_time" value="<?php echo htmlspecialchars($recipe['CookTime']); ?>" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="ingredients" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-mortar-pestle mr-2"></i>Ingredients (one per line)
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="ingredients" name="ingredients" rows="5" required><?php echo implode("\n", $allIngredients); ?></textarea>
                </div>
                <div class="mb-4">
                    <label for="instructions" class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-list-ol mr-2"></i>Instructions
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="instructions" name="instructions" rows="5" required><?php echo htmlspecialchars($recipe['Instructions']); ?></textarea>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" name="edit_recipe" class="bg-recipe-green hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="fas fa-save mr-2"></i>Update Recipe
                    </button>
                    <?php if ($recipe['Status'] == 'pending'): ?>
                        <div>
                            <button type="submit" name="action" value="approve" class="bg-recipe-green hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">
                                <i class="fas fa-check mr-2"></i>Approve Recipe
                            </button>
                            <button type="button" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" onclick="openRejectModal()">
                                <i class="fas fa-times mr-2"></i>Reject Recipe
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($recipe['Status'] == 'approved'): ?>
            <div class="bg-white shadow-md rounded-lg p-6 mb-8">
                <h2 class="text-2xl font-semibold mb-4 text-recipe-orange">
                    <i class="fas fa-comments mr-3"></i>Reviews
                </h2>
                <?php foreach ($reviews as $review): ?>
                    <div class="border-b border-gray-200 pb-4 mb-4 last:border-b-0 last:pb-0 last:mb-0">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-yellow-400">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo ($i <= $review['Rating']) ? '' : ' text-gray-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($review['Username']); ?>
                                <i class="fas fa-calendar-alt ml-3 mr-2"></i><?php echo date('M d, Y', strtotime($review['ReviewDate'])); ?>
                            </div>
                        </div>
                        <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($review['ReviewText']); ?></p>
                        <form method="POST" action="" class="text-right">
                            <input type="hidden" name="review_id" value="<?php echo $review['ReviewID']; ?>">
                            <button type="submit" name="delete_review" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded text-sm">
                                <i class="fas fa-trash-alt mr-2"></i>Delete Review
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Reject Recipe
                            </h3>
                            <div class="mt-2">
                                <form method="POST" action="" id="rejectForm">
                                    <input type="hidden" name="recipe_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <div class="mb-4">
                                        <label for="rejection_reason" class="block text-gray-700 text-sm font-bold mb-2">
                                            <i class="fas fa-comment-alt mr-2"></i>Reason for Rejection:
                                        </label>
                                        <textarea id="rejection_reason" name="rejection_reason" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" form="rejectForm" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Reject Recipe
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-gray-800 text-white mt-12 py-4">
        <div class="container mx-auto text-center">
            <p>&copy; 2024 ALASHIBARECIPES. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function openRejectModal() {
            document.getElementById('rejectModal').classList.remove('hidden');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
    </script>
</body>
</html>