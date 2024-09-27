<?php
session_start();
require_once 'connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Both username and password are required.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT AdminID, Username, Password FROM admin WHERE Username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                if (password_verify($password, $admin['Password'])) {
                    $_SESSION['AdminID'] = $admin['AdminID'];
                    $_SESSION['AdminUsername'] = $admin['Username'];
                    header("Location: admin.php");
                    exit();
                } elseif ($password === $admin['Password']) {
                    $_SESSION['AdminID'] = $admin['AdminID'];
                    $_SESSION['AdminUsername'] = $admin['Username'];
                    header("Location: admin.php");
                    exit();
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#FF9800',
                        'primary-dark': '#F57C00',
                        secondary: '#FFB74D',
                        accent: '#FFF3E0',
                    },
                    animation: {
                        fadeIn: 'fadeIn 0.5s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-accent min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg overflow-hidden w-full max-w-md p-8">
        <h1 class="text-3xl font-bold text-primary mb-2 animate-fadeIn">Alashibarecipes</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-6 animate-fadeIn">Admin Login</h2>
        <?php if ($error): ?>
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 animate-fadeIn" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Admin Username</label>
                <input type="text" id="username" name="username" required
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition duration-150 ease-in-out">
                Login
            </button>
        </form>
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">Sharing delicious moments, one recipe at a time.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const errorMessage = document.getElementById('error-message');
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            const submitButton = document.querySelector('button[type="submit"]');

            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;

                if (!username || !password) {
                    e.preventDefault();
                    showError('Please fill in both username and password.');
                }
            });

            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.classList.add('ring-2', 'ring-primary', 'ring-opacity-50', 'border-primary');
                });
                input.addEventListener('blur', function() {
                    this.classList.remove('ring-2', 'ring-primary', 'ring-opacity-50', 'border-primary');
                });
            });

            submitButton.addEventListener('mouseenter', function() {
                this.classList.add('animate-pulse');
            });
            submitButton.addEventListener('mouseleave', function() {
                this.classList.remove('animate-pulse');
            });

            const formElements = document.querySelectorAll('form > div');
            formElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100 * (index + 1));
            });

            function showError(message) {
                if (!errorMessage) {
                    const newErrorMessage = document.createElement('div');
                    newErrorMessage.id = 'error-message';
                    newErrorMessage.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 animate-fadeIn';
                    newErrorMessage.setAttribute('role', 'alert');
                    newErrorMessage.innerHTML = `<strong class="font-bold">Error!</strong> <span class="block sm:inline">${message}</span>`;
                    form.insertBefore(newErrorMessage, form.firstChild);
                } else {
                    errorMessage.textContent = message;
                    errorMessage.classList.remove('hidden');
                }
                errorMessage.classList.add('animate-wiggle');
                setTimeout(() => {
                    errorMessage.classList.remove('animate-wiggle');
                }, 1000);
            }
        });
    </script>
</body>
</html>