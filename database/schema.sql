CREATE DATABASE IF NOT EXISTS opd_admin;
USE opd_admin;

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  sku VARCHAR(100),
  imageUrl VARCHAR(255),
  price DECIMAL(10,2),
  status VARCHAR(50),
  inventory INT,
  category VARCHAR(120),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS orders (
  id VARCHAR(64) PRIMARY KEY,
  number VARCHAR(100),
  status VARCHAR(50),
  customerName VARCHAR(255),
  customerEmail VARCHAR(255),
  customerPhone VARCHAR(50),
  address1 VARCHAR(255),
  address2 VARCHAR(255),
  city VARCHAR(100),
  state VARCHAR(100),
  postal VARCHAR(20),
  country VARCHAR(100),
  notes TEXT,
  userId VARCHAR(64),
  total DECIMAL(10,2),
  currency VARCHAR(10),
  paymentStatus VARCHAR(50),
  fulfillmentStatus VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
);

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

CREATE TABLE IF NOT EXISTS product_associations (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  relatedProductId VARCHAR(64),
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

CREATE TABLE IF NOT EXISTS cart_accounting (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
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

CREATE TABLE IF NOT EXISTS order_accounting (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
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
  purchaseLimitOrder DECIMAL(12,2),
  purchaseLimitDay DECIMAL(12,2),
  purchaseLimitMonth DECIMAL(12,2),
  limitNone TINYINT(1),
  paymentMethodId VARCHAR(64),
  smsConsent TINYINT(1),
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
  parentId VARCHAR(64),
  category VARCHAR(32),
  position INT,
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS customers (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  status VARCHAR(50),
  ltv DECIMAL(12,2),
  tags VARCHAR(255),
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS inventory (
  id VARCHAR(64) PRIMARY KEY,
  sku VARCHAR(100),
  location VARCHAR(120),
  onHand INT,
  reserved INT,
  available INT,
  reorderPoint INT,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS promotions (
  id VARCHAR(64) PRIMARY KEY,
  code VARCHAR(100),
  type VARCHAR(50),
  value DECIMAL(10,2),
  status VARCHAR(50),
  startsAt DATETIME,
  endsAt DATETIME,
  usageLimit INT,
  used INT,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS payments (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  method VARCHAR(60),
  amount DECIMAL(10,2),
  status VARCHAR(50),
  capturedAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS payment_methods (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  label VARCHAR(255),
  type VARCHAR(50),
  last4 VARCHAR(8),
  expMonth INT,
  expYear INT,
  createdAt DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS shipments (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  carrier VARCHAR(100),
  tracking VARCHAR(120),
  status VARCHAR(50),
  shippedAt DATETIME,
  eta DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS analytics_reports (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  period VARCHAR(50),
  metric VARCHAR(100),
  value DECIMAL(12,2),
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS content_pages (
  id VARCHAR(64) PRIMARY KEY,
  title VARCHAR(255),
  slug VARCHAR(255),
  status VARCHAR(50),
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  email VARCHAR(255),
  companyName VARCHAR(255),
  cellPhone VARCHAR(50),
  address VARCHAR(255),
  city VARCHAR(120),
  state VARCHAR(120),
  zip VARCHAR(20),
  passwordHash VARCHAR(255),
  role VARCHAR(50),
  status VARCHAR(50),
  lastLogin DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS integrations (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  type VARCHAR(100),
  status VARCHAR(50),
  lastSync DATETIME,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS settings (
  id VARCHAR(64) PRIMARY KEY,
  `key` VARCHAR(100),
  `value` TEXT,
  updatedAt DATETIME
);

CREATE TABLE IF NOT EXISTS reliability (
  id VARCHAR(64) PRIMARY KEY,
  type VARCHAR(50),
  status VARCHAR(50),
  message TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
);
