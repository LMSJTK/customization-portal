/**
 * Main Application Module
 * Handles UI interactions and content management
 */

class CustomizationPortal {
    constructor() {
        this.currentTab = 'emails';
        this.contentItems = [];
        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        // Wait for authentication to be ready
        window.addEventListener('auth:ready', (event) => {
            this.onAuthReady(event.detail.user);
        });

        // Set up event listeners for login/logout
        this.setupAuthListeners();
    }

    /**
     * Called when authentication is ready
     */
    onAuthReady(user) {
        console.log('Application ready for user:', user);

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
     * Customize content - open brand kit modal
     */
    customizeContent(contentId) {
        console.log('Customizing content:', contentId);

        // Store the content ID for later use
        this.currentContentId = contentId;

        // Show brand kit modal
        this.showBrandKitModal();

        // TODO: In future iterations, this will open the full editor
    }

    /**
     * Show brand kit modal
     */
    showBrandKitModal() {
        const modal = document.getElementById('brand-kit-modal');
        if (modal) {
            modal.style.display = 'flex';
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
     * Apply brand kit to content
     */
    async applyBrandKit() {
        try {
            console.log('Applying brand kit to content:', this.currentContentId);

            // TODO: Implement actual brand kit application
            // This will be expanded in future iterations

            alert('Brand kit will be applied to the content. This feature is coming soon!');

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
