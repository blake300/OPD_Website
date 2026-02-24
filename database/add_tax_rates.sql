-- Sales Tax Rate Groups
-- Stores admin-managed tax rates with associated zip codes

CREATE TABLE IF NOT EXISTS tax_rate_groups (
  id VARCHAR(64) PRIMARY KEY,
  name VARCHAR(255),
  rate DECIMAL(6,4) NOT NULL,
  createdAt DATETIME,
  updatedAt DATETIME,
  INDEX idx_rate (rate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tax_rate_zips (
  id VARCHAR(64) PRIMARY KEY,
  groupId VARCHAR(64) NOT NULL,
  zip VARCHAR(10) NOT NULL,
  UNIQUE INDEX idx_zip (zip),
  INDEX idx_groupId (groupId),
  FOREIGN KEY (groupId) REFERENCES tax_rate_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
