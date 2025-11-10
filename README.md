# Cofense Customization Portal

A Single Page Application (SPA) that allows users to customize email templates and educational content with Okta authentication.

## Features

- **Okta Authentication**: Secure authentication using Okta Auth JS SDK
- **Content Management**: Browse and customize emails, newsletters, education materials, and more
- **Brand Kit Manager**: Configure brand identity including colors, logos, and fonts
- **Visual Editor**: Select elements and modify properties via sidebar
- **Inline Text Editing**: Direct text editing within the preview
- **Multi-organization Support**: Uses Okta claims for user and organization information

## Technology Stack

- **Frontend**: Vanilla JavaScript SPA
- **Backend**: PHP 7.4+ with PDO
- **Database**: PostgreSQL
- **Authentication**: Okta Auth JS SDK 7.7.0
- **Server**: Apache (LAMP stack)

## Prerequisites

- PHP 7.4 or higher
- PostgreSQL 12 or higher
- Apache with mod_rewrite enabled
- Okta account with application configured
- OpenSSL extension for PHP

## Project Structure

```
customization-portal/
├── public/                 # Frontend files (document root)
│   ├── index.html         # Main SPA entry point
│   ├── .htaccess          # Apache configuration
│   ├── css/
│   │   └── styles.css     # Application styles
│   ├── js/
│   │   ├── config.js      # Okta configuration
│   │   ├── auth.js        # Authentication module
│   │   └── app.js         # Main application logic
│   └── api/               # Backend PHP API
│       ├── config.php     # Application configuration
│       ├── db.php         # Database connection
│       ├── auth.php       # Authentication middleware
│       ├── content.php    # Content API endpoints
│       ├── test.php       # Simple API test endpoint
│       └── debug.php      # Diagnostic script
├── .env.example           # Environment variables template
├── .env                   # Environment variables (create from .env.example)
├── CustomizationPortal.pdf        # Figma UI mockups
└── SPA_Auth_JS_javascript.pdf     # Okta Auth documentation
```

## Setup Instructions

### 1. Database Setup

Create the PostgreSQL database and table:

```sql
CREATE DATABASE customization_portal;

\c customization_portal

CREATE SCHEMA IF NOT EXISTS global;

CREATE TABLE IF NOT EXISTS global.content (
    id text PRIMARY KEY,
    company_id text,
    title text,
    description text,
    content_type text,
    content_preview text,
    content_url text,
    email_from_address text,
    email_subject text,
    email_body_html text,
    email_attachment_filename text,
    email_attachment_content bytea,
    created_at timestamp DEFAULT now(),
    updated_at timestamp DEFAULT now()
);

-- Optional: Add some sample data
INSERT INTO global.content (id, title, description, content_type, created_at, updated_at)
VALUES
    ('email_001', 'Welcome Email', 'Welcome new users to the platform', 'emails', NOW(), NOW()),
    ('edu_001', 'Phishing Awareness', 'Educational content about phishing', 'education', NOW(), NOW());
```

### 2. Configure Okta

1. **Create an Okta Application**:
   - Log in to your Okta Admin Console
   - Go to **Applications** > **Create App Integration**
   - Select **OIDC - OpenID Connect**
   - Choose **Single-Page Application**

2. **Configure Application Settings**:
   - **App integration name**: Cofense Customization Portal
   - **Sign-in redirect URIs**: `http://localhost:9000/` (or your domain)
   - **Sign-out redirect URIs**: `http://localhost:9000/` (or your domain)
   - **Controlled access**: Allow everyone in your organization to access

3. **Save and Note**:
   - Client ID
   - Okta domain (e.g., `dev-12345.okta.com`)
   - Issuer URL (usually `https://your-domain.okta.com/oauth2/default`)

4. **Configure Custom Claims** (Optional):
   - To include organization information in the token, configure custom claims in your Okta authorization server
   - Add claims for `organization` and `organization_id`

### 3. Application Configuration

1. **Copy environment file**:
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file** with your actual values:
   ```env
   # Database
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=customization_portal
   DB_USER=your_db_user
   DB_PASS=your_db_password

   # Okta
   OKTA_DOMAIN=your-domain.okta.com
   OKTA_CLIENT_ID=your_client_id
   OKTA_ISSUER=https://your-domain.okta.com/oauth2/default

   # Application
   APP_URL=http://localhost
   API_URL=http://localhost/api
   CORS_ALLOWED_ORIGINS=*
   ```

3. **Edit `public/js/config.js`** with your Okta settings:
   ```javascript
   window.OKTA_CONFIG = {
       url: 'https://your-domain.okta.com',
       clientId: 'your_client_id',
       redirectUri: 'http://localhost:9000/',
       issuer: 'https://your-domain.okta.com/oauth2/default',
       scopes: ['openid', 'profile', 'email'],
       pkce: true
   };
   ```

### 4. Apache Configuration

1. **Set document root** to the `public` directory:
   ```apache
   DocumentRoot "/path/to/customization-portal/public"
   <Directory "/path/to/customization-portal/public">
       Options -Indexes +FollowSymLinks
       AllowOverride All
       Require all granted
   </Directory>
   ```

2. **Enable mod_rewrite**:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

3. **Ensure PHP is enabled**:
   ```bash
   sudo a2enmod php7.4  # or your PHP version
   sudo systemctl restart apache2
   ```

### 5. File Permissions

Set appropriate permissions:
```bash
# Make API files executable by web server
chmod 755 api/*.php

# Ensure .env is not publicly accessible
chmod 600 .env
```

### 6. Start the Application

1. **For local development**, you can use PHP's built-in server:
   ```bash
   cd public
   php -S localhost:9000
   ```

2. **Or use Apache** with the configuration above

3. **Open your browser** and navigate to:
   ```
   http://localhost:9000
   ```

4. **Click "Sign In with Okta"** to authenticate

## Authentication Flow

1. User clicks "Sign In with Okta"
2. Redirected to Okta login page
3. After successful login, Okta redirects back to the app with tokens
4. Frontend stores tokens and extracts user information from ID token
5. API requests include the access token in the Authorization header
6. Backend verifies the JWT signature using Okta's JWKS
7. User information (email, name, organization) is extracted from token claims

## API Endpoints

### GET /api/content.php

Get list of content items or specific content by ID.

**Query Parameters**:
- `type` (optional): Filter by content type (emails, education, etc.)
- `id` (optional): Get specific content item

**Response**:
```json
{
  "success": true,
  "items": [...],
  "count": 10
}
```

### POST /api/content.php

Create new customized content.

**Body**:
```json
{
  "title": "My Custom Email",
  "description": "Description",
  "content_type": "emails",
  "email_body_html": "<html>...</html>"
}
```

**Response**:
```json
{
  "success": true,
  "id": "content_12345",
  "message": "Content created successfully"
}
```

### PUT /api/content.php?id={id}

Update existing content.

**Response**:
```json
{
  "success": true,
  "message": "Content updated successfully"
}
```

### DELETE /api/content.php?id={id}

Delete content.

**Response**:
```json
{
  "success": true,
  "message": "Content deleted successfully"
}
```

## Security Considerations

1. **JWT Verification**: All API requests verify JWT signatures against Okta's public keys
2. **CORS**: Configure `CORS_ALLOWED_ORIGINS` in production to restrict access
3. **HTTPS**: Always use HTTPS in production
4. **Content Security Policy**: Configured in `.htaccess`
5. **No Stored Credentials**: User and organization info from Okta claims only
6. **SQL Injection Protection**: Using PDO with prepared statements

## Troubleshooting

### "Configuration Error" on login screen

- Check that `public/js/config.js` has correct Okta values
- Ensure `OKTA_CONFIG` object is properly defined

### "Authentication required" API errors

- Check that Okta token is being sent in Authorization header
- Verify Okta configuration matches between frontend and backend
- Check browser console for CORS errors

### Database connection errors

- Verify PostgreSQL is running
- Check `.env` file has correct database credentials
- Ensure PostgreSQL user has proper permissions

### "Invalid JWT signature" errors

- Verify `OKTA_ISSUER` matches your Okta authorization server
- Check that token hasn't expired
- Ensure system time is synchronized (JWT validation is time-sensitive)

## Future Enhancements

- [ ] Full visual editor with element selection
- [ ] Inline text editing
- [ ] Brand kit auto-application to templates
- [ ] Image upload and management
- [ ] Template preview in different formats
- [ ] Export functionality
- [ ] Version control for customized content
- [ ] Collaboration features

## Development Notes

- Frontend uses vanilla JavaScript (no framework dependencies except Okta Auth JS)
- Backend uses vanilla PHP with PDO (no framework)
- Designed to work on standard LAMP servers
- Database interactions use prepared statements for security
- Organization data comes from Okta claims (no local user/org tables)

## License

Proprietary - Cofense Internal Use Only

## Support

For issues or questions, please contact the development team.
