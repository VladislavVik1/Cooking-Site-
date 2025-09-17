<?php

if (!function_exists('safe')) {
    function safe($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

if (!isset($categories)) {
    $categories = [];
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name");
            $categories = $stmt->fetchAll();
        } catch (Throwable $e) {
            
        }
    }
}

$year = date('Y');
?>
<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Вкусные рецепты</h3>
                <p>Лучшие рецепты для вашей кухни</p>
            </div>

            <div class="footer-section">
                <h4>Категории</h4>
                <ul>
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="index.php?category=<?= (int)$category['id'] ?>#recipes">
                                <?= safe($category['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Ссылки</h4>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="index.php#recipes">Рецепты</a></li>
                    <li><a href="index.php#categories">Категории</a></li>
                    <li><a href="index.php#about">О нас</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Контакты</h4>
                <div class="social-icons">
                    <a href="#" aria-label="Telegram"><i class="fab fa-telegram"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= $year ?> Вкусные рецепты. Все права защищены.</p>
        </div>
    </div>
</footer>

<script defer src="/script.js"></script>
</body>
</html>
