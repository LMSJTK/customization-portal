/**
 * Brand Kit Manager Module
 * Handles fetching and saving brand kit data
 */

class BrandKitManager {
    constructor() {
        this.brandKit = null;
        this.isDefault = true;
        this.listeners = [];
    }

    /**
     * Fetch brand kit from API
     */
    async fetch() {
        try {
            const response = await window.authManager.apiCall(
                window.API_CONFIG.endpoints.brandKit,
                { method: 'GET' }
            );

            if (!response.ok) {
                throw new Error('Failed to fetch brand kit');
            }

            const data = await response.json();

            if (data.success) {
                this.brandKit = data.brand_kit;
                this.isDefault = data.is_default || false;

                console.log('Brand kit loaded:', this.brandKit);
                console.log('Is default:', this.isDefault);

                // Notify listeners
                this.notifyListeners('loaded', this.brandKit);

                return this.brandKit;
            } else {
                throw new Error('Invalid response from brand kit API');
            }
        } catch (error) {
            console.error('Error fetching brand kit:', error);

            // Return default brand kit on error
            this.brandKit = this.getDefaultBrandKit();
            this.isDefault = true;

            return this.brandKit;
        }
    }

    /**
     * Save brand kit to API
     */
    async save(brandKitData) {
        try {
            // Validate colors before saving
            if (brandKitData.primary_color && !this.isValidHexColor(brandKitData.primary_color)) {
                throw new Error('Invalid primary color format. Must be hex (e.g., #4F46E5)');
            }

            if (brandKitData.text_color && !this.isValidHexColor(brandKitData.text_color)) {
                throw new Error('Invalid text color format. Must be hex (e.g., #FFFFFF)');
            }

            const response = await window.authManager.apiCall(
                window.API_CONFIG.endpoints.brandKit,
                {
                    method: 'PUT',
                    body: JSON.stringify(brandKitData)
                }
            );

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to save brand kit');
            }

            const data = await response.json();

            if (data.success) {
                // Update local brand kit with saved values
                this.brandKit = {
                    ...this.brandKit,
                    ...brandKitData,
                    updated_at: new Date().toISOString()
                };
                this.isDefault = false;

                console.log('Brand kit saved:', this.brandKit);

                // Notify listeners
                this.notifyListeners('saved', this.brandKit);

                return this.brandKit;
            } else {
                throw new Error('Save failed');
            }
        } catch (error) {
            console.error('Error saving brand kit:', error);

            // Notify listeners of error
            this.notifyListeners('error', { error: error.message });

            throw error;
        }
    }

    /**
     * Update specific brand kit property and auto-save
     */
    async updateProperty(property, value) {
        try {
            const updateData = { [property]: value };
            return await this.save(updateData);
        } catch (error) {
            console.error('Error updating brand kit property:', error);
            throw error;
        }
    }

    /**
     * Delete brand kit (reset to defaults)
     */
    async reset() {
        try {
            const response = await window.authManager.apiCall(
                window.API_CONFIG.endpoints.brandKit,
                { method: 'DELETE' }
            );

            if (!response.ok) {
                throw new Error('Failed to reset brand kit');
            }

            const data = await response.json();

            if (data.success) {
                // Reset to defaults
                this.brandKit = this.getDefaultBrandKit();
                this.isDefault = true;

                console.log('Brand kit reset to defaults');

                // Notify listeners
                this.notifyListeners('reset', this.brandKit);

                return this.brandKit;
            }
        } catch (error) {
            console.error('Error resetting brand kit:', error);
            throw error;
        }
    }

    /**
     * Get current brand kit
     */
    getBrandKit() {
        return this.brandKit;
    }

    /**
     * Get primary color
     */
    getPrimaryColor() {
        return this.brandKit?.primary_color || '#4F46E5';
    }

    /**
     * Get text color
     */
    getTextColor() {
        return this.brandKit?.text_color || '#FFFFFF';
    }

    /**
     * Get logo URL
     */
    getLogoUrl() {
        return this.brandKit?.logo_url || null;
    }

    /**
     * Get font family
     */
    getFontFamily() {
        return this.brandKit?.font_family || 'Inter';
    }

    /**
     * Check if using default brand kit
     */
    isUsingDefaults() {
        return this.isDefault;
    }

    /**
     * Get default brand kit values
     */
    getDefaultBrandKit() {
        return {
            id: null,
            company_id: null,
            primary_color: '#4F46E5',
            text_color: '#FFFFFF',
            logo_url: null,
            font_family: 'Inter',
            created_at: null,
            updated_at: null
        };
    }

    /**
     * Validate hex color format
     */
    isValidHexColor(color) {
        return /^#[0-9A-Fa-f]{6}$/.test(color);
    }

    /**
     * Add event listener for brand kit changes
     * Events: 'loaded', 'saved', 'reset', 'error'
     */
    addEventListener(callback) {
        this.listeners.push(callback);
    }

    /**
     * Remove event listener
     */
    removeEventListener(callback) {
        this.listeners = this.listeners.filter(listener => listener !== callback);
    }

    /**
     * Notify all listeners of an event
     */
    notifyListeners(event, data) {
        this.listeners.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Error in brand kit listener:', error);
            }
        });
    }

    /**
     * Convert hex color to RGB
     */
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    /**
     * Convert RGB to hex
     */
    rgbToHex(r, g, b) {
        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
    }

    /**
     * Debounce helper for auto-save
     */
    debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    /**
     * Create debounced save function
     */
    createDebouncedSave(delay = 500) {
        return this.debounce((property, value) => {
            return this.updateProperty(property, value);
        }, delay);
    }
}

// Initialize brand kit manager
const brandKitManager = new BrandKitManager();

// Export for use in other modules
window.brandKitManager = brandKitManager;

// Auto-load brand kit when auth is ready
window.addEventListener('auth:ready', async () => {
    console.log('Auth ready, loading brand kit...');
    await brandKitManager.fetch();
});
