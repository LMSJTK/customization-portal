-- Migration: Add support for customized content tracking
-- This adds a column to track which original content a customized version is based on

-- For PostgreSQL:
ALTER TABLE global.content ADD COLUMN IF NOT EXISTS original_content_id TEXT;
CREATE INDEX IF NOT EXISTS idx_original_content_id ON global.content(original_content_id);
CREATE INDEX IF NOT EXISTS idx_company_original ON global.content(company_id, original_content_id);

-- For MySQL:
-- ALTER TABLE content ADD COLUMN original_content_id VARCHAR(255) DEFAULT NULL;
-- CREATE INDEX idx_original_content_id ON content(original_content_id);
-- CREATE INDEX idx_company_original ON content(company_id, original_content_id);

-- This column stores the ID of the original file-based content when a customized
-- version is created. This allows:
-- 1. Tracking which content has been customized by an organization
-- 2. Finding a company's customized version of original content
-- 3. Keeping the original file-based content unchanged
