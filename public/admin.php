<?php
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

$flash = $_SESSION['flash'] ?? ['ok' => [], 'err' => []];
$_SESSION['flash'] = ['ok' => [], 'err' => []];

function flash_ok($m){ $_SESSION['flash']['ok'][] = $m; }
function flash_err($m){ $_SESSION['flash']['err'][] = $m; }


function redirect_tab(string $tab, array $params = []) {
    $q = http_build_query(array_merge(['tab' => $tab], $params));
    header("Location: admin.php?{$q}");
    exit;
}
function val($key, $default='') { return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default; }

function check_csrf_from_get(): bool {
    return isset($_GET['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_GET['csrf']);
}
function check_csrf_from_post(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf']);
}

if (isset($_POST['add_category'])) {
    if (!check_csrf_from_post()) { flash_err('CSRF токен неверный'); redirect_tab('categories'); }
    $name = trim($_POST['cat_name'] ?? '');
    $desc = trim($_POST['cat_desc'] ?? '');
    if ($name === '') { flash_err('Укажите название категории'); redirect_tab('categories'); }
    try {
        $st = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $st->execute([$name, $desc !== '' ? $desc : null]);
        flash_ok('Категория добавлена');
    } catch (Throwable $e) {
        flash_err('Не удалось добавить категорию: ' . $e->getMessage());
    }
    redirect_tab('categories');
}

if (isset($_GET['delete_category'])) {
    if (!check_csrf_from_get()) { flash_err('CSRF токен неверный'); redirect_tab('categories'); }
    $id = (int)$_GET['delete_category'];
    $pageKeep = (int)($_GET['page'] ?? 1);
    try {
        $st = $pdo->prepare("DELETE FROM categories WHERE id=?");
        $st->execute([$id]);
        flash_ok('Категория удалена (рецепты будут без категории)');
    } catch (Throwable $e) {
        flash_err('Не удалось удалить категорию: ' . $e->getMessage());
    }
    redirect_tab('categories', ['page' => $pageKeep]);
}

if (isset($_POST['add_ingredient'])) {
    if (!check_csrf_from_post()) { flash_err('CSRF токен неверный'); redirect_tab('ingredients'); }
    $name = trim($_POST['ingr_name'] ?? '');
    if ($name === '') { flash_err('Введите название ингредиента'); redirect_tab('ingredients'); }
    try {
        $st = $pdo->prepare("INSERT INTO ingredients (name, description) VALUES (?, NULL)");
        $st->execute([$name]);
        flash_ok('Ингредиент добавлен');
    } catch (Throwable $e) {
        flash_err('Не удалось добавить ингредиент: ' . $e->getMessage());
    }
    redirect_tab('ingredients');
}

if (isset($_GET['delete_ingredient'])) {
    if (!check_csrf_from_get()) { flash_err('CSRF токен неверный'); redirect_tab('ingredients'); }
    $id = (int)$_GET['delete_ingredient'];
    try {
        $st = $pdo->prepare("DELETE FROM ingredients WHERE id=?");
        $st->execute([$id]);
        flash_ok('Ингредиент удалён');
    } catch (Throwable $e) {
        flash_err('Не удалось удалить ингредиент: ' . $e->getMessage());
    }
    redirect_tab('ingredients');
}


if (isset($_POST['add_recipe'])) {
    if (!check_csrf_from_post()) { flash_err('CSRF токен неверный'); redirect_tab('add-recipe'); }
    $title         = val('title');
    $description   = val('description');
    $ingredients_t = val('ingredients_text');
    $instructions  = val('instructions');
    $cooking_time  = (int)($_POST['cooking_time'] ?? 0);
    $difficulty    = $_POST['difficulty'] ?? 'средне';
    $category_id   = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;

    if ($title === '' || $description === '' || $ingredients_t === '' || $instructions === '' || $cooking_time <= 0) {
        flash_err('Заполните обязательные поля');
        redirect_tab('add-recipe');
    }

    $image_path = 'default_recipe.jpg';
    if (!empty($_FILES['image']['tmp_name'])) {
        $res = uploadImage($_FILES['image']);
        if (!empty($res['success'])) $image_path = $res['filename'];
        else flash_err($res['message'] ?? 'Ошибка загрузки изображения');
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("INSERT INTO recipes
            (title, description, ingredients, instructions, cooking_time, difficulty, category_id, image_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING id");
        $st->execute([$title, $description, $ingredients_t, $instructions, $cooking_time, $difficulty, $category_id, $image_path]);
        $recipe_id = (int)$st->fetchColumn();

        if (!empty($_POST['ingredient_names'])) {
            $names = $_POST['ingredient_names'];
            $qtys  = $_POST['quantities'] ?? [];
            $units = $_POST['units'] ?? [];
            foreach ($names as $i => $nm) {
                $nm = trim((string)$nm);
                if ($nm === '') continue;
                $q  = trim((string)($qtys[$i] ?? ''));
                $u  = trim((string)($units[$i] ?? ''));

            
                $s = $pdo->prepare("SELECT id FROM ingredients WHERE name=?");
                $s->execute([$nm]);
                $row = $s->fetch();
                if ($row) {
                    $ing_id = (int)$row['id'];
                } else {
                    $s = $pdo->prepare("INSERT INTO ingredients (name) VALUES (?) RETURNING id");
                    $s->execute([$nm]);
                    $ing_id = (int)$s->fetchColumn();
                }

                $s = $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit)
                                    VALUES (?, ?, ?, ?)
                                    ON CONFLICT (recipe_id, ingredient_id)
                                    DO UPDATE SET quantity = EXCLUDED.quantity, unit = EXCLUDED.unit");
                $s->execute([$recipe_id, $ing_id, $q !== '' ? $q : null, $u !== '' ? $u : null]);
            }
        }

        $pdo->commit();
        flash_ok('Рецепт добавлен (#'.$recipe_id.')');
        redirect_tab('recipes');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_err('Не удалось добавить рецепт: ' . $e->getMessage());
        redirect_tab('add-recipe');
    }
}

if (isset($_GET['delete_recipe'])) {
    if (!check_csrf_from_get()) { flash_err('CSRF токен неверный'); redirect_tab('recipes'); }
    $id = (int)$_GET['delete_recipe'];
    $pageKeep = (int)($_GET['page'] ?? 1);
    try {
        $st = $pdo->prepare("DELETE FROM recipes WHERE id=?");
        $st->execute([$id]); 
        flash_ok('Рецепт удалён');
    } catch (Throwable $e) {
        flash_err('Не удалось удалить рецепт: ' . $e->getMessage());
    }
    redirect_tab('recipes', ['page' => $pageKeep]);
}

if (isset($_POST['update_image'])) {
    if (!check_csrf_from_post()) { flash_err('CSRF токен неверный'); redirect_tab('recipes'); }
    $id = (int)($_POST['recipe_id'] ?? 0);
    if ($id <= 0 || empty($_FILES['new_image']['tmp_name'])) {
        flash_err('Не выбран рецепт или файл');
        redirect_tab('recipes');
    }
    $res = uploadImage($_FILES['new_image']);
    if (empty($res['success'])) {
        flash_err($res['message'] ?? 'Ошибка загрузки');
        redirect_tab('recipes');
    }
    try {
        $st = $pdo->prepare("UPDATE recipes SET image_path=?, updated_at=NOW() WHERE id=?");
        $st->execute([$res['filename'], $id]);
        flash_ok('Изображение обновлено');
    } catch (Throwable $e) {
        flash_err('Не удалось обновить изображение: '.$e->getMessage());
    }
    redirect_tab('recipes', ['page' => (int)($_GET['page'] ?? 1)]);
}

if (isset($_POST['save_recipe'])) {
    if (!check_csrf_from_post()) { flash_err('CSRF токен неверный'); redirect_tab('recipes'); }
    $id            = (int)($_POST['id'] ?? 0);
    $title         = val('title');
    $description   = val('description');
    $ingredients_t = val('ingredients_text');
    $instructions  = val('instructions');
    $cooking_time  = (int)($_POST['cooking_time'] ?? 0);
    $difficulty    = $_POST['difficulty'] ?? 'средне';
    $category_id   = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;

    if ($id <= 0) { flash_err('Не передан ID рецепта'); redirect_tab('recipes'); }
    if ($title === '' || $description === '' || $ingredients_t === '' || $instructions === '' || $cooking_time <= 0) {
        flash_err('Заполните обязательные поля');
        redirect_tab('edit-recipe', ['id' => $id]);
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("UPDATE recipes
            SET title=?, description=?, ingredients=?, instructions=?, cooking_time=?, difficulty=?, category_id=?, updated_at=NOW()
            WHERE id=?");
        $st->execute([$title, $description, $ingredients_t, $instructions, $cooking_time, $difficulty, $category_id, $id]);

       
        $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=?")->execute([$id]);

        $names = $_POST['ingredient_names'] ?? [];
        $qtys  = $_POST['quantities'] ?? [];
        $units = $_POST['units'] ?? [];
        foreach ($names as $i => $nm) {
            $nm = trim((string)$nm);
            if ($nm === '') continue;
            $q  = trim((string)($qtys[$i] ?? ''));
            $u  = trim((string)($units[$i] ?? ''));

            $s = $pdo->prepare("SELECT id FROM ingredients WHERE name=?");
            $s->execute([$nm]);
            $row = $s->fetch();
            if ($row) {
                $ing_id = (int)$row['id'];
            } else {
                $s = $pdo->prepare("INSERT INTO ingredients (name) VALUES (?) RETURNING id");
                $s->execute([$nm]);
                $ing_id = (int)$s->fetchColumn();
            }

            $s = $pdo->prepare("INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit)
                                VALUES (?, ?, ?, ?)");
            $s->execute([$id, $ing_id, $q !== '' ? $q : null, $u !== '' ? $u : null]);
        }

        $pdo->commit();
        flash_ok('Рецепт сохранён');
        redirect_tab('recipes');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_err('Ошибка сохранения: ' . $e->getMessage());
        redirect_tab('edit-recipe', ['id' => $id]);
    }
}


$tab  = $_GET['tab'] ?? 'dashboard';
$page = max(1, (int)($_GET['page'] ?? 1));

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$count_recipes     = (int)$pdo->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
$count_categories  = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$count_ingredients = (int)$pdo->query("SELECT COUNT(*) FROM ingredients")->fetchColumn();

$per_page = 20;
$offset   = ($page - 1) * $per_page;
$total_recipes = $count_recipes;
$pages = max(1, (int)ceil($total_recipes / $per_page));
$recipes = $pdo->query("
    SELECT r.*, c.name AS category_name
    FROM recipes r
    LEFT JOIN categories c ON r.category_id = c.id
    ORDER BY r.created_at DESC NULLS LAST, r.id DESC
    LIMIT {$per_page} OFFSET {$offset}
")->fetchAll();

$all_ingredients = $pdo->query("SELECT id, name, created_at FROM ingredients ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора — Вкусные рецепты</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- UI -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#ff6b6b;--primary-dark:#ff5252;--secondary:#4ecdc4;--secondary-dark:#3dbcb4;--dark:#333;--light:#f9f9f9;--gray:#888;--light-gray:#ddd;--white:#fff;--success:#28a745;--danger:#dc3545;--warning:#ffc107;--info:#17a2b8}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Raleway',sans-serif;background:#f5f7f9;color:#333;line-height:1.6}
        .admin-wrapper{display:flex;min-height:100vh}
        .admin-sidebar{width:250px;background:linear-gradient(135deg,#2c3e50,#1a2530);color:#fff;padding:20px 0;box-shadow:0 0 15px rgba(0,0,0,.1);position:fixed;height:100vh;overflow-y:auto;z-index:1000;transition:.3s}
        .admin-sidebar .logo{padding:0 20px 20px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .admin-sidebar .logo i{font-size:24px;color:var(--primary)}
        .admin-sidebar .logo h2{font-family:'Playfair Display',serif;font-size:22px}
        .sidebar-menu{list-style:none}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;padding:12px 20px;color:rgba(255,255,255,.8);text-decoration:none;transition:.3s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,.05);color:#fff;border-left:3px solid var(--primary)}
        .sidebar-menu a i{margin-right:10px;font-size:18px;width:24px;text-align:center}
        .admin-content{flex:1;margin-left:250px;padding:20px}
        .admin-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid var(--light-gray)}
        .admin-header h1{font-family:'Playfair Display',serif;font-size:28px;color:var(--dark)}
        .user-info{display:flex;align-items:center;gap:10px}
        .user-avatar{width:40px;height:40px;border-radius:50%;background:var(--secondary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold}
        .stats-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:30px}
        .stat-card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 5px 15px rgba(0,0,0,.05);display:flex;align-items:center;transition:.3s}
        .stat-card:hover{transform:translateY(-5px)}
        .stat-icon{width:60px;height:60px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:24px;margin-right:15px;color:#fff}
        .stat-icon.recipes{background:linear-gradient(45deg,#ff6b6b,#ff8787)}
        .stat-icon.categories{background:linear-gradient(45deg,#4ecdc4,#6ed9d2)}
        .stat-icon.ingredients{background:linear-gradient(45deg,#ffa726,#ffb74d)}
        .stat-icon.users{background:linear-gradient(45deg,#42a5f5,#64b5f6)}
        .stat-info h3{font-size:24px;margin-bottom:5px}
        .stat-info p{color:var(--gray);font-size:14px}
        .tab-content{display:none;background:#fff;border-radius:10px;padding:25px;box-shadow:0 5px 15px rgba(0,0,0,.05);margin-bottom:30px}
        .tab-content.active{display:block;animation:fadeIn .5s}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .tab-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid var(--light-gray)}
        .tab-header h2{font-family:'Playfair Display',serif;font-size:22px}
        .admin-table{width:100%;border-collapse:collapse}
        .admin-table th,.admin-table td{padding:12px 15px;text-align:left;border-bottom:1px solid var(--light-gray)}
        .admin-table th{background:#f8f9fa;font-weight:600;color:var(--dark)}
        .admin-table tr:hover{background:#f8f9fa}
        .table-image{width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #eee}
        .action-buttons{display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:8px 15px;border:none;border-radius:5px;font-weight:500;cursor:pointer;transition:.3s;text-decoration:none;font-size:14px;gap:6px}
        .btn-sm{padding:5px 10px;font-size:12px}
        .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-dark)}
        .btn-secondary{background:var(--secondary);color:#fff}.btn-secondary:hover{background:var(--secondary-dark)}
        .btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#c82333}
        .btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#218838}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;font-weight:500}
        .form-control{width:100%;padding:12px 15px;border:1px solid var(--light-gray);border-radius:5px;font-size:16px;transition:border-color .3s}
        .form-control:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(255,107,107,.2)}
        textarea.form-control{min-height:120px;resize:vertical}
        .ingredient-row{display:flex;gap:10px;margin-bottom:10px;align-items:center}
        .ingredient-row input,.ingredient-row select{flex:1}
        .alert{padding:15px;border-radius:5px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .menu-toggle{display:none;font-size:24px;cursor:pointer;color:#dark}
        .pagination{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:15px}
        .pagination a,.pagination span{padding:6px 10px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;text-decoration:none;color:#111}
        .pagination .active{background:#111;color:#fff;border-color:#111}
        @media (max-width:992px){.admin-sidebar{width:70px;overflow:visible}.admin-sidebar .logo h2,.sidebar-menu a span{display:none}.admin-sidebar .logo{justify-content:center}.sidebar-menu a{justify-content:center;padding:15px}.admin-sidebar .logo i{margin-right:0;font-size:20px}.admin-content{margin-left:70px}}
        @media (max-width:768px){.stats-cards{grid-template-columns:1fr}.admin-header{flex-direction:column;align-items:flex-start;gap:15px}.ingredient-row{flex-direction:column;align-items:stretch}.action-buttons{flex-direction:column}}
        @media (max-width:576px){.menu-toggle{display:block}.admin-sidebar{transform:translateX(-100%)}.admin-sidebar.active{transform:translateX(0)}.admin-content{margin-left:0;padding:15px}.admin-table{display:block;overflow-x:auto}}
        .inline-img-form input[type=file]{font-size:12px}
    </style>
</head>
<body>
<div class="admin-wrapper">
    <!-- sidebar -->
    <div class="admin-sidebar" id="admin-sidebar">
        <div class="logo"><i class="fas fa-utensils"></i><h2>Админ-панель</h2></div>
        <ul class="sidebar-menu">
            <li><a href="?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> <span>Дашборд</span></a></li>
            <li><a href="?tab=recipes&page=<?= $page ?>" class="<?= $tab==='recipes'?'active':'' ?>" data-tab="recipes"><i class="fas fa-book"></i> <span>Рецепты</span></a></li>
            <li><a href="?tab=add-recipe" class="<?= $tab==='add-recipe'?'active':'' ?>" data-tab="add-recipe"><i class="fas fa-plus-circle"></i> <span>Добавить рецепт</span></a></li>
            <li><a href="?tab=categories" class="<?= $tab==='categories'?'active':'' ?>" data-tab="categories"><i class="fas fa-tags"></i> <span>Категории</span></a></li>
            <li><a href="?tab=ingredients" class="<?= $tab==='ingredients'?'active':'' ?>" data-tab="ingredients"><i class="fas fa-carrot"></i> <span>Ингредиенты</span></a></li>
            <li><a href="/index.php"><i class="fas fa-home"></i> <span>На сайт</span></a></li>
        </ul>
    </div>

   
    <div class="admin-content">
        <div class="admin-header">
            <div><i class="menu-toggle fas fa-bars" id="menu-toggle"></i><h1>Панель администратора</h1></div>
            <div class="user-info"><div class="user-avatar">A</div><span>Администратор</span></div>
        </div>

   
        <?php if (!empty($flash['ok'])): ?>
            <div class="alert alert-success"><?= implode('<br>', array_map('safe', $flash['ok'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($flash['err'])): ?>
            <div class="alert alert-error"><?= implode('<br>', array_map('safe', $flash['err'])) ?></div>
        <?php endif; ?>

        <!-- stats -->
        <div class="stats-cards">
            <div class="stat-card"><div class="stat-icon recipes"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?= (int)$count_recipes ?></h3><p>Рецептов</p></div></div>
            <div class="stat-card"><div class="stat-icon categories"><i class="fas fa-tags"></i></div><div class="stat-info"><h3><?= (int)$count_categories ?></h3><p>Категорий</p></div></div>
            <div class="stat-card"><div class="stat-icon ingredients"><i class="fas fa-carrot"></i></div><div class="stat-info"><h3><?= (int)$count_ingredients ?></h3><p>Ингредиентов</p></div></div>
            <div class="stat-card"><div class="stat-icon users"><i class="fas fa-users"></i></div><div class="stat-info"><h3>0</h3><p>Пользователей</p></div></div>
        </div>

     
        <div class="tab-content <?= $tab==='dashboard'?'active':'' ?>" id="dashboard-tab">
            <div class="tab-header">
                <h2>Обзор системы</h2>
                <a class="btn btn-primary" href="?tab=recipes"><i class="fas fa-book"></i> К списку рецептов</a>
            </div>
            <p>Добро пожаловать! Используйте меню слева для управления контентом.</p>
        </div>

      
        <div class="tab-content <?= $tab==='recipes'?'active':'' ?>" id="recipes-tab">
            <div class="tab-header">
                <h2>Управление рецептами</h2>
                <a class="btn btn-primary" href="?tab=add-recipe"><i class="fas fa-plus"></i> Добавить рецепт</a>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Изображение</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Сложность</th>
                        <th>Время</th>
                        <th>Создан</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recipes): ?>
                    <tr><td colspan="8">Рецептов пока нет.</td></tr>
                <?php else: foreach ($recipes as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td>
                            <img src="images/<?= safe($r['image_path']) ?>" class="table-image" alt="">
                            <form class="inline-img-form" method="post" enctype="multipart/form-data" style="margin-top:6px">
                                <input type="hidden" name="csrf" value="<?= safe($CSRF) ?>">
                                <input type="hidden" name="recipe_id" value="<?= (int)$r['id'] ?>">
                                <input type="file" name="new_image" accept="image/*" required>
                                <button class="btn btn-secondary btn-sm" name="update_image" value="1"><i class="fas fa-image"></i> Заменить</button>
                            </form>
                        </td>
                        <td><?= safe($r['title']) ?></td>
                        <td><?= safe($r['category_name'] ?? '—') ?></td>
                        <td><?= safe($r['difficulty']) ?></td>
                        <td><?= (int)$r['cooking_time'] ?> мин</td>
                        <td><?php
                            $t = !empty($r['created_at']) ? strtotime((string)$r['created_at']) : false;
                            echo $t ? date('d.m.Y', $t) : '—';
                        ?></td>
                        <td class="action-buttons">
                            <a class="btn btn-primary btn-sm" href="?tab=edit-recipe&id=<?= (int)$r['id'] ?>"><i class="fas fa-edit"></i> Редактировать</a>
                            <a class="btn btn-danger btn-sm"
                               href="?tab=recipes&page=<?= $page ?>&delete_recipe=<?= (int)$r['id'] ?>&csrf=<?= safe($CSRF) ?>"
                               onclick="return confirm('Удалить рецепт #<?= (int)$r['id'] ?>?')">
                               <i class="fas fa-trash"></i> Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php for ($p=1;$p<=$pages;$p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?= $p ?></span>
                    <?php else: ?><a href="?tab=recipes&page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

      
        <div class="tab-content <?= $tab==='add-recipe'?'active':'' ?>" id="add-recipe-tab">
            <div class="tab-header"><h2>Добавление нового рецепта</h2></div>
            <form method="POST" enctype="multipart/form-data" action="?tab=add-recipe">
                <input type="hidden" name="csrf" value="<?= safe($CSRF) ?>">
                <div class="form-group"><label for="title">Название рецепта *</label><input type="text" id="title" name="title" class="form-control" required></div>
                <div class="form-group"><label for="description">Описание рецепта *</label><textarea id="description" name="description" class="form-control" required></textarea></div>
                <div class="form-group"><label for="ingredients_text">Ингредиенты (текст) *</label><textarea id="ingredients_text" name="ingredients_text" class="form-control" required placeholder="Яйца - 3 шт, Мука - 200 г, Соль - по вкусу"></textarea></div>
                <div class="form-group"><label for="instructions">Инструкция приготовления *</label><textarea id="instructions" name="instructions" class="form-control" required></textarea></div>
                <div class="form-group"><label for="cooking_time">Время приготовления (мин) *</label><input type="number" id="cooking_time" name="cooking_time" class="form-control" required min="1"></div>
                <div class="form-group">
                    <label for="difficulty">Сложность *</label>
                    <select id="difficulty" name="difficulty" class="form-control" required>
                        <option value="легко">Легко</option><option value="средне">Средне</option><option value="сложно">Сложно</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_id">Категория *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>"><?= safe($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="image">Изображение (опционально)</label><input type="file" id="image" name="image" class="form-control" accept="image/*"></div>

                <div class="form-group">
                    <label>Ингредиенты (структурировано, по строкам — опционально)</label>
                    <div id="ingredientsContainer">
                        <div class="ingredient-row">
                            <input type="text" name="ingredient_names[]" placeholder="Название ингредиента" class="form-control">
                            <input type="text" name="quantities[]" placeholder="Количество" class="form-control">
                            <select name="units[]" class="form-control">
                                <option value="г">г</option><option value="кг">кг</option><option value="мл">мл</option><option value="л">л</option>
                                <option value="шт">шт</option><option value="ч.л.">ч.л.</option><option value="ст.л.">ст.л.</option>
                                <option value="стакан">стакан</option><option value="щепотка">щепотка</option><option value="по вкусу">по вкусу</option>
                            </select>
                            <button type="button" class="remove-ingredient btn btn-danger"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <button type="button" id="addIngredient" class="btn btn-secondary"><i class="fas fa-plus"></i> Добавить ингредиент</button>
                </div>

                <button type="submit" name="add_recipe" class="btn btn-primary"><i class="fas fa-save"></i> Добавить рецепт</button>
            </form>
        </div>

     
        <div class="tab-content <?= $tab==='edit-recipe'?'active':'' ?>" id="edit-recipe-tab">
            <div class="tab-header">
                <h2>Редактирование рецепта</h2>
                <a class="btn btn-secondary" href="?tab=recipes&page=<?= $page ?>"><i class="fas fa-arrow-left"></i> Назад к списку</a>
            </div>

            <?php
            $edit_recipe = null;
            $edit_links  = [];
            if ($tab === 'edit-recipe') {
                $edit_id = (int)($_GET['id'] ?? 0);
                if ($edit_id > 0) {
                    $st = $pdo->prepare("SELECT * FROM recipes WHERE id=?");
                    $st->execute([$edit_id]);
                    $edit_recipe = $st->fetch();

                    if ($edit_recipe) {
                        $st = $pdo->prepare("SELECT ri.quantity, ri.unit, i.name
                                             FROM recipe_ingredients ri
                                             JOIN ingredients i ON i.id = ri.ingredient_id
                                             WHERE ri.recipe_id=?
                                             ORDER BY ri.id");
                        $st->execute([$edit_id]);
                        $edit_links = $st->fetchAll();
                    } else {
                        flash_err('Рецепт не найден');
                        redirect_tab('recipes');
                    }
                } else {
                    flash_err('Не передан ID рецепта');
                    redirect_tab('recipes');
                }
            }
            ?>

            <?php if ($edit_recipe): ?>
            <form method="POST" action="?tab=edit-recipe&id=<?= (int)$edit_recipe['id'] ?>">
                <input type="hidden" name="csrf" value="<?= safe($CSRF) ?>">
                <input type="hidden" name="id" value="<?= (int)$edit_recipe['id'] ?>">

                <div class="form-group">
                    <label for="title_e">Название рецепта *</label>
                    <input type="text" id="title_e" name="title" class="form-control" value="<?= safe($edit_recipe['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="description_e">Описание рецепта *</label>
                    <textarea id="description_e" name="description" class="form-control" required><?= safe($edit_recipe['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="ingredients_text_e">Ингредиенты (текстовое поле) *</label>
                    <textarea id="ingredients_text_e" name="ingredients_text" class="form-control" required><?= safe($edit_recipe['ingredients']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="instructions_e">Инструкция приготовления *</label>
                    <textarea id="instructions_e" name="instructions" class="form-control" required><?= safe($edit_recipe['instructions']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="cooking_time_e">Время приготовления (мин) *</label>
                    <input type="number" id="cooking_time_e" name="cooking_time" class="form-control" value="<?= (int)$edit_recipe['cooking_time'] ?>" min="1" required>
                </div>

                <div class="form-group">
                    <label for="difficulty_e">Сложность *</label>
                    <select id="difficulty_e" name="difficulty" class="form-control" required>
                        <?php
                          $difs = ['легко'=>'Легко','средне'=>'Средне','сложно'=>'Сложно'];
                          foreach ($difs as $k=>$v) {
                              $sel = ($edit_recipe['difficulty']===$k)?'selected':'';
                              echo "<option value=\"".safe($k)."\" {$sel}>".safe($v)."</option>";
                          }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id_e">Категория *</label>
                    <select id="category_id_e" name="category_id" class="form-control" required>
                        <option value="">Выберите категорию</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= ($edit_recipe['category_id']==$category['id'])?'selected':''; ?>>
                                <?= safe($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Ингредиенты (структурировано — связи рецепта)</label>
                    <div id="ingredientsContainerEdit">
                        <?php if ($edit_links): foreach ($edit_links as $ln): ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_names[]" class="form-control" value="<?= safe($ln['name']) ?>" placeholder="Название ингредиента">
                                <input type="text" name="quantities[]" class="form-control" value="<?= safe($ln['quantity']) ?>" placeholder="Количество">
                                <select name="units[]" class="form-control">
                                    <?php
                                      $units = ['г','кг','мл','л','шт','ч.л.','ст.л.','стакан','щепотка','по вкусу'];
                                      foreach ($units as $u) {
                                          $sel = ($ln['unit']===$u)?'selected':'';
                                          echo "<option value=\"".safe($u)."\" {$sel}>".safe($u)."</option>";
                                      }
                                    ?>
                                </select>
                                <button type="button" class="remove-ingredient btn btn-danger"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_names[]" class="form-control" placeholder="Название ингредиента">
                                <input type="text" name="quantities[]" class="form-control" placeholder="Количество">
                                <select name="units[]" class="form-control">
                                    <option value="г">г</option><option value="кг">кг</option><option value="мл">мл</option><option value="л">л</option>
                                    <option value="шт">шт</option><option value="ч.л.">ч.л.</option><option value="ст.л.">ст.л.</option>
                                    <option value="стакан">стакан</option><option value="щепотка">щепотка</option><option value="по вкусу">по вкусу</option>
                                </select>
                                <button type="button" class="remove-ingredient btn btn-danger"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="addIngredientEdit" class="btn btn-secondary"><i class="fas fa-plus"></i> Добавить ингредиент</button>
                    <div style="color:#777; font-size:13px; margin-top:6px">При сохранении связи пересобираются: старые строки будут удалены и заменены теми, что в форме.</div>
                </div>

                <button type="submit" name="save_recipe" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить изменения</button>
            </form>
            <?php endif; ?>
        </div>

    
        <div class="tab-content <?= $tab==='categories'?'active':'' ?>" id="categories-tab">
            <div class="tab-header"><h2>Управление категориями</h2></div>
            <form method="post" action="?tab=categories" style="margin-bottom:16px">
                <input type="hidden" name="csrf" value="<?= safe($CSRF) ?>">
                <div class="form-group"><label>Новая категория</label><input type="text" name="cat_name" class="form-control" placeholder="Название категории" required></div>
                <div class="form-group"><label>Описание</label><input type="text" name="cat_desc" class="form-control" placeholder="Короткое описание (опционально)"></div>
                <button class="btn btn-primary" name="add_category" value="1"><i class="fas fa-plus"></i> Добавить</button>
            </form>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Название</th><th>Описание</th><th>Создана</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= safe($c['name']) ?></td>
                        <td><?= safe($c['description']) ?></td>
                        <td><?php
                            $t = !empty($c['created_at']) ? strtotime((string)$c['created_at']) : false;
                            echo $t ? date('d.m.Y', $t) : '—';
                        ?></td>
                        <td>
                            <a class="btn btn-danger btn-sm"
                               href="?tab=categories&delete_category=<?= (int)$c['id'] ?>&csrf=<?= safe($CSRF) ?>"
                               onclick="return confirm('Удалить категорию «<?= safe($c['name']) ?>»?')">
                               <i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

       
        <div class="tab-content <?= $tab==='ingredients'?'active':'' ?>" id="ingredients-tab">
            <div class="tab-header"><h2>Управление ингредиентами</h2></div>
            <form method="post" action="?tab=ingredients" style="margin-bottom:16px">
                <input type="hidden" name="csrf" value="<?= safe($CSRF) ?>">
                <div class="form-group"><label>Новый ингредиент</label><input type="text" name="ingr_name" class="form-control" placeholder="Название ингредиента" required></div>
                <button class="btn btn-primary" name="add_ingredient" value="1"><i class="fas fa-plus"></i> Добавить</button>
            </form>
            <table class="admin-table">
                <thead><tr><th>ID</th><th>Название</th><th>Создан</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($all_ingredients as $ing): ?>
                    <tr>
                        <td><?= (int)$ing['id'] ?></td>
                        <td><?= safe($ing['name']) ?></td>
                        <td><?php
                            $t = !empty($ing['created_at']) ? strtotime((string)$ing['created_at']) : false;
                            echo $t ? date('d.m.Y', $t) : '—';
                        ?></td>
                        <td>
                            <a class="btn btn-danger btn-sm"
                               href="?tab=ingredients&delete_ingredient=<?= (int)$ing['id'] ?>&csrf=<?= safe($CSRF) ?>"
                               onclick="return confirm('Удалить ингредиент «<?= safe($ing['name']) ?>»?')">
                               <i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>

document.getElementById('addIngredient')?.addEventListener('click', function() {
    const container = document.getElementById('ingredientsContainer');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.innerHTML = `
        <input type="text" name="ingredient_names[]" placeholder="Название ингредиента" class="form-control">
        <input type="text" name="quantities[]" placeholder="Количество" class="form-control">
        <select name="units[]" class="form-control">
            <option value="г">г</option><option value="кг">кг</option><option value="мл">мл</option><option value="л">л</option>
            <option value="шт">шт</option><option value="ч.л.">ч.л.</option><option value="ст.л.">ст.л.</option>
            <option value="стакан">стакан</option><option value="щепотка">щепотка</option><option value="по вкусу">по вкусу</option>
        </select>
        <button type="button" class="remove-ingredient btn btn-danger"><i class="fas fa-times"></i></button>`;
    container.appendChild(row);
    row.querySelector('.remove-ingredient').addEventListener('click', ()=>row.remove());
});
document.querySelectorAll('.remove-ingredient').forEach(btn=>{
    btn.addEventListener('click', function(){ this.parentElement.remove(); });
});


document.getElementById('addIngredientEdit')?.addEventListener('click', function() {
    const container = document.getElementById('ingredientsContainerEdit');
    const row = document.createElement('div');
    row.className = 'ingredient-row';
    row.innerHTML = `
        <input type="text" name="ingredient_names[]" class="form-control" placeholder="Название ингредиента">
        <input type="text" name="quantities[]" class="form-control" placeholder="Количество">
        <select name="units[]" class="form-control">
            <option value="г">г</option><option value="кг">кг</option><option value="мл">мл</option><option value="л">л</option>
            <option value="шт">шт</option><option value="ч.л.">ч.л.</option><option value="ст.л.">ст.л.</option>
            <option value="стакан">стакан</option><option value="щепотка">щепотка</option><option value="по вкусу">по вкусу</option>
        </select>
        <button type="button" class="remove-ingredient btn btn-danger"><i class="fas fa-times"></i></button>`;
    container.appendChild(row);
    row.querySelector('.remove-ingredient').addEventListener('click', ()=>row.remove());
});


document.getElementById('menu-toggle')?.addEventListener('click', ()=>{
    document.getElementById('admin-sidebar').classList.toggle('active');
});
</script>
</body>
</html>
