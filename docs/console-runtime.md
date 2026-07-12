# Wonder Console runtime

The Genesis Console is a framework-free editor workspace served as static files from `apps/console`.

## Start the API

Install PHP dependencies, apply the PostgreSQL migration and run:

```bash
php -S 0.0.0.0:8080 -t apps/api/public
```

## Start the Console

In a second terminal:

```bash
php -S 0.0.0.0:8081 -t apps/console
```

Open `http://localhost:8081`.

The Console expects the API at `http://localhost:8080`. A different base URL can be set in the browser console:

```js
localStorage.setItem('wonderos_api_base', 'https://api.example.com');
location.reload();
```

## Genesis workflow

1. Open the Console.
2. Choose **Create Barn Owl**.
3. Review the prefilled canonical fields.
4. Create the entity through `POST /v1/entities`.
5. Inspect its permanent Wonder ID, status, confidence and revision.
6. Use `Command/Ctrl + K` to focus search.
7. Search remembered entities by name or query the canonical API directly with a Wonder ID.

The Console stores only a lightweight recent-entity index in local browser storage. PostgreSQL remains the source of truth.
