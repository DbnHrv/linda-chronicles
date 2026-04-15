# Pharmacy Assistant Workflow Diagram

## Complete Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                  PHARMACIST ASSISTANT DASHBOARD                     │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ STATISTICS                                                   │  │
│  │ ├─ Ready to Dispense: X                                      │  │
│  │ ├─ Dispensed Today: X                                        │  │
│  │ ├─ My Total Dispensed: X                                     │  │
│  │ └─ Low Stock Items: X                                        │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ SEARCH PRODUCT AVAILABILITY                                  │  │
│  │ ┌────────────────────────────────────────────────────────┐   │  │
│  │ │ 🔍 Search by product name, code, or generic name... │   │  │
│  │ └────────────────────────────────────────────────────────┘   │  │
│  │                                                              │  │
│  │ SEARCH RESULTS (Real-time)                                  │  │
│  │ ┌────────────────────────────────────────────────────────┐   │  │
│  │ │ Product Code: PARA-001                                 │   │  │
│  │ │ Product Name: Paracetamol 500mg                        │   │  │
│  │ │ Generic: Acetaminophen · Tablet · Pack: 10            │   │  │
│  │ │ Stock: 150 PCS  [Adequate Stock] ✓                    │   │  │
│  │ │                                                        │   │  │
│  │ │ Product Code: PARA-002                                 │   │  │
│  │ │ Product Name: Paracetamol 1000mg                       │   │  │
│  │ │ Generic: Acetaminophen · Tablet · Pack: 20            │   │  │
│  │ │ Stock: 5 PCS  [Low Stock] ⚠️                           │   │  │
│  │ └────────────────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ PRESCRIPTIONS READY TO DISPENSE                              │  │
│  │                                                              │  │
│  │ ┌────────────────────────────────────────────────────────┐   │  │
│  │ │ 📋 Patient: John Doe                                   │   │  │
│  │ │    Dr. Smith · Mar 15, 2026                            │   │  │
│  │ │    Customer: Jane Doe (jane@email.com)                 │   │  │
│  │ │    Status: [Verified]                                  │   │  │
│  │ │    [Dispense] [View] ✓                                 │   │  │
│  │ └────────────────────────────────────────────────────────┘   │  │
│  │                                                              │  │
│  │ ┌────────────────────────────────────────────────────────┐   │  │
│  │ │ 📋 Patient: Jane Smith                                 │   │  │
│  │ │    Dr. Johnson · Mar 14, 2026                          │   │  │
│  │ │    Customer: John Smith (john@email.com)               │   │  │
│  │ │    Status: [Approved]                                  │   │  │
│  │ │    [Dispense] [View] ✓                                 │   │  │
│  │ └────────────────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                              ↓
                    ┌─────────┴─────────┐
                    ↓                   ↓
        ┌──────────────────┐  ┌──────────────────┐
        │ Click "Dispense" │  │ Click Product    │
        │ on Prescription  │  │ in Search        │
        └────────┬─────────┘  └────────┬─────────┘
                 ↓                     ↓
        ┌──────────────────────────────────────┐
        │ Navigate to Dispense Products Page   │
        │ with URL parameters:                 │
        │ ?rx_id=X  or  ?product_id=Y          │
        └────────┬─────────────────────────────┘
                 ↓
        ┌──────────────────────────────────────┐
        │ DISPENSE PRODUCTS PAGE               │
        │                                      │
        │ ┌──────────────────────────────────┐ │
        │ │ SELECT PRESCRIPTION              │ │
        │ │ [Pre-selected if rx_id provided] │ │
        │ │ ├─ Patient: John Doe ✓           │ │
        │ │ ├─ Dr. Smith                     │ │
        │ │ └─ Date: Mar 15, 2026            │ │
        │ └──────────────────────────────────┘ │
        │                                      │
        │ ┌──────────────────────────────────┐ │
        │ │ SELECT PRODUCT                   │ │
        │ │ [Pre-selected if product_id]     │ │
        │ │ ├─ Code: PARA-001 ✓              │ │
        │ │ ├─ Name: Paracetamol 500mg       │ │
        │ │ ├─ Stock: 150 PCS [Adequate]     │ │
        │ │ └─ [Selected Product]            │ │
        │ └──────────────────────────────────┘ │
        │                                      │
        │ ┌──────────────────────────────────┐ │
        │ │ QUANTITY: [1] (Max: 150)         │ │
        │ └──────────────────────────────────┘ │
        │                                      │
        │ [Dispense Medicine] [Back]           │
        └────────┬─────────────────────────────┘
                 ↓
        ┌──────────────────────────────────────┐
        │ CONFIRM DISPENSING                   │
        │ ✓ Prescription: Verified             │
        │ ✓ Product: Available                 │
        │ ✓ Quantity: Valid                    │
        │ ✓ Stock Updated                      │
        │ ✓ Record Created                     │
        └────────┬─────────────────────────────┘
                 ↓
        ┌──────────────────────────────────────┐
        │ SUCCESS MESSAGE                      │
        │ "Medicine dispensed successfully"    │
        │                                      │
        │ [Back to Dashboard]                  │
        └──────────────────────────────────────┘
```

## Search Workflow

```
┌─────────────────────────────────────────┐
│ User Types in Search Bar                │
│ "Paracetamol"                           │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ JavaScript: searchProducts()            │
│ - Get search query                      │
│ - Filter productsData array             │
│ - Match: name, code, generic name       │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ Display Results                         │
│ ├─ Paracetamol 500mg (150 stock)        │
│ ├─ Paracetamol 1000mg (5 stock)         │
│ └─ Paracetamol Syrup (45 stock)         │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ User Clicks Result                      │
│ "Paracetamol 500mg"                     │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ Navigate to Dispense Page               │
│ ?product_id=1                           │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ Product Auto-selected                   │
│ Ready for dispensing                    │
└─────────────────────────────────────────┘
```

## File Viewer Workflow

```
┌─────────────────────────────────────────┐
│ User Clicks "View" on Prescription      │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ JavaScript: viewPrescriptionFile()      │
│ - Get filename                          │
│ - Detect file type (.pdf, .jpg, .png)   │
└────────────┬────────────────────────────┘
             ↓
        ┌────┴────┐
        ↓         ↓
    ┌─────┐   ┌──────┐
    │ PDF │   │ IMAGE│
    └──┬──┘   └───┬──┘
       ↓          ↓
    ┌──────────────────────────┐
    │ Load in Modal            │
    │ - PDF: iframe viewer     │
    │ - Image: img tag         │
    └────────┬─────────────────┘
             ↓
    ┌──────────────────────────┐
    │ Display Modal            │
    │ [Close Button]           │
    └────────┬─────────────────┘
             ↓
    ┌──────────────────────────┐
    │ User Reviews File        │
    │ Then Closes Modal        │
    └──────────────────────────┘
```

## Stock Status Indicators

```
┌─────────────────────────────────────────┐
│ STOCK STATUS CALCULATION                │
│                                         │
│ IF current_stock <= reorder_level       │
│   → Status: LOW (🔴 Red)                │
│   → Action: Reorder soon                │
│                                         │
│ ELSE IF current_stock <= (reorder * 1.5)│
│   → Status: MEDIUM (🟡 Orange)          │
│   → Action: Monitor levels              │
│                                         │
│ ELSE                                    │
│   → Status: ADEQUATE (🟢 Green)         │
│   → Action: Normal operations           │
└─────────────────────────────────────────┘
```

## Data Flow Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                    DATABASE                                  │
│                                                              │
│  ┌─────────────────┐  ┌──────────────┐  ┌──────────────┐   │
│  │  prescriptions  │  │   products   │  │    users     │   │
│  │  ├─ id          │  │  ├─ id       │  │  ├─ id       │   │
│  │  ├─ customer_id │  │  ├─ code     │  │  ├─ name     │   │
│  │  ├─ patient     │  │  ├─ name     │  │  ├─ email    │   │
│  │  ├─ doctor      │  │  ├─ stock    │  │  └─ role     │   │
│  │  ├─ image       │  │  ├─ reorder  │  └──────────────┘   │
│  │  ├─ date        │  │  └─ status   │                      │
│  │  └─ status      │  └──────────────┘                      │
│  └─────────────────┘                                        │
└──────────────────────────────────────────────────────────────┘
         ↓                    ↓                    ↓
    ┌────────────────────────────────────────────────┐
    │  PHARMACIST ASSISTANT DASHBOARD                │
    │  ├─ Fetch prescriptions (JOIN users)           │
    │  ├─ Fetch products (with stock status)         │
    │  └─ Display in UI                              │
    └────────────────────────────────────────────────┘
         ↓                    ↓
    ┌────────────────────────────────────────────────┐
    │  JAVASCRIPT                                    │
    │  ├─ productsData = [all products]              │
    │  ├─ searchProducts() - filter array            │
    │  ├─ selectPrescription() - select item         │
    │  └─ selectProduct() - select item              │
    └────────────────────────────────────────────────┘
         ↓
    ┌────────────────────────────────────────────────┐
    │  DISPENSE PRODUCTS PAGE                        │
    │  ├─ Check URL parameters                       │
    │  ├─ Pre-select prescription/product            │
    │  ├─ Validate stock                             │
    │  └─ Process dispensing                         │
    └────────────────────────────────────────────────┘
         ↓
    ┌────────────────────────────────────────────────┐
    │  DATABASE UPDATE                               │
    │  ├─ Update product stock                       │
    │  ├─ Create dispensed_medicines record          │
    │  └─ Update prescription status                 │
    └────────────────────────────────────────────────┘
```

## User Journey Map

```
START
  ↓
┌─────────────────────────────────────────┐
│ 1. LOGIN                                │
│    Pharmacist Assistant logs in         │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ 2. VIEW DASHBOARD                       │
│    See statistics and prescriptions     │
└────────────┬────────────────────────────┘
             ↓
        ┌────┴────┬────────┐
        ↓         ↓        ↓
    ┌─────┐  ┌──────┐  ┌──────┐
    │ A   │  │  B   │  │  C   │
    └──┬──┘  └───┬──┘  └───┬──┘
       ↓         ↓         ↓
    ┌──────────────────────────────────┐
    │ A: SEARCH PRODUCT                │
    │    - Type in search bar           │
    │    - See results                  │
    │    - Click product                │
    │    - Go to dispense               │
    └──────────────────────────────────┘
    
    ┌──────────────────────────────────┐
    │ B: VIEW PRESCRIPTION              │
    │    - Click "View" button          │
    │    - See prescription file        │
    │    - Close modal                  │
    │    - Return to dashboard          │
    └──────────────────────────────────┘
    
    ┌──────────────────────────────────┐
    │ C: DISPENSE PRESCRIPTION          │
    │    - Click "Dispense" button      │
    │    - Prescription pre-selected    │
    │    - Select product & quantity    │
    │    - Confirm dispensing           │
    │    - See success message          │
    └──────────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│ 3. COMPLETE TASK                        │
│    Medicine dispensed successfully      │
└────────────┬────────────────────────────┘
             ↓
        ┌────┴────┐
        ↓         ↓
    ┌─────┐   ┌──────┐
    │ END │   │ NEXT │
    └─────┘   └───┬──┘
                  ↓
            ┌──────────────┐
            │ Repeat steps │
            │ 2-3 for next │
            │ prescription │
            └──────────────┘
```
