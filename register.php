<?php
session_start();
require_once("connection.php");

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established. Please check your connection settings.");
}

$message = '';
$messageType = '';

if (isset($_POST['submit'])) {
    $email = $_POST['email'];  
    $user = $_POST['username'];  
    $pass = $_POST['password'];  
    $confirm_pass = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($email) || empty($user) || empty($pass) || empty($confirm_pass)) {
        $errors[] = "All fields are required. Please fill in all the information.";
    }
    
    if ($pass !== $confirm_pass) {
        $errors[] = "Password and Confirm Password do not match. Please ensure they are identical.";
    }
    
    if (strlen($pass) < 8 || !preg_match("/[A-Za-z]/", $pass) || !preg_match("/[0-9]/", $pass)) {
        $errors[] = "Password must be at least 8 characters long and include both letters and numbers for security.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM User WHERE Username = ? OR Email = ?");
        $stmt->execute([$user, $email]);
        $result = $stmt->fetch();

        if ($result) {
            $message = "Username or Email is already in use. Please choose another one or log in if you already have an account.";
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO User (Username, Email, Password) VALUES (?, ?, ?)");
            $hashed_password = password_hash($pass, PASSWORD_DEFAULT);
            
            if ($stmt->execute([$user, $email, $hashed_password])) {
                $message = "Registration successful! You can now log in with your new account.";
                $messageType = 'success';
            } else {
                $message = "An error occurred during registration. Please try again later or contact support if the problem persists.";
                $messageType = 'error';
            }
        }
    } else {
        $message = implode("<br>", $errors);
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Register</title>
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
                <h1 class="text-3xl font-bold text-primary mb-4 animate-fadeIn">Welcome to Our Community!</h1>
                <p class="text-gray-600 mb-6 animate-fadeIn">Join the #1 Recipe Sharing Platform and start your culinary journey today!</p>
                
                <div id="message" class="<?php echo $messageType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> px-4 py-3 rounded relative mb-4 <?php echo !empty($message) ? 'block' : 'hidden'; ?>" role="alert">
                    <strong class="font-bold"><?php echo $messageType === 'success' ? 'Success!' : 'Error:'; ?></strong>
                    <span class="block sm:inline"><?php echo $message; ?></span>
                </div>
                
                <form name="form1" method="post" action="" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" placeholder="Choose a unique username">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" placeholder="Enter your email address">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" placeholder="Create a strong password">
                        <p class="mt-1 text-sm text-gray-500">Password must be at least 8 characters long and include both letters and numbers.</p>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200" placeholder="Confirm your password">
                    </div>
                    <div>
                        <label class="inline-flex items-center mt-3">
                            <input type="checkbox" class="form-checkbox h-5 w-5 text-primary" id="show_password">
                            <span class="ml-2 text-gray-700">Show Password</span>
                        </label>
                    </div>
                    <div>
                        <input type="submit" name="submit" value="Create Account" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                    </div>
                </form>
                <p class="mt-4 text-center text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="font-medium text-primary hover:text-primary-dark transition-colors duration-200 hover:underline">Log in here</a>
                </p>
            </div>
            <div class="w-full md:w-1/2 relative overflow-hidden">
                <img src="images/food1.jpeg" alt="Delicious Food" class="object-cover w-full h-full">
                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <div class="text-center text-white">
                        <h2 class="text-3xl font-bold animate-fadeIn">Your Culinary Journey Starts Here</h2>
                        <p class="mt-2 text-xl animate-fadeIn">Cook, Share, Inspire</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Form validation script loaded");
            const form = document.querySelector('form');
            const messageElement = document.getElementById('message');
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
            const submitButton = document.querySelector('input[type="submit"]');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            function showMessage(message, isError = true) {
                messageElement.innerHTML = `<strong class="font-bold">${isError ? 'Error:' : 'Success!'}</strong> <span class="block sm:inline">${message}</span>`;
                messageElement.className = isError
                    ? 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 block animate-fadeIn'
                    : 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 block animate-fadeIn';
            }

            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value;
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                let errors = [];

                if (!username || !email || !password || !confirmPassword) {
                    errors.push("Please fill in all fields.");
                }

                if (!emailRegex.test(email)) {
                    errors.push("Please enter a valid email address.");
                }

                if (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
                    errors.push("Password must be at least 8 characters long and include both letters and numbers.");
                }

                if (password !== confirmPassword) {
                    errors.push("Password and Confirm Password do not match.");
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    showMessage(errors.join("<br>"));
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

            const showPasswordCheckbox = document.getElementById('show_password');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            showPasswordCheckbox.addEventListener('change', function() {
                const type = showPasswordCheckbox.checked ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                confirmPasswordInput.setAttribute('type', type);
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
        });
    </script>
</body>
</html>