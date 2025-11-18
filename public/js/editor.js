/**
 * Email Editor Module
 * Handles visual editing of email templates
 */

class EmailEditor {
    constructor() {
        this.contentId = null;
        this.content = null;
        this.selectedElement = null;
        this.textEditMode = false;
        this.originalStyles = {};
        this.originalTextHTML = '';
        this.brandKitApplied = false;
        this.hasUnsavedChanges = false;

        this.init();
    }

    /**
     * Initialize the editor
     */
    async init() {
        // Get content ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        this.contentId = urlParams.get('id');

        if (!this.contentId) {
            this.showError('No content ID provided');
            return;
        }

        // Wait for authentication
        if (!window.authManager || !window.authManager.getCurrentUser()) {
            window.addEventListener('auth:ready', async () => {
                await this.loadContent();
                this.setupEventListeners();
            });
        } else {
            await this.loadContent();
            this.setupEventListeners();
        }
    }

    /**
     * Load content from API
     */
    async loadContent() {
        try {
            const response = await window.authManager.apiCall(
                `/api/content.php?id=${this.contentId}`,
                { method: 'GET' }
            );

            if (!response.ok) {
                throw new Error('Failed to load content');
            }

            const data = await response.json();

            if (data.success && data.content) {
                this.content = data.content;
                this.renderContent();

                // Load brand kit
                await this.loadBrandKit();

                this.hideLoading();
            } else {
                throw new Error('Content not found');
            }
        } catch (error) {
            console.error('Error loading content:', error);
            this.showError('Failed to load content: ' + error.message);
        }
    }

    /**
     * Load brand kit
     */
    async loadBrandKit() {
        try {
            if (!window.brandKitManager) {
                console.warn('Brand kit manager not available');
                return;
            }

            await window.brandKitManager.fetch();
            console.log('Brand kit loaded in editor:', window.brandKitManager.getBrandKit());
        } catch (error) {
            console.error('Error loading brand kit:', error);
        }
    }

    /**
     * Render content into the preview
     */
    renderContent() {
        const preview = document.getElementById('email-preview');
        const titleEl = document.getElementById('content-title');
        const metaEl = document.getElementById('content-meta');

        // Update header
        if (titleEl) {
            titleEl.textContent = this.content.title || 'Email Template';
        }

        if (metaEl) {
            const updated = new Date(this.content.updated_at).toLocaleString();
            metaEl.textContent = `Last edited ${updated}`;
        }

        // Load email HTML
        if (this.content.email_body_html) {
            preview.innerHTML = this.content.email_body_html;
        } else {
            // Load default template if no HTML
            preview.innerHTML = this.getDefaultTemplate();
        }

        // Mark elements as editable
        this.markEditableElements();

        // Save original styles for undo
        this.saveOriginalStyles();
    }

    /**
     * Get default email template
     */
    getDefaultTemplate() {
        return `
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div id="email-header" class="editable-element" style="background-color: #2563eb; color: white; padding: 48px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 class="editable-element" style="margin: 0; font-size: 36px; font-weight: bold;">${this.content.title || 'Email Title'}</h1>
                    <p class="editable-element" style="margin: 16px 0 0 0; font-size: 18px;">Essential information for your team</p>
                </div>
                <div style="padding: 40px;">
                    <h2 class="editable-element" style="color: #111827; font-size: 24px; margin: 0 0 16px 0;">Main Content</h2>
                    <p class="editable-element" style="color: #4b5563; font-size: 16px; line-height: 1.6; margin: 0 0 24px 0;">
                        This is your email content. Click on any text to select it and edit using the sidebar controls, or double-click to edit inline.
                    </p>
                    <h2 class="editable-element" style="color: #111827; font-size: 24px; margin: 0 0 16px 0;">Additional Information</h2>
                    <p class="editable-element" style="color: #4b5563; font-size: 16px; line-height: 1.6; margin: 0 0 32px 0;">
                        Add more content here. You can customize colors, fonts, and text using the editor tools.
                    </p>
                    <a href="#" id="email-button" class="editable-element" style="display: inline-block; background-color: #2563eb; color: white; padding: 12px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">
                        Take Action
                    </a>
                </div>
                <div style="padding: 24px; text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb;">
                    <p style="margin: 0;">© 2025 Your Company. All rights reserved.</p>
                    <p style="margin: 8px 0 0 0;">Contact: info@company.com</p>
                </div>
            </div>
        `;
    }

    /**
     * Mark elements as editable
     */
    markEditableElements() {
        const preview = document.getElementById('email-preview');

        // Find all elements with .editable-element class
        const editableElements = preview.querySelectorAll('.editable-element');

        editableElements.forEach(el => {
            // Add click handler for selection
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectElement(el);
            });

            // Add double-click handler for inline editing
            el.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                if (this.textEditMode) {
                    this.startInlineEdit(el);
                }
            });
        });

        // Click outside to deselect
        preview.addEventListener('click', (e) => {
            if (e.target === preview || !e.target.closest('.editable-element')) {
                this.deselectElement();
            }
        });
    }

    /**
     * Save original styles for undo functionality
     */
    saveOriginalStyles() {
        const preview = document.getElementById('email-preview');
        const header = preview.querySelector('#email-header');
        const button = preview.querySelector('#email-button');

        if (header) {
            this.originalStyles.headerBG = header.style.backgroundColor || '#2563eb';
            this.originalStyles.headerColor = header.style.color || '#ffffff';
        }

        if (button) {
            this.originalStyles.buttonBG = button.style.backgroundColor || '#2563eb';
            this.originalStyles.buttonColor = button.style.color || '#ffffff';
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Text edit mode toggle
        const toggleTextBtn = document.getElementById('toggle-text-edit');
        if (toggleTextBtn) {
            toggleTextBtn.addEventListener('click', () => this.toggleTextEditMode());
        }

        // Save button
        const saveBtn = document.getElementById('save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveContent());
        }

        // Preview button
        const previewBtn = document.getElementById('preview-btn');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this.showPreview());
        }

        // Brand kit buttons
        const applyBrandBtn = document.getElementById('apply-brand-kit-btn');
        if (applyBrandBtn) {
            applyBrandBtn.addEventListener('click', () => this.applyBrandKit());
        }

        const undoBrandBtn = document.getElementById('undo-brand-kit-btn');
        if (undoBrandBtn) {
            undoBrandBtn.addEventListener('click', () => this.undoBrandKit());
        }

        // Element styling controls
        this.setupStylingControls();

        // Text content controls
        this.setupTextControls();

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    /**
     * Setup styling controls
     */
    setupStylingControls() {
        const bgColorPicker = document.getElementById('bg-color-picker');
        const bgColorHex = document.getElementById('bg-color-hex');
        const textColorPicker = document.getElementById('text-color-picker');
        const textColorHex = document.getElementById('text-color-hex');
        const fontSizeSelect = document.getElementById('font-size-select');
        const fontWeightSelect = document.getElementById('font-weight-select');

        // Background color
        if (bgColorPicker) {
            bgColorPicker.addEventListener('input', (e) => {
                if (this.selectedElement) {
                    this.selectedElement.style.backgroundColor = e.target.value;
                    bgColorHex.value = e.target.value.toUpperCase();
                    this.markUnsaved();
                }
            });
        }

        if (bgColorHex) {
            bgColorHex.addEventListener('change', (e) => {
                if (this.selectedElement) {
                    const color = this.normalizeHexColor(e.target.value);
                    if (color) {
                        this.selectedElement.style.backgroundColor = color;
                        bgColorPicker.value = color;
                        this.markUnsaved();
                    }
                }
            });
        }

        // Text color
        if (textColorPicker) {
            textColorPicker.addEventListener('input', (e) => {
                if (this.selectedElement) {
                    this.selectedElement.style.color = e.target.value;
                    textColorHex.value = e.target.value.toUpperCase();
                    this.markUnsaved();
                }
            });
        }

        if (textColorHex) {
            textColorHex.addEventListener('change', (e) => {
                if (this.selectedElement) {
                    const color = this.normalizeHexColor(e.target.value);
                    if (color) {
                        this.selectedElement.style.color = color;
                        textColorPicker.value = color;
                        this.markUnsaved();
                    }
                }
            });
        }

        // Font size
        if (fontSizeSelect) {
            fontSizeSelect.addEventListener('change', (e) => {
                if (this.selectedElement) {
                    this.selectedElement.style.fontSize = e.target.value;
                    this.markUnsaved();
                }
            });
        }

        // Font weight
        if (fontWeightSelect) {
            fontWeightSelect.addEventListener('change', (e) => {
                if (this.selectedElement) {
                    this.selectedElement.style.fontWeight = e.target.value;
                    this.markUnsaved();
                }
            });
        }
    }

    /**
     * Setup text content controls
     */
    setupTextControls() {
        const textInput = document.getElementById('text-content-input');
        const applyBtn = document.getElementById('text-apply-btn');
        const resetBtn = document.getElementById('text-reset-btn');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                if (this.selectedElement && textInput) {
                    const cleaned = this.sanitizeHTML(textInput.value);
                    this.selectedElement.innerHTML = cleaned;
                    this.showToast('Text updated');
                    this.markUnsaved();
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                if (this.selectedElement) {
                    this.selectedElement.innerHTML = this.originalTextHTML;
                    textInput.value = this.originalTextHTML;
                    this.showToast('Text reverted');
                }
            });
        }
    }

    /**
     * Select an element
     */
    selectElement(element) {
        // Deselect previous
        if (this.selectedElement) {
            this.selectedElement.classList.remove('selected');
        }

        // Select new
        this.selectedElement = element;
        element.classList.add('selected');

        // Update sidebar
        this.updateSidebar(element);

        // Show element editor
        document.getElementById('brand-kit-section').style.display = 'none';
        document.getElementById('element-editor-section').style.display = 'block';
    }

    /**
     * Deselect element
     */
    deselectElement() {
        if (this.selectedElement) {
            this.selectedElement.classList.remove('selected');
            this.selectedElement = null;
        }

        // Show brand kit section
        document.getElementById('brand-kit-section').style.display = 'block';
        document.getElementById('element-editor-section').style.display = 'none';
    }

    /**
     * Update sidebar with element properties
     */
    updateSidebar(element) {
        const styles = window.getComputedStyle(element);

        // Background color
        const bgColor = this.rgbToHex(styles.backgroundColor);
        document.getElementById('bg-color-picker').value = bgColor;
        document.getElementById('bg-color-hex').value = bgColor;

        // Text color
        const textColor = this.rgbToHex(styles.color);
        document.getElementById('text-color-picker').value = textColor;
        document.getElementById('text-color-hex').value = textColor;

        // Font size
        document.getElementById('font-size-select').value = styles.fontSize;

        // Font weight
        document.getElementById('font-weight-select').value = styles.fontWeight;

        // Text content
        this.originalTextHTML = element.innerHTML;
        document.getElementById('text-content-input').value = element.innerHTML;
    }

    /**
     * Toggle text edit mode
     */
    toggleTextEditMode() {
        this.textEditMode = !this.textEditMode;

        const preview = document.getElementById('email-preview');
        const hint = document.getElementById('text-edit-hint');
        const toggleBtn = document.getElementById('toggle-text-edit');

        if (this.textEditMode) {
            preview.classList.add('text-edit-mode');
            hint.classList.add('show');
            toggleBtn.classList.add('active');
        } else {
            preview.classList.remove('text-edit-mode');
            hint.classList.remove('show');
            toggleBtn.classList.remove('active');

            // Close any open TinyMCE editors
            if (window.tinymce) {
                tinymce.editors.slice().forEach(ed => ed.remove());
            }
        }
    }

    /**
     * Start inline editing with TinyMCE
     */
    startInlineEdit(element) {
        if (!window.tinymce) {
            // Fallback to contentEditable
            this.startNativeInlineEdit(element);
            return;
        }

        // If already editing, skip
        if (tinymce.get(element.id)) return;

        // Ensure element has an ID
        if (!element.id) {
            element.id = 'editable-' + Math.random().toString(36).slice(2);
        }

        this.originalTextHTML = element.innerHTML;

        tinymce.init({
            target: element,
            inline: true,
            menubar: false,
            toolbar_persist: true,
            plugins: 'link lists autolink',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link removeformat',
            valid_elements: 'a[href|title|target|rel],strong/b,em/i,u,span[style],p,br,h1,h2,h3,h4,h5,h6',
            branding: false,
            setup: (editor) => {
                editor.on('init', () => {
                    editor.focus();
                });

                editor.on('blur', () => {
                    const cleaned = this.sanitizeHTML(editor.getContent());
                    element.innerHTML = cleaned;
                    setTimeout(() => editor.remove(), 0);
                    this.showToast('Text updated');
                    this.markUnsaved();
                });

                editor.on('keydown', (e) => {
                    if (e.key === 'Escape') {
                        element.innerHTML = this.originalTextHTML;
                        editor.blur();
                    }
                });
            }
        });
    }

    /**
     * Fallback native inline editing
     */
    startNativeInlineEdit(element) {
        if (!element.getAttribute('data-editing')) {
            this.originalTextHTML = element.innerHTML;
            element.setAttribute('data-editing', '1');
        }

        element.contentEditable = 'true';
        element.focus();

        // Place cursor at end
        const selection = document.getSelection();
        const range = document.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);

        // Handle blur
        const blurHandler = () => {
            const cleaned = this.sanitizeHTML(element.innerHTML);
            element.innerHTML = cleaned;
            element.contentEditable = 'false';
            element.removeAttribute('data-editing');
            element.removeEventListener('blur', blurHandler);
            this.showToast('Text updated');
            this.markUnsaved();
        };

        element.addEventListener('blur', blurHandler);

        // Handle Escape key
        element.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                element.innerHTML = this.originalTextHTML;
                element.blur();
            }
        });
    }

    /**
     * Apply brand kit to template
     */
    async applyBrandKit() {
        if (!window.brandKitManager) {
            this.showError('Brand kit manager not available');
            return;
        }

        const brandKit = window.brandKitManager.getBrandKit();

        if (!brandKit) {
            this.showError('Brand kit not loaded. Please try again.');
            // Try to load it
            await this.loadBrandKit();
            return;
        }

        console.log('Applying brand kit:', brandKit);

        const preview = document.getElementById('email-preview');

        // Apply to header
        const header = preview.querySelector('#email-header');
        if (header) {
            console.log('Applying to header:', brandKit.primary_color, brandKit.text_color);
            header.style.backgroundColor = brandKit.primary_color;
            header.style.color = brandKit.text_color;

            // Apply to nested editable elements in header
            header.querySelectorAll('.editable-element').forEach(el => {
                el.style.color = brandKit.text_color;
            });
        } else {
            console.warn('Header element #email-header not found');
        }

        // Apply to button
        const button = preview.querySelector('#email-button');
        if (button) {
            console.log('Applying to button:', brandKit.primary_color, brandKit.text_color);
            button.style.backgroundColor = brandKit.primary_color;
            button.style.color = brandKit.text_color;
        } else {
            console.warn('Button element #email-button not found');
        }

        // Update UI
        this.brandKitApplied = true;
        this.updateBrandKitStatus(true);
        this.showToast('Brand kit applied');
        this.markUnsaved();
    }

    /**
     * Undo brand kit changes
     */
    undoBrandKit() {
        const preview = document.getElementById('email-preview');

        // Restore header
        const header = preview.querySelector('#email-header');
        if (header) {
            header.style.backgroundColor = this.originalStyles.headerBG;
            header.style.color = this.originalStyles.headerColor;

            header.querySelectorAll('.editable-element').forEach(el => {
                el.style.color = this.originalStyles.headerColor;
            });
        }

        // Restore button
        const button = preview.querySelector('#email-button');
        if (button) {
            button.style.backgroundColor = this.originalStyles.buttonBG;
            button.style.color = this.originalStyles.buttonColor;
        }

        // Update UI
        this.brandKitApplied = false;
        this.updateBrandKitStatus(false);
        this.showToast('Brand kit changes undone');
        this.markUnsaved();
    }

    /**
     * Update brand kit status UI
     */
    updateBrandKitStatus(applied) {
        const statusDiv = document.getElementById('brand-kit-status');
        const statusIcon = document.getElementById('status-icon');
        const statusTitle = document.getElementById('status-title');
        const statusMessage = document.getElementById('status-message');
        const applyBtn = document.getElementById('apply-brand-kit-btn');
        const undoBtn = document.getElementById('undo-brand-kit-btn');

        if (applied) {
            statusDiv.classList.add('applied');
            statusIcon.classList.add('success');
            statusIcon.textContent = '✓';
            statusTitle.textContent = 'Brand Kit Applied';
            statusMessage.textContent = 'Your template has been updated with your brand colors.';
            applyBtn.style.display = 'none';
            undoBtn.style.display = 'block';
        } else {
            statusDiv.classList.remove('applied');
            statusIcon.classList.remove('success');
            statusIcon.textContent = '✨';
            statusTitle.textContent = 'Brand Kit Available';
            statusMessage.textContent = 'Apply your company\'s brand colors to this template automatically.';
            applyBtn.style.display = 'block';
            undoBtn.style.display = 'none';
        }
    }

    /**
     * Save content to database
     */
    async saveContent() {
        try {
            const preview = document.getElementById('email-preview');
            const updatedHTML = preview.innerHTML;

            const response = await window.authManager.apiCall(
                `/api/content.php?id=${this.contentId}`,
                {
                    method: 'PUT',
                    body: JSON.stringify({
                        email_body_html: updatedHTML
                    })
                }
            );

            if (!response.ok) {
                throw new Error('Failed to save content');
            }

            const data = await response.json();

            if (data.success) {
                this.hasUnsavedChanges = false;
                this.showToast('Template saved successfully');

                // Update meta
                const metaEl = document.getElementById('content-meta');
                if (metaEl) {
                    metaEl.textContent = `Last edited ${new Date().toLocaleString()}`;
                }
            } else {
                throw new Error('Save failed');
            }
        } catch (error) {
            console.error('Error saving content:', error);
            this.showError('Failed to save: ' + error.message);
        }
    }

    /**
     * Show preview (placeholder)
     */
    showPreview() {
        // TODO: Open preview in new window/modal
        alert('Preview functionality coming soon!');
    }

    /**
     * Mark content as having unsaved changes
     */
    markUnsaved() {
        this.hasUnsavedChanges = true;
    }

    /**
     * Sanitize HTML to prevent XSS
     */
    sanitizeHTML(dirty) {
        const allowed = new Set(['A', 'B', 'I', 'U', 'STRONG', 'EM', 'BR', 'P', 'SPAN', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'DIV']);
        const tmp = document.createElement('div');
        tmp.innerHTML = dirty;

        // Remove dangerous elements
        tmp.querySelectorAll('script,style,iframe,object,embed').forEach(n => n.remove());

        // Clean attributes
        const nodes = tmp.getElementsByTagName('*');
        for (let i = nodes.length - 1; i >= 0; i--) {
            const el = nodes[i];

            if (!allowed.has(el.tagName)) {
                const parent = el.parentNode;
                while (el.firstChild) parent.insertBefore(el.firstChild, el);
                parent.removeChild(el);
                continue;
            }

            // Remove event handlers
            for (let j = el.attributes.length - 1; j >= 0; j--) {
                const attr = el.attributes[j];
                if (attr.name.toLowerCase().startsWith('on')) {
                    el.removeAttribute(attr.name);
                }
            }

            // Special handling for links
            if (el.tagName === 'A') {
                const href = (el.getAttribute('href') || '').trim();
                if (href.toLowerCase().startsWith('javascript:')) {
                    el.removeAttribute('href');
                }
            }
        }

        return tmp.innerHTML;
    }

    /**
     * Normalize hex color
     */
    normalizeHexColor(color) {
        color = color.trim();
        if (!color.startsWith('#')) {
            color = '#' + color;
        }
        if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
            return color.toUpperCase();
        }
        return null;
    }

    /**
     * Convert RGB to hex
     */
    rgbToHex(rgb) {
        const match = rgb.match(/\d+/g);
        if (!match) return '#000000';

        const r = parseInt(match[0]);
        const g = parseInt(match[1]);
        const b = parseInt(match[2]);

        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
    }

    /**
     * Hide loading screen
     */
    hideLoading() {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('editor-container').style.display = 'flex';
    }

    /**
     * Show toast notification
     */
    showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.toggle('error', isError);
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showToast(message, true);
    }
}

// Initialize editor when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.emailEditor = new EmailEditor();
});
