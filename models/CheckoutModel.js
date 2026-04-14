/**
 * Checkout Model - Handles transaction data and pricing calculations
 */
class CheckoutModel {
    constructor() {
        this.currentTransaction = null;
        this.transactionHistory = this.loadTransactionHistory();
        this.vatRate = 0.12; // 12% VAT
        this.discounts = {
            senior: 0.20,    // 20% senior citizen discount
            pwd: 0.20        // 20% PWD discount
        };
    }

    /**
     * Load transaction history from localStorage
     * @returns {Array} Array of completed transactions
     */
    loadTransactionHistory() {
        try {
            const saved = localStorage.getItem('transactionHistory');
            return saved ? JSON.parse(saved) : [];
        } catch (error) {
            console.error('Error loading transaction history:', error);
            return [];
        }
    }

    /**
     * Save transaction history to localStorage
     */
    saveTransactionHistory() {
        try {
            localStorage.setItem('transactionHistory', JSON.stringify(this.transactionHistory));
            return true;
        } catch (error) {
            console.error('Error saving transaction history:', error);
            return false;
        }
    }

    /**
     * Start a new transaction
     * @param {Object} dispensedItem - Item that was just dispensed
     */
    startTransaction(dispensedItem) {
        this.currentTransaction = {
            id: this.generateTransactionId(),
            items: [dispensedItem],
            dateTime: new Date().toISOString(),
            status: 'active',
            subtotal: 0,
            discount: 0,
            discountType: null,
            vat: 0,
            total: 0,
            paymentMethod: null,
            customerInfo: {
                name: '',
                type: 'regular' // regular, senior, pwd
            }
        };
        
        this.calculateTotals();
        return this.currentTransaction;
    }

    /**
     * Add item to current transaction
     * @param {Object} item - Item to add
     */
    addItem(item) {
        if (!this.currentTransaction) {
            this.startTransaction(item);
            return;
        }

        // Check if item already exists in transaction
        const existingItemIndex = this.currentTransaction.items.findIndex(
            i => i.medicationId === item.medicationId && i.dosage === item.dosage
        );

        if (existingItemIndex !== -1) {
            // Update quantity if item exists
            this.currentTransaction.items[existingItemIndex].quantity += 1;
        } else {
            // Add new item
            item.quantity = 1;
            this.currentTransaction.items.push(item);
        }

        this.calculateTotals();
    }

    /**
     * Remove item from current transaction
     * @param {number} itemIndex - Index of item to remove
     */
    removeItem(itemIndex) {
        if (!this.currentTransaction || itemIndex < 0 || itemIndex >= this.currentTransaction.items.length) {
            return false;
        }

        this.currentTransaction.items.splice(itemIndex, 1);
        
        if (this.currentTransaction.items.length === 0) {
            this.currentTransaction = null;
        } else {
            this.calculateTotals();
        }
        
        return true;
    }

    /**
     * Update item quantity
     * @param {number} itemIndex - Index of item to update
     * @param {number} quantity - New quantity
     */
    updateItemQuantity(itemIndex, quantity) {
        if (!this.currentTransaction || itemIndex < 0 || itemIndex >= this.currentTransaction.items.length) {
            return false;
        }

        if (quantity <= 0) {
            return this.removeItem(itemIndex);
        }

        this.currentTransaction.items[itemIndex].quantity = quantity;
        this.calculateTotals();
        return true;
    }

    /**
     * Apply discount
     * @param {string} discountType - Type of discount ('senior', 'pwd', or null)
     */
    applyDiscount(discountType) {
        if (!this.currentTransaction) return false;

        if (discountType && this.discounts[discountType]) {
            this.currentTransaction.discountType = discountType;
            this.currentTransaction.customerInfo.type = discountType;
        } else {
            this.currentTransaction.discountType = null;
            this.currentTransaction.customerInfo.type = 'regular';
        }

        this.calculateTotals();
        return true;
    }

    /**
     * Set customer name
     * @param {string} customerName - Customer name
     */
    setCustomerName(customerName) {
        if (!this.currentTransaction) return false;
        
        this.currentTransaction.customerInfo.name = customerName;
        return true;
    }

    /**
     * Calculate totals for current transaction
     */
    calculateTotals() {
        if (!this.currentTransaction) return;

        // Calculate subtotal
        this.currentTransaction.subtotal = this.currentTransaction.items.reduce((sum, item) => {
            return sum + (item.unitPrice * (item.quantity || 1));
        }, 0);

        // Calculate discount
        if (this.currentTransaction.discountType && this.discounts[this.currentTransaction.discountType]) {
            this.currentTransaction.discount = this.currentTransaction.subtotal * this.discounts[this.currentTransaction.discountType];
        } else {
            this.currentTransaction.discount = 0;
        }

        // Calculate VAT on discounted amount
        const taxableAmount = this.currentTransaction.subtotal - this.currentTransaction.discount;
        this.currentTransaction.vat = taxableAmount * this.vatRate;

        // Calculate total
        this.currentTransaction.total = taxableAmount + this.currentTransaction.vat;
    }

    /**
     * Set payment method
     * @param {string} paymentMethod - Payment method ('cash', 'credit_card', 'insurance')
     */
    setPaymentMethod(paymentMethod) {
        if (!this.currentTransaction) return false;
        
        this.currentTransaction.paymentMethod = paymentMethod;
        return true;
    }

    /**
     * Complete transaction
     * @param {Object} paymentDetails - Additional payment details
     * @returns {Object} Completed transaction
     */
    completeTransaction(paymentDetails = {}) {
        if (!this.currentTransaction) return null;

        // Update transaction status
        this.currentTransaction.status = 'completed';
        this.currentTransaction.completedAt = new Date().toISOString();
        
        // Add payment details
        if (paymentDetails.cashReceived && this.currentTransaction.paymentMethod === 'cash') {
            this.currentTransaction.cashReceived = paymentDetails.cashReceived;
            this.currentTransaction.change = paymentDetails.cashReceived - this.currentTransaction.total;
        }

        // Add to history
        this.transactionHistory.push({...this.currentTransaction});
        this.saveTransactionHistory();

        const completedTransaction = {...this.currentTransaction};
        this.currentTransaction = null;
        
        return completedTransaction;
    }

    /**
     * Cancel current transaction
     */
    cancelTransaction() {
        this.currentTransaction = null;
    }

    /**
     * Get current transaction
     * @returns {Object|null} Current transaction
     */
    getCurrentTransaction() {
        return this.currentTransaction ? {...this.currentTransaction} : null;
    }

    /**
     * Get transaction history
     * @returns {Array} Transaction history
     */
    getTransactionHistory() {
        return [...this.transactionHistory];
    }

    /**
     * Generate transaction ID
     * @returns {string} Generated transaction ID
     */
    generateTransactionId() {
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 1000);
        return `TXN${timestamp}${random}`;
    }

    /**
     * Format currency amount
     * @param {number} amount - Amount to format
     * @returns {string} Formatted currency
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    }

    /**
     * Get receipt data for printing
     * @returns {Object} Receipt data
     */
    getReceiptData() {
        if (!this.currentTransaction) return null;

        return {
            transactionId: this.currentTransaction.id,
            dateTime: new Date(this.currentTransaction.dateTime).toLocaleString('en-PH'),
            customerName: this.currentTransaction.customerInfo.name || 'Walk-in Customer',
            customerType: this.currentTransaction.customerInfo.type,
            items: this.currentTransaction.items.map(item => ({
                name: item.medicationName,
                dosage: item.dosage,
                quantity: item.quantity || 1,
                unitPrice: item.unitPrice,
                subtotal: item.unitPrice * (item.quantity || 1)
            })),
            subtotal: this.currentTransaction.subtotal,
            discount: this.currentTransaction.discount,
            discountType: this.currentTransaction.discountType,
            vat: this.currentTransaction.vat,
            total: this.currentTransaction.total,
            paymentMethod: this.currentTransaction.paymentMethod,
            cashier: 'Pharmacy Staff' // This would come from user session
        };
    }

    /**
     * Export transaction data
     * @returns {Object} Export data
     */
    exportData() {
        return {
            currentTransaction: this.currentTransaction,
            transactionHistory: this.transactionHistory,
            exportDate: new Date().toISOString(),
            version: '1.0.0'
        };
    }

    /**
     * Import transaction data
     * @param {Object} data - Data to import
     * @returns {boolean} Success status
     */
    importData(data) {
        try {
            if (data.transactionHistory && Array.isArray(data.transactionHistory)) {
                this.transactionHistory = data.transactionHistory;
                return this.saveTransactionHistory();
            }
            return false;
        } catch (error) {
            console.error('Error importing transaction data:', error);
            return false;
        }
    }

    /**
     * Get sales statistics
     * @returns {Object} Sales statistics
     */
    getSalesStatistics() {
        const stats = {
            totalTransactions: this.transactionHistory.length,
            totalRevenue: 0,
            totalDiscounts: 0,
            totalVAT: 0,
            paymentMethods: {},
            customerTypes: {},
            topItems: {},
            dailySales: {}
        };

        this.transactionHistory.forEach(transaction => {
            if (transaction.status === 'completed') {
                stats.totalRevenue += transaction.total;
                stats.totalDiscounts += transaction.discount;
                stats.totalVAT += transaction.vat;

                // Payment methods
                if (transaction.paymentMethod) {
                    stats.paymentMethods[transaction.paymentMethod] = 
                        (stats.paymentMethods[transaction.paymentMethod] || 0) + 1;
                }

                // Customer types
                if (transaction.customerInfo && transaction.customerInfo.type) {
                    stats.customerTypes[transaction.customerInfo.type] = 
                        (stats.customerTypes[transaction.customerInfo.type] || 0) + 1;
                }

                // Top items
                transaction.items.forEach(item => {
                    const itemKey = `${item.medicationName} (${item.dosage})`;
                    if (!stats.topItems[itemKey]) {
                        stats.topItems[itemKey] = { quantity: 0, revenue: 0 };
                    }
                    stats.topItems[itemKey].quantity += (item.quantity || 1);
                    stats.topItems[itemKey].revenue += item.unitPrice * (item.quantity || 1);
                });

                // Daily sales
                const date = new Date(transaction.dateTime).toLocaleDateString('en-PH');
                if (!stats.dailySales[date]) {
                    stats.dailySales[date] = { transactions: 0, revenue: 0 };
                }
                stats.dailySales[date].transactions += 1;
                stats.dailySales[date].revenue += transaction.total;
            }
        });

        return stats;
    }
}
