/**
 * Main Application Module
 * Handles UI interactions and content management
 */

class CustomizationPortal {
    constructor() {
        this.currentTab = 'emails';
        this.contentItems = [];
        this.initialized = false;
        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        // Set up event listeners for login/logout first
        this.setupAuthListeners();

        // Check if auth is already ready (race condition fix)
        if (window.authManager && window.authManager.getCurrentUser()) {
            console.log('Auth already ready, initializing app immediately');
            this.onAuthReady(window.authManager.getCurrentUser());
        }

        // Also wait for authentication to be ready (for initial login)
        window.addEventListener('auth:ready', (event) => {
            console.log('Received auth:ready event');
            this.onAuthReady(event.detail.user);
        });
    }

    /**
     * Called when authentication is ready
     */
    onAuthReady(user) {
        // Prevent double initialization
        if (this.initialized) {
            console.log('App already initialized, skipping');
            return;
        }

        console.log('Application ready for user:', user);
        this.initialized = true;

        // Update user info in header
        this.updateUserInfo(user);

        // Set up all event listeners
        this.setupEventListeners();

        // Load initial content
        this.loadContent();
    }

    /**
     * Set up authentication event listeners
     */
    setupAuthListeners() {
        // Login button
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', () => {
                window.authManager.login();
            });
        }

        // Logout button
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to sign out?')) {
                    window.authManager.logout();
                }
            });
        }
    }

    /**
     * Update user information in the header
     */
    updateUserInfo(user) {
        const userNameElement = document.getElementById('user-name');
        const userOrgElement = document.getElementById('user-org');

        if (userNameElement) {
            userNameElement.textContent = user.name || user.email;
        }

        if (userOrgElement) {
            userOrgElement.textContent = user.organization || '';
        }
    }

    /**
     * Set up event listeners for the application
     */
    setupEventListeners() {
        // Tab navigation
        const tabButtons = document.querySelectorAll('.tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });

        // Search input
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }

        // Brand kit modal close
        const closeModalBtn = document.querySelector('.close-modal');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => {
                this.closeBrandKitModal();
            });
        }

        // Apply brand kit button
        const applyBrandBtn = document.querySelector('.apply-brand-btn');
        if (applyBrandBtn) {
            applyBrandBtn.addEventListener('click', () => {
                this.applyBrandKit();
            });
        }

        // Brand kit color controls
        this.setupBrandKitControls();
    }

    /**
     * Setup brand kit color picker controls
     */
    setupBrandKitControls() {
        const colorPicker = document.getElementById('color-picker');
        const colorHex = document.getElementById('color-hex');
        const addColorBtn = document.querySelector('.btn-add-color');

        // Color picker change
        if (colorPicker) {
            colorPicker.addEventListener('input', async (e) => {
                const color = e.target.value.toUpperCase();
                if (colorHex) colorHex.value = color;

                // Auto-save color
                try {
                    await window.brandKitManager.updateProperty('primary_color', color);
                    this.showSuccessMessage('Primary color updated');
                } catch (error) {
                    console.error('Error saving color:', error);
                }
            });
        }

        // Hex input change
        if (colorHex) {
            colorHex.addEventListener('change', async (e) => {
                let color = e.target.value.trim();

                // Add # if missing
                if (!color.startsWith('#')) {
                    color = '#' + color;
                }

                // Validate and save
                if (window.brandKitManager.isValidHexColor(color)) {
                    color = color.toUpperCase();
                    if (colorPicker) colorPicker.value = color;
                    e.target.value = color;

                    try {
                        await window.brandKitManager.updateProperty('primary_color', color);
                        this.showSuccessMessage('Primary color updated');
                    } catch (error) {
                        console.error('Error saving color:', error);
                        this.showError('Invalid color format');
                    }
                } else {
                    this.showError('Invalid hex color format. Use #RRGGBB');
                }
            });
        }

        // Add color button (placeholder for future)
        if (addColorBtn) {
            addColorBtn.addEventListener('click', () => {
                console.log('Add color to palette - coming soon');
            });
        }
    }

    /**
     * Show success message
     */
    showSuccessMessage(message) {
        // Simple console log for now - can be upgraded to toast notification
        console.log('Success:', message);
    }

    /**
     * Switch content tab
     */
    switchTab(tabName) {
        // Update active tab button
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });

        this.currentTab = tabName;
        this.loadContent();
    }

    /**
     * Load content from API
     */
    async loadContent() {
        try {
            const response = await window.authManager.apiCall(
                `/api/content.php?type=${this.currentTab}`,
                { method: 'GET' }
            );

            if (!response.ok) {
                throw new Error('Failed to load content');
            }

            const data = await response.json();
            this.contentItems = data.items || [];
            this.renderContentGrid();
        } catch (error) {
            console.error('Error loading content:', error);
            this.showError('Failed to load content. Please try again.');
        }
    }

    /**
     * Render content grid
     */
    renderContentGrid() {
        const grid = document.getElementById('content-grid');
        if (!grid) return;

        if (this.contentItems.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <p>No ${this.currentTab} templates available.</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = this.contentItems.map(item => `
            <div class="content-card" data-id="${item.id}">
                <div class="content-preview">
                    ${item.content_preview || '<div class="preview-placeholder">No preview</div>'}
                </div>
                <div class="content-info">
                    <h3 class="content-title">${this.escapeHtml(item.title)}</h3>
                    <p class="content-description">${this.escapeHtml(item.description || '')}</p>
                    <div class="content-actions">
                        <button class="btn-primary btn-small" onclick="app.customizeContent('${item.id}')">
                            Customize
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Handle search
     */
    handleSearch(query) {
        // Filter content items based on search query
        const filtered = this.contentItems.filter(item => {
            const searchText = `${item.title} ${item.description}`.toLowerCase();
            return searchText.includes(query.toLowerCase());
        });

        // Render filtered results
        const grid = document.getElementById('content-grid');
        if (!grid) return;

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <p>No results found for "${this.escapeHtml(query)}"</p>
                </div>
            `;
            return;
        }

        grid.innerHTML = filtered.map(item => `
            <div class="content-card" data-id="${item.id}">
                <div class="content-preview">
                    ${item.content_preview || '<div class="preview-placeholder">No preview</div>'}
                </div>
                <div class="content-info">
                    <h3 class="content-title">${this.escapeHtml(item.title)}</h3>
                    <p class="content-description">${this.escapeHtml(item.description || '')}</p>
                    <div class="content-actions">
                        <button class="btn-primary btn-small" onclick="app.customizeContent('${item.id}')">
                            Customize
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Customize content - open editor
     */
    customizeContent(contentId) {
        console.log('Customizing content:', contentId);

        // Navigate to editor
        window.location.href = `/editor.html?id=${contentId}`;
    }

    /**
     * Show brand kit modal
     */
    showBrandKitModal() {
        const modal = document.getElementById('brand-kit-modal');
        if (modal) {
            modal.style.display = 'flex';

            // Load current brand kit values into modal
            this.loadBrandKitIntoModal();
        }
    }

    /**
     * Close brand kit modal
     */
    closeBrandKitModal() {
        const modal = document.getElementById('brand-kit-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    /**
     * Load brand kit values into modal UI
     */
    loadBrandKitIntoModal() {
        if (!window.brandKitManager) return;

        const brandKit = window.brandKitManager.getBrandKit();
        if (!brandKit) return;

        // Update color inputs if they exist
        const colorPicker = document.getElementById('color-picker');
        const colorHex = document.getElementById('color-hex');

        if (colorPicker && brandKit.primary_color) {
            colorPicker.value = brandKit.primary_color;
        }

        if (colorHex && brandKit.primary_color) {
            colorHex.value = brandKit.primary_color;
        }

        // Update status indicator
        const statusIndicator = document.querySelector('.brand-kit-status span:last-child');
        if (statusIndicator) {
            if (window.brandKitManager.isUsingDefaults()) {
                statusIndicator.textContent = 'Using Default Brand Kit';
            } else {
                statusIndicator.textContent = 'Custom Brand Kit Active';
            }
        }

        console.log('Loaded brand kit into modal:', brandKit);
    }

    /**
     * Apply brand kit to content
     */
    async applyBrandKit() {
        try {
            console.log('Applying brand kit to content:', this.currentContentId);

            if (!window.brandKitManager) {
                throw new Error('Brand kit manager not initialized');
            }

            const brandKit = window.brandKitManager.getBrandKit();

            // Show feedback to user
            const isDefault = window.brandKitManager.isUsingDefaults();
            const message = isDefault
                ? 'Default brand kit will be applied to this content.'
                : `Your custom brand kit will be applied:\n- Primary Color: ${brandKit.primary_color}\n- Text Color: ${brandKit.text_color}\n- Font: ${brandKit.font_family}`;

            alert(message + '\n\nNote: Full editor integration coming soon!');

            this.closeBrandKitModal();
        } catch (error) {
            console.error('Error applying brand kit:', error);
            this.showError('Failed to apply brand kit. Please try again.');
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        // Simple error handling - can be improved with a modal or toast
        alert(message);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize application
const app = new CustomizationPortal();
window.app = app;
