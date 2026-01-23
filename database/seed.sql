USE opd_admin;

INSERT INTO products (id, name, sku, price, status, inventory, category, createdAt, updatedAt) VALUES
('prod-1001', 'OPD Pipe Clamp', 'OPD-CLAMP-01', 129.50, 'published', 82, 'Rig Supplies', NOW(), NOW()),
('prod-1002', 'Hydraulic Hose Kit', 'OPD-HOSE-22', 289.00, 'draft', 15, 'Maintenance', NOW(), NOW());

INSERT INTO orders (id, number, status, customerName, customerEmail, customerPhone, address1, address2, city, state, postal, country, notes, userId, total, currency, paymentStatus, fulfillmentStatus, createdAt, updatedAt) VALUES
('ord-2301', 'OPD-2301', 'processing', 'Mesa Pipeline', 'ops@mesa-pipe.com', '555-0100', '100 Supply Rd', NULL, 'Odessa', 'TX', '79761', 'USA', NULL, 'user-1', 12450.00, 'USD', 'paid', 'picking', NOW(), NOW()),
('ord-2302', 'OPD-2302', 'shipped', 'Red Ridge Energy', 'support@redridge.com', '555-0132', '44 Basin Ave', NULL, 'Midland', 'TX', '79701', 'USA', NULL, 'user-1', 8420.00, 'USD', 'paid', 'shipped', NOW(), NOW());

INSERT INTO customers (id, name, email, phone, status, ltv, tags, createdAt, updatedAt) VALUES
('cust-501', 'Mesa Pipeline', 'ops@mesa-pipe.com', '555-0100', 'active', 74210.00, 'VIP,Wholesale', NOW(), NOW()),
('cust-502', 'Red Ridge Energy', 'support@redridge.com', '555-0132', 'active', 32100.00, 'Wholesale', NOW(), NOW());

INSERT INTO inventory (id, sku, location, onHand, reserved, available, reorderPoint, updatedAt) VALUES
('inv-1001', 'OPD-CLAMP-01', 'Odessa', 82, 12, 70, 20, NOW()),
('inv-1002', 'OPD-HOSE-22', 'Midland', 15, 3, 12, 10, NOW());

INSERT INTO promotions (id, code, type, value, status, startsAt, endsAt, usageLimit, used, updatedAt) VALUES
('promo-1', 'RIG10', 'percent', 10.00, 'active', NOW(), NULL, 500, 32, NOW());

INSERT INTO payments (id, orderId, method, amount, status, capturedAt, updatedAt) VALUES
('pay-1', 'ord-2301', 'ACH', 12450.00, 'captured', NOW(), NOW());

INSERT INTO shipments (id, orderId, carrier, tracking, status, shippedAt, eta, updatedAt) VALUES
('ship-1', 'ord-2302', 'FedEx Freight', 'FX-9901', 'in_transit', NOW(), '2026-01-25 00:00:00', NOW());

INSERT INTO analytics_reports (id, name, period, metric, value, updatedAt) VALUES
('rep-1', 'Sales by Region', '30d', 'Revenue', 182400.00, NOW());

INSERT INTO content_pages (id, title, slug, status, updatedAt) VALUES
('page-1', 'Home', '/', 'published', NOW());

INSERT INTO users (id, name, email, passwordHash, role, status, lastLogin, updatedAt) VALUES
('user-1', 'Blake Cantrell', 'blake@opd.com', NULL, 'admin', 'active', NOW(), NOW());

INSERT INTO product_variants (id, productId, name, sku, price, inventory, status, createdAt, updatedAt) VALUES
('var-1', 'prod-1001', 'Clamp - Small', 'OPD-CLAMP-01-S', 119.50, 42, 'active', NOW(), NOW()),
('var-2', 'prod-1001', 'Clamp - Large', 'OPD-CLAMP-01-L', 139.50, 40, 'active', NOW(), NOW());

INSERT INTO favorites (id, userId, productId, createdAt, updatedAt) VALUES
('fav-1', 'user-1', 'prod-1002', NOW(), NOW());

INSERT INTO clients (id, userId, name, email, phone, status, notes, createdAt, updatedAt) VALUES
('client-1', 'user-1', 'Mesa Pipeline', 'ops@mesa-pipe.com', '555-0100', 'active', 'Preferred account.', NOW(), NOW());

INSERT INTO vendors (id, userId, name, contact, email, phone, status, createdAt, updatedAt) VALUES
('vendor-1', 'user-1', 'Permian Steel', 'T. Sanders', 'tsanders@permiansteel.com', '555-0148', 'active', NOW(), NOW());

INSERT INTO equipment (id, userId, name, serial, status, location, notes, createdAt, updatedAt) VALUES
('equip-1', 'user-1', 'Hydraulic Pump', 'HP-8821', 'active', 'Midland Yard', 'Scheduled inspection in Q2.', NOW(), NOW());

INSERT INTO accounting_codes (id, userId, code, description, status, createdAt, updatedAt) VALUES
('acct-1', 'user-1', 'OPD-OPS-001', 'Operations supplies', 'active', NOW(), NOW());

INSERT INTO integrations (id, name, type, status, lastSync, updatedAt) VALUES
('int-1', 'ShipStation', 'shipping', 'connected', NOW(), NOW());

INSERT INTO settings (id, `key`, `value`, updatedAt) VALUES
('set-1', 'currency', 'USD', NOW());

INSERT INTO reliability (id, type, status, message, createdAt, updatedAt) VALUES
('rel-1', 'backup', 'ok', 'Last backup completed 2 hours ago', NOW(), NOW());
