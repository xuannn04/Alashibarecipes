<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['AdminID'])) {
    header("Location: admin_login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';

// Handle delete review action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_review'])) {
    $review_id = $_POST['review_id'];
    $stmt = $pdo->prepare("DELETE FROM Reviews WHERE ReviewID = ?");
    try {
        $stmt->execute([$review_id]);
        $message = "Review deleted successfully.";
    } catch (PDOException $e) {
        $message = "Error deleting review: " . $e->getMessage();
    }
}

// Search functionality
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : '';

// Fetch recipes with cuisine type
$tables = ['Breakfast', 'Lunch', 'Dinner', 'Side', 'Dessert'];
$pending_recipes = [];
$approved_recipes = [];
$rejected_recipes = [];

foreach ($tables as $table) {
    $query = "
        SELECT 
            $table.{$table}ID as ID, 
            $table.{$table}Name as Name, 
            $table.{$table}Description as Description, 
            $table.Status, 
            '$table' as Type,
            Cuisine.CuisineName as CuisineType
        FROM $table
        JOIN MealCategory ON $table.{$table}ID = MealCategory.MealID
        JOIN Cuisine ON MealCategory.CuisineID = Cuisine.CuisineID
    ";

    // Add search conditions
    if (!empty($search_term)) {
        switch ($search_type) {
            case 'id':
                $query .= " WHERE $table.{$table}ID = :search_term";
                break;
            case 'name':
                $query .= " WHERE $table.{$table}Name LIKE :search_term";
                break;
            case 'description':
                $query .= " WHERE $table.{$table}Description LIKE :search_term";
                break;
            case 'type':
                $query .= " WHERE '$table' LIKE :search_term";
                break;
            case 'cuisine':
                $query .= " WHERE Cuisine.CuisineName LIKE :search_term";
                break;
            default:
                $query .= " WHERE ($table.{$table}ID = :search_term OR $table.{$table}Name LIKE :search_term OR $table.{$table}Description LIKE :search_term OR '$table' LIKE :search_term OR Cuisine.CuisineName LIKE :search_term)";
        }
    }

    $stmt = $pdo->prepare($query);

    if (!empty($search_term)) {
        $search_param = ($search_type == 'id') ? $search_term : "%$search_term%";
        $stmt->bindParam(':search_term', $search_param);
    }

    $stmt->execute();
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recipes as $recipe) {
        switch ($recipe['Status']) {
            case 'pending':
                $pending_recipes[] = $recipe;
                break;
            case 'approved':
                $approved_recipes[] = $recipe;
                break;
            case 'rejected':
                $rejected_recipes[] = $recipe;
                break;
        }
    }
}

// Count recipes 
$pending_count = count($pending_recipes);
$approved_count = count($approved_recipes);
$rejected_count = count($rejected_recipes);

function displayRecipeTable($recipes, $status) {
    if (empty($recipes)) {
        echo "<p class='text-gray-600 mt-4'>No recipes found.</p>";
    } else {
        echo "<div class='overflow-x-auto'>";
        echo "<table class='min-w-full bg-white rounded-lg overflow-hidden'>";
        echo "<thead class='bg-recipe-orange text-white'><tr>";
        echo "<th class='py-2 px-4 text-left cursor-pointer' onclick='sortTable(this, 0)'>ID <i class='fas fa-sort'></i></th>";
        echo "<th class='py-2 px-4 text-left cursor-pointer' onclick='sortTable(this, 1)'>Name <i class='fas fa-sort'></i></th>";
        echo "<th class='py-2 px-4 text-left cursor-pointer' onclick='sortTable(this, 2)'>Description <i class='fas fa-sort'></i></th>";
        echo "<th class='py-2 px-4 text-left cursor-pointer' onclick='sortTable(this, 3)'>Type <i class='fas fa-sort'></i></th>";
        echo "<th class='py-2 px-4 text-left cursor-pointer' onclick='sortTable(this, 4)'>Cuisine <i class='fas fa-sort'></i></th>";
        echo "<th class='py-2 px-4 text-left'>Actions</th>";
        echo "</tr></thead>";
        echo "<tbody>";
        foreach ($recipes as $recipe) {
            $rowClass = '';
            switch ($status) {
                case 'pending':
                    $rowClass = 'bg-yellow-100 hover:bg-yellow-200';
                    break;
                case 'approved':
                    $rowClass = 'bg-green-100 hover:bg-green-200';
                    break;
                case 'rejected':
                    $rowClass = 'bg-red-100 hover:bg-red-200';
                    break;
            }
            echo "<tr class='border-b $rowClass'>";
            echo "<td class='py-2 px-4'>" . htmlspecialchars($recipe['ID']) . "</td>";
            echo "<td class='py-2 px-4'>" . htmlspecialchars($recipe['Name']) . "</td>";
            echo "<td class='py-2 px-4'>" . htmlspecialchars($recipe['Description']) . "</td>";
            echo "<td class='py-2 px-4'>" . htmlspecialchars($recipe['Type']) . "</td>";
            echo "<td class='py-2 px-4'>" . htmlspecialchars($recipe['CuisineType']) . "</td>";
            echo "<td class='py-2 px-4'>";
            echo "<a href='admin_view_recipe.php?id=" . $recipe['ID'] . "&type=" . strtolower($recipe['Type']) . "' class='bg-recipe-green hover:bg-green-600 text-white font-bold py-1 px-2 rounded text-sm mr-2'>View/Edit</a> ";
            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Admin main page</title>
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
            <i class="fas fa-utensils mr-3"></i>Admin Kitchen - Recipe Management
        </h1>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4 text-recipe-orange">
                <i class="fas fa-search mr-3"></i>Search Recipes
            </h2>
            <form action="" method="GET" class="flex flex-wrap items-center">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>" class="flex-grow mr-2 mb-2 sm:mb-0 p-2 border rounded">
                <select name="search_type" class="mr-2 mb-2 sm:mb-0 p-2 border rounded">
                    <option value="">All Fields</option>
                    <option value="id" <?php echo $search_type == 'id' ? 'selected' : ''; ?>>ID</option>
                    <option value="name" <?php echo $search_type == 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="description" <?php echo $search_type == 'description' ? 'selected' : ''; ?>>Description</option>
                    <option value="type" <?php echo $search_type == 'type' ? 'selected' : ''; ?>>Type</option>
                    <option value="cuisine" <?php echo $search_type == 'cuisine' ? 'selected' : ''; ?>>Cuisine</option>
                </select>
                <button type="submit" class="bg-recipe-green hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </form>
        </div>
        
        <!-- Tabbed Interface -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <div class="mb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px" id="myTab" role="tablist">
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 rounded-t-lg bg-yellow-100 hover:bg-yellow-200 text-yellow-800" id="pending-tab" data-tabs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">
                            Pending Recipes
                            <span class="ml-2 px-2 py-1 bg-yellow-200 text-yellow-800 rounded-full text-xs font-semibold">
                                <?php echo $pending_count; ?>
                            </span>
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg bg-green-100 hover:bg-green-200 text-green-800" id="approved-tab" data-tabs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                            Approved Recipes
                            <span class="ml-2 px-2 py-1 bg-green-200 text-green-800 rounded-full text-xs font-semibold">
                                <?php echo $approved_count; ?>
                            </span>
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg bg-red-100 hover:bg-red-200 text-red-800" id="rejected-tab" data-tabs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                            Rejected Recipes
                            <span class="ml-2 px-2 py-1 bg-red-200 text-red-800 rounded-full text-xs font-semibold">
                                <?php echo $rejected_count; ?>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
            <div id="myTabContent">
                <div class="hidden p-4 rounded-lg bg-gray-50" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <?php displayRecipeTable($pending_recipes, 'pending'); ?>
                </div>
                <div class="hidden p-4 rounded-lg bg-gray-50" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <?php displayRecipeTable($approved_recipes, 'approved'); ?>
                </div>
                <div class="hidden p-4 rounded-lg bg-gray-50" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
                    <?php displayRecipeTable($rejected_recipes, 'rejected'); ?>
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
        // Tabs functionality
        let tabs = document.querySelectorAll('[role="tab"]');
        let tabContents = document.querySelectorAll('[role="tabpanel"]');

        tabs.forEach(tab => {
            tab.addEventListener('click', changeTabs);
        });

        function changeTabs(e) {
            let target = e.target;
            let parent = target.parentNode;
            let grandparent = parent.parentNode;

            // Remove all current selected tabs
            grandparent.querySelectorAll('[aria-selected="true"]').forEach(t => t.setAttribute('aria-selected', false));

            // Set this tab as selected
            target.setAttribute('aria-selected', true);

            // Hide all tab panels
            let tabPanel = document.querySelector(target.getAttribute('data-tabs-target'));
            tabContents.forEach(tc => tc.classList.add('hidden'));

            // Show the selected panel
            tabPanel.classList.remove('hidden');
        }

        // Set the first tab as active by default
        document.querySelector('[role="tab"]').click();

        function sortTable(th, n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = th.closest('table');
            switching = true;
            // Set the sorting direction to ascending:
            dir = "asc";
            /* Make a loop that will continue until
            no switching has been done: */
            while (switching) {
                // Start by saying: no switching is done:
                switching = false;
                rows = table.rows;
                /* Loop through all table rows */
                for (i = 1; i < (rows.length - 1); i++) {
                    // Start by saying there should be no switching:
                    shouldSwitch = false;
                    /* Get the two elements you want to compare,
                    one from current row and one from the next: */
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    /* Check if the two rows should switch place,
                    based on the direction, asc or desc: */
                    if (dir == "asc") {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                            // If so, mark as a switch and break the loop
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                if (shouldSwitch) {
                    /* If a switch has been marked, make the switch
                    and mark that a switch has been done: */
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    // Each time a switch is done, increase this count by 1:
                    switchcount++;
                } else {
                    /* If no switching has been done AND the direction is "asc",
                    set the direction to "desc" and run the while loop again. */
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
            // Update sort icons
            table.querySelectorAll('th i').forEach(icon => icon.className = 'fas fa-sort');
            th.querySelector('i').className = dir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
        }
    </script>
</body>
</html>