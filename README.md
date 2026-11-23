# Веб-приложение для учета финансов и личного бюджета

**Автор:** Сабанов Егор Олегович  
**Этап:** роли, операции, административная панель, месячные бюджеты и оптимизация БД

## 1. Описание проекта

«Личный финансовый помощник» — PHP-приложение для учета доходов и расходов и планирования личного бюджета.

Реализованы:
- регистрация, вход, роли `user`/`admin` и серверная проверка доступа;
- список и карточка операций, поиск, фильтры и постраничная выдача;
- административный список, поиск, статусы, сортировка, редактирование и управление пользователями;
- месячный бюджет, лимиты расходов по категориям и уведомления о пороге;
- MariaDB-схема, SQL-миграция индексов и запросы для `EXPLAIN`.

Ручное создание операций, полноценный CSV-импорт, отчеты и диаграммы остаются следующими задачами.

## 2. Структура репозитория

```text
finance-assistant-control-point/
├── analysis/                # анализ предметной области и черновик ТЗ
├── ER/                      # редактируемая ER-схема + PNG + Mermaid
├── database/                # SQL-схема, тестовые INSERT и проверочные запросы
├── docs/                    # инструкции по установке и проверке
├── report/                  # отчет по первой контрольной точке
├── screenshots/             # подтверждение Git и структуры проекта
├── backend/                 # минимальная проверка подключения PHP → MySQL
├── frontend/                # PHP-интерфейс и локальный Bootstrap
├── .gitignore
└── README.md
```

## 3. Требуемое ПО

Для текущей контрольной точки достаточно:
- Windows 10/11;
- XAMPP (Apache + MySQL/MariaDB + phpMyAdmin);
- Git;
- любой редактор кода (например, VS Code);
- diagrams.net / draw.io — для редактирования ER-диаграммы.

Для следующего этапа разработки интерфейса понадобится Node.js LTS и npm.

## 4. Установка БД через XAMPP и phpMyAdmin

1. Установить XAMPP.
2. Запустить **Apache** и **MySQL** в XAMPP Control Panel.
3. Открыть `http://localhost/phpmyadmin/`.
4. Перейти на вкладку **Импорт**.
5. Выбрать `database/install.sql`.
6. Нажать **Импорт**.
7. В левой части phpMyAdmin должна появиться база `finance_app`.
8. Открыть базу и убедиться, что созданы таблицы:
   - `users`
   - `categories`
   - `transactions`
   - `import_batches`
   - `budgets`
   - `budget_limits`
   - `notifications`

Файл `database/install.sql` создает структуру, индексы и демонстрационные данные.
Для ранее установленной базы выполните `database/migrate_budget_optimization.sql`.

## 5. Проверка тестовых данных

После импорта открыть phpMyAdmin → `finance_app` → выполнить запрос:

```sql
SELECT COUNT(*) AS users_count FROM users;
SELECT COUNT(*) AS categories_count FROM categories;
SELECT COUNT(*) AS transactions_count FROM transactions;
SELECT COUNT(*) AS import_batches_count FROM import_batches;
SELECT COUNT(*) AS budgets_count FROM budgets;
SELECT COUNT(*) AS budget_limits_count FROM budget_limits;
SELECT COUNT(*) AS notifications_count FROM notifications;
```

Ожидаются ненулевые значения по всем таблицам.

Подробная проверка находится в `database/verify.sql`.

## 6. Проверка PHP → MySQL

Скопировать проект, например, в:

```text
C:\xampp\htdocs\finance-assistant-control-point\
```

Открыть:

```text
http://localhost/finance-assistant-control-point/backend/public/health.php
```

При корректном подключении будет JSON со статусом `ok` и именем базы `finance_app`.

Если root-пользователь XAMPP использует другой пароль, значения подключения задаются в `backend/config.php` (локально) на основе `backend/config.example.php`.

## 7. Как открыть редактируемую ER-диаграмму

Файл:

```text
ER/finance_app_ER.drawio
```

Открывается через https://app.diagrams.net/ или настольный draw.io/diagrams.net.

Дополнительно есть `ER/finance_app_ER.mmd` — Mermaid-исходник диаграммы и `ER/finance_app_ER.png` — готовое изображение для отчета.

## 8. Git

Репозиторий уже инициализирован локально и содержит несколько осмысленных коммитов. Проверить:

```bash
git log --oneline --decorate --graph --all
```

Пример ожидаемой истории в этом комплекте:

```text
* docs: add control point report
* db: add verification queries and install script
* db: align SQL schema with finance_app ER model
* docs: add analysis, draft TЗ and project guide
* init: create finance assistant control point
```

Для подтверждения в папке `screenshots/` находится `git_log.png` и текстовый `git_log.txt`.

## 9. Публикация на GitHub

Создать **пустой** репозиторий на GitHub без README, `.gitignore` и лицензии, затем выполнить:

```bash
git remote add origin https://github.com/ВАШ_USERNAME/finance-assistant.git
git remote -v
git push -u origin main
```

Если история коммитов уже создана локально, `push` отправит ее в GitHub вместе с коммитами. Подробная инструкция с GitHub CLI и обычным Git находится в `docs/02_GitHub.md`.

## 10. Готовые этапы

Данные бюджета проверяются на странице `frontend/budget.php`. В существующей базе экран открывает самый свежий бюджет пользователя. Суммы считают только завершенные расходы за период; лимиты считаются отдельно по категории. Предупреждения записываются в `notifications` и могут быть отмечены прочитанными.

## 11. Важно

Это учебная версия приложения. Ручной ввод операций, полноценный CSV-импорт, отчеты, диаграммы и производственное развертывание остаются дальнейшими задачами.
## 12. Реализованный этап — регистрация и авторизация

Текущий этап проекта включает рабочую регистрацию и авторизацию пользователя.

Реализовано:
- HTML5/CSS3 страницы `frontend/register.php` и `frontend/login.php`;
- серверная валидация в PHP;
- хеширование паролей через `password_hash()`;
- проверка через `password_verify()`;
- PDO prepared statements без эмуляции;
- защита HTML-форм от CSRF;
- сессии и `session_regenerate_id()` после входа;
- личный кабинет и выход;
- JSON API для будущего React-клиента.

Подробная инструкция находится в `docs/03_Авторизация_установка_и_проверка.md`.

## 13. Реализованные задания по операциям и ролям

- Роли `user` и `admin` хранятся в `users.role`; регистрация всегда создаёт обычного пользователя.
- Пользователь видит только собственные финансовые операции.
- Страница операций поддерживает поиск по описанию и категории, фильтры по типу, категории и диапазону дат, постраничная выдача — по 20 записей.
- Карточка операции показывает сумму, дату, категорию, описание и источник записи; чужая запись для пользователя отвечает 404.
- Начальная административная панель показывает записи всех пользователей с поиском и фильтрами. Доступ проверяется на сервере.
- Страница бюджета считает завершенные расходы выбранного месяца, отображает общий план и лимиты категорий, сохраняет пороговые уведомления.

Для существующей базы выполните `database/migrate_roles.sql` один раз, чтобы добавить поле роли. На новой базе поле уже создаётся файлами `database/schema.sql` и `database/install.sql`. Инструкция по входу в демо-аккаунт и назначению администратора приведена в `frontend/README.md`.

## 14. Индексы и планы запросов

Составные индексы `idx_transactions_user_date_id`, `idx_transactions_user_period_summary` и `idx_transactions_user_category_period` поддерживают ленту операций, месячную сводку и расчет лимитов. Сценарии `EXPLAIN` находятся в `database/optimization_queries.sql`; короткую проверку фактического плана запускает `php database/verify_query_optimization.php` при работающей БД.

Полный пакет для передачи куратору — `Finance-2-full-package.zip`. Документы и редактируемая диаграмма находятся в папке `Документы`.
