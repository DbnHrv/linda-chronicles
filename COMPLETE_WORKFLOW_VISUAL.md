# Complete Pharmacy Assistant Workflow - Visual Guide

## End-to-End User Journey

```
┌─────────────────────────────────────────────────────────────────┐
│                    CUSTOMER DASHBOARD                           │
│                                                                 │
│  Upload Prescription                                            │
│  ├─ Patient name: John Doe                                      │
│  ├─ Doctor name: Dr. Smith                                      │
│  └─ Prescription file: prescription.pdf                         │
│                                                                 │
│  [Upload Prescription]                                          │
└─────────────────────────────────────────────────────────────────┘
                          ↓
                   Prescription Stored
                   Status: PENDING
                          ↓
┌─────────────────────────────────────────────────────────────────┐
│              PHARMACIST ASSISTANT DASHBOARD                     │
│                                                                 │
│  Statistics                                                     │
│  ├─ Ready to Dispense: 5                                        │
│  ├─ Dispensed Today: 12                                         │
│  ├─ My Total Dispensed: 248                                     │
│  └─ Low Stock Items: 3                                          │
│                                                                 │
│  Prescriptions Ready to Dispense                                │
│  ├─ Patient: John Doe                                           │
│  │  Dr. Smith · Mar 15, 2026                                    │
│  │  Customer: Jane Doe (jane@email.com)                         │
│  │  Status: [Verified]                                          │
│  │  [Dispense] [View] ← Click View                              │
│  │                                                              │
│  └─ Patient: Jane Smith                                         │
│     Dr. Johnson · Mar 14, 2026                                  │
│     Customer: John Smith (john@email.com)                       │
│     Status: [Approved]                                          │
│     [Dispense] [View]                                           │
│                                                                 │
│  All Customer Prescriptions                                     │
│  Filter: [All] [Pending] [Verified] [Approved] [Dispensed]     │
│  ├─ Patient: okioki                                             │
│  │  Dr. okioki · Mar 15, 2026 10:30                             │
│  │  Customer: Harvey Customer (harvey@email.com)                │
│  │  Status: [Pending]                                           │
│  │  [View]                                                      │
│  │                                                              │
│  └─ Patient: okioki                                             │
│     Dr. okioki · Mar 14, 2026 09:15                             │
│     Customer: Harvey Customer (harvey@email.com)                │
│     Status: [Verified]                                          │
│     [View] [Dispense]                                           │
│                                                                 │
│  Processes                                                      │
│  ├─ Check Product Availability                                  │
│  └─ Dispense Product                                            │
└─────────────────────────────────────────────────────────────────┘
         ↓                                    ↓
    ┌────────────────────┐          ┌──────────────────────┐
    │ Click [View]       │          │ Click [Dispense]     │
    └────────┬───────────┘          └──────────┬───────────┘
             ↓                                  ↓
    ┌──────────────────────────────┐  ┌──────────────────────┐
    │ FILE VIEWER MODAL            │  │ DISPENSE PAGE        │
    │                              │  │                      │
    │ ┌────────────────────────┐   │  │ Prescription:        │
    │ │ Prescription File      │   │  │ [Pre-selected] ✓     │
    │ │                        │   │  │                      │
    │ │ [PDF/Image displayed]  │   │  │ Product:             │
    │ │                        │   │  │ [Select from list]   │
    │ │                        │   │  │                      │
    │ └────────────────────────┘   │  │ Quantity:            │
    │                              │  │ [Enter number]       │
    │ [Check Availability] [Close] │  │                      │
    │         ↓                    │  │ [Dispense Medicine]  │
    │         │                    │  └──────────┬───────────┘
    │         │                    │             ↓
    │         │                    │      Success Message
    │         │                    │      Stock Updated
    │         │                    │      Status: DISPENSED
    │         │                    │
    │         └────────────────────────────────────────────┐
    │                                                      │
    └──────────────────────────────────────────────────────┘
                          ↓
    ┌──────────────────────────────────────────────────────┐
    │ CHECK PRODUCT AVAILABILITY PAGE                      │
    │                                                      │
    │ ┌──────────────────────────────────────────────────┐ │
    │ │ Selected Prescription Box                        │ │
    │ │                                                  │ │
    │ │ 📋 Selected Prescription                         │ │
    │ │                                                  │ │
    │ │ Patient: John Doe · Dr. Smith                    │ │
    │ │ Mar 15, 2026 10:30 · Status: [Verified]         │ │
    │ │                                                  │ │
    │ │                              [View File]         │ │
    │ └──────────────────────────────────────────────────┘ │
    │                                                      │
    │ Your Prescriptions                                   │
    │ ├─ Patient: okioki                                  │
    │ │  Dr. okioki · Mar 15, 2026                        │
    │ │  Status: [Pending]                               │
    │ │  [View]                                           │
    │ │                                                  │
    │ └─ Patient: okioki                                  │
    │    Dr. okioki · Mar 14, 2026                        │
    │    Status: [Verified]                              │
    │    [View]                                           │
    │                                                      │
    │ Search Product Availability                         │
    │ [Search bar] [Clear]                                │
    │ [Search results with stock status]                  │
    │                                                      │
    │ Product Inventory Status                            │
    │ [Filter buttons]                                    │
    │ [Detailed product table]                            │
    └──────────────────────────────────────────────────────┘
```

---

## Detailed Feature Flows

### Flow 1: View Prescription → Check Availability

```
START
  ↓
Pharmacist Assistant Dashboard
  ↓
Find prescription in list
  ↓
Click [View] button
  ↓
┌─────────────────────────────────────┐
│ FILE VIEWER MODAL                   │
│                                     │
│ [Prescription File displayed]       │
│                                     │
│ [Check Availability] [Close]        │
└─────────────────────────────────────┘
  ↓
Click [Check Availability]
  ↓
Navigate to Check Product Availability
  ↓
URL: check_product_availability.php?rx_id=5
  ↓
┌─────────────────────────────────────┐
│ SELECTED PRESCRIPTION BOX            │
│                                     │
│ 📋 Selected Prescription            │
│ Patient: John Doe · Dr. Smith       │
│ Mar 15, 2026 10:30 · [Verified]     │
│ [View File]                         │
└─────────────────────────────────────┘
  ↓
Check product availability
  ↓
Search for products
  ↓
View inventory
  ↓
END
```

### Flow 2: View Selected Prescription File

```
START
  ↓
Check Product Availability page
  ↓
See selected prescription box
  ↓
Click [View File] button
  ↓
┌─────────────────────────────────────┐
│ FILE VIEWER MODAL                   │
│                                     │
│ [Prescription File displayed]       │
│                                     │
│ [Check Availability] [Close]        │
└─────────────────────────────────────┘
  ↓
Review prescription
  ↓
Click [Close]
  ↓
Return to availability page
  ↓
END
```

### Flow 3: Dispense Prescription

```
START
  ↓
Pharmacist Assistant Dashboard
  ↓
Find prescription in "Ready to Dispense"
  ↓
Click [Dispense] button
  ↓
Navigate to Dispense Products page
  ↓
Prescription PRE-SELECTED
  ↓
┌─────────────────────────────────────┐
│ DISPENSE PRODUCTS PAGE              │
│                                     │
│ Prescription: [Pre-selected] ✓       │
│ Product: [Select from list]         │
│ Quantity: [Enter number]            │
│                                     │
│ [Dispense Medicine]                 │
└─────────────────────────────────────┘
  ↓
Select product
  ↓
Enter quantity
  ↓
Click [Dispense Medicine]
  ↓
Confirm in popup
  ↓
Success message
  ↓
Stock updated
  ↓
Prescription status: DISPENSED
  ↓
END
```

---

## Component Interactions

### File Viewer Modal
```
┌─────────────────────────────────────────────────────────┐
│ Prescription File                              [Close]  │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │                                                 │   │
│  │  [PDF/Image displayed here]                     │   │
│  │                                                 │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
├─────────────────────────────────────────────────────────┤
│ [Check Availability] [Close]                            │
└─────────────────────────────────────────────────────────┘
```

### Selected Prescription Box
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Selected Prescription                                │
│                                                         │
│ Patient: John Doe · Dr. Smith                           │
│ Mar 15, 2026 10:30 · Status: [Verified]                │
│                                                         │
│                                    [View File]          │
└─────────────────────────────────────────────────────────┘
```

### Prescription Card
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Patient: John Doe                                    │
│    Dr. Smith · Mar 15, 2026 10:30                       │
│    Customer: Jane Doe (jane@email.com)                  │
│    Status: [Verified]                                   │
│                                                         │
│                    [View] [Dispense]                    │
└─────────────────────────────────────────────────────────┘
```

---

## Data Flow Diagram

```
Customer Uploads Prescription
         ↓
    Database: prescriptions table
    ├─ id: 5
    ├─ customer_id: 1
    ├─ patient_name: John Doe
    ├─ doctor_name: Dr. Smith
    ├─ prescription_image: rx_1_1234567890.pdf
    ├─ upload_date: 2026-03-15 10:30:00
    └─ status: Pending
         ↓
Pharmacist Assistant Dashboard
    ├─ Fetches all prescriptions
    ├─ Displays in list
    └─ User clicks [View]
         ↓
File Viewer Modal Opens
    ├─ Loads prescription file
    ├─ Shows [Check Availability] button
    └─ Button includes rx_id=5
         ↓
User clicks [Check Availability]
    ├─ Navigate to check_product_availability.php?rx_id=5
    └─ URL parameter passed
         ↓
Check Product Availability Page
    ├─ Fetches prescription with id=5
    ├─ Joins with users table
    ├─ Displays in selected box
    └─ Shows prescription details
         ↓
User can:
    ├─ View prescription file
    ├─ Search products
    ├─ Check availability
    └─ Return to dashboard
```

---

## Status Progression

```
PENDING
  ↓
  Pharmacist reviews prescription
  ↓
VERIFIED
  ↓
  Pharmacist approves
  ↓
APPROVED
  ↓
  Pharmacist dispenses
  ↓
DISPENSED
  ↓
  Customer pays
  ↓
PAYMENT COMPLETED
```

---

## Key Features Summary

### Prescription Management
✅ View all prescriptions
✅ Filter by status
✅ View prescription files
✅ Quick dispensing access

### Product Availability
✅ Real-time search
✅ Stock status indicators
✅ Inventory table
✅ Product details

### Dispensing Process
✅ Pre-selected prescriptions
✅ Stock validation
✅ Quantity confirmation
✅ Automatic updates

### File Viewing
✅ PDF support
✅ Image support
✅ Modal overlay
✅ Easy navigation

### Context Awareness
✅ Selected prescription displayed
✅ Easy access to prescription file
✅ Seamless navigation
✅ Reduced steps

---

## Benefits

### For Pharmacy Assistants
✅ Efficient workflow
✅ Quick navigation
✅ Context-aware display
✅ Easy access to information
✅ Reduced errors

### For Pharmacy Operations
✅ Better prescription management
✅ Improved efficiency
✅ Accurate dispensing
✅ Better tracking
✅ Reduced processing time

### For Customers
✅ Faster processing
✅ Better service
✅ Accurate medications
✅ Clear status tracking

---

## Technical Stack

- **Backend**: PHP with PDO
- **Database**: MySQL
- **Frontend**: HTML, CSS, JavaScript
- **Security**: CSRF tokens, input validation
- **Performance**: Optimized queries, client-side filtering

---

**Status**: ✅ Complete and Ready for Deployment
**Version**: 3.1.0 (Final)
**Last Updated**: April 15, 2026
