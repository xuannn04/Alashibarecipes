<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alashibarecipes - Logged Out</title>
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
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    },
                    animation: {
                        fadeIn: 'fadeIn 0.5s ease-out'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body class="bg-accent min-h-screen flex items-center justify-center font-['Poppins']">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center animate-fadeIn">
        <h1 class="text-3xl font-semibold text-gray-800 mb-4">You've been logged out</h1>
        <p class="text-gray-600 mb-6">Thank you for using our Recipe Sharing Platform. We hope to see you again soon!</p>
        
        <div class="space-y-4 mb-8">
            <a href="login.php" class="block w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2 px-4 rounded transition duration-300">
                Log In Again
            </a>
        </div>
        
    </div>
    
    <footer class="absolute bottom-0 w-full text-center py-4 text-gray-500 text-sm">
        <p>&copy; 2024 ALASHIBARECIPES. All Rights Reserved.</p>
    </footer>

    <?php
    session_start();
    session_destroy();
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fadeIn');
            elements.forEach((el, index) => {
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