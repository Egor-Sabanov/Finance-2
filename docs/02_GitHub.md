# Публикация проекта на GitHub

## Вариант 1 — через Git Bash

1. Создать на GitHub новый **пустой** репозиторий, например `finance-assistant`.
2. Не создавать на GitHub README, `.gitignore` и License, потому что они уже находятся в локальном проекте.
3. Открыть Git Bash в корне проекта.
4. Проверить состояние:

```bash
git status
```

5. Добавить remote:

```bash
git remote add origin https://github.com/ВАШ_USERNAME/finance-assistant.git
```

6. Проверить:

```bash
git remote -v
```

7. Отправить историю:

```bash
git push -u origin main
```

После push открыть страницу репозитория GitHub. В ней должны быть видны файлы, коммиты и README.

## Вариант 2 — GitHub Desktop

1. Установить GitHub Desktop.
2. Add existing repository.
3. Выбрать корневую папку проекта.
4. Нажать Publish repository.
5. Указать имя `finance-assistant`.
6. При необходимости сделать репозиторий Public.

## Что показать преподавателю

Показать:

```bash
git log --oneline --decorate --graph --all
```

и страницу GitHub с вкладкой Code и историей Commits.

## Безопасность

Не отправлять в GitHub реальные пароли, API-ключи и токены. Для локальных секретов использовать `.env`, который исключён через `.gitignore`.
