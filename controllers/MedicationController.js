/**
 * Medication Controller - Handles business logic and coordinates between models and views
 */
class MedicationController {
    constructor() {
        this.inventoryModel = new InventoryModel();
        this.logsModel = new LogsModel();
        this.lowStockThreshold = 10;
        this.currentView = null;
        this.init();
    }

    /**
     * Initialize the controller
     */
    init() {
        this.setupEventListeners();
        this.updateDateTime();
        this.loadInitialData();
        this.startClock();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Form submission
        document.getElementById('dispensingForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleDispensing();
        });

        // Medication selection
        document.getElementById('medicationSelect').addEventListener('change', () => {
            this.updateStockStatus();
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.refreshInventory();
        });

        // Search functionality
        document.getElementById('inventorySearch').addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        // Add medication modal
        document.getElementById('addInventoryBtn').addEventListener('click', () => {
            this.showAddMedicationModal();
        });

        document.getElementById('addMedicationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleAddMedication();
        });

        // Modal close buttons
        document.querySelectorAll('.close, .close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                this.hideModal();
            });
        });

        // Close modal on outside click
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('addMedicationModal');
            if (e.target === modal) {
                this.hideModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }

    /**
     * Load initial data into views
     */
    loadInitialData() {
        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.renderLogsTable();
    }

    /**
     * Handle medication dispensing
     */
    handleDispensing() {
        const medicationId = document.getElementById('medicationSelect').value;
        const dosage = document.getElementById('dosageInput').value.trim();
        const remark = document.getElementById('remarkInput').value.trim();
        const dispensedBy = document.getElementById('userSelect').value;

        // Validation
        if (!medicationId || !dosage) {
            this.showAlert('Please select medication and enter dosage', 'danger');
            return;
        }

        const medication = this.inventoryModel.getMedicationById(medicationId);
        if (!medication) {
            this.showAlert('Medication not found', 'danger');
            return;
        }

        const stockStatus = this.inventoryModel.getStockStatus(medicationId, this.lowStockThreshold);
        if (!stockStatus.available) {
            this.showAlert('Medication is out of stock', 'danger');
            return;
        }

        // Perform dispensing
        const dispenseSuccess = this.inventoryModel.dispenseMedication(medicationId);
        if (!dispenseSuccess) {
            this.showAlert('Failed to dispense medication', 'danger');
            return;
        }

        // Add log entry
        const logEntry = {
            medicationId: medication.id,
            medicationName: medication.name,
            dosage: dosage,
            dispensedBy: dispensedBy,
            remark: remark
        };

        const logSuccess = this.logsModel.addLog(logEntry);
        if (!logSuccess) {
            // Rollback inventory change if logging fails
            this.inventoryModel.updateQuantity(medicationId, medication.quantity + 1);
            this.showAlert('Failed to log dispensing action', 'danger');
            return;
        }

        // Update UI
        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.renderLogsTable();
        this.updateStockStatus();

        // Clear form
        this.clearDispensingForm();

        // Show success message
        this.showAlert(`Successfully dispensed ${medication.name} (${dosage})`, 'success');

        // Check for low stock alert
        this.checkLowStockAlert(medication);
    }

    /**
     * Handle adding new medication
     */
    handleAddMedication() {
        const name = document.getElementById('newMedicationName').value.trim();
        const dosage = document.getElementById('newMedicationDosage').value.trim();
        const quantity = parseInt(document.getElementById('newMedicationQuantity').value);

        // Validation
        if (!name || !dosage || isNaN(quantity) || quantity < 0) {
            this.showAlert('Please fill all fields with valid values', 'danger');
            return;
        }

        const newMedication = {
            name: name,
            dosage: dosage,
            quantity: quantity
        };

        const success = this.inventoryModel.addMedication(newMedication);
        if (success) {
            this.loadMedicationDropdown();
            this.renderInventoryTable();
            this.hideModal();
            this.showAlert(`Successfully added ${name} to inventory`, 'success');
        } else {
            this.showAlert('Failed to add medication', 'danger');
        }
    }

    /**
     * Handle search functionality
     */
    handleSearch(searchTerm) {
        const filteredMedications = this.inventoryModel.searchMedications(searchTerm);
        this.renderInventoryTable(filteredMedications);
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + D for quick dispense focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            document.getElementById('medicationSelect').focus();
        }
        
        // Escape to close modal
        if (e.key === 'Escape') {
            const modal = document.getElementById('addMedicationModal');
            if (modal.style.display === 'block') {
                this.hideModal();
            }
        }
    }

    /**
     * Load medication dropdown
     */
    loadMedicationDropdown() {
        const select = document.getElementById('medicationSelect');
        select.innerHTML = '<option value="">Select Medication...</option>';
        
        const medications = this.inventoryModel.getAllMedications();
        medications.forEach(med => {
            const option = document.createElement('option');
            option.value = med.id;
            option.textContent = `${med.name} (${med.dosage})`;
            option.disabled = med.quantity === 0;
            if (med.quantity === 0) {
                option.textContent += ' - OUT OF STOCK';
            }
            select.appendChild(option);
        });
    }

    /**
     * Update stock status display
     */
    updateStockStatus() {
        const medicationId = document.getElementById('medicationSelect').value;
        const stockStatus = document.getElementById('stockStatus');
        const unitsRemaining = document.getElementById('unitsRemaining');
        const dispenseBtn = document.getElementById('dispenseBtn');

        if (!medicationId) {
            stockStatus.textContent = '--';
            unitsRemaining.textContent = '-- units remaining';
            stockStatus.className = '';
            dispenseBtn.disabled = true;
            return;
        }

        const status = this.inventoryModel.getStockStatus(medicationId, this.lowStockThreshold);
        unitsRemaining.textContent = `${status.quantity} units remaining`;
        
        // Update status display
        stockStatus.className = '';
        switch (status.status) {
            case 'out_of_stock':
                stockStatus.textContent = 'OUT OF STOCK';
                stockStatus.classList.add('text-danger', 'fw-bold');
                dispenseBtn.disabled = true;
                break;
            case 'low_stock':
                stockStatus.textContent = 'LOW STOCK';
                stockStatus.classList.add('text-warning', 'fw-bold');
                dispenseBtn.disabled = false;
                break;
            case 'in_stock':
                stockStatus.textContent = 'IN STOCK';
                stockStatus.classList.add('text-success', 'fw-bold');
                dispenseBtn.disabled = false;
                break;
            default:
                stockStatus.textContent = '--';
                dispenseBtn.disabled = true;
        }
    }

    /**
     * Render inventory table
     */
    renderInventoryTable(medications = null) {
        const tbody = document.getElementById('inventoryTableBody');
        tbody.innerHTML = '';

        const medicationList = medications || this.inventoryModel.getAllMedications();
        
        if (medicationList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No medications found</td></tr>';
            return;
        }

        medicationList.forEach(medication => {
            const row = document.createElement('tr');
            
            // Add row classes for Bootstrap styling
            if (medication.quantity === 0) {
                row.classList.add('table-danger');
            } else if (medication.quantity <= this.lowStockThreshold) {
                row.classList.add('table-warning');
            }

            const statusBadge = this.getStatusBadge(medication.quantity);
            
            row.innerHTML = `
                <td>${medication.id}</td>
                <td>${medication.name}</td>
                <td>${medication.dosage}</td>
                <td>${medication.quantity}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="medicationController.editMedication('${medication.id}')">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    /**
     * Render logs table
     */
    renderLogsTable() {
        const tbody = document.getElementById('logsTableBody');
        tbody.innerHTML = '';

        const recentLogs = this.logsModel.getRecentLogs(10);
        
        if (recentLogs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No dispensing logs available</td></tr>';
            return;
        }

        recentLogs.forEach(log => {
            const formattedLog = this.logsModel.formatLogForDisplay(log);
            const row = document.createElement('tr');

            row.innerHTML = `
                <td>${log.medicationId}</td>
                <td>${log.medicationName}</td>
                <td>${log.dosage}</td>
                <td>${formattedLog.formattedDateTime}</td>
                <td>${log.dispensedBy}</td>
                <td>${log.remark}</td>
            `;
            
            tbody.appendChild(row);
        });
    }

    /**
     * Get status badge HTML
     */
    getStatusBadge(quantity) {
        if (quantity === 0) {
            return '<span class="badge bg-danger">Out of Stock</span>';
        } else if (quantity <= this.lowStockThreshold) {
            return '<span class="badge bg-warning">Low Stock</span>';
        } else {
            return '<span class="badge bg-success">In Stock</span>';
        }
    }

    /**
     * Edit medication quantity
     */
    editMedication(medicationId) {
        const medication = this.inventoryModel.getMedicationById(medicationId);
        if (!medication) return;

        const newQuantity = prompt(`Update quantity for ${medication.name} (current: ${medication.quantity}):`, medication.quantity);
        
        if (newQuantity !== null) {
            const quantity = parseInt(newQuantity);
            if (!isNaN(quantity) && quantity >= 0) {
                const success = this.inventoryModel.updateQuantity(medicationId, quantity);
                if (success) {
                    this.loadMedicationDropdown();
                    this.renderInventoryTable();
                    this.updateStockStatus();
                    this.showAlert(`Updated ${medication.name} quantity to ${quantity}`, 'success');
                } else {
                    this.showAlert('Failed to update quantity', 'danger');
                }
            } else {
                this.showAlert('Please enter a valid quantity', 'danger');
            }
        }
    }

    /**
     * Check and show low stock alert
     */
    checkLowStockAlert(medication) {
        const status = this.inventoryModel.getStockStatus(medication.id, this.lowStockThreshold);
        if (status.status === 'low_stock') {
            setTimeout(() => {
                this.showAlert(`Warning: ${medication.name} is running low on stock (${status.quantity} units remaining)`, 'warning');
            }, 1000);
        }
    }

    /**
     * Refresh inventory display
     */
    refreshInventory() {
        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.showAlert('Inventory refreshed successfully', 'success');
    }

    /**
     * Clear dispensing form
     */
    clearDispensingForm() {
        document.getElementById('dosageInput').value = '';
        document.getElementById('remarkInput').value = '';
        document.getElementById('medicationSelect').value = '';
    }

    /**
     * Show add medication modal
     */
    showAddMedicationModal() {
        document.getElementById('addMedicationModal').style.display = 'block';
        document.getElementById('newMedicationName').focus();
    }

    /**
     * Hide modal
     */
    hideModal() {
        document.getElementById('addMedicationModal').style.display = 'none';
        document.getElementById('addMedicationForm').reset();
    }

    /**
     * Show alert message
     */
    showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alert);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    /**
     * Update date and time display
     */
    updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
    }

    /**
     * Start clock for updating time
     */
    startClock() {
        setInterval(() => {
            this.updateDateTime();
        }, 60000); // Update every minute
    }

    /**
     * Export all data
     */
    exportData() {
        const data = {
            inventory: this.inventoryModel.exportData(),
            logs: this.logsModel.exportData(),
            exportDate: new Date().toISOString()
        };
        
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `medication_data_${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showAlert('Data exported successfully', 'success');
    }

    /**
     * Import data
     */
    importData(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);
                let success = true;
                
                if (data.inventory) {
                    success = this.inventoryModel.importData(data.inventory) && success;
                }
                
                if (data.logs) {
                    success = this.logsModel.importData(data.logs) && success;
                }
                
                if (success) {
                    this.loadInitialData();
                    this.showAlert('Data imported successfully', 'success');
                } else {
                    this.showAlert('Error importing data', 'danger');
                }
            } catch (error) {
                this.showAlert('Error importing data: ' + error.message, 'danger');
            }
        };
        reader.readAsText(file);
    }

    /**
     * Get system statistics
     */
    getStatistics() {
        return {
            inventory: {
                total: this.inventoryModel.getAllMedications().length,
                inStock: this.inventoryModel.getAllMedications().filter(med => med.quantity > 0).length,
                lowStock: this.inventoryModel.getLowStockMedications(this.lowStockThreshold).length,
                outOfStock: this.inventoryModel.getOutOfStockMedications().length
            },
            logs: this.logsModel.getStatistics()
        };
    }
}
