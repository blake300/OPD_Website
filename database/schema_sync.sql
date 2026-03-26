-- OPD schema sync (idempotent)
-- Re-run safely to create missing tables/columns.
SET @db := DATABASE();

-- Base tables
CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  sku VARCHAR(100),
  imageUrl VARCHAR(255),
  price DECIMAL(10,2),
  status VARCHAR(50),
  featured TINYINT(1) DEFAULT 0,
  service TINYINT(1),
  largeDelivery TINYINT(1) DEFAULT 0,
  daysOut INT,
  posNum INT,
  inventory INT,
  invStockTo INT,
  invMin INT,
  category VARCHAR(120),
  shortDescription TEXT,
  longDescription TEXT,
  wgt DECIMAL(10,2),
  lng DECIMAL(10,2),
  wdth DECIMAL(10,2),
  hght DECIMAL(10,2),
  tags VARCHAR(255),
  vnName VARCHAR(255),
  vnContact VARCHAR(255),
  vnPrice DECIMAL(10,2),
  compName VARCHAR(255),
  compPrice DECIMAL(10,2),
  shelfNum VARCHAR(120),
  estFreight DECIMAL(10,2),
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS product_images (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX productId (productId)
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
  billingFirstName VARCHAR(100),
  billingLastName VARCHAR(100),
  billingCompany VARCHAR(255),
  billingAddress1 VARCHAR(255),
  billingAddress2 VARCHAR(255),
  billingCity VARCHAR(100),
  billingStateCode VARCHAR(100),
  billingEmail VARCHAR(255),
  billingPhone VARCHAR(50),
  billingPostcode VARCHAR(20),
  shippingFirstName VARCHAR(100),
  shippingLastName VARCHAR(100),
  shippingCompany VARCHAR(255),
  shippingAddress1 VARCHAR(255),
  shippingAddress2 VARCHAR(255),
  shippingCity VARCHAR(100),
  shippingStateCode VARCHAR(100),
  shippingPhone VARCHAR(50),
  shippingPostcode VARCHAR(20),
  notes TEXT,
  userId VARCHAR(64),
  clientId VARCHAR(64),
  clientUserId VARCHAR(64),
  orderAmount DECIMAL(10,2),
  total DECIMAL(10,2),
  tax DECIMAL(10,2),
  shipping DECIMAL(10,2),
  refundAmount DECIMAL(10,2),
  currency VARCHAR(10),
  shippingMethod VARCHAR(50),
  deliveryZone TINYINT,
  deliveryClass VARCHAR(10),
  paymentStatus VARCHAR(50),
  fulfillmentStatus VARCHAR(50),
  approvalStatus VARCHAR(20) DEFAULT 'Not required',
  approvalSentAt DATETIME,
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS product_variants (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  name VARCHAR(255),
  sku VARCHAR(100),
  price DECIMAL(10,2),
  largeDelivery TINYINT(1) DEFAULT 0,
  inventory INT,
  invStockTo INT,
  invMin INT,
  status VARCHAR(50),
  posNum INT,
  shortDescription TEXT,
  longDescription TEXT,
  wgt DECIMAL(10,2),
  lng DECIMAL(10,2),
  wdth DECIMAL(10,2),
  hght DECIMAL(10,2),
  tags VARCHAR(255),
  vnName VARCHAR(255),
  vnContact VARCHAR(255),
  vnPrice DECIMAL(10,2),
  compName VARCHAR(255),
  compPrice DECIMAL(10,2),
  shelfNum VARCHAR(120),
  estFreight DECIMAL(10,2),
  parentName VARCHAR(255),
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS product_associations (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  relatedProductId VARCHAR(64),
  sortOrder INT,
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
  arrivalDate DATE,
  associationSourceProductId VARCHAR(64),
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
  productName VARCHAR(255),
  variantName VARCHAR(255),
  sku VARCHAR(100),
  price DECIMAL(10,2),
  quantity INT,
  total DECIMAL(10,2),
  arrivalDate DATE,
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
CREATE TABLE IF NOT EXISTS favorite_categories (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(120),
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS favorite_entries (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  categoryId VARCHAR(64),
  productId VARCHAR(64),
  variantId VARCHAR(64),
  quantity INT,
  splitsJson MEDIUMTEXT,
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS clients (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  email VARCHAR(255),
  linkedUserId VARCHAR(64),
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
  linkedUserId VARCHAR(64),
  phone VARCHAR(50),
  status VARCHAR(50),
  purchaseLimitOrder DECIMAL(12,2),
  purchaseLimitDay DECIMAL(12,2),
  purchaseLimitMonth DECIMAL(12,2),
  limitNone TINYINT(1),
  autoApprove TINYINT(1) DEFAULT 1,
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
  status VARCHAR(50) DEFAULT 'Pending Approval',
  location VARCHAR(120),
  notes TEXT,
  contactName VARCHAR(255),
  contactPhone VARCHAR(50),
  contactEmail VARCHAR(255),
  quantity INT DEFAULT 1,
  price DECIMAL(10,2),
  productId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_userId (userId),
  INDEX idx_status (status),
  INDEX idx_productId (productId)
);
CREATE TABLE IF NOT EXISTS equipment_images (
  id VARCHAR(64) PRIMARY KEY,
  equipmentId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_equipmentId (equipmentId)
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
  externalId VARCHAR(255),
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
  brand VARCHAR(50),
  last4 VARCHAR(8),
  stripePaymentMethodId VARCHAR(255),
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
CREATE TABLE IF NOT EXISTS pages (
  id VARCHAR(64) PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  template VARCHAR(50) DEFAULT 'custom',
  status VARCHAR(20) DEFAULT 'draft',
  metaDescription TEXT,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_slug (slug),
  INDEX idx_status (status)
);
CREATE TABLE IF NOT EXISTS page_sections (
  id VARCHAR(64) PRIMARY KEY,
  pageId VARCHAR(64) NOT NULL,
  sectionType VARCHAR(50) NOT NULL,
  content JSON,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_pageId (pageId),
  FOREIGN KEY (pageId) REFERENCES pages(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  lastName VARCHAR(120),
  email VARCHAR(255),
  companyName VARCHAR(255),
  cellPhone VARCHAR(50),
  address VARCHAR(255),
  address2 VARCHAR(255),
  city VARCHAR(120),
  state VARCHAR(120),
  zip VARCHAR(20),
  shippingFirstName VARCHAR(120),
  shippingLastName VARCHAR(120),
  shippingCompany VARCHAR(255),
  shippingAddress1 VARCHAR(255),
  shippingAddress2 VARCHAR(255),
  shippingCity VARCHAR(120),
  shippingState VARCHAR(120),
  shippingPostcode VARCHAR(20),
  shippingPhone VARCHAR(50),
  bioNotes TEXT,
  passwordHash VARCHAR(255),
  stripeCustomerId VARCHAR(255),
  role VARCHAR(50),
  status VARCHAR(50),
  allowInvoice TINYINT(1) DEFAULT 0,
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
CREATE TABLE IF NOT EXISTS rate_limit (
  id VARCHAR(255) PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt DATETIME NOT NULL,
  INDEX idx_identifier_type (identifier, type),
  INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reliability (
  id VARCHAR(64) PRIMARY KEY,
  type VARCHAR(50),
  status VARCHAR(50),
  message TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
);
CREATE TABLE IF NOT EXISTS remember_me_tokens (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64) NOT NULL,
  tokenHash VARCHAR(128) NOT NULL,
  expiresAt DATETIME NOT NULL,
  createdAt DATETIME NOT NULL,
  INDEX idx_remember_user (userId),
  INDEX idx_remember_expires (expiresAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure missing columns exist
SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'sku');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `sku` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'imageUrl');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `imageUrl` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'price');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `price` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'featured');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `featured` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'service');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `service` TINYINT(1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'largeDelivery');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `largeDelivery` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'daysOut');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `daysOut` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'posNum');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `posNum` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'inventory');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `inventory` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'invStockTo');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `invStockTo` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'invMin');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `invMin` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'category');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `category` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'shortDescription');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `shortDescription` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'longDescription');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `longDescription` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'wgt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `wgt` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'lng');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `lng` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'wdth');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `wdth` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'hght');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `hght` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'tags');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `tags` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'vnName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `vnName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'vnContact');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `vnContact` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'vnPrice');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `vnPrice` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'compName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `compName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'compPrice');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `compPrice` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'shelfNum');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `shelfNum` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'estFreight');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `estFreight` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `products` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'url');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `url` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'isPrimary');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `isPrimary` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `sortOrder` INT DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_images` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'number');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `number` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'customerName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `customerName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'customerEmail');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `customerEmail` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'customerPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `customerPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'address1');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `address1` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'address2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `address2` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'city');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `city` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'state');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `state` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'postal');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `postal` VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'country');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `country` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingFirstName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingFirstName` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingLastName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingLastName` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingCompany');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingCompany` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingAddress1');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingAddress1` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingAddress2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingAddress2` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingCity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingCity` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingStateCode');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingStateCode` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingEmail');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingEmail` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'billingPostcode');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `billingPostcode` VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingFirstName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingFirstName` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingLastName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingLastName` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingCompany');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingCompany` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingAddress1');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingAddress1` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingAddress2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingAddress2` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingCity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingCity` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingStateCode');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingStateCode` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingPostcode');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingPostcode` VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'notes');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `notes` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'clientId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `clientId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'clientUserId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `clientUserId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'orderAmount');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `orderAmount` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'total');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `total` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'tax');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `tax` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shipping');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shipping` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'refundAmount');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `refundAmount` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'currency');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `currency` VARCHAR(10)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'shippingMethod');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `shippingMethod` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'deliveryZone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `deliveryZone` TINYINT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'deliveryClass');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `deliveryClass` VARCHAR(10)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'paymentStatus');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `paymentStatus` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'fulfillmentStatus');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `fulfillmentStatus` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'approvalStatus');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `approvalStatus` VARCHAR(20) DEFAULT ''Not required''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'approvalSentAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `approvalSentAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `orders` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'sku');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `sku` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'price');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `price` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'largeDelivery');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `largeDelivery` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'inventory');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `inventory` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'invStockTo');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `invStockTo` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'invMin');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `invMin` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'posNum');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `posNum` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'shortDescription');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `shortDescription` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'longDescription');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `longDescription` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'wgt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `wgt` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'lng');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `lng` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'wdth');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `wdth` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'hght');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `hght` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'tags');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `tags` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'vnName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `vnName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'vnContact');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `vnContact` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'vnPrice');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `vnPrice` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'compName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `compName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'compPrice');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `compPrice` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'shelfNum');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `shelfNum` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'estFreight');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `estFreight` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'parentName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `parentName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_variants` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'relatedProductId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `relatedProductId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `sortOrder` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `product_associations` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'cartId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `cartId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'variantId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `variantId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'quantity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `quantity` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'arrivalDate');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `arrivalDate` DATE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'associationSourceProductId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `associationSourceProductId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_items` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'cartId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `cartId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'clientId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `clientId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'groupsJson');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `groupsJson` MEDIUMTEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'assignmentsJson');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `assignmentsJson` MEDIUMTEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `cart_accounting` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'orderId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `orderId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'variantId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `variantId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'productName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `productName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'variantName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `variantName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'sku');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `sku` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'price');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `price` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'quantity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `quantity` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'total');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `total` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'arrivalDate');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `arrivalDate` DATE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_items` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'orderId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `orderId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'clientId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `clientId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'groupsJson');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `groupsJson` MEDIUMTEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'assignmentsJson');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `assignmentsJson` MEDIUMTEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `order_accounting` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorites` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorites` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorites` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorites` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorites` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `name` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `sortOrder` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_categories` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'categoryId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `categoryId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'variantId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `variantId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'quantity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `quantity` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'splitsJson');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `splitsJson` MEDIUMTEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `sortOrder` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `favorite_entries` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'email');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `email` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'linkedUserId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `linkedUserId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'phone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `phone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'notes');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `notes` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `clients` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'contact');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `contact` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'email');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `email` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'linkedUserId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `linkedUserId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'phone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `phone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'purchaseLimitOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `purchaseLimitOrder` DECIMAL(12,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'purchaseLimitDay');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `purchaseLimitDay` DECIMAL(12,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'purchaseLimitMonth');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `purchaseLimitMonth` DECIMAL(12,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'limitNone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `limitNone` TINYINT(1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'autoApprove');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `autoApprove` TINYINT(1) DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'paymentMethodId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `paymentMethodId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'smsConsent');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `smsConsent` TINYINT(1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `vendors` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'serial');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `serial` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `status` VARCHAR(50) DEFAULT ''Pending Approval''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'location');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `location` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'notes');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `notes` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'contactName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `contactName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'contactPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `contactPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'contactEmail');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `contactEmail` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'quantity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `quantity` INT DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'price');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `price` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'productId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `productId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'equipmentId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `equipmentId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'url');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `url` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'isPrimary');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `isPrimary` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `sortOrder` INT DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `equipment_images` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'code');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `code` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'description');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `description` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'parentId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `parentId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'category');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `category` VARCHAR(32)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'position');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `position` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `accounting_codes` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'email');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `email` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'phone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `phone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'ltv');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `ltv` DECIMAL(12,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'tags');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `tags` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `customers` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'sku');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `sku` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'location');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `location` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'onHand');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `onHand` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'reserved');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `reserved` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'available');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `available` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'reorderPoint');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `reorderPoint` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `inventory` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'code');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `code` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'type');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `type` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'value');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `value` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'startsAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `startsAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'endsAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `endsAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'usageLimit');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `usageLimit` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'used');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `used` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `promotions` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'orderId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `orderId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'method');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `method` VARCHAR(60)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'externalId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `externalId` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'amount');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `amount` DECIMAL(10,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'capturedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `capturedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `userId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'label');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `label` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'type');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `type` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'brand');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `brand` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'last4');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `last4` VARCHAR(8)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'stripePaymentMethodId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `stripePaymentMethodId` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'expMonth');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `expMonth` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'expYear');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `expYear` INT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'orderId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `orderId` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'carrier');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `carrier` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'tracking');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `tracking` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'shippedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `shippedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'eta');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `eta` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `shipments` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'period');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `period` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'metric');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `metric` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'value');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `value` DECIMAL(12,2)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `analytics_reports` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `content_pages` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name = 'title');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `content_pages` ADD COLUMN `title` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name = 'slug');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `content_pages` ADD COLUMN `slug` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `content_pages` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `content_pages` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'slug');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `slug` VARCHAR(100) NOT NULL UNIQUE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'title');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `title` VARCHAR(255) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'template');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `template` VARCHAR(50) DEFAULT ''custom''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `status` VARCHAR(20) DEFAULT ''draft''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'metaDescription');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `metaDescription` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `pages` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'pageId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `pageId` VARCHAR(64) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'sectionType');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `sectionType` VARCHAR(50) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'content');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `content` JSON', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'sortOrder');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `sortOrder` INT DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `page_sections` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'lastName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `lastName` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'companyName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `companyName` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'cellPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `cellPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'address');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `address` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'address2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `address2` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'city');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `city` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'state');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `state` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'zip');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `zip` VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingFirstName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingFirstName` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingLastName');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingLastName` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingCompany');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingCompany` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingAddress1');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingAddress1` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingAddress2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingAddress2` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingCity');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingCity` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingState');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingState` VARCHAR(120)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingPostcode');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingPostcode` VARCHAR(20)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'shippingPhone');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `shippingPhone` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'bioNotes');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `bioNotes` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'passwordHash');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `passwordHash` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'stripeCustomerId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `stripeCustomerId` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'role');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `role` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'allowInvoice');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `allowInvoice` TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'lastLogin');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `lastLogin` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'name');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `name` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'type');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `type` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'lastSync');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `lastSync` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `integrations` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `settings` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'key');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `settings` ADD COLUMN `key` VARCHAR(100)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'value');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `settings` ADD COLUMN `value` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `settings` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `id` VARCHAR(255)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'identifier');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `identifier` VARCHAR(255) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'type');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `type` VARCHAR(50) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'attempts');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `attempts` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'locked_until');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `locked_until` DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name = 'last_attempt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `rate_limit` ADD COLUMN `last_attempt` DATETIME NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'type');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `type` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'status');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `status` VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'message');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `message` TEXT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `createdAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name = 'updatedAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `reliability` ADD COLUMN `updatedAt` DATETIME', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name = 'id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `remember_me_tokens` ADD COLUMN `id` VARCHAR(64)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name = 'userId');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `remember_me_tokens` ADD COLUMN `userId` VARCHAR(64) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name = 'tokenHash');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `remember_me_tokens` ADD COLUMN `tokenHash` VARCHAR(128) NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name = 'expiresAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `remember_me_tokens` ADD COLUMN `expiresAt` DATETIME NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name = 'createdAt');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE `remember_me_tokens` ADD COLUMN `createdAt` DATETIME NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
