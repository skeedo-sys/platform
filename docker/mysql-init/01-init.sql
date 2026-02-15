-- Skeedo MySQL Initial Setup Script
-- This script is automatically run when MySQL container starts

-- Create initial database structure if needed
-- Note: Database and user are created by docker-compose environment variables

-- Ensure UTF-8 charset
ALTER DATABASE skeedo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create any initial tables or data here
-- Example: Initial configuration tables, default settings, etc.

-- Grant privileges
GRANT ALL PRIVILEGES ON skeedo.* TO 'skeedo'@'%';
FLUSH PRIVILEGES;
