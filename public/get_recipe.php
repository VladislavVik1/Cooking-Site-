<?php
require_once __DIR__ . '/../config.php';

$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recipe_id <= 0) {
    http_response_code(400);
    echo '<p>Не указан ID рецепта.</p>';
    exit;
}

$st = $pdo->prepare("
    SELECT r.*, c.name AS category_name
    FROM recipes r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.id = ?
");
$st->execute([$recipe_id]);
$recipe = $st->fetch();

if (!$recipe) {
    http_response_code(404);
    echo '<p>Рецепт не найден.</p>';
    exit;
}


$sti = $pdo->prepare("
    SELECT i.name, ri.quantity, ri.unit
    FROM recipe_ingredients ri
    JOIN ingredients i ON i.id = ri.ingredient_id
    WHERE ri.recipe_id = ?
    ORDER BY ri.id
");
$sti->execute([$recipe_id]);
$ingredients = $sti->fetchAll();


?>
<div class="recipe-modal">
  <div class="modal-recipe-image">
    <img src="images/<?= safe($recipe['image_path']) ?>" alt="<?= safe($recipe['title']) ?>">
  </div>
  <h2><?= safe($recipe['title']) ?></h2>

  <div class="modal-recipe-meta">
    <span><i class="fas fa-clock"></i> <?= (int)$recipe['cooking_time'] ?> мин</span>
    <span><i class="fas fa-chart-pie"></i> <?= safe($recipe['difficulty']) ?></span>
    <span><i class="fas fa-folder"></i> <?= safe($recipe['category_name'] ?? 'Без категории') ?></span>
  </div>

  <p class="modal-recipe-description"><?= safe($recipe['description']) ?></p>

  <div class="modal-ingredients">
    <h3>Ингредиенты</h3>
    <ul>
      <?php if ($ingredients): ?>
        <?php foreach ($ingredients as $ing): ?>
          <li>
            <?= safe($ing['name']) ?>
            <?php if (!empty($ing['quantity'])): ?>
              — <?= safe($ing['quantity']) ?> <?= safe($ing['unit'] ?? '') ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach (explode(',', (string)$recipe['ingredients']) as $row): ?>
          <?php $row = trim($row); if ($row === '') continue; ?>
          <li><?= safe($row) ?></li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </div>

  <div class="modal-instructions">
    <h3>Инструкция приготовления</h3>
    <div><?= nl2br(safe($recipe['instructions'])) ?></div>
  </div>
</div>
