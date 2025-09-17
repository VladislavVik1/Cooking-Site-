Современный сайт с рецептами на PHP 8.2 + Nginx + PostgreSQL. Поиск (ILIKE), категории, модальные карточки рецептов, адаптивный интерфейс и загрузка изображений. Проект готов к запуску в Docker (Compose) с авто-инициализацией БД и к деплою в прод (Traefik/HTTPS или Render).
EN: Modern recipe website built with PHP 8.2, Nginx, and PostgreSQL. ILIKE-based search, categories, modal recipe view, responsive UI, and image uploads. Docker-first (Compose) with automatic DB initialization and production-ready deployment options (Traefik/HTTPS or Render).

О проекте

Поиск по названию/описанию/ингредиентам (ILIKE, лёгкая токенизация).

Категории, сортировка по дате.

Модалка с деталями рецепта и структурированными ингредиентами (recipe_ingredients).

Загрузка изображений в public/images/.

Безопасное экранирование (safe()), PDO с подготовленными выражениями.

Сервисы Docker: db (PostgreSQL), php (PHP-FPM), web (Nginx) и опционально adminer.

Технологический стек

PHP-FPM 8.2: pdo_pgsql, pgsql, mbstring, curl, opcache

Nginx (root: /var/www/html/public)

PostgreSQL 17 (инициализация из ./db/schema.sql)

Docker Compose

Структура проекта
project/
├─ db/                        # SQL-инициализация БД (выполняется при первом старте пустого тома)
│  └─ schema.sql
├─ docker/
│  ├─ nginx/
│  │  └─ default.conf         # конфиг Nginx (root -> /public)
│  └─ php/
│     ├─ Dockerfile
│     └─ php.ini
├─ partials/                  # header/footer
├─ public/                    # корень сайта (доступен из браузера)
│  ├─ index.php
│  ├─ get_recipe.php
│  ├─ style.css, script.js
│  └─ images/                 # загружаемые изображения
├─ config.php                 # подключение БД, загрузки, утилиты
├─ .env                       # переменные окружения (не коммитить)
└─ docker-compose.yml

Запуск локально (Docker)
1) Предусловия

Установлены Docker Desktop и Docker Compose.

Свободен порт 80 (или поменяйте публикацию порта в docker-compose.yml).

2) Создайте .env
APP_ENV=prod
DB_NAME=cooking_site
DB_USER=Admin
DB_PASS=12345ttt

3) Запустите
docker compose up -d --build


Сайт: http://localhost

(опционально) Adminer: http://localhost:8081

Инициализация БД (db/schema.sql) выполнится однократно, при первом создании пустого тома db_data.

4) Проверка коннекта PHP → Postgres (опционально)
docker compose exec php php -r '$pdo=new PDO(getenv("DB_DSN"),getenv("DB_USER"),getenv("DB_PASS")); echo "OK ".$pdo->query("select now()")->fetchColumn();'

5) Полезные команды
docker compose ps
docker compose logs -f db
docker compose logs -f web
docker compose logs -f php
docker compose exec web nginx -t && docker compose exec web nginx -s reload
docker compose down -v   # остановка и удаление томов (сотрёт БД!)

Запуск без Docker (Windows/macOS/Linux)
0) Установите

PHP 8.2+ (добавьте php в PATH).

PostgreSQL 16/17 (pgAdmin или psql).

1) Создайте БД и пользователя (один раз)

Через psql:

psql -U postgres -c 'CREATE ROLE "Admin" LOGIN PASSWORD '\''12345ttt'\'';'
psql -U postgres -c 'CREATE DATABASE cooking_site OWNER "Admin";'
psql -U Admin -d cooking_site -f db/schema.sql


Либо выполните db/schema.sql целиком через pgAdmin. Убедитесь, что расширение citext доступно.

2) Проверьте config.php

Для локального запуска без Docker укажите классический хост:

$host     = '127.0.0.1';
$port     = 5432;
$dbname   = 'cooking_site';
$user     = 'Admin';
$password = '12345ttt';

3) Поднимите встроенный сервер PHP

Из корня проекта:

php -S 127.0.0.1:8000 -t public


Откройте: http://127.0.0.1:8000

(Если порт занят — замените 8000 на свободный.)

Ключевые настройки
docker/nginx/default.conf
server {
  listen 80;
  server_name _;

  root /var/www/html/public;
  index index.php index.html;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass php:9000;
  }

  client_max_body_size 25m;
  sendfile on;
}


Рекомендации продакшн-хардена: запрет скрытых файлов, кэш статики, try_files $fastcgi_script_name =404;.

docker/php/Dockerfile (фрагмент)
RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev git unzip libcurl4-openssl-dev \
  && docker-php-ext-install pdo_pgsql pgsql mbstring curl \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

config.php

Читает DSN/креды из переменных окружения (для Docker), есть локальные фолбэки.

UPLOAD_DIR указывает на __DIR__ . '/public/images/' (файлы доступны по вебу).

Хуки/операция

Healthcheck БД в docker-compose.yml (pg_isready) — php ждёт service_healthy.

Авто-инициализация БД — db/schema.sql применяется при создании пустого тома.

OPcache включён в php.ini для ускорения PHP-FPM.

Статика — храните CSS/JS/изображения в public/, используйте абсолютные пути: /style.css, /images/....

Продакшн-деплой (VPS/Cloud)
Вариант A: Traefik (авто-HTTPS)

Настройте DNS (A-запись на IP сервера).

Добавьте сервис traefik и лейблы к web в docker-compose.prod.yml (наружу порты открывает только Traefik).

Запуск:

docker compose -f docker-compose.prod.yml up -d --build


Сертификаты Let’s Encrypt выдаются автоматически.

Вариант B: Render.com

Соберите единый контейнер (Nginx + PHP-FPM под supervisord), используйте DATABASE_URL.

Описывайте инфраструктуру через render.yaml (web-service + managed Postgres).

Важно: для public/images подключите Persistent Disk или S3-совместимое хранилище.

Загрузка файлов

Локально (Compose) — сохраняются в bind-mount, данные не теряются.

В облаке — используйте диск/S3: ephemeral-контейнеры теряют файловую систему при деплое.

Частые проблемы

CSS/JS не грузятся — файл не в public/ или относительный путь. Используйте /style.css, /script.js, /images/....

404 из админки — ссылка ведёт на public/index.php. Должно быть / или /index.php.

Нет коннекта к БД — проверьте DB_* в контейнере php, логи db.

Порт 80 занят — временно смените маппинг на "8080:80" и откройте http://localhost:8080.

Лицензия

Добавьте файл LICENSE по вашему выбору (MIT/Apache-2.0/GPL-3.0 и т.п.).

🇬🇧 English Version
About

Search across title/description/ingredients (ILIKE + light tokenization).

Categories, newest-first sorting.

Modal recipe details with structured ingredients (recipe_ingredients).

Image uploads into public/images/.

Safe HTML escaping (safe()), PDO prepared statements.

Docker services: db (Postgres), php (PHP-FPM), web (Nginx), optional adminer.

Tech Stack

PHP-FPM 8.2: pdo_pgsql, pgsql, mbstring, curl, opcache

Nginx (root: /var/www/html/public)

PostgreSQL 17 (init via ./db/schema.sql)

Docker Compose

Project Structure
project/
├─ db/
│  └─ schema.sql
├─ docker/
│  ├─ nginx/
│  │  └─ default.conf
│  └─ php/
│     ├─ Dockerfile
│     └─ php.ini
├─ partials/
├─ public/
│  ├─ index.php
│  ├─ get_recipe.php
│  ├─ style.css, script.js
│  └─ images/
├─ config.php
├─ .env
└─ docker-compose.yml

Run Locally (Docker)
1) Prereqs

Docker Desktop and Docker Compose installed.

Port 80 available (or change the mapping in compose).

2) .env
APP_ENV=prod
DB_NAME=cooking_site
DB_USER=Admin
DB_PASS=12345ttt

3) Start
docker compose up -d --build


App: http://localhost

(optional) Adminer: http://localhost:8081

DB init (db/schema.sql) runs once when the db_data volume is first created.

4) Connectivity check (optional)
docker compose exec php php -r '$pdo=new PDO(getenv("DB_DSN"),getenv("DB_USER"),getenv("DB_PASS")); echo "OK ".$pdo->query("select now()")->fetchColumn();'

5) Handy commands
docker compose ps
docker compose logs -f db
docker compose logs -f web
docker compose logs -f php
docker compose exec web nginx -t && docker compose exec web nginx -s reload
docker compose down -v   # stop & remove volumes (wipes the DB)

Run Without Docker
0) Install

PHP 8.2+

PostgreSQL 16/17

1) Create DB & user (once)
psql -U postgres -c 'CREATE ROLE "Admin" LOGIN PASSWORD '\''12345ttt'\'';'
psql -U postgres -c 'CREATE DATABASE cooking_site OWNER "Admin";'
psql -U Admin -d cooking_site -f db/schema.sql

2) Configure config.php
$host='127.0.0.1'; $port=5432; $dbname='cooking_site'; $user='Admin'; $password='12345ttt';

3) Start built-in PHP server
php -S 127.0.0.1:8000 -t public


Open http://127.0.0.1:8000

Key Settings
docker/nginx/default.conf

(see Russian section for the full snippet)

docker/php/Dockerfile

(ensure extensions: pdo_pgsql pgsql mbstring curl, enable opcache)

config.php

Reads env DSN/creds (Docker) with local fallbacks; UPLOAD_DIR → public/images/.

Ops Hooks

DB healthcheck via pg_isready; php waits for service_healthy.

DB init from db/ on first empty volume creation.

OPcache enabled in php.ini.

Static assets live under public/ with absolute URLs (/style.css, /images/...).

Production Deployment
A: Traefik (auto HTTPS)

Configure DNS (A-record → your VPS IP), add Traefik and labels, then:

docker compose -f docker-compose.prod.yml up -d --build

B: Render.com

Single container (Nginx + PHP-FPM via supervisord), use DATABASE_URL.

render.yaml provisions web + managed Postgres.

Use Persistent Disk or S3 for public/images.

File Uploads

Local (Compose): persisted via bind mount.

Cloud: use persistent storage (disk/S3) — ephemeral containers lose local FS on deploy.

Troubleshooting

CSS/JS not loading → not under public/ or using relative paths. Use /style.css, /script.js, /images/....

404 from admin → link points to public/index.php. Use / or /index.php.

DB connect error → verify DB_* in the php container, check db logs.

Port 80 busy → map "8080:80" and open http://localhost:8080.

License

Add a LICENSE file of your choice (MIT/Apache-2.0/GPL-3.0, etc.).
