# API runtime

Run the first WonderOS API with PHP's development server after applying the PostgreSQL migration:

```bash
composer install
php -S 0.0.0.0:8080 -t apps/api/public
```

Required environment variables:

```text
DATABASE_DSN=pgsql:host=127.0.0.1;port=5432;dbname=wonderos
DATABASE_USER=wonderos
DATABASE_PASSWORD=wonderos
```

Create Barn Owl:

```bash
curl -X POST http://localhost:8080/v1/entities \
  -H 'Content-Type: application/json' \
  -d '{"canonical_name":"Barn Owl","family":"Living Things","type":"Bird","confidence":0.9}'
```

Retrieve it using the returned permanent Wonder ID:

```bash
curl http://localhost:8080/v1/entities/WND-ENT-000001
```
