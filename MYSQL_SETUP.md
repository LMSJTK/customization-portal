# MySQL Setup for Customization Portal

This guide shows how to set up the Customization Portal with MySQL instead of PostgreSQL.

## Prerequisites

- MySQL 5.7+ or MariaDB 10.2+
- PHP with `pdo_mysql` extension

## 1. Install MySQL/MariaDB

```bash
# Ubuntu/Debian
sudo apt-get install mysql-server php-mysql

# CentOS/RHEL
sudo yum install mysql-server php-mysqlnd

# macOS
brew install mysql
```

## 2. Create Database and Table

Connect to MySQL:
```bash
mysql -u root -p
```

Create database and table:
```sql
-- Create database
CREATE DATABASE customization_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE customization_portal;

-- Create content table
CREATE TABLE content (
    id VARCHAR(255) PRIMARY KEY,
    company_id VARCHAR(255),
    title TEXT,
    description TEXT,
    content_type VARCHAR(100),
    content_preview TEXT,
    content_url TEXT,
    email_from_address VARCHAR(255),
    email_subject TEXT,
    email_body_html LONGTEXT,
    email_attachment_filename VARCHAR(255),
    email_attachment_content LONGBLOB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_type (content_type),
    INDEX idx_company_id (company_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a MySQL user (optional, for security)
CREATE USER 'customization'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON customization_portal.* TO 'customization'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Add Sample Data (Optional)

```sql
INSERT INTO content (id, title, description, content_type, created_at, updated_at)
VALUES
    ('email_001', 'Welcome Email', 'Welcome new users to the platform', 'emails', NOW(), NOW()),
    ('edu_001', 'Phishing Awareness', 'Educational content about phishing', 'education', NOW(), NOW());
```

## 4. Configure .env

Update your `.env` file with MySQL settings:

```env
# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=customization_portal
DB_USER=customization
DB_PASS=your_secure_password
```

Note: `DB_SCHEMA` is not used for MySQL and will be ignored.

## 5. Test Connection

Visit the debug endpoint to verify the setup:
```
http://your-domain/api/debug.php
```

Look for:
```
2. Environment File (.env):
   DB_TYPE: mysql
   ...

3. PDO Database Extensions:
   pdo_mysql: ✓
   Required for DB_TYPE=mysql: pdo_mysql

5. Database Connection Test:
   Connection: ✓ SUCCESS
   Database type: mysql
   Content table: ✓ EXISTS (content)
   Content count: 2 items
```

## Key Differences from PostgreSQL

1. **No Schema**: MySQL doesn't use schemas the same way PostgreSQL does. The table is just `content` instead of `global.content`.

2. **Data Types**:
   - PostgreSQL `text` → MySQL `TEXT` or `LONGTEXT`
   - PostgreSQL `bytea` → MySQL `LONGBLOB`
   - PostgreSQL `timestamp` → MySQL `TIMESTAMP`

3. **Auto-update**: MySQL supports `ON UPDATE CURRENT_TIMESTAMP` natively, so `updated_at` automatically updates.

4. **Character Set**: MySQL uses `utf8mb4` for full Unicode support (including emoji).

## Troubleshooting

### Error: "pdo_mysql extension not loaded"

Install the PHP MySQL extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-mysql
sudo systemctl restart apache2

# CentOS/RHEL
sudo yum install php-mysqlnd
sudo systemctl restart httpd
```

### Error: "Access denied for user"

Check your MySQL user and password:
```sql
-- As root
SHOW GRANTS FOR 'customization'@'localhost';

-- Reset password if needed
ALTER USER 'customization'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

### Error: "Table 'content' doesn't exist"

Make sure you're in the correct database:
```sql
USE customization_portal;
SHOW TABLES;
```

## Performance Tips

1. **Enable Query Cache** (MySQL 5.7, not in 8.0+):
```sql
SET GLOBAL query_cache_size = 67108864;  -- 64MB
SET GLOBAL query_cache_type = 1;
```

2. **Optimize InnoDB**:
```sql
SET GLOBAL innodb_buffer_pool_size = 1073741824;  -- 1GB
```

3. **Add Indexes** based on your query patterns:
```sql
-- If you frequently filter by company_id and content_type
CREATE INDEX idx_company_content_type ON content(company_id, content_type);
```

## Switching from PostgreSQL to MySQL

If you're migrating from PostgreSQL:

1. Export data from PostgreSQL:
```bash
pg_dump -U postgres -t global.content customization_portal > content_export.sql
```

2. Convert PostgreSQL dump to MySQL format (manual edits needed)
3. Import into MySQL
4. Update `.env` to use `DB_TYPE=mysql`

## Support

For issues or questions about MySQL configuration, check:
- MySQL Documentation: https://dev.mysql.com/doc/
- MariaDB Documentation: https://mariadb.com/kb/en/
