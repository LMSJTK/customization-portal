/**
 * Frontend Configuration
 * Okta authentication settings
 *
 * IMPORTANT: Replace these values with your actual Okta configuration
 */

window.OKTA_CONFIG = {
    // Your Okta domain (e.g., 'dev-12345.okta.com')
    url: 'https://your-domain.okta.com',

    // Your Okta application client ID
    // Get this from Okta Admin Console > Applications > Your App
    clientId: 'your_client_id_here',

    // Redirect URI - should match what's configured in Okta
    // For local development: 'http://localhost:9000/'
    // For production: 'https://yourdomain.com/'
    redirectUri: window.location.origin + '/',

    // Issuer URL - typically your Okta domain + /oauth2/default
    issuer: 'https://your-domain.okta.com/oauth2/default',

    // Scopes to request
    scopes: ['openid', 'profile', 'email'],

    // Enable PKCE (recommended for SPAs)
    pkce: true
};

// API Configuration
window.API_CONFIG = {
    // API base URL
    baseUrl: '/api',

    // Endpoints
    endpoints: {
        content: '/api/content.php'
    }
};
