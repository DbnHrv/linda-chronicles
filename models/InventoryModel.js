/**
 * Inventory Model - Handles medication inventory data management
 */
class InventoryModel {
    constructor() {
        this.storageKey = 'medicationInventory';
        this.inventory = this.loadInventory();
    }

    /**
     * Load inventory from localStorage
     * @returns {Array} Array of medication objects
     */
    loadInventory() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            return saved ? JSON.parse(saved) : this.getDefaultInventory();
        } catch (error) {
            console.error('Error loading inventory:', error);
            return this.getDefaultInventory();
        }
    }

    /**
     * Get default sample inventory data
     * @returns {Array} Default inventory array
     */
    getDefaultInventory() {
        return [
            { id: 'MED001', name: 'Paracetamol', dosage: '500mg', quantity: 50, unitPrice: 15.50 },
            { id: 'MED002', name: 'Ibuprofen', dosage: '400mg', quantity: 8, unitPrice: 22.75 },
            { id: 'MED003', name: 'Amoxicillin', dosage: '250mg', quantity: 30, unitPrice: 45.00 },
            { id: 'MED004', name: 'Aspirin', dosage: '100mg', quantity: 0, unitPrice: 18.25 },
            { id: 'MED005', name: 'Cough Syrup', dosage: '100ml', quantity: 15, unitPrice: 65.00 }
        ];
    }

    /**
     * Save inventory to localStorage
     */
    saveInventory() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.inventory));
            return true;
        } catch (error) {
            console.error('Error saving inventory:', error);
            return false;
        }
    }

    /**
     * Get all inventory items
     * @returns {Array} All medications
     */
    getAllMedications() {
        return [...this.inventory];
    }

    /**
     * Get medication by ID
     * @param {string} medicationId - Medication ID
     * @returns {Object|null} Medication object or null
     */
    getMedicationById(medicationId) {
        return this.inventory.find(med => med.id === medicationId) || null;
    }

    /**
     * Add new medication
     * @param {Object} medication - Medication object
     * @returns {boolean} Success status
     */
    addMedication(medication) {
        try {
            // Generate unique ID
            medication.id = this.generateMedicationId();
            this.inventory.push(medication);
            return this.saveInventory();
        } catch (error) {
            console.error('Error adding medication:', error);
            return false;
        }
    }

    /**
     * Update medication quantity
     * @param {string} medicationId - Medication ID
     * @param {number} newQuantity - New quantity
     * @returns {boolean} Success status
     */
    updateQuantity(medicationId, newQuantity) {
        try {
            const medication = this.getMedicationById(medicationId);
            if (medication) {
                medication.quantity = parseInt(newQuantity);
                return this.saveInventory();
            }
            return false;
        } catch (error) {
            console.error('Error updating quantity:', error);
            return false;
        }
    }

    /**
     * Dispense medication (reduce quantity by 1)
     * @param {string} medicationId - Medication ID
     * @returns {boolean} Success status
     */
    dispenseMedication(medicationId) {
        try {
            const medication = this.getMedicationById(medicationId);
            if (medication && medication.quantity > 0) {
                medication.quantity -= 1;
                return this.saveInventory();
            }
            return false;
        } catch (error) {
            console.error('Error dispensing medication:', error);
            return false;
        }
    }

    /**
     * Get medications with low stock
     * @param {number} threshold - Low stock threshold
     * @returns {Array} Low stock medications
     */
    getLowStockMedications(threshold = 10) {
        return this.inventory.filter(med => med.quantity > 0 && med.quantity <= threshold);
    }

    /**
     * Get out of stock medications
     * @returns {Array} Out of stock medications
     */
    getOutOfStockMedications() {
        return this.inventory.filter(med => med.quantity === 0);
    }

    /**
     * Get stock status for a medication
     * @param {string} medicationId - Medication ID
     * @param {number} threshold - Low stock threshold
     * @returns {Object} Stock status information
     */
    getStockStatus(medicationId, threshold = 10) {
        const medication = this.getMedicationById(medicationId);
        if (!medication) {
            return { status: 'not_found', quantity: 0, available: false };
        }

        if (medication.quantity === 0) {
            return { status: 'out_of_stock', quantity: 0, available: false };
        } else if (medication.quantity <= threshold) {
            return { status: 'low_stock', quantity: medication.quantity, available: true };
        } else {
            return { status: 'in_stock', quantity: medication.quantity, available: true };
        }
    }

    /**
     * Generate unique medication ID
     * @returns {string} Generated ID
     */
    generateMedicationId() {
        const maxId = Math.max(...this.inventory.map(med => {
            const match = med.id.match(/MED(\d+)/);
            return match ? parseInt(match[1]) : 0;
        }), 0);
        return 'MED' + String(maxId + 1).padStart(3, '0');
    }

    /**
     * Search medications by name or ID
     * @param {string} searchTerm - Search term
     * @returns {Array} Filtered medications
     */
    searchMedications(searchTerm) {
        const term = searchTerm.toLowerCase();
        return this.inventory.filter(med => 
            med.name.toLowerCase().includes(term) || 
            med.id.toLowerCase().includes(term) ||
            med.dosage.toLowerCase().includes(term)
        );
    }

    /**
     * Reset inventory to default data
     */
    resetToDefault() {
        this.inventory = this.getDefaultInventory();
        return this.saveInventory();
    }

    /**
     * Export inventory data
     * @returns {Object} Inventory data
     */
    exportData() {
        return {
            inventory: this.inventory,
            exportDate: new Date().toISOString(),
            version: '1.0.0'
        };
    }

    /**
     * Import inventory data
     * @param {Object} data - Inventory data to import
     * @returns {boolean} Success status
     */
    importData(data) {
        try {
            if (data && Array.isArray(data.inventory)) {
                this.inventory = data.inventory;
                return this.saveInventory();
            }
            return false;
        } catch (error) {
            console.error('Error importing inventory data:', error);
            return false;
        }
    }
}
