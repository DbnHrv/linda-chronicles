<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            <ul class="navbar-menu">
                <?php if (isAuthenticated()): ?>
                    <li><span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?> (<?php echo htmlspecialchars($_SESSION['user_role_name']); ?>)</span></li>
                    <li><a href="<?php echo APP_URL; ?>/?action=logout" class="btn-logout">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo APP_URL; ?>/?action=login">Login</a></li>
                    <li><a href="<?php echo APP_URL; ?>/?action=register">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
           