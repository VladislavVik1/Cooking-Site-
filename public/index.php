<?php

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
    $full = realpath(__DIR__ . $path);
    if ($full && is_file($full)) {
        return false; 
    }
}

require_once __DIR__ . '/../config.php';

function recipe_img_src(string $name, bool $usePlaceholder = false ): string {
  $name = trim($name);
  if ($name === '') {
      return $usePlaceholder ? asset('images/logotip.jpg') : '';
  }
  if (preg_match('~^https?://~i', $name)) {
      return $name; 
  }

  $base = basename($name);

  if (defined('UPLOAD_DIR')) {
      $upFs = rtrim(UPLOAD_DIR, '/\\') . '/' . $base;
      if (is_file($upFs)) {

          return asset('images/' . rawurlencode($base));
      }
  }

  $imgFs = __DIR__ . '/images/' . $base;
  if (is_file($imgFs)) {
      return asset('images/' . rawurlencode($base));
  }


  return $usePlaceholder ? asset('images/logotip.jpg') : '';
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$search_q_raw = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_value = safe($search_q_raw);
$category_id  = (isset($_GET['category']) && $_GET['category'] !== '') ? (int)$_GET['category'] : null;

$norm = mb_strtolower(str_replace('ё','е',$search_q_raw), 'UTF-8');
$norm = preg_replace('~[-–—]+~u', ' ', $norm);
$tokens_raw = preg_split('/[\s,.;:!?\(\)"]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);

$stop = ['и','в','во','на','с','со','по','из','для','к','от','до','за','о','об','при','без','над','под','у','ли','или','та','же'];
$suffixes = [
    'иями','ями','ами','ями','ев','ов','ей','ой','ий','ый','ая','яя','ое','ее','ые','ие','ых','их',
    'ую','юю','ом','ем','ам','ям','ах','ях','ую','ю','а','я','о','е','ы','и','у'
];
$tokens = [];
foreach ($tokens_raw as $w) {
    if (in_array($w, $stop, true)) continue;
    foreach ($suffixes as $suf) {
        $len = mb_strlen($suf,'UTF-8');
        if (mb_strlen($w,'UTF-8') > $len + 2 && mb_substr($w, -$len, null, 'UTF-8') === $suf) {
            $w = mb_substr($w, 0, mb_strlen($w,'UTF-8') - $len, 'UTF-8');
            break;
        }
    }
    if ($w !== '' && !in_array($w, $tokens, true)) $tokens[] = $w;
}

$recipes = [];
$filtersUsed = ($category_id !== null || $search_q_raw !== '');

if ($filtersUsed) {
    $sql = "
        SELECT r.*, c.name AS category_name
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($category_id !== null) {
        $sql .= " AND r.category_id = :category_id";
        $params['category_id'] = $category_id;
    }

    if (!empty($tokens)) {
        $i = 0;
        foreach ($tokens as $tok) {
            $i++;
            $ph1 = ":t{$i}_1";
            $ph2 = ":t{$i}_2";
            $ph3 = ":t{$i}_3";
            $sql .= " AND (r.title ILIKE {$ph1} OR r.description ILIKE {$ph2} OR r.ingredients ILIKE {$ph3})";
            $like = "%{$tok}%";
            $params["t{$i}_1"] = $like;
            $params["t{$i}_2"] = $like;
            $params["t{$i}_3"] = $like;
        }
    } elseif ($search_q_raw !== '') {
        $sql .= " AND (r.title ILIKE :q1 OR r.description ILIKE :q2 OR r.ingredients ILIKE :q3)";
        $like = "%{$norm}%";
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }

    $sql .= " ORDER BY r.created_at DESC NULLS LAST, r.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("
        SELECT r.*, c.name AS category_name
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        ORDER BY r.created_at DESC NULLS LAST, r.id DESC
        LIMIT 12
    ");
    $recipes = $stmt->fetchAll();
}

$page_title = 'Вкусные рецепты';
include __DIR__ . '/../partials/head.php';
?>
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title">Откройте мир вкусной кухни</h1>
    <p class="hero-description">Найдите идеальные рецепты для любого случая</p>
    <a href="#recipes" class="hero-btn">Начать готовить</a>
  </div>
  <div class="hero-image">
    <div class="floating-image">
      <img src="<?= asset('images/dish1.jpg') ?>" alt="Блюдо 1">
      <img src="<?= asset('images/dish2.jpg') ?>" alt="Блюдо 2">
      <img src="<?= asset('images/dish3.jpg') ?>" alt="Блюдо 3">
      <img src="<?= asset('images/dish4.jpg') ?>" alt="Блюдо 4">
    </div>
  </div>
</section>

<section class="search-section">
  <div class="container">
    <h2>Найдите свой идеальный рецепт</h2>
    <form method="GET" class="search-form" action="#recipes">
      <div class="search-box">
        <input type="text" name="search" placeholder="Поиск рецептов..." value="<?= $search_value ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
      </div>
      <div class="filters">
        <select name="category">
          <option value="">Все категории</option>
          <?php foreach ($categories as $category): ?>
            <?php $sel = (isset($_GET['category']) && (int)$_GET['category'] === (int)$category['id']) ? 'selected' : ''; ?>
            <option value="<?= (int)$category['id'] ?>" <?= $sel ?>>
              <?= safe($category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="filter-btn">Применить фильтр</button>
      </div>
    </form>
  </div>
</section>

<section id="recipes" class="recipes-section">
  <div class="container">
    <h2>Наши рецепты</h2>
    <div class="recipes-grid">
      <?php if ($recipes): ?>
        <?php foreach ($recipes as $recipe): ?>
          <div class="recipe-card">
            <div class="recipe-image">
              <img src="<?= recipe_img_src($recipe['image_path'] ?? '') ?>" alt="<?= safe($recipe['title']) ?>">
              <div class="recipe-category"><?= safe($recipe['category_name'] ?? 'Без категории') ?></div>
              <div class="recipe-difficulty"><?= safe($recipe['difficulty']) ?></div>
            </div>
            <div class="recipe-content">
              <h3><?= safe($recipe['title']) ?></h3>
              <p><?= safe(mb_substr((string)$recipe['description'], 0, 100)) ?>...</p>
              <div class="recipe-meta">
                <span><i class="fas fa-clock"></i> <?= (int)$recipe['cooking_time'] ?> мин</span>
                <span><i class="fas fa-calendar"></i>
                  <?php
                    $t = !empty($recipe['created_at']) ? strtotime((string)$recipe['created_at']) : false;
                    echo $t ? date('d.m.Y', $t) : '—';
                  ?>
                </span>
              </div>
              <a href="#" class="recipe-btn" data-id="<?= (int)$recipe['id'] ?>">Смотреть рецепт</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-results">
          <i class="fas fa-search"></i>
          <h3>Рецепты не найдены</h3>
          <p>Попробуйте изменить ключевые слова или выбрать другую категорию.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="categories" class="categories-section">
  <div class="container">
    <h2>Категории рецептов</h2>
    <div class="categories-grid">
      <?php foreach ($categories as $category): ?>
        <div class="category-card">
          <div class="category-icon">
            <i class="fa-solid fa-<?=
              $category['name'] === 'Завтраки'        ? 'mug-hot' :
              ($category['name'] === 'Основные блюда' ? 'drumstick-bite' :
              ($category['name'] === 'Десерты'        ? 'ice-cream' :
              ($category['name'] === 'Салаты'         ? 'leaf' :
              ($category['name'] === 'Супы'           ? 'utensils' :
              ($category['name'] === 'Закуски'        ? 'cheese' :
              ($category['name'] === 'Напитки'        ? 'glass-whiskey' :
              ($category['name'] === 'Выпечка'        ? 'bread-slice' : 'utensils'))))))) ?>"></i>
          </div>
          <h3><?= safe($category['name']) ?></h3>
          <p><?= safe($category['description']) ?></p>
          <a href="?category=<?= (int)$category['id'] ?>#recipes" class="category-btn">Смотреть рецепты</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="about" class="about-section">
  <div class="container">
    <div class="about-content">
      <div class="about-text">
        <h2>О нашем проекте</h2>
        <p>Мы создали этот сайт для всех любителей кулинарии. Здесь вы найдете рецепты на любой вкус - от простых повседневных блюд до изысканных кулинарных шедевров.</p>
        <p>Наша миссия - вдохновлять вас на приготовление вкусной и полезной пищи, делиться кулинарными советами и делать процесс готовки удовольствием.</p>
        <div class="about-stats">
          <div class="stat"><span class="stat-number">150+</span><span class="stat-label">Рецептов</span></div>
          <div class="stat"><span class="stat-number">5</span><span class="stat-label">Категорий</span></div>
          <div class="stat"><span class="stat-number">1000+</span><span class="stat-label">Пользователей</span></div>
        </div>
      </div>
      <div class="about-image">
        <img src="<?= asset('images/logotip.jpg') ?>" alt="О нас">
      </div>
    </div>
  </div>
</section>

<div class="modal" id="recipeModal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
(function () {
  const modal = document.getElementById('recipeModal');
  const body  = document.getElementById('modalBody');
  const close = modal?.querySelector('.close');

  function openModal(html) {
    body.innerHTML = html;
    modal.classList.add('open');
  }
  function closeModal() {
    modal.classList.remove('open');
    body.innerHTML = '';
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.recipe-btn');
    if (btn) {
      e.preventDefault();
      const id = btn.getAttribute('data-id');
      try {
        const resp = await fetch('get_recipe.php?id=' + encodeURIComponent(id), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const html = await resp.text();
        openModal(html);
      } catch (err) {
        openModal('<p>Не удалось загрузить рецепт.</p>');
      }
    }
    if (e.target === modal) closeModal();
  });

  close?.addEventListener('click', closeModal);
})();
</script>
