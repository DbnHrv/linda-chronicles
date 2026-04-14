// Medication Dispensing & Log System JavaScript
class MedicationSystem {
    constructor() {
        this.inventory = this.loadInventory();
        this.logs = this.loadLogs();
        this.lowStockThreshold = 10;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateDateTime();
        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.renderLogsTable();
        this.startClock();
    }

    // Data Management
    loadInventory() {
        const saved = localStorage.getItem('medicationInventory');
        if (saved) {
            return JSON.parse(saved);
        }
        // Default sample data
        return [
            { id: 'MED001', name: 'Paracetamol', dosage: '500mg', quantity: 50 },
            { id: 'MED002', name: 'Ibuprofen', dosage: '400mg', quantity: 8 },
            { id: 'MED003', name: 'Amoxicillin', dosage: '250mg', quantity: 30 },
            { id: 'MED004', name: 'Aspirin', dosage: '100mg', quantity: 0 },
            { id: 'MED005', name: 'Cough Syrup', dosage: '100ml', quantity: 15 }
        ];
    }

    loadLogs() {
        const saved = localStorage.getItem('medicationLogs');
        return saved ? JSON.parse(saved) : [];
    }

    saveInventory() {
        localStorage.setItem('medicationInventory', JSON.stringify(this.inventory));
    }

    saveLogs() {
        localStorage.setItem('medicationLogs', JSON.stringify(this.logs));
    }

    // Event Listeners
    setupEventListeners() {
        // Form submission
        document.getElementById('dispensingForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.dispenseMedication();
        });

        // Medication selection
        document.getElementById('medicationSelect').addEventListener('change', () => {
            this.updateStockStatus();
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            this.loadMedicationDropdown();
            this.renderInventoryTable();
            this.showAlert('Inventory refreshed successfully', 'success');
        });

        // Search functionality
        document.getElementById('inventorySearch').addEventListener('input', (e) => {
            this.filterInventoryTable(e.target.value);
        });

        // Add medication modal
        document.getElementById('addInventoryBtn').addEventListener('click', () => {
            this.showAddMedicationModal();
        });

        document.getElementById('addMedicationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.addNewMedication();
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
    }

    // UI Updates
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

    startClock() {
        setInterval(() => {
            this.updateDateTime();
        }, 60000); // Update every minute
    }

    loadMedicationDropdown() {
        const select = document.getElementById('medicationSelect');
        select.innerHTML = '<option value="">Select Medication...</option>';
        
        this.inventory.forEach(med => {
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

        const medication = this.inventory.find(med => med.id === medicationId);
        if (medication) {
            unitsRemaining.textContent = `${medication.quantity} units remaining`;
            
            if (medication.quantity === 0) {
                stockStatus.textContent = 'OUT OF STOCK';
                stockStatus.className = 'stock-status-out';
                dispenseBtn.disabled = true;
            } else if (medication.quantity <= this.lowStockThreshold) {
                stockStatus.textContent = 'LOW STOCK';
                stockStatus.className = 'stock-status-low';
                dispenseBtn.disabled = false;
            } else {
                stockStatus.textContent = 'IN STOCK';
                stockStatus.className = 'stock-status-available';
                dispenseBtn.disabled = false;
            }
        }
    }

    // Medication Dispensing
    dispenseMedication() {
        const medicationId = document.getElementById('medicationSelect').value;
        const dosage = document.getElementById('dosageInput').value.trim();
        const remark = document.getElementById('remarkInput').value.trim();
        const dispensedBy = document.getElementById('userSelect').value;

        if (!medicationId || !dosage) {
            this.showAlert('Please select medication and enter dosage', 'danger');
            return;
        }

        const medication = this.inventory.find(med => med.id === medicationId);
        if (!medication) {
            this.showAlert('Medication not found', 'danger');
            return;
        }

        if (medication.quantity === 0) {
            this.showAlert('Medication is out of stock', 'danger');
            return;
        }

        // Update inventory
        medication.quantity -= 1;
        this.saveInventory();

        // Add log entry
        const logEntry = {
            medicationId: medication.id,
            medicationName: medication.name,
            dosage: dosage,
            dateTime: new Date().toISOString(),
            dispensedBy: dispensedBy,
            remark: remark || 'No remark'
        };

        this.logs.unshift(logEntry);
        this.saveLogs();

        // Update UI
        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.renderLogsTable();
        this.updateStockStatus();

        // Clear form
        document.getElementById('dosageInput').value = '';
        document.getElementById('remarkInput').value = '';
        document.getElementById('medicationSelect').value = '';

        // Show success message
        this.showAlert(`Successfully dispensed ${medication.name} (${dosage})`, 'success');

        // Check for low stock alert
        if (medication.quantity <= this.lowStockThreshold && medication.quantity > 0) {
            setTimeout(() => {
                this.showAlert(`Warning: ${medication.name} is running low on stock (${medication.quantity} units remaining)`, 'warning');
            }, 1000);
        }
    }

    // Table Rendering
    renderInventoryTable() {
        const tbody = document.getElementById('inventoryTableBody');
        tbody.innerHTML = '';

        this.inventory.forEach(medication => {
            const row = document.createElement('tr');
            
            // Add row classes for styling
            if (medication.quantity === 0) {
                row.className = 'out-of-stock-row';
            } else if (medication.quantity <= this.lowStockThreshold) {
                row.className = 'low-stock-row';
            }

            const statusClass = medication.quantity === 0 ? 'status-out-of-stock' :
                               medication.quantity <= this.lowStockThreshold ? 'status-low-stock' : 'status-in-stock';
            
            const statusText = medication.quantity === 0 ? 'Out of Stock' :
                              medication.quantity <= this.lowStockThreshold ? 'Low Stock' : 'In Stock';

            row.innerHTML = `
                <td>${medication.id}</td>
                <td>${medication.name}</td>
                <td>${medication.dosage}</td>
                <td>${medication.quantity}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <button class="btn btn-secondary" onclick="medSystem.editMedication('${medication.id}')" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">Edit</button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    renderLogsTable() {
        const tbody = document.getElementById('logsTableBody');
        tbody.innerHTML = '';

        const recentLogs = this.logs.slice(0, 10);
        
        if (recentLogs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-secondary);">No dispensing logs available</td></tr>';
            return;
        }

        recentLogs.forEach(log => {
            const row = document.createElement('tr');
            const date = new Date(log.dateTime);
            const formattedDate = date.toLocaleDateString('en-US');
            const formattedTime = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

            row.innerHTML = `
                <td>${log.medicationId}</td>
                <td>${log.medicationName}</td>
                <td>${log.dosage}</td>
                <td>${formattedDate} ${formattedTime}</td>
                <td>${log.dispensedBy}</td>
                <td>${log.remark}</td>
            `;
            
            tbody.appendChild(row);
        });
    }

    // Search and Filter
    filterInventoryTable(searchTerm) {
        const rows = document.querySelectorAll('#inventoryTableBody tr');
        const term = searchTerm.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    // Modal Functions
    showAddMedicationModal() {
        document.getElementById('addMedicationModal').style.display = 'block';
        document.getElementById('newMedicationName').focus();
    }

    hideModal() {
        document.getElementById('addMedicationModal').style.display = 'none';
        document.getElementById('addMedicationForm').reset();
    }

    addNewMedication() {
        const name = document.getElementById('newMedicationName').value.trim();
        const dosage = document.getElementById('newMedicationDosage').value.trim();
        const quantity = parseInt(document.getElementById('newMedicationQuantity').value);

        if (!name || !dosage || isNaN(quantity) || quantity < 0) {
            this.showAlert('Please fill all fields with valid values', 'danger');
            return;
        }

        // Generate unique ID
        const newId = 'MED' + String(this.inventory.length + 1).padStart(3, '0');

        const newMedication = {
            id: newId,
            name: name,
            dosage: dosage,
            quantity: quantity
        };

        this.inventory.push(newMedication);
        this.saveInventory();

        this.loadMedicationDropdown();
        this.renderInventoryTable();
        this.hideModal();
        this.showAlert(`Successfully added ${name} to inventory`, 'success');
    }

    editMedication(medicationId) {
        const medication = this.inventory.find(med => med.id === medicationId);
        if (!medication) return;

        const newQuantity = prompt(`Update quantity for ${medication.name} (current: ${medication.quantity}):`, medication.quantity);
        
        if (newQuantity !== null) {
            const quantity = parseInt(newQuantity);
            if (!isNaN(quantity) && quantity >= 0) {
                medication.quantity = quantity;
                this.saveInventory();
                this.loadMedicationDropdown();
                this.renderInventoryTable();
                this.updateStockStatus();
                this.showAlert(`Updated ${medication.name} quantity to ${quantity}`, 'success');
            } else {
                this.showAlert('Please enter a valid quantity', 'danger');
            }
        }
    }

    // Alert System
    showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        alertContainer.appendChild(alert);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }

    // Utility Functions
    generateMedicationId() {
        const maxId = Math.max(...this.inventory.map(med => {
            const match = med.id.match(/MED(\d+)/);
            return match ? parseInt(match[1]) : 0;
        }), 0);
        return 'MED' + String(maxId + 1).padStart(3, '0');
    }

    exportData() {
        const data = {
            inventory: this.inventory,
            logs: this.logs,
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
    }

    importData(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = JSON.parse(e.target.result);
                if (data.inventory && data.logs) {
                    this.inventory = data.inventory;
                    this.logs = data.logs;
                    this.saveInventory();
                    this.saveLogs();
                    this.loadMedicationDropdown();
                    this.renderInventoryTable();
                    this.renderLogsTable();
                    this.showAlert('Data imported successfully', 'success');
                } else {
                    this.showAlert('Invalid data format', 'danger');
                }
            } catch (error) {
                this.showAlert('Error importing data: ' + error.message, 'danger');
            }
        };
        reader.readAsText(file);
    }
}

// Initialize the system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.medSystem = new MedicationSystem();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + D for quick dispense focus
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        document.getElementById('medicationSelect').focus();
    }
    
    // Escape to close modal
    if (e.key === 'Escape') {
        const modal = document.getElementById('addMedicationModal');
        if (modal.style.display === 'block') {
            modal.style.display = 'none';
        }
    }
});
