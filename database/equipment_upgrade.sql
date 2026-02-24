-- Equipment table upgrades for Used Equipment Process
ALTER TABLE equipment
  ADD COLUMN IF NOT EXISTS contactName VARCHAR(255) AFTER notes,
  ADD COLUMN IF NOT EXISTS contactPhone VARCHAR(50) AFTER contactName,
  ADD COLUMN IF NOT EXISTS contactEmail VARCHAR(255) AFTER contactPhone,
  ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1 AFTER contactEmail,
  ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) AFTER quantity,
  ADD COLUMN IF NOT EXISTS productId VARCHAR(64) AFTER price;

ALTER TABLE equipment
  MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending Approval';

ALTER TABLE equipment
  ADD INDEX IF NOT EXISTS idx_userId (userId),
  ADD INDEX IF NOT EXISTS idx_status (status),
  ADD INDEX IF NOT EXISTS idx_productId (productId);

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
