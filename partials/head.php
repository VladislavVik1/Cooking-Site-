<?php

if (!function_exists('safe')) {
    function safe($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!isset($page_title)) { $page_title = 'Вкусные рецепты'; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= safe($page_title) ?></title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Raleway:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<header>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-utensils"></i>
                <span>Вкусные рецепты</span>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link">Главная</a>
                <a href="index.php#recipes" class="nav-link">Рецепты</a>
                <a href="index.php#categories" class="nav-link">Категории</a>
                <a href="index.php#about" class="nav-link">О нас</a>
                <a href="admin.php" class="nav-link">Админ Панель</a>
            </div>
            <div class="nav-toggle" aria-label="Открыть меню" role="button" tabindex="0">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
</header>
