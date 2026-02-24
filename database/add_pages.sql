-- Pages table for custom page builder
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

-- Page sections for content blocks
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
