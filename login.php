<?php
session_start();
require_once("connection.php");

$error = '';

if (isset($_POST['submit'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Both username and password fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE Username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['Password'])) {
                $_SESSION['valid'] = $user['Username'];
                $_SESSION['username'] = $user['Username'];
                $_SESSION['UserID'] = $user['UserID'];
                header('Location: index.php');
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } catch(PDOException $e) {
            // Log the error instead of displaying it to the user
            error_log("Database error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Login</title>
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
                    keyframes: {
                        wiggle: {
                            '0%, 100%': { transform: 'rotate(-3deg)' },
                            '50%': { transform: 'rotate(3deg)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    },
                    animation: {
                        wiggle: 'wiggle 1s ease-in-out infinite',
                        fadeIn: 'fadeIn 0.5s ease-out'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-accent min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg overflow-hidden max-w-4xl w-full">
        <div class="flex flex-col md:flex-row">
            <div class="w-full md:w-1/2 p-8">
                <h1 class="text-3xl font-bold text-primary mb-4 animate-fadeIn">Welcome to Alashibarecipe!</h1>
                <p class="text-gray-600 mb-6 animate-fadeIn">Join the #1 Recipe Sharing Platform</p>
                <?php if (!empty($error)): ?>
                    <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 animate-fadeIn" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"> <?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                <form name="form1" method="post" action="" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" aria-describedby="username-help">
                        <p id="username-help" class="mt-1 text-sm text-gray-500">Enter the username you used when registering.</p>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" aria-describedby="password-help">
                        <p id="password-help" class="mt-1 text-sm text-gray-500">Enter your password. Case sensitive.</p>
                    </div>
                    <div>
                        <input type="submit" name="submit" value="Login" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                    </div>
                </form>
                <p class="mt-4 text-center text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="font-medium text-primary hover:text-primary-dark transition-colors duration-200 hover:underline">Register</a>
                </p>
            </div>
            <div class="w-full md:w-1/2 relative overflow-hidden">
                <img src="images/food1.jpeg" alt="Delicious Food" class="object-cover w-full h-full">
                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <div class="text-center text-white">
                        <h2 class="text-3xl font-bold animate-fadeIn">Your Next Recipe Adventure Awaits!</h2>
                        <p class="mt-2 text-xl animate-fadeIn">Cook, Share, Inspire</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const errorMessage = document.getElementById('error-message');
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            const submitButton = document.querySelector('input[type="submit"]');

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