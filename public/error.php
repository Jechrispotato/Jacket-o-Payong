<?php
// public/error.php - Error page
$error = $_GET['message'] ?? 'Something went wrong. Please try again.';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Error - Jacket o Payong?</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">

    <style>
        :root {
            --glass-background: rgba(255, 255, 255, 0.12);
            --glass-border: rgba(255, 255, 255, 0.35);
            --glass-blur: 25px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
            background: #fefffaff;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(var(--glass-blur));
            -webkit-backdrop-filter: blur(var(--glass-blur));
            border: 1px solid var(--glass-border);
            border-radius: 40px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .error-icon {
            font-size: 80px;
            color: #fc7c7cff;
            margin-bottom: 30px;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .error-title {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
        }

        .error-message {
            font-size: 18px;
            color: rgba(27, 27, 27, 0.95);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .error-button-group {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .error-button {
            background: rgba(255, 255, 255, 0.3);
            color: #252525ff;
            padding: 14px 32px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.4);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            font-size: 16px;
        }

        .error-button:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .error-button.primary {
            background: rgba(34, 197, 94, 0.4);
            border-color: rgba(34, 197, 94, 0.8);
        }

        .error-button.primary:hover {
            background: rgba(34, 197, 94, 0.6);
            border-color: rgba(34, 197, 94, 1);
        }

        .divider {
            color: rgba(255, 255, 255, 0.3);
            margin: 30px 0;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .error-container {
                padding: 40px 20px;
            }

            .error-title {
                font-size: 28px;
            }

            .error-message {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
                
        <p class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </p>

        <div class="error-button-group">
            <a href="index.php" class="error-button primary">
                <i class="fas fa-home"></i> Go Home
            </a>
        </div>
    </div>
</body>

</html>
