# OPD Admin (PHP + MySQL)

## Local setup (WAMP)

1. Copy `.env.example` to `.env` and update the MySQL credentials.
2. In phpMyAdmin, create the database and tables:
   - Import `database/schema.sql`
   - Import `database/seed.sql` (optional)
3. Point your WAMP Virtual Host (DocumentRoot) to `OPD_Website/public`.
4. Ensure `pdo_mysql` is enabled in `php.ini`.
5. Create an admin user:
   - `php scripts/create_admin.php --email admin@opd.com --password "ChangeMe123" --name "Admin" --role admin`
6. Visit `http://localhost/admin.php` (admin login at `http://localhost/admin-login.php`).

### Schema updates (if you already imported an older schema)

Run these in phpMyAdmin or the MySQL CLI:

```sql
ALTER TABLE users ADD COLUMN passwordHash VARCHAR(255);
ALTER TABLE orders
  ADD COLUMN customerEmail VARCHAR(255),
  ADD COLUMN customerPhone VARCHAR(50),
  ADD COLUMN address1 VARCHAR(255),
  ADD COLUMN address2 VARCHAR(255),
  ADD COLUMN city VARCHAR(100),
  ADD COLUMN state VARCHAR(100),
  ADD COLUMN postal VARCHAR(20),
  ADD COLUMN country VARCHAR(100),
  ADD COLUMN notes TEXT,
  ADD COLUMN userId VARCHAR(64);
```

Then import the new tables from `database/schema.sql` or run:

```sql
CREATE TABLE IF NOT EXISTS product_variants (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  name VARCHAR(255),
  sku VARCHAR(100),
  price DECIMAL(10,2),
  inventory INT,
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS carts (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS cart_items (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  productId VARCHAR(64),
  variantId VARCHAR(64),
  quantity INT,
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS order_items (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  productId VARCHAR(64),
  variantId VARCHAR(64),
  name VARCHAR(255),
  price DECIMAL(10,2),
  quantity INT,
  total DECIMAL(10,2),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS favorites (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  productId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS clients (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  status VARCHAR(50),
  notes TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS vendors (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  contact VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS equipment (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  serial VARCHAR(120),
  status VARCHAR(50),
  location VARCHAR(120),
  notes TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS accounting_codes (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  code VARCHAR(120),
  description VARCHAR(255),
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
);
```

## Bluehost

1. Create a MySQL database and user in cPanel.
2. Upload the `OPD_Website` folder contents.
3. Set the document root to `OPD_Website/public`.
4. Update `.env` with Bluehost DB credentials.
5. Import `database/schema.sql` and `database/seed.sql` using phpMyAdmin.
6. Create an admin user using `scripts/create_admin.php` from the server shell or phpMyAdmin.
7. Admin login: `/admin-login.php`. Customer login: `/login.php`.

## API endpoints

All admin panels talk to these endpoints:

- `/api/products.php`
- `/api/orders.php`
- `/api/customers.php`
- `/api/inventory.php`
- `/api/promotions.php`
- `/api/payments.php`
- `/api/shipments.php`
- `/api/analytics.php`
- `/api/content.php`
- `/api/users.php`
- `/api/integrations.php`
- `/api/settings.php`
- `/api/reliability.php`

## Brain Tool

See [BRAIN_TOOL](BRAIN_TOOL.md) for quick start and usage.

