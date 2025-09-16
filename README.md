# Cooking-Site-
Современный сайт с рецептами на PHP 8.2 + Nginx + PostgreSQL. Поиск по рецептам (ILIKE), категории, модальные карточки, адаптивный интерфейс и загрузка изображений. Готов к деплою через Docker Compose; init-скрипты БД в /db, прод-конфиг для Traefik/HTTPS.

О проекте

Сайт рецептов на PHP 8.2 + Nginx + PostgreSQL. Поддерживает поиск (ILIKE), категории, модальные карточки рецепта, адаптивный интерфейс и загрузку изображений. Собран для запуска в Docker, с автоматической инициализацией БД.

Возможности

Поиск по названию/описанию/ингредиентам (ILIKE, токенизация).

Категории рецептов, сортировка по дате.

Модалка с деталями рецепта и списком ингредиентов (таблица recipe_ingredients).

Загрузка изображений на сервер.

Безопасное экранирование HTML (safe()), PDO с подготовленными запросами.

Готовые Docker-сервисы: db, php, web (+ опционально adminer).

Технологический стек

PHP-FPM 8.2 (расширения: pdo_pgsql, pgsql, mbstring, curl, opcache)

Nginx (root: /var/www/html/public)

PostgreSQL 17 (инициализация из ./db/schema.sql)

Docker Compose

Структура проекта
project/
├─ db/                        # SQL-инициализация БД (выполняется при первом старте тома)
│  └─ schema.sql
├─ docker/
│  ├─ nginx/
│  │  └─ default.conf         # конфиг nginx (root -> /public)
│  └─ php/
│     ├─ Dockerfile
│     └─ php.ini
├─ partials/                  # хедер/футер
├─ public/                    # корневая папка сайта (доступна из браузера)
│  ├─ index.php
│  ├─ get_recipe.php
│  ├─ style.css, script.js
│  └─ images/                 # сюда сохраняются загружаемые изображения
├─ config.php                 # подключение БД, загрузки, утилиты
├─ .env                       # переменные окружения (не коммитить!)
└─ docker-compose.yml

Быстрый старт (локально)
1) Предусловия

Установлены Docker и Docker Compose.

Свободен порт 80 (или измените публикацию порта в compose).

2) Настройте .env
APP_ENV=prod
DB_NAME=cooking_site
DB_USER=
DB_PASS=

3) Запуск
docker compose up -d --build


Сайт: http://localhost

(опционально) Админер: http://localhost:8081

Инициализация БД (db/schema.sql) выполнится только при первом старте пустого тома db_data.

4) Проверка соединения PHP → Postgres (опционально)
docker compose exec php php -r '$pdo=new PDO(getenv("DB_DSN"),getenv("DB_USER"),getenv("DB_PASS")); echo "OK ".$pdo->query("select now()")->fetchColumn();'

5) Полезные команды
docker compose ps
docker compose logs -f web
docker compose logs -f php
docker compose logs -f db
docker compose exec web nginx -t && docker compose exec web nginx -s reload
docker compose down -v   # стоп и удаление томов (сотрёт БД!)

Ключевые файлы и настройки
Nginx (docker/nginx/default.conf)

root: /var/www/html/public

Красивые URL:

location / { try_files $uri $uri/ /index.php?$query_string; }


Проксирование PHP:

location ~ \.php$ {
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_pass php:9000;
}


Рекомендации: запрет скрытых файлов, кэш статики, try_files $fastcgi_script_name =404;.

PHP (docker/php/Dockerfile)

Убедитесь, что ставятся нужные расширения:

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev git unzip libcurl4-openssl-dev \
  && docker-php-ext-install pdo_pgsql pgsql mbstring curl \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

config.php

Читает DSN/креды из ENV (или собирает по умолчанию).

UPLOAD_DIR указывает на __DIR__ . '/public/images/' — чтобы файлы были доступны по вебу.

Хуки (операционные)

Healthcheck БД — в docker-compose.yml (pg_isready), сервис php ждёт service_healthy.

Инициализация БД — папка db/ монтируется в /docker-entrypoint-initdb.d (выполняется при создании кластера).

Опкеш PHP — включён в php.ini (ускорение FPM).

Статика — кладите CSS/JS/изображения в public/ и используйте абсолютные пути (/style.css, /images/...).

Продакшн деплой (VPS + Docker Compose + HTTPS)
Вариант 1: через Traefik (авто-HTTPS)

Настройте DNS для домена (A-запись на IP VPS).

Добавьте traefik и лейблы к web в docker-compose.prod.yml (вынесите порты наружу только у Traefik).

Запустите:

docker compose -f docker-compose.prod.yml up -d --build


Traefik выпишет сертификаты Let’s Encrypt автоматически.

Вариант 2: Render.com

Соберите единый образ (Nginx + PHP-FPM через supervisord), настройте переменную DATABASE_URL.

Используйте render.yaml (Blueprint) для веб-сервиса и управляемого Postgres.

Важно: постоянное хранилище для public/images — подключите Persistent Disk или S3.

Тонкости загрузки файлов

Локально (compose) изображения сохраняются в bind-маунт → не пропадают.
В облаке с бессостоячными контейнерами используйте диск/облако (например, S3).

Частые проблемы и решения

CSS/JS не грузятся — файл не в public/ или относительный путь. Используйте /style.css, /script.js, /images/....

404 при переходе из админки — ссылка на public/index.php. Должно быть / или /index.php.

Нет коннекта к БД — проверьте DB_* в php (docker compose exec php env | grep DB_), логи db.

Порт 80 занят — временно меняйте на "8080:80" и открывайте http://localhost:8080.

Лицензия

Добавьте файл LICENSE по вашему выбору (MIT/Apache-2.0/GPL-3.0 и т.д.).

🇬🇧 English Version
About

Recipe website powered by PHP 8.2, Nginx, and PostgreSQL. It features ILIKE-based search, categories, modal recipe view, responsive UI, and image uploads. Docker-first setup with automatic DB initialization.

Features

Full-text–like search (ILIKE with tokenization) across title/description/ingredients.

Categories, newest-first ordering.

Modal recipe details with structured ingredients (recipe_ingredients).

Image uploads stored under public/images.

Secure output escaping (safe()), PDO prepared statements.

Ready-made Docker services: db, php, web (+ optional adminer).

Tech Stack

PHP-FPM 8.2 (pdo_pgsql, pgsql, mbstring, curl, opcache)

Nginx (root: /var/www/html/public)

PostgreSQL 17 (init from ./db/schema.sql)

Docker Compose

Project Structure
project/
├─ db/                        # DB init (runs on first empty volume creation)
│  └─ schema.sql
├─ docker/
│  ├─ nginx/
│  │  └─ default.conf
│  └─ php/
│     ├─ Dockerfile
│     └─ php.ini
├─ partials/
├─ public/                    # web root
│  ├─ index.php
│  ├─ get_recipe.php
│  ├─ style.css, script.js
│  └─ images/                 # uploaded media
├─ config.php
├─ .env
└─ docker-compose.yml

Quick Start (local)
1) Prereqs

Docker and Docker Compose installed.

Port 80 available (or change the mapping).

2) .env
APP_ENV=prod
DB_NAME=cooking_site
DB_USER=Admin
DB_PASS=strong_password

3) Run
docker compose up -d --build


App: http://localhost

(optional) Adminer: http://localhost:8081

DB init (db/schema.sql) runs only once when the db_data volume is created.

4) Connectivity test (optional)
docker compose exec php php -r '$pdo=new PDO(getenv("DB_DSN"),getenv("DB_USER"),getenv("DB_PASS")); echo "OK ".$pdo->query("select now()")->fetchColumn();'

5) Useful commands
docker compose ps
docker compose logs -f web
docker compose logs -f php
docker compose logs -f db
docker compose exec web nginx -t && docker compose exec web nginx -s reload
docker compose down -v   # stop & remove volumes (wipes DB!)

Key Files & Settings
Nginx (docker/nginx/default.conf)

root: /var/www/html/public

Pretty URLs:

location / { try_files $uri $uri/ /index.php?$query_string; }


PHP proxy:

location ~ \.php$ {
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_pass php:9000;
}


Recommended: deny dotfiles, cache static assets, try_files $fastcgi_script_name =404;.

PHP (docker/php/Dockerfile)

Ensure extensions are installed:

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev git unzip libcurl4-openssl-dev \
  && docker-php-ext-install pdo_pgsql pgsql mbstring curl \
  && docker-php-ext-enable opcache \
  && rm -rf /var/lib/apt/lists/*

config.php

Reads DSN/credentials from env (fallbacks included).

UPLOAD_DIR points to __DIR__ . '/public/images/' so files are web-served.

Hooks (operational)

DB healthcheck in docker-compose.yml (pg_isready), php waits for service_healthy.

DB init from db/ on first run of an empty volume.

PHP opcache enabled in php.ini.

Static assets go under public/; use absolute URLs (/style.css, /images/...).

Production Deploy (VPS + Docker Compose + HTTPS)
Option 1: Traefik (auto HTTPS)

Configure DNS (A-record to your VPS IP).

Add traefik service and labels to web in docker-compose.prod.yml (only Traefik exposes ports).

Run:

docker compose -f docker-compose.prod.yml up -d --build


Traefik will provision Let’s Encrypt certs automatically.

Option 2: Render.com

Build a single container (Nginx + PHP-FPM via supervisord), use DATABASE_URL.

render.yaml (Blueprint) provisions a web service + managed Postgres.

Important: use a Persistent Disk or S3 for public/images.

File Uploads

Local (compose): uploads persist via bind mount.
Cloud: use persistent storage (disk/S3) — ephemeral containers lose local files on deploys.

Troubleshooting

CSS/JS not loading — file not in public/ or relative path. Use /style.css, /script.js, /images/....

404 from admin — link pointing to public/index.php. Use / or /index.php.

DB connect error — verify DB_* in the php container and check db logs.

Port 80 in use — map "8080:80" and open http://localhost:8080.

License

Add a LICENSE file of your choice (MIT/Apache-2.0/GPL-3.0, etc.).
