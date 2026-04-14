/**
 * Logs Model - Handles medication dispensing logs data management
 */
class LogsModel {
    constructor() {
        this.storageKey = 'medicationLogs';
        this.logs = this.loadLogs();
    }

    /**
     * Load logs from localStorage
     * @returns {Array} Array of log entries
     */
    loadLogs() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            return saved ? JSON.parse(saved) : [];
        } catch (error) {
            console.error('Error loading logs:', error);
            return [];
        }
    }

    /**
     * Save logs to localStorage
     */
    saveLogs() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.logs));
            return true;
        } catch (error) {
            console.error('Error saving logs:', error);
            return false;
        }
    }

    /**
     * Add new dispensing log entry
     * @param {Object} logEntry - Log entry object
     * @returns {boolean} Success status
     */
    addLog(logEntry) {
        try {
            const newLog = {
                id: this.generateLogId(),
                medicationId: logEntry.medicationId,
                medicationName: logEntry.medicationName,
                dosage: logEntry.dosage,
                dateTime: new Date().toISOString(),
                dispensedBy: logEntry.dispensedBy || 'Staff',
                remark: logEntry.remark || 'No remark'
            };
            
            this.logs.unshift(newLog); // Add to beginning
            return this.saveLogs();
        } catch (error) {
            console.error('Error adding log:', error);
            return false;
        }
    }

    /**
     * Get all log entries
     * @returns {Array} All log entries
     */
    getAllLogs() {
        return [...this.logs];
    }

    /**
     * Get recent log entries
     * @param {number} limit - Number of recent logs to return
     * @returns {Array} Recent log entries
     */
    getRecentLogs(limit = 10) {
        return this.logs.slice(0, limit);
    }

    /**
     * Get logs by medication ID
     * @param {string} medicationId - Medication ID
     * @returns {Array} Filtered log entries
     */
    getLogsByMedication(medicationId) {
        return this.logs.filter(log => log.medicationId === medicationId);
    }

    /**
     * Get logs by date range
     * @param {Date} startDate - Start date
     * @param {Date} endDate - End date
     * @returns {Array} Filtered log entries
     */
    getLogsByDateRange(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        return this.logs.filter(log => {
            const logDate = new Date(log.dateTime);
            return logDate >= start && logDate <= end;
        });
    }

    /**
     * Get logs by staff member
     * @param {string} staffName - Staff member name
     * @returns {Array} Filtered log entries
     */
    getLogsByStaff(staffName) {
        return this.logs.filter(log => log.dispensedBy === staffName);
    }

    /**
     * Search logs by multiple criteria
     * @param {Object} searchCriteria - Search criteria object
     * @returns {Array} Filtered log entries
     */
    searchLogs(searchCriteria) {
        return this.logs.filter(log => {
            let matches = true;
            
            if (searchCriteria.medicationName) {
                matches = matches && log.medicationName.toLowerCase().includes(searchCriteria.medicationName.toLowerCase());
            }
            
            if (searchCriteria.dispensedBy) {
                matches = matches && log.dispensedBy.toLowerCase().includes(searchCriteria.dispensedBy.toLowerCase());
            }
            
            if (searchCriteria.remark) {
                matches = matches && log.remark.toLowerCase().includes(searchCriteria.remark.toLowerCase());
            }
            
            if (searchCriteria.startDate) {
                const logDate = new Date(log.dateTime);
                matches = matches && logDate >= new Date(searchCriteria.startDate);
            }
            
            if (searchCriteria.endDate) {
                const logDate = new Date(log.dateTime);
                matches = matches && logDate <= new Date(searchCriteria.endDate);
            }
            
            return matches;
        });
    }

    /**
     * Get dispensing statistics
     * @returns {Object} Statistics object
     */
    getStatistics() {
        const stats = {
            totalDispensing: this.logs.length,
            todayDispensing: 0,
            thisWeekDispensing: 0,
            thisMonthDispensing: 0,
            topMedications: {},
            topStaff: {}
        };

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const weekStart = new Date(today);
        weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

        this.logs.forEach(log => {
            const logDate = new Date(log.dateTime);
            
            // Time-based statistics
            if (logDate >= today) {
                stats.todayDispensing++;
            }
            if (logDate >= weekStart) {
                stats.thisWeekDispensing++;
            }
            if (logDate >= monthStart) {
                stats.thisMonthDispensing++;
            }
            
            // Top medications
            if (!stats.topMedications[log.medicationName]) {
                stats.topMedications[log.medicationName] = 0;
            }
            stats.topMedications[log.medicationName]++;
            
            // Top staff
            if (!stats.topStaff[log.dispensedBy]) {
                stats.topStaff[log.dispensedBy] = 0;
            }
            stats.topStaff[log.dispensedBy]++;
        });

        // Sort and limit top items
        stats.topMedications = Object.entries(stats.topMedications)
            .sort(([,a], [,b]) => b - a)
            .slice(0, 5)
            .reduce((obj, [key, value]) => ({ ...obj, [key]: value }), {});

        stats.topStaff = Object.entries(stats.topStaff)
            .sort(([,a], [,b]) => b - a)
            .slice(0, 5)
            .reduce((obj, [key, value]) => ({ ...obj, [key]: value }), {});

        return stats;
    }

    /**
     * Generate unique log ID
     * @returns {string} Generated ID
     */
    generateLogId() {
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 1000);
        return `LOG${timestamp}${random}`;
    }

    /**
     * Delete log entry
     * @param {string} logId - Log ID
     * @returns {boolean} Success status
     */
    deleteLog(logId) {
        try {
            const index = this.logs.findIndex(log => log.id === logId);
            if (index !== -1) {
                this.logs.splice(index, 1);
                return this.saveLogs();
            }
            return false;
        } catch (error) {
            console.error('Error deleting log:', error);
            return false;
        }
    }

    /**
     * Clear all logs
     * @returns {boolean} Success status
     */
    clearAllLogs() {
        try {
            this.logs = [];
            return this.saveLogs();
        } catch (error) {
            console.error('Error clearing logs:', error);
            return false;
        }
    }

    /**
     * Export logs data
     * @returns {Object} Logs data
     */
    exportData() {
        return {
            logs: this.logs,
            statistics: this.getStatistics(),
            exportDate: new Date().toISOString(),
            version: '1.0.0'
        };
    }

    /**
     * Import logs data
     * @param {Object} data - Logs data to import
     * @returns {boolean} Success status
     */
    importData(data) {
        try {
            if (data && Array.isArray(data.logs)) {
                this.logs = data.logs;
                return this.saveLogs();
            }
            return false;
        } catch (error) {
            console.error('Error importing logs data:', error);
            return false;
        }
    }

    /**
     * Format log entry for display
     * @param {Object} log - Log entry
     * @returns {Object} Formatted log entry
     */
    formatLogForDisplay(log) {
        const date = new Date(log.dateTime);
        return {
            ...log,
            formattedDate: date.toLocaleDateString('en-US'),
            formattedTime: date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
            formattedDateTime: `${date.toLocaleDateString('en-US')} ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`
        };
    }
}
