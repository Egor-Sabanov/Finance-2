ПОЛНЫЙ ПАКЕТ FINANCE-2

Запуск на компьютере с XAMPP:
1. Распакуйте содержимое архива в C:\xampp\htdocs\Finance-admin-panel.
2. В XAMPP запустите Apache и MySQL.
3. Откройте http://localhost/Finance-admin-panel/frontend/login.php.
4. Документы и редактируемая ER-диаграмма лежат в папке «Документы».
5. Для старой базы один раз выполните database/migrate_budget_optimization.sql в phpMyAdmin → SQL.
6. Войдите как egor.demo@example.com / Demo123! и откройте «Бюджет» для демонстрации бюджета.

Чтобы изменить ER-схему, откройте Документы\ER_диаграмма_финансы.drawio на app.diagrams.net. PNG и SVG предназначены для просмотра.

Для планов запросов откройте database/optimization_queries.sql и выполните EXPLAIN. Скрипт database/verify_query_optimization.php выводит выбранные MariaDB индексы.
