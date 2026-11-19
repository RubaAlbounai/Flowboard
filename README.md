# Flowboard

A small project manager built with Laravel and plain JavaScript. You sign up, create projects and move tasks across a board (To do, In progress, Done), a bit like a very reduced Trello or ClickUp. I built it to practise authentication, relationships between models, ordering and testing.

There are two parts: a REST API in Laravel, and a front end in `public/` (a landing page, sign in and register pages, and the app itself). No build step and no framework on the front end.

## What it does

- Sign up and log in. Every user only sees their own projects.
- Projects with a name, a colour and a description, and a progress bar.
- Tasks with a title, description, status, priority (low, normal, high, urgent) and due date.
- Board view with drag and drop between columns, or list view. The order of the cards is saved.
- Search and a priority filter. Overdue and soon-due dates are highlighted.

## Run it locally

You need PHP 8.0.2 or newer, Composer and MySQL.

```bash
git clone https://github.com/RubaAlbounai/Flowboard.git
cd To-do-list
composer install
cp .env.example .env
php artisan key:generate
```

Create a database called `todo_list` (or change `DB_DATABASE` in `.env`), then:

```bash
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000. The seeder adds a demo account with two projects: `demo@example.com` / `password`. It is only meant for local use.

## API

Everything except `register` and `login` needs the header `Authorization: Bearer <token>`.

| Method | URL | What it does |
| --- | --- | --- |
| POST | `/api/register` | Create an account, returns the user and a token |
| POST | `/api/login` | Returns the user and a token |
| POST | `/api/logout` | Deletes the current token |
| GET | `/api/user` | The logged-in user |
| GET, POST | `/api/projects` | List your projects (with task counts) or create one |
| GET, PUT, DELETE | `/api/projects/{id}` | Show, rename or delete a project (deleting also deletes its tasks) |
| GET | `/api/projects/{id}/tasks` | Tasks of a project, in board order. Filters: `status`, `priority`, `search` |
| POST | `/api/projects/{id}/tasks` | Add a task to the bottom of its column |
| GET, PUT, DELETE | `/api/tasks/{id}` | Show, update or delete a task |
| POST | `/api/tasks/{id}/move` | Move a task: `{"status": "done", "position": 0}` |

Statuses are `pending`, `in-progress` and `done`. Responses: `200`/`201` on success, `401` without a valid token, `404` for something that does not exist or belongs to somebody else, `422` for invalid data.

The file `To do list Postman.postman_collection.json` has a request for each endpoint. Run "Log in" first, it saves the token for the other requests.

## How the order of cards works

Each task has a `position` inside its column. A new task goes to the bottom. When a card is moved, the server rebuilds the positions of the target column (and of the old one if the status changed) as 0, 1, 2... so there are no gaps. Deleting a task closes the gap the same way.

## Tests

```bash
php artisan test
```

They use an in-memory SQLite database, so the `pdo_sqlite` PHP extension must be enabled. They cover registration and login, the projects of one user being hidden from another, validation, filters, moving and re-ordering cards, and deleting.

## Limits

- Projects belong to one user. There are no teams, assignees or comments yet.
- The three statuses are fixed.
- Tokens do not expire.
