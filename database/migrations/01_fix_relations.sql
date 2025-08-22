-- Migration: Fix Database Relations
-- Perbaikan relasi antar tabel untuk memastikan integritas data

USE siagabapok_db;

-- 1. Tambahkan tabel commodities jika belum ada
CREATE TABLE IF NOT EXISTS commodities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    unit VARCHAR(20) NOT NULL DEFAULT 'Kg',
    category VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Migrasi data dari tabel komoditas ke commodities jika ada
INSERT IGNORE INTO commodities (name, category)
SELECT nama_komoditas, kategori FROM komoditas;

-- 3. Tambahkan kolom commodity_id ke tabel prices jika belum ada
ALTER TABLE prices
ADD COLUMN IF NOT EXISTS commodity_id INT NULL AFTER commodity_name,
ADD COLUMN IF NOT EXISTS market_id INT NULL AFTER market_name;

-- 4. Update kolom commodity_id berdasarkan commodity_name
UPDATE prices p
JOIN commodities c ON p.commodity_name = c.name
SET p.commodity_id = c.id
WHERE p.commodity_id IS NULL;

-- 5. Update kolom market_id berdasarkan market_name
UPDATE prices p
JOIN pasar m ON p.market_name = m.nama_pasar
SET p.market_id = m.id_pasar
WHERE p.market_id IS NULL;

-- 6. Tambahkan foreign key constraints
ALTER TABLE prices
ADD CONSTRAINT fk_prices_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id) ON DELETE RESTRICT,
ADD CONSTRAINT fk_prices_market FOREIGN KEY (market_id) REFERENCES pasar(id_pasar) ON DELETE RESTRICT;

-- 7. Buat view untuk menggantikan akses langsung ke kolom fiktif
CREATE OR REPLACE VIEW view_prices AS
SELECT 
    p.id,
    p.commodity_id,
    c.name AS commodity_name,
    c.unit,
    p.price,
    p.market_id,
    m.nama_pasar AS market_name,
    p.uptd_user_id,
    u.full_name AS uptd_name,
    p.status,
    p.approved_by,
    a.full_name AS approved_by_name,
    p.approved_at,
    p.notes,
    p.created_at,
    p.updated_at
FROM 
    prices p
JOIN 
    commodities c ON p.commodity_id = c.id
JOIN 
    pasar m ON p.market_id = m.id_pasar
JOIN 
    users u ON p.uptd_user_id = u.id
LEFT JOIN 
    users a ON p.approved_by = a.id;

-- 8. Buat view untuk harga terbaru
CREATE OR REPLACE VIEW view_latest_prices AS
SELECT 
    p.id,
    p.commodity_id,
    c.name AS commodity_name,
    c.unit,
    p.price,
    p.market_id,
    m.nama_pasar AS market_name,
    p.created_at
FROM 
    prices p
JOIN 
    commodities c ON p.commodity_id = c.id
JOIN 
    pasar m ON p.market_id = m.id_pasar
WHERE 
    p.status = 'approved'
AND 
    p.created_at = (
        SELECT MAX(created_at) 
        FROM prices 
        WHERE commodity_id = p.commodity_id 
        AND market_id = p.market_id 
        AND status = 'approved'
    );

-- 9. Buat indeks untuk meningkatkan performa query
CREATE INDEX IF NOT EXISTS idx_prices_commodity_id ON prices(commodity_id);
CREATE INDEX IF NOT EXISTS idx_prices_market_id ON prices(market_id);

SELECT 'Database migration completed successfully' AS message;