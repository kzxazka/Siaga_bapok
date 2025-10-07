-- Add chart_color column to commodities table
ALTER TABLE commodities
ADD COLUMN IF NOT EXISTS chart_color VARCHAR(7) DEFAULT '#3498db' COMMENT 'Color code in hex format (e.g., #3498db)';

-- Set default colors for existing commodities (optional)
UPDATE commodities SET chart_color = '#3498db' WHERE chart_color IS NULL;
