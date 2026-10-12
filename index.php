<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Request Portal</title>
    <link href="../css/tailwind.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">
    <link rel="icon" href="images/logo2.png?v=<?php echo time(); ?>" type="image/png">
    <style>
        /* Professional animations and custom styling for logistics branding */
        .fade-in-up {
            animation: fadeInUp 1.2s ease-out;
        }
        
        .logo-glow {
            animation: logoGlow 2s ease-in-out infinite alternate;
        }
        
        .loading-pulse {
            animation: loadingPulse 1.8s ease-in-out infinite;
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes logoGlow {
            from { 
                filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); 
            }
            to { 
                filter: drop-shadow(0 0 20px rgba(255, 215, 0, 0.6)); 
            }
        }
        
        @keyframes loadingPulse {
            0%, 100% { 
                opacity: 0.6; 
                transform: scale(1); 
            }
            50% { 
                opacity: 1; 
                transform: scale(1.05); 
            }
        }
        
        .font-display { font-family: 'Inter', sans-serif; }
        .font-body { font-family: 'Source Sans Pro', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-950 via-blue-950 to-blue-800 min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- Added subtle background pattern for depth -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.03"%3E%3Ccircle cx="30" cy="30" r="2"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>
    
    <div class="relative z-10 text-center fade-in-up max-w-md mx-auto px-6">
        <!-- Removed background box from logo section -->
        <div class="mb-12">
            <div class="mb-8 relative">
                <img src="images/logo.png" alt="Logistic 2 Logo" class="h-20 w-auto mx-auto logo-glow">
            </div>
            <h1 class="text-5xl font-display font-bold text-yellow-400 mb-3 tracking-wide">
                Logistic 2
            </h1>
            <p class="text-blue-100 font-body text-lg font-medium">
                Reservation Request Portal
            </p>
        </div>
        
        <!-- Removed progress bar and countdown, kept only loading dots -->
        <div class="mb-10">
            <div class="flex justify-center items-center space-x-3 mb-6">
                <div class="loading-pulse w-3 h-3 bg-yellow-400 rounded-full shadow-lg"></div>
                <div class="loading-pulse w-3 h-3 bg-yellow-400 rounded-full shadow-lg" style="animation-delay: 0.3s;"></div>
                <div class="loading-pulse w-3 h-3 bg-yellow-400 rounded-full shadow-lg" style="animation-delay: 0.6s;"></div>
            </div>
        </div>
        
        <!-- Updated footer with 2025 and Group 8 branding -->
        <div class="text-center">
            <p class="text-blue-300 font-body text-xs">
                © 2025 Logistic 2 - Group 8 • All rights reserved.
            </p>
        </div>
    </div>

    <!-- Added JavaScript for 3-second redirect to login page -->
    <script>
        // Redirect to login page after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php'; //  'views/login.php' 'admin/dashboard.php'
        }, 2000);
    </script>
</body>
</html>
