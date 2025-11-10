/**
 * Okta Authentication Module
 * Handles authentication flow using Okta Auth JS SDK
 */

class AuthManager {
    constructor() {
        this.authClient = null;
        this.currentUser = null;
        this.accessToken = null;
        this.init();
    }

    /**
     * Initialize Okta Auth Client
     */
    init() {
        // Get configuration from environment or config
        const config = window.OKTA_CONFIG || {
            url: '',  // Will be set via config
            clientId: '',  // Will be set via config
            redirectUri: window.location.origin + '/',
            issuer: '',  // Will be set via config
            scopes: ['openid', 'profile', 'email'],
            pkce: true
        };

        // Validate configuration
        if (!config.url || !config.clientId) {
            console.error('Okta configuration is missing. Please check your config.');
            this.handleConfigError();
            return;
        }

        // Initialize Okta Auth Client
        this.authClient = new OktaAuth({
            issuer: config.issuer || config.url + '/oauth2/default',
            clientId: config.clientId,
            redirectUri: config.redirectUri,
            scopes: config.scopes,
            pkce: config.pkce
        });

        console.log('OktaAuth initialized');

        // Start token manager to enable auto-renewal
        this.authClient.start();
        console.log('TokenManager started');

        // Set up token renewal listener
        this.authClient.tokenManager.on('renewed', (key, newToken, oldToken) => {
            console.log('Token renewed:', key);
            if (key === 'accessToken') {
                this.accessToken = newToken.accessToken;
            }
        });

        // Set up token expiration listener
        this.authClient.tokenManager.on('expired', (key, expiredToken) => {
            console.log('Token expired:', key);
        });

        // Set up error listener
        this.authClient.tokenManager.on('error', (err) => {
            console.error('TokenManager error:', err);
        });

        // Start authentication flow
        this.handleAuthentication();
    }

    /**
     * Handle the authentication flow
     */
    async handleAuthentication() {
        try {
            // Check if this is a redirect callback from Okta
            if (this.authClient.isLoginRedirect()) {
                await this.handleLoginRedirect();
            } else {
                // Check if user is already authenticated
                await this.checkAuthentication();
            }
        } catch (error) {
            console.error('Authentication error:', error);
            this.showLoginScreen();
        }
    }

    /**
     * Handle redirect from Okta after login
     */
    async handleLoginRedirect() {
        try {
            console.log('Handling login redirect...');
            // Parse tokens from URL
            const { tokens } = await this.authClient.token.parseFromUrl();

            console.log('Tokens received:', {
                accessToken: tokens.accessToken ? 'Yes' : 'No',
                idToken: tokens.idToken ? 'Yes' : 'No'
            });

            // Store tokens
            this.authClient.tokenManager.setTokens(tokens);
            console.log('Tokens stored in tokenManager');

            // Set access token for API calls
            if (tokens.accessToken) {
                this.accessToken = tokens.accessToken.accessToken;
                console.log('Access token set for API calls');
            }

            // Get user info and show app
            await this.getUserInfo();
            this.showApp();

            // Clean up URL
            window.history.replaceState({}, document.title, window.location.pathname);
        } catch (error) {
            console.error('Error handling login redirect:', error);
            this.showLoginScreen();
        }
    }

    /**
     * Check if user is already authenticated
     */
    async checkAuthentication() {
        try {
            console.log('Checking authentication...');
            const accessToken = await this.authClient.tokenManager.get('accessToken');
            const idToken = await this.authClient.tokenManager.get('idToken');

            console.log('Access token:', accessToken ? 'Found' : 'Not found');
            console.log('ID token:', idToken ? 'Found' : 'Not found');

            if (accessToken && idToken) {
                // User is authenticated
                console.log('User is authenticated, setting access token');
                this.accessToken = accessToken.accessToken;
                await this.getUserInfo();
                this.showApp();
            } else {
                // User is not authenticated
                console.log('No valid tokens found, showing login screen');
                this.showLoginScreen();
            }
        } catch (error) {
            console.error('Error checking authentication:', error);
            this.showLoginScreen();
        }
    }

    /**
     * Get user information from ID token
     */
    async getUserInfo() {
        try {
            const idToken = await this.authClient.tokenManager.get('idToken');

            if (idToken && idToken.claims) {
                this.currentUser = {
                    name: idToken.claims.name || idToken.claims.email,
                    email: idToken.claims.email,
                    sub: idToken.claims.sub,
                    // Custom Okta claims for organization info
                    organization: idToken.claims.organization || idToken.claims.org || 'Unknown Organization',
                    organizationId: idToken.claims.organizationId || idToken.claims.org_id || null
                };

                console.log('User authenticated:', this.currentUser);
            }
        } catch (error) {
            console.error('Error getting user info:', error);
        }
    }

    /**
     * Initiate login with Okta
     */
    login() {
        if (!this.authClient) {
            this.handleConfigError();
            return;
        }

        this.authClient.token.getWithRedirect({
            scopes: ['openid', 'profile', 'email']
        });
    }

    /**
     * Sign out the user
     */
    async logout() {
        try {
            if (!this.authClient) {
                return;
            }

            // Clear tokens
            await this.authClient.tokenManager.clear();

            // Sign out from Okta
            await this.authClient.signOut();

            // Clear current user
            this.currentUser = null;
            this.accessToken = null;

            // Show login screen
            this.showLoginScreen();
        } catch (error) {
            console.error('Error during logout:', error);
            // Force show login screen even if logout fails
            this.showLoginScreen();
        }
    }

    /**
     * Get current user information
     */
    getCurrentUser() {
        return this.currentUser;
    }

    /**
     * Get access token for API calls
     */
    getAccessToken() {
        return this.accessToken;
    }

    /**
     * Make authenticated API call
     */
    async apiCall(endpoint, options = {}) {
        if (!this.accessToken) {
            throw new Error('No access token available');
        }

        const headers = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.accessToken}`,
            ...options.headers
        };

        const response = await fetch(endpoint, {
            ...options,
            headers
        });

        if (response.status === 401) {
            // Token expired, need to re-authenticate
            this.showLoginScreen();
            throw new Error('Authentication expired');
        }

        return response;
    }

    /**
     * Show login screen
     */
    showLoginScreen() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('login-required').style.display = 'flex';
        document.getElementById('app').style.display = 'none';
    }

    /**
     * Show main application
     */
    showApp() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('login-required').style.display = 'none';
        document.getElementById('app').style.display = 'block';

        // Trigger custom event that app is ready
        window.dispatchEvent(new CustomEvent('auth:ready', {
            detail: { user: this.currentUser }
        }));
    }

    /**
     * Handle configuration error
     */
    handleConfigError() {
        document.getElementById('loading').style.display = 'none';
        const loginContainer = document.getElementById('login-required');
        loginContainer.style.display = 'flex';
        loginContainer.innerHTML = `
            <div class="login-card">
                <h1>Configuration Error</h1>
                <p>Okta authentication is not properly configured. Please contact your administrator.</p>
                <p style="font-size: 0.875rem; color: #666; margin-top: 1rem;">
                    Check that OKTA_CONFIG is properly set in config.js
                </p>
            </div>
        `;
    }
}

// Initialize authentication manager
const authManager = new AuthManager();

// Export for use in other modules
window.authManager = authManager;
