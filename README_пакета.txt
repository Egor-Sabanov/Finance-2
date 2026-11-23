ПОЛНЫЙ ПАКЕТ FINANCE-2

Запуск на компьютере с XAMPP:
1. Распакуйте содержимое архива в C:\xampp\htdocs\Finance-admin-panel.
2. В XAMPP запустите Apache и MySQL.
3. Откройте http://localhost/Finance-admin-panel/frontend/login.php.
4. Документы и редактируемая ER-диаграмма лежат в папке «Документы».
5. Для старой базы один раз выполните database/migrate_budget_optimization.sql в phpMyAdmin → SQL.
6. Войдите как egor.demo@example.com / Demo123! и откройте «Бюджет» или «Конвертер валют».

Чтобы изменить ER-схему, откройте Документы\ER_диаграмма_финансы.drawio на app.diagrams.net. PNG и SVG предназначены для просмотра.

Для планов запросов откройте database/optimization_queries.sql и выполните EXPLAIN. Скрипт database/verify_query_optimization.php выводит выбранные MariaDB индексы.

Для существующей базы выполните database/migrate_external_rates.sql. Страница frontend/currency.php берет XML-курсы Банка России и кэширует их в exchange_rates; при недоступности сети показывает последний сохраненный курс.
Журнал ошибок находится в var/logs/application.log. Apache запрещает читать его через браузер, а файл журнала не включается в Git.
