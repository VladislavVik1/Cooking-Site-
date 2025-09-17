<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}

$recipe_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.*, c.name as category_name 
    FROM recipes r 
    LEFT JOIN categories c ON r.category_id = c.id 
    WHERE r.id = ?
");
$stmt->execute([$recipe_id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT i.name, ri.quantity, ri.unit 
    FROM recipe_ingredients ri 
    JOIN ingredients i ON ri.ingredient_id = i.id 
    WHERE ri.recipe_id = ?
");
$stmt->execute([$recipe_id]);
$ingredients = $stmt->fetchAll();

if (empty($ingredients)) {
    $ingredients_list = explode(',', $recipe['ingredients']);
    $ingredients = [];
    foreach ($ingredients_list as $item) {
        $ingredients[] = ['name' => trim($item)];
    }
}

$pdo->prepare("UPDATE recipes SET views = COALESCE(views, 0) + 1 WHERE id = ?")->execute([$recipe_id]);

$stmt = $pdo->prepare("
    SELECT r.*, c.name as category_name 
    FROM recipes r 
    LEFT JOIN categories c ON r.category_id = c.id 
    WHERE r.category_id = ? AND r.id != ? 
    ORDER BY RAND() 
    LIMIT 3
");
$stmt->execute([$recipe['category_id'], $recipe_id]);
$similar_recipes = $stmt->fetchAll();
?>

<?php
require_once 'config.php';
$page_title = 'Рецепт — Вкусные рецепты';
include __DIR__ . '/partials/head.php';
?>
    <section class="recipe-detail-section">
        <div class="container">
            <div class="recipe-detail">
                <div class="recipe-detail-image">
                    <img src="images/<?= safe($recipe['image_path']) ?>" alt="<?= safe($recipe['title']) ?>">
                    <div class="recipe-detail-meta">
                        <span class="recipe-category"><?= safe($recipe['category_name'] ?? 'Без категории') ?></span>
                        <span class="recipe-difficulty"><?= safe($recipe['difficulty']) ?></span>
                        <span><i class="fas fa-clock"></i> <?= safe($recipe['cooking_time']) ?> мин</span>
                        <span><i class="fas fa-eye"></i> <?= safe($recipe['views'] ?? 0) ?> просмотров</span>
                    </div>
                </div>
                
                <div class="recipe-detail-content">
                    <h1><?= safe($recipe['title']) ?></h1>
                    <p class="recipe-description"><?= safe($recipe['description']) ?></p>
                    
                    <div class="recipe-ingredients">
                        <h2>Ингредиенты</h2>
                        <ul>
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li>
                                    <?= safe($ingredient['name']) ?>
                                    <?php if (!empty($ingredient['quantity'])): ?>
                                        - <?= safe($ingredient['quantity']) ?>
                                        <?php if (!empty($ingredient['unit'])): ?>
                                            <?= safe($ingredient['unit']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="recipe-instructions">
                        <h2>Инструкция приготовления</h2>
                        <div class="instructions-steps">
                            <?php
                            $steps = explode("\n", $recipe['instructions']);
                            foreach ($steps as $index => $step):
                                if (!empty(trim($step))):
                            ?>
                                <div class="instruction-step">
                                    <div class="step-number"><?= $index + 1 ?></div>
                                    <div class="step-text"><?= safe(trim($step)) ?></div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($recipe['video_url'])): ?>
                    <div class="recipe-video">
                        <h2>Видео рецепта</h2>
                        <div class="video-container">
                            <iframe width="100%" height="400" src="https://www.youtube.com/embed/<?= getYouTubeId($recipe['video_url']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($similar_recipes)): ?>
            <div class="similar-recipes">
                <h2>Похожие рецепты</h2>
                <div class="recipes-grid">
                    <?php foreach ($similar_recipes as $similar_recipe): ?>
                        <div class="recipe-card">
                            <div class="recipe-image">
                                <img src="images/<?= safe($similar_recipe['image_path']) ?>" alt="<?= safe($similar_recipe['title']) ?>">
                                <div class="recipe-category"><?= safe($similar_recipe['category_name'] ?? 'Без категории') ?></div>
                                <div class="recipe-difficulty"><?= safe($similar_recipe['difficulty']) ?></div>
                            </div>
                            <div class="recipe-content">
                                <h3><?= safe($similar_recipe['title']) ?></h3>
                                <p><?= safe(mb_substr($similar_recipe['description'], 0, 100) . '...') ?></p>
                                <div class="recipe-meta">
                                    <span><i class="fas fa-clock"></i> <?= safe($similar_recipe['cooking_time']) ?> мин</span>
                                    <span><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($similar_recipe['created_at'])) ?></span>
                                </div>
                                <a href="recipe.php?id=<?= $similar_recipe['id'] ?>" class="recipe-btn">Смотреть рецепт</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

<?php include __DIR__ . '/partials/footer.php'; ?>
