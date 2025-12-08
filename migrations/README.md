Migration files
===============

Place SQL migration files in this directory. Files are applied in filename order by `bin/migrate`.

Example usage (from project root):

```bash
DB_HOST=127.0.0.1 DB_USER=oauth_user DB_PASS=supersecret DB_NAME=oauth_bridge ./bin/migrate
```

The example `2025_12_08_add_redirect_uri.sql` adds the `redirect_uri` column to `oauth_start_tokens`.
