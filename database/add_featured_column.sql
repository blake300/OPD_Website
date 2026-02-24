-- Add featured column to products table
ALTER TABLE products ADD COLUMN featured TINYINT(1) DEFAULT 0;
