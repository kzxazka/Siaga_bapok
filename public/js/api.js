class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
        this.headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            method: 'GET',
            headers: { ...this.headers, ...options.headers },
            ...options
        };

        // Show loading state
        this.showLoading(true);

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data;

        } catch (error) {
            console.error('API Error:', error);
            this.showError(error.message);
            throw error;
        } finally {
            this.showLoading(false);
        }
    }

    showLoading(show) {
        const loader = document.getElementById('loading-overlay') || this.createLoader();
        loader.style.display = show ? 'flex' : 'none';
    }

    showError(message) {
        const errorContainer = document.getElementById('error-container') || this.createErrorContainer();
        errorContainer.textContent = message;
        errorContainer.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorContainer.style.display = 'none';
        }, 5000);
    }

    createLoader() {
        const loader = document.createElement('div');
        loader.id = 'loading-overlay';
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        loader.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        `;
        document.body.appendChild(loader);
        return loader;
    }

    createErrorContainer() {
        const container = document.createElement('div');
        container.id = 'error-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            z-index: 9999;
            display: none;
            max-width: 400px;
        `;
        document.body.appendChild(container);
        return container;
    }
}

// Usage example:
const api = new ApiClient('/api');

// Example API call with loading and error handling
async function loadProducts(page = 1) {
    try {
        const data = await api.request(`/products?page=${page}`);
        renderProducts(data.products);
        updatePagination(data.pagination);
    } catch (error) {
        // Error is already handled by ApiClient
    }
}