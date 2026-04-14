# Medication Dispensing & Log System

A comprehensive web-based medication dispensing and logging system designed for medical facilities, pharmacies, and healthcare providers. Built with **MVC architecture** and **Bootstrap 5** for a professional, responsive interface.

## Architecture

### MVC Structure
The system follows the Model-View-Controller (MVC) design pattern:

- **Models** (`/models/`): Handle data management and business logic
  - `InventoryModel.js`: Manages medication inventory data
  - `LogsModel.js`: Manages dispensing logs and statistics
  
- **Views** (`/views/`): HTML templates with Bootstrap components
  - Main interface in `index.html` with responsive Bootstrap layout
  
- **Controllers** (`/controllers/`): Coordinate between models and views
  - `MedicationController.js`: Handles all user interactions and system logic

### Technology Stack
- **Frontend**: HTML5, Bootstrap 5, Bootstrap Icons
- **JavaScript**: ES6+ with modular MVC architecture
- **Storage**: Browser LocalStorage for data persistence
- **Design**: Responsive, mobile-first with professional medical theme

## Features

### Core Functionality
- **Real-time Inventory Management**: Track medication stock levels with automatic updates
- **Dispensing Interface**: Simple form-based medication dispensing with validation
- **Automated Logging**: Every dispensing action is automatically logged with timestamps
- **Low Stock Alerts**: Visual warnings when inventory falls below threshold (10 units)
- **User Tracking**: Track which staff member performed each dispensing action

### Data Management
- **Product Inventory**: Manage medication stock with ID, name, dosage, and quantity
- **Product Logs**: Complete dispensing history with all transaction details
- **Data Persistence**: All data saved locally in browser storage
- **Search & Filter**: Real-time search through inventory
- **Statistics**: Comprehensive dispensing analytics and reporting

### UI Features
- **Bootstrap 5 Design**: Modern, responsive interface with professional medical theme
- **Responsive Layout**: Works seamlessly on desktop, tablet, and mobile devices
- **Status Indicators**: Color-coded stock status with Bootstrap badges
- **Recent Logs Display**: Shows last 10 dispensing transactions in responsive table
- **Modal Forms**: Bootstrap modals for adding new medications
- **Icons**: Bootstrap Icons for enhanced visual communication

## System Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- Local web server (optional, for production use)
- No additional dependencies required

## Quick Start

1. **Download/Clone** the project files to your local directory
2. **Open** `index.html` in your web browser
3. **Start** using the system immediately with sample data

### For Local Development (Optional)

If you want to run this on a local web server (like XAMPP, WAMP, or Live Server):

```bash
# Using Python's built-in server
python -m http.server 8000

# Using Node.js http-server
npx http-server

# Using PHP built-in server
php -S localhost:8000
```

Then navigate to `http://localhost:8000` in your browser.

## Usage Guide

### Dispensing Medication

1. **Select Medication**: Choose from the dropdown menu (out-of-stock items are disabled)
2. **Enter Dosage**: Specify the dosage/quantity dispensed (e.g., "500mg", "1 tablet")
3. **Add Remark**: Optional notes about the dispensing (e.g., "Patient A", "Emergency stock")
4. **Click Dispense**: The system will:
   - Update inventory automatically
   - Log the transaction with timestamp
   - Show success confirmation
   - Alert if stock becomes low

### Managing Inventory

1. **View Current Stock**: Real-time display of all medications with status indicators
2. **Add New Medication**: Click "Add New Medication" button to expand inventory
3. **Edit Quantities**: Click "Edit" button to update stock levels
4. **Search**: Use the search box to filter medications quickly

### Understanding Status Indicators

- 🟢 **In Stock**: Green badge - sufficient stock (>10 units)
- 🟡 **Low Stock**: Yellow badge - warning level (≤10 units)
- 🔴 **Out of Stock**: Red badge - no stock available (0 units)

### Viewing Logs

The system displays the 10 most recent dispensing transactions including:
- Medication ID and Name
- Dosage dispensed
- Date and time of transaction
- Staff member who performed the action
- Any remarks added

## Data Schema

### Product Inventory Structure
```json
{
  "id": "MED001",
  "name": "Paracetamol",
  "dosage": "500mg",
  "quantity": 50
}
```

### Product Logs Structure
```json
{
  "medicationId": "MED001",
  "medicationName": "Paracetamol",
  "dosage": "500mg",
  "dateTime": "2024-01-15T10:30:00.000Z",
  "dispensedBy": "Staff",
  "remark": "Patient A"
}
```

## Project Structure

```
product_log/
├── index.html                 # Main application view (Bootstrap UI)
├── README.md                  # Documentation
├── models/                    # Data models
│   ├── InventoryModel.js      # Medication inventory management
│   └── LogsModel.js           # Dispensing logs and statistics
├── controllers/               # Business logic controllers
│   └── MedicationController.js # Main application controller
├── views/                     # View templates (empty - using index.html)
├── assets/                    # Static assets
│   ├── css/                   # Custom CSS files (if needed)
│   └── js/                    # Additional JavaScript files
├── script.js                  # Legacy script (deprecated)
└── styles.css                 # Legacy styles (deprecated)
```

## Customization

### Low Stock Threshold
To change the low stock warning threshold, modify the `lowStockThreshold` value in `controllers/MedicationController.js`:

```javascript
this.lowStockThreshold = 10; // Change this value
```

### Adding Sample Data
The system includes sample medications on first load. To customize the initial inventory, modify the `getDefaultInventory()` method in `models/InventoryModel.js`.

### Bootstrap Customization
The system uses Bootstrap 5 with custom CSS overrides in the `<style>` section of `index.html`. For extensive customizations:
1. Create a new CSS file in `assets/css/`
2. Link it in the HTML head
3. Override Bootstrap variables as needed

### MVC Extensions
To add new features:
1. **Models**: Add new data management classes in `/models/`
2. **Controllers**: Extend `MedicationController.js` or create new controllers
3. **Views**: Add new HTML sections or create separate view files

## Data Storage

- **Location**: Browser's LocalStorage
- **Format**: JSON
- **Persistence**: Data persists between browser sessions
- **Backup**: Export functionality available for data backup

### Data Backup/Restore

To backup your data:
1. Open browser developer console (F12)
2. Run: `medicationController.exportData()`

To restore data:
1. Use the import functionality (can be added to UI if needed)
2. Or manually restore via LocalStorage

## Security Considerations

- Data is stored locally in the browser
- No server-side processing or database connections
- Suitable for single-user or small clinic environments
- For multi-user environments, consider implementing server-side storage

## Browser Compatibility

- ✅ Chrome 80+
- ✅ Firefox 75+
- ✅ Safari 13+
- ✅ Edge 80+

## Troubleshooting

### Common Issues

1. **Data not saving**: Check if browser allows LocalStorage
2. **Medications not appearing**: Refresh the page or click "Refresh Inventory"
3. **Dispense button disabled**: Ensure medication is in stock and selected

### Reset to Default Data

To clear all data and start fresh:
1. Open browser developer console
2. Run: `localStorage.clear()`
3. Refresh the page

## Future Enhancements

Potential features for future versions:
- Multi-user support with login system
- Barcode scanning integration
- Prescription management
- Reporting and analytics
- Email notifications for low stock
- Cloud data synchronization
- Mobile app version

## Support

For issues, questions, or feature requests, please refer to the system documentation or contact your system administrator.

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**License**: MIT
