
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
