-- OPD schema sync (preserve data)
-- Rebuilds tables to match schema.sql and keeps old tables as backups.
SET @db := DATABASE();
SET FOREIGN_KEY_CHECKS=0;
SET SESSION group_concat_max_len = 10240;

-- products
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'products');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS products (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_products_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_products_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','name','sku','imageUrl','price','status','featured','service','largeDelivery','daysOut','posNum','inventory','invStockTo','invMin','category','shortDescription','longDescription','wgt','lng','wdth','hght','tags','vnName','vnContact','vnPrice','compName','compPrice','shelfNum','estFreight','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'products' AND column_name IN ('id','name','sku','imageUrl','price','status','featured','service','largeDelivery','daysOut','posNum','inventory','invStockTo','invMin','category','shortDescription','longDescription','wgt','lng','wdth','hght','tags','vnName','vnContact','vnPrice','compName','compPrice','shelfNum','estFreight','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_products_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `products`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `products` TO `__schema_sync_old_products_20260413_160408`, `__schema_sync_new_products_20260413_160408` TO `products`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_images
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'product_images');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS product_images (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX productId (productId)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_product_images_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_product_images_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX productId (productId)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','productId','url','isPrimary','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_images' AND column_name IN ('id','productId','url','isPrimary','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_product_images_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `product_images`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `product_images` TO `__schema_sync_old_product_images_20260413_160408`, `__schema_sync_new_product_images_20260413_160408` TO `product_images`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- orders
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'orders');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS orders (
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
  approvalStatus VARCHAR(20) DEFAULT ''Not required'',
  approvalSentAt DATETIME,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_orders_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_orders_20260413_160408` (
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
  approvalStatus VARCHAR(20) DEFAULT ''Not required'',
  approvalSentAt DATETIME,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','number','status','customerName','customerEmail','customerPhone','address1','address2','city','state','postal','country','billingFirstName','billingLastName','billingCompany','billingAddress1','billingAddress2','billingCity','billingStateCode','billingEmail','billingPhone','billingPostcode','shippingFirstName','shippingLastName','shippingCompany','shippingAddress1','shippingAddress2','shippingCity','shippingStateCode','shippingPhone','shippingPostcode','notes','userId','clientId','clientUserId','orderAmount','total','tax','shipping','refundAmount','currency','shippingMethod','deliveryZone','deliveryClass','paymentStatus','fulfillmentStatus','approvalStatus','approvalSentAt','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'orders' AND column_name IN ('id','number','status','customerName','customerEmail','customerPhone','address1','address2','city','state','postal','country','billingFirstName','billingLastName','billingCompany','billingAddress1','billingAddress2','billingCity','billingStateCode','billingEmail','billingPhone','billingPostcode','shippingFirstName','shippingLastName','shippingCompany','shippingAddress1','shippingAddress2','shippingCity','shippingStateCode','shippingPhone','shippingPostcode','notes','userId','clientId','clientUserId','orderAmount','total','tax','shipping','refundAmount','currency','shippingMethod','deliveryZone','deliveryClass','paymentStatus','fulfillmentStatus','approvalStatus','approvalSentAt','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_orders_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `orders`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `orders` TO `__schema_sync_old_orders_20260413_160408`, `__schema_sync_new_orders_20260413_160408` TO `orders`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_variants
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'product_variants');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS product_variants (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_product_variants_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_product_variants_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','productId','name','sku','price','largeDelivery','inventory','invStockTo','invMin','status','posNum','shortDescription','longDescription','wgt','lng','wdth','hght','tags','vnName','vnContact','vnPrice','compName','compPrice','shelfNum','estFreight','parentName','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_variants' AND column_name IN ('id','productId','name','sku','price','largeDelivery','inventory','invStockTo','invMin','status','posNum','shortDescription','longDescription','wgt','lng','wdth','hght','tags','vnName','vnContact','vnPrice','compName','compPrice','shelfNum','estFreight','parentName','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_product_variants_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `product_variants`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `product_variants` TO `__schema_sync_old_product_variants_20260413_160408`, `__schema_sync_new_product_variants_20260413_160408` TO `product_variants`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_associations
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'product_associations');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS product_associations (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  relatedProductId VARCHAR(64),
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_product_associations_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_product_associations_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  productId VARCHAR(64),
  relatedProductId VARCHAR(64),
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','productId','relatedProductId','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'product_associations' AND column_name IN ('id','productId','relatedProductId','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_product_associations_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `product_associations`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `product_associations` TO `__schema_sync_old_product_associations_20260413_160408`, `__schema_sync_new_product_associations_20260413_160408` TO `product_associations`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- carts
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'carts');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS carts (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_carts_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_carts_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  status VARCHAR(50),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','status','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'carts' AND column_name IN ('id','userId','status','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_carts_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `carts`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `carts` TO `__schema_sync_old_carts_20260413_160408`, `__schema_sync_new_carts_20260413_160408` TO `carts`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cart_items
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'cart_items');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS cart_items (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  productId VARCHAR(64),
  variantId VARCHAR(64),
  quantity INT,
  arrivalDate DATE,
  associationSourceProductId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_cart_items_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_cart_items_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  productId VARCHAR(64),
  variantId VARCHAR(64),
  quantity INT,
  arrivalDate DATE,
  associationSourceProductId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','cartId','productId','variantId','quantity','arrivalDate','associationSourceProductId','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_items' AND column_name IN ('id','cartId','productId','variantId','quantity','arrivalDate','associationSourceProductId','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_cart_items_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `cart_items`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `cart_items` TO `__schema_sync_old_cart_items_20260413_160408`, `__schema_sync_new_cart_items_20260413_160408` TO `cart_items`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cart_accounting
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'cart_accounting');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS cart_accounting (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_cart_accounting_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_cart_accounting_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  cartId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','cartId','clientId','groupsJson','assignmentsJson','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'cart_accounting' AND column_name IN ('id','cartId','clientId','groupsJson','assignmentsJson','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_cart_accounting_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `cart_accounting`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `cart_accounting` TO `__schema_sync_old_cart_accounting_20260413_160408`, `__schema_sync_new_cart_accounting_20260413_160408` TO `cart_accounting`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- order_items
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'order_items');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS order_items (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_order_items_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_order_items_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','orderId','productId','variantId','name','productName','variantName','sku','price','quantity','total','arrivalDate','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_items' AND column_name IN ('id','orderId','productId','variantId','name','productName','variantName','sku','price','quantity','total','arrivalDate','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_order_items_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `order_items`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `order_items` TO `__schema_sync_old_order_items_20260413_160408`, `__schema_sync_new_order_items_20260413_160408` TO `order_items`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- order_accounting
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'order_accounting');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS order_accounting (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_order_accounting_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_order_accounting_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  clientId VARCHAR(64),
  groupsJson MEDIUMTEXT,
  assignmentsJson MEDIUMTEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','orderId','clientId','groupsJson','assignmentsJson','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'order_accounting' AND column_name IN ('id','orderId','clientId','groupsJson','assignmentsJson','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_order_accounting_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `order_accounting`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `order_accounting` TO `__schema_sync_old_order_accounting_20260413_160408`, `__schema_sync_new_order_accounting_20260413_160408` TO `order_accounting`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- favorites
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'favorites');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS favorites (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  productId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_favorites_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_favorites_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  productId VARCHAR(64),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','productId','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorites' AND column_name IN ('id','userId','productId','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_favorites_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `favorites`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `favorites` TO `__schema_sync_old_favorites_20260413_160408`, `__schema_sync_new_favorites_20260413_160408` TO `favorites`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- favorite_categories
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'favorite_categories');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS favorite_categories (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(120),
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_favorite_categories_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_favorite_categories_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(120),
  sortOrder INT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','name','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_categories' AND column_name IN ('id','userId','name','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_favorite_categories_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `favorite_categories`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `favorite_categories` TO `__schema_sync_old_favorite_categories_20260413_160408`, `__schema_sync_new_favorite_categories_20260413_160408` TO `favorite_categories`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- favorite_entries
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'favorite_entries');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS favorite_entries (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_favorite_entries_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_favorite_entries_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','categoryId','productId','variantId','quantity','splitsJson','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'favorite_entries' AND column_name IN ('id','userId','categoryId','productId','variantId','quantity','splitsJson','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_favorite_entries_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `favorite_entries`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `favorite_entries` TO `__schema_sync_old_favorite_entries_20260413_160408`, `__schema_sync_new_favorite_entries_20260413_160408` TO `favorite_entries`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- clients
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'clients');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS clients (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_clients_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_clients_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','name','email','linkedUserId','phone','status','notes','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'clients' AND column_name IN ('id','userId','name','email','linkedUserId','phone','status','notes','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_clients_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `clients`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `clients` TO `__schema_sync_old_clients_20260413_160408`, `__schema_sync_new_clients_20260413_160408` TO `clients`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vendors
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'vendors');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS vendors (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_vendors_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_vendors_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','name','contact','email','linkedUserId','phone','status','purchaseLimitOrder','purchaseLimitDay','purchaseLimitMonth','limitNone','autoApprove','paymentMethodId','smsConsent','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'vendors' AND column_name IN ('id','userId','name','contact','email','linkedUserId','phone','status','purchaseLimitOrder','purchaseLimitDay','purchaseLimitMonth','limitNone','autoApprove','paymentMethodId','smsConsent','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_vendors_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `vendors`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `vendors` TO `__schema_sync_old_vendors_20260413_160408`, `__schema_sync_new_vendors_20260413_160408` TO `vendors`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- equipment
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'equipment');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS equipment (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  serial VARCHAR(120),
  status VARCHAR(50) DEFAULT ''Pending Approval'',
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_equipment_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_equipment_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64),
  name VARCHAR(255),
  serial VARCHAR(120),
  status VARCHAR(50) DEFAULT ''Pending Approval'',
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','name','serial','status','location','notes','contactName','contactPhone','contactEmail','quantity','price','productId','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment' AND column_name IN ('id','userId','name','serial','status','location','notes','contactName','contactPhone','contactEmail','quantity','price','productId','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_equipment_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `equipment`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `equipment` TO `__schema_sync_old_equipment_20260413_160408`, `__schema_sync_new_equipment_20260413_160408` TO `equipment`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- equipment_images
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'equipment_images');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS equipment_images (
  id VARCHAR(64) PRIMARY KEY,
  equipmentId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_equipmentId (equipmentId)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_equipment_images_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_equipment_images_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  equipmentId VARCHAR(64),
  url TEXT,
  isPrimary TINYINT(1) DEFAULT 0,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_equipmentId (equipmentId)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','equipmentId','url','isPrimary','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'equipment_images' AND column_name IN ('id','equipmentId','url','isPrimary','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_equipment_images_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `equipment_images`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `equipment_images` TO `__schema_sync_old_equipment_images_20260413_160408`, `__schema_sync_new_equipment_images_20260413_160408` TO `equipment_images`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- accounting_codes
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'accounting_codes');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS accounting_codes (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_accounting_codes_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_accounting_codes_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','code','description','status','parentId','category','position','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'accounting_codes' AND column_name IN ('id','userId','code','description','status','parentId','category','position','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_accounting_codes_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `accounting_codes`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `accounting_codes` TO `__schema_sync_old_accounting_codes_20260413_160408`, `__schema_sync_new_accounting_codes_20260413_160408` TO `accounting_codes`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- customers
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'customers');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS customers (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  status VARCHAR(50),
  ltv DECIMAL(12,2),
  tags VARCHAR(255),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_customers_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_customers_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  email VARCHAR(255),
  phone VARCHAR(50),
  status VARCHAR(50),
  ltv DECIMAL(12,2),
  tags VARCHAR(255),
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','name','email','phone','status','ltv','tags','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'customers' AND column_name IN ('id','name','email','phone','status','ltv','tags','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_customers_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `customers`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `customers` TO `__schema_sync_old_customers_20260413_160408`, `__schema_sync_new_customers_20260413_160408` TO `customers`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- inventory
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'inventory');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS inventory (
  id VARCHAR(64) PRIMARY KEY,
  sku VARCHAR(100),
  location VARCHAR(120),
  onHand INT,
  reserved INT,
  available INT,
  reorderPoint INT,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_inventory_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_inventory_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  sku VARCHAR(100),
  location VARCHAR(120),
  onHand INT,
  reserved INT,
  available INT,
  reorderPoint INT,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','sku','location','onHand','reserved','available','reorderPoint','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'inventory' AND column_name IN ('id','sku','location','onHand','reserved','available','reorderPoint','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_inventory_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `inventory`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `inventory` TO `__schema_sync_old_inventory_20260413_160408`, `__schema_sync_new_inventory_20260413_160408` TO `inventory`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- promotions
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'promotions');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS promotions (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_promotions_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_promotions_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','code','type','value','status','startsAt','endsAt','usageLimit','used','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'promotions' AND column_name IN ('id','code','type','value','status','startsAt','endsAt','usageLimit','used','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_promotions_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `promotions`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `promotions` TO `__schema_sync_old_promotions_20260413_160408`, `__schema_sync_new_promotions_20260413_160408` TO `promotions`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'payments');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS payments (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  method VARCHAR(60),
  externalId VARCHAR(255),
  amount DECIMAL(10,2),
  status VARCHAR(50),
  capturedAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_payments_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_payments_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  method VARCHAR(60),
  externalId VARCHAR(255),
  amount DECIMAL(10,2),
  status VARCHAR(50),
  capturedAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','orderId','method','externalId','amount','status','capturedAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payments' AND column_name IN ('id','orderId','method','externalId','amount','status','capturedAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_payments_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `payments`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `payments` TO `__schema_sync_old_payments_20260413_160408`, `__schema_sync_new_payments_20260413_160408` TO `payments`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_methods
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'payment_methods');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS payment_methods (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_payment_methods_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_payment_methods_20260413_160408` (
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','label','type','brand','last4','stripePaymentMethodId','expMonth','expYear','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'payment_methods' AND column_name IN ('id','userId','label','type','brand','last4','stripePaymentMethodId','expMonth','expYear','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_payment_methods_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `payment_methods`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `payment_methods` TO `__schema_sync_old_payment_methods_20260413_160408`, `__schema_sync_new_payment_methods_20260413_160408` TO `payment_methods`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- shipments
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'shipments');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS shipments (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  carrier VARCHAR(100),
  tracking VARCHAR(120),
  status VARCHAR(50),
  shippedAt DATETIME,
  eta DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_shipments_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_shipments_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  orderId VARCHAR(64),
  carrier VARCHAR(100),
  tracking VARCHAR(120),
  status VARCHAR(50),
  shippedAt DATETIME,
  eta DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','orderId','carrier','tracking','status','shippedAt','eta','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'shipments' AND column_name IN ('id','orderId','carrier','tracking','status','shippedAt','eta','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_shipments_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `shipments`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `shipments` TO `__schema_sync_old_shipments_20260413_160408`, `__schema_sync_new_shipments_20260413_160408` TO `shipments`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- analytics_reports
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'analytics_reports');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS analytics_reports (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  period VARCHAR(50),
  metric VARCHAR(100),
  value DECIMAL(12,2),
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_analytics_reports_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_analytics_reports_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  period VARCHAR(50),
  metric VARCHAR(100),
  value DECIMAL(12,2),
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','name','period','metric','value','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'analytics_reports' AND column_name IN ('id','name','period','metric','value','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_analytics_reports_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `analytics_reports`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `analytics_reports` TO `__schema_sync_old_analytics_reports_20260413_160408`, `__schema_sync_new_analytics_reports_20260413_160408` TO `analytics_reports`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- content_pages
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'content_pages');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS content_pages (
  id VARCHAR(64) PRIMARY KEY,
  title VARCHAR(255),
  slug VARCHAR(255),
  status VARCHAR(50),
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_content_pages_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_content_pages_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  title VARCHAR(255),
  slug VARCHAR(255),
  status VARCHAR(50),
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','title','slug','status','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'content_pages' AND column_name IN ('id','title','slug','status','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_content_pages_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `content_pages`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `content_pages` TO `__schema_sync_old_content_pages_20260413_160408`, `__schema_sync_new_content_pages_20260413_160408` TO `content_pages`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pages
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'pages');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS pages (
  id VARCHAR(64) PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  template VARCHAR(50) DEFAULT ''custom'',
  status VARCHAR(20) DEFAULT ''draft'',
  metaDescription TEXT,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_slug (slug),
  INDEX idx_status (status)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_pages_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_pages_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  template VARCHAR(50) DEFAULT ''custom'',
  status VARCHAR(20) DEFAULT ''draft'',
  metaDescription TEXT,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_slug (slug),
  INDEX idx_status (status)
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','slug','title','template','status','metaDescription','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'pages' AND column_name IN ('id','slug','title','template','status','metaDescription','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_pages_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `pages`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `pages` TO `__schema_sync_old_pages_20260413_160408`, `__schema_sync_new_pages_20260413_160408` TO `pages`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- page_sections
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'page_sections');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS page_sections (
  id VARCHAR(64) PRIMARY KEY,
  pageId VARCHAR(64) NOT NULL,
  sectionType VARCHAR(50) NOT NULL,
  content JSON,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_pageId (pageId),
  FOREIGN KEY (pageId) REFERENCES pages(id) ON DELETE CASCADE
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_page_sections_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_page_sections_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  pageId VARCHAR(64) NOT NULL,
  sectionType VARCHAR(50) NOT NULL,
  content JSON,
  sortOrder INT DEFAULT 0,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_pageId (pageId),
  FOREIGN KEY (pageId) REFERENCES pages(id) ON DELETE CASCADE
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','pageId','sectionType','content','sortOrder','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'page_sections' AND column_name IN ('id','pageId','sectionType','content','sortOrder','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_page_sections_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `page_sections`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `page_sections` TO `__schema_sync_old_page_sections_20260413_160408`, `__schema_sync_new_page_sections_20260413_160408` TO `page_sections`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'users');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  lastName VARCHAR(120),
  email VARCHAR(255),
  companyName VARCHAR(255),
  cellPhone VARCHAR(50),
  ccEmail VARCHAR(255),
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_users_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_users_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  lastName VARCHAR(120),
  email VARCHAR(255),
  companyName VARCHAR(255),
  cellPhone VARCHAR(50),
  ccEmail VARCHAR(255),
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
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','name','lastName','email','companyName','cellPhone','ccEmail','address','address2','city','state','zip','shippingFirstName','shippingLastName','shippingCompany','shippingAddress1','shippingAddress2','shippingCity','shippingState','shippingPostcode','shippingPhone','bioNotes','passwordHash','stripeCustomerId','role','status','allowInvoice','lastLogin','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name IN ('id','name','lastName','email','companyName','cellPhone','ccEmail','address','address2','city','state','zip','shippingFirstName','shippingLastName','shippingCompany','shippingAddress1','shippingAddress2','shippingCity','shippingState','shippingPostcode','shippingPhone','bioNotes','passwordHash','stripeCustomerId','role','status','allowInvoice','lastLogin','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_users_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `users`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `users` TO `__schema_sync_old_users_20260413_160408`, `__schema_sync_new_users_20260413_160408` TO `users`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- integrations
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'integrations');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS integrations (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  type VARCHAR(100),
  status VARCHAR(50),
  lastSync DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_integrations_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_integrations_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  type VARCHAR(100),
  status VARCHAR(50),
  lastSync DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','name','type','status','lastSync','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'integrations' AND column_name IN ('id','name','type','status','lastSync','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_integrations_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `integrations`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `integrations` TO `__schema_sync_old_integrations_20260413_160408`, `__schema_sync_new_integrations_20260413_160408` TO `integrations`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- settings
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'settings');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS settings (
  id VARCHAR(64) PRIMARY KEY,
  `key` VARCHAR(100),
  `value` TEXT,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_settings_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_settings_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  `key` VARCHAR(100),
  `value` TEXT,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','key','value','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name IN ('id','key','value','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_settings_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `settings`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `settings` TO `__schema_sync_old_settings_20260413_160408`, `__schema_sync_new_settings_20260413_160408` TO `settings`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rate_limit
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'rate_limit');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS rate_limit (
  id VARCHAR(255) PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt DATETIME NOT NULL,
  INDEX idx_identifier_type (identifier, type),
  INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_rate_limit_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_rate_limit_20260413_160408` (
  id VARCHAR(255) PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt DATETIME NOT NULL,
  INDEX idx_identifier_type (identifier, type),
  INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','identifier','type','attempts','locked_until','last_attempt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'rate_limit' AND column_name IN ('id','identifier','type','attempts','locked_until','last_attempt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_rate_limit_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `rate_limit`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `rate_limit` TO `__schema_sync_old_rate_limit_20260413_160408`, `__schema_sync_new_rate_limit_20260413_160408` TO `rate_limit`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- reliability
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'reliability');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS reliability (
  id VARCHAR(64) PRIMARY KEY,
  type VARCHAR(50),
  status VARCHAR(50),
  message TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_reliability_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_reliability_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  type VARCHAR(50),
  status VARCHAR(50),
  message TEXT,
  createdAt DATETIME,
  updatedAt DATETIME
)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','type','status','message','createdAt','updatedAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'reliability' AND column_name IN ('id','type','status','message','createdAt','updatedAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_reliability_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `reliability`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `reliability` TO `__schema_sync_old_reliability_20260413_160408`, `__schema_sync_new_reliability_20260413_160408` TO `reliability`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- remember_me_tokens
SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = 'remember_me_tokens');
SET @sql := IF(@table_exists = 0, 'CREATE TABLE IF NOT EXISTS remember_me_tokens (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64) NOT NULL,
  tokenHash VARCHAR(128) NOT NULL,
  expiresAt DATETIME NOT NULL,
  createdAt DATETIME NOT NULL,
  INDEX idx_remember_user (userId),
  INDEX idx_remember_expires (expiresAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `__schema_sync_new_remember_me_tokens_20260413_160408`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'CREATE TABLE `__schema_sync_new_remember_me_tokens_20260413_160408` (
  id VARCHAR(64) PRIMARY KEY,
  userId VARCHAR(64) NOT NULL,
  tokenHash VARCHAR(128) NOT NULL,
  expiresAt DATETIME NOT NULL,
  createdAt DATETIME NOT NULL,
  INDEX idx_remember_user (userId),
  INDEX idx_remember_expires (expiresAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @cols := NULL;
SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, 'id','userId','tokenHash','expiresAt','createdAt')) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = 'remember_me_tokens' AND column_name IN ('id','userId','tokenHash','expiresAt','createdAt');
SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `__schema_sync_new_remember_me_tokens_20260413_160408` (', @cols, ') SELECT ', @cols, ' FROM `remember_me_tokens`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@table_exists = 1, 'RENAME TABLE `remember_me_tokens` TO `__schema_sync_old_remember_me_tokens_20260413_160408`, `__schema_sync_new_remember_me_tokens_20260413_160408` TO `remember_me_tokens`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
