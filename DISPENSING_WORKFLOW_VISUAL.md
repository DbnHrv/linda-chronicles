# Dispensing from Product Availability - Visual Workflow

## Complete Dispensing Process

```
┌─────────────────────────────────────────────────────────────────┐
│              CHECK PRODUCT AVAILABILITY PAGE                    │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Selected Prescription Box                               │   │
│  │                                                         │   │
│  │ 📋 Selected Prescription                                │   │
│  │ Patient: John Doe · Dr. Smith                           │   │
│  │ Mar 15, 2026 10:30 · Status: [Verified]                │   │
│  │                                    [View File]          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ DISPENSING FORM                                         │   │
│  │                                                         │   │
│  │ 💊 Dispense Medicine                                    │   │
│  │                                                         │   │
│  │ Select Product to Dispense:                             │   │
│  │ ┌─────────────────────────────────────────────────────┐ │   │
│  │ │ PARA-001                                            │ │   │
│  │ │ Paracetamol 500mg                                   │ │   │
│  │ │ Acetaminophen · Tablet · Pack: 10                   │ │   │
│  │ │                          [Adequate] (150)           │ │   │
│  │ │                                                     │ │   │
│  │ │ PARA-002                                            │ │   │
│  │ │ Paracetamol 1000mg                                  │ │   │
│  │ │ Acetaminophen · Tablet · Pack: 20                   │ │   │
│  │ │                          [Low] (5)                  │ │   │
│  │ │                                                     │ │   │
│  │ │ AMOX-001 ← SELECTED                                 │ │   │
│  │ │ Amoxicillin 500mg                                   │ │   │
│  │ │ Amoxicillin · Capsule · Pack: 10                    │ │   │
│  │ │                          [Adequate] (200)           │ │   │
│  │ └─────────────────────────────────────────────────────┘ │   │
│  │                                                         │   │
│  │ Selected Product: Amoxicillin 500mg (Stock: 200)       │   │
│  │ Quantity to Dispense: [10]                             │   │
│  │                                                         │   │
│  │ [Clear] [Dispense Medicine]                            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Your Prescriptions                                             │
│  [Search Bar]                                                   │
│  [Product Inventory Table]                                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Workflow

### Step 1: View Prescription
```
Pharmacist Assistant Dashboard
    ↓
Find prescription in list
    ↓
Click [View] button
    ↓
File Viewer Modal opens
```

### Step 2: Navigate to Availability
```
File Viewer Modal
    ↓
Click [Check Availability] button
    ↓
Navigate to Check Product Availability page
    ↓
URL: check_product_availability.php?rx_id=5
```

### Step 3: See Selected Prescription
```
Check Product Availability Page loads
    ↓
Selected Prescription Box displays
    ↓
Shows:
├─ Patient: John Doe
├─ Doctor: Dr. Smith
├─ Date: Mar 15, 2026 10:30
└─ Status: [Verified]
```

### Step 4: Dispensing Form Appears
```
Below Selected Prescription Box
    ↓
Dispensing Form displays
    ↓
Shows:
├─ Product Selector (scrollable list)
├─ Selected Product field (empty)
└─ Quantity field (empty)
```

### Step 5: Select Product
```
User clicks on product in list
    ↓
Product highlighted with accent color
    ↓
Product details populate:
├─ Selected Product: [Product name]
├─ Quantity field: max set to stock
└─ Quantity value: 1 (default)
```

### Step 6: Enter Quantity
```
User modifies quantity field
    ↓
Quantity validated:
├─ Min: 1
├─ Max: Available stock
└─ Type: Integer
```

### Step 7: Dispense
```
User clicks [Dispense Medicine]
    ↓
Confirmation popup appears
    ↓
User confirms
    ↓
Form submitted
```

### Step 8: Processing
```
Server validates:
├─ CSRF token ✓
├─ Prescription exists ✓
├─ Product exists ✓
├─ Stock available ✓
└─ Quantity valid ✓
    ↓
Database updates:
├─ Product stock decreased
├─ Dispensed medicine record created
├─ Prescription status → DISPENSED
└─ User ID recorded
```

### Step 9: Success
```
Success message displayed
    ↓
Form resets
    ↓
Selected prescription cleared
    ↓
Ready for next dispensing
```

---

## Product Selection Interface

### Product List Display
```
┌─────────────────────────────────────────────────────────┐
│ Select Product to Dispense:                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ PARA-001                                                │
│ Paracetamol 500mg                                       │
│ Acetaminophen · Tablet · Pack: 10                       │
│                                    [Adequate] (150)     │
│                                                         │
│ PARA-002                                                │
│ Paracetamol 1000mg                                      │
│ Acetaminophen · Tablet · Pack: 20                       │
│                                    [Low] (5)            │
│                                                         │
│ AMOX-001 ← SELECTED (highlighted)                       │
│ Amoxicillin 500mg                                       │
│ Amoxicillin · Capsule · Pack: 10                        │
│                                    [Adequate] (200)     │
│                                                         │
│ CEPH-001                                                │
│ Cephalexin 500mg                                        │
│ Cephalexin · Capsule · Pack: 10                         │
│                                    [Medium] (75)        │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Stock Status Badges
```
🟢 [Adequate] (150) - Green badge, sufficient stock
🟡 [Medium] (75)    - Orange badge, moderate stock
🔴 [Low] (5)        - Red badge, low stock
```

---

## Form Fields

### Selected Product Field
```
┌─────────────────────────────────────────────────────────┐
│ Selected Product                                        │
├─────────────────────────────────────────────────────────┤
│ Amoxicillin 500mg (Stock: 200)                          │
│ [Read-only field]                                       │
└─────────────────────────────────────────────────────────┘
```

### Quantity Field
```
┌─────────────────────────────────────────────────────────┐
│ Quantity to Dispense                                    │
├─────────────────────────────────────────────────────────┤
│ [10]                                                    │
│ Min: 1, Max: 200 (based on stock)                       │
└─────────────────────────────────────────────────────────┘
```

---

## Form Actions

### Buttons
```
┌──────────────────────────────────────────────────────────┐
│ [Clear] [Dispense Medicine]                              │
└──────────────────────────────────────────────────────────┘

Clear Button:
├─ Resets product selection
├─ Clears quantity field
└─ Removes visual selection

Dispense Medicine Button:
├─ Shows confirmation popup
├─ Submits form on confirm
└─ Processes dispensing
```

---

## Confirmation Popup

```
┌─────────────────────────────────────────────────────────┐
│ Confirm Dispensing                                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Confirm dispensing this medicine?                       │
│                                                         │
│ [Cancel] [OK]                                           │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Success/Error Messages

### Success Message
```
┌─────────────────────────────────────────────────────────┐
│ ✓ Medicine dispensed successfully.                      │
└─────────────────────────────────────────────────────────┘

Actions:
├─ Form resets
├─ Selected prescription cleared
└─ Ready for next dispensing
```

### Error Messages
```
┌─────────────────────────────────────────────────────────┐
│ ✗ All fields required.                                  │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ ✗ Insufficient stock. Available: 5                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ ✗ Product not found.                                    │
└─────────────────────────────────────────────────────────┘
```

---

## Data Flow

```
User selects product
    ↓
JavaScript: selectProduct()
    ├─ Set product ID in hidden field
    ├─ Display product name
    ├─ Set max quantity
    └─ Highlight product
    ↓
User enters quantity
    ↓
User clicks Dispense
    ↓
Confirmation popup
    ↓
User confirms
    ↓
Form submitted (POST)
    ↓
Server: Validate CSRF token
    ↓
Server: Check stock availability
    ↓
Server: Call dispenseMedicine()
    ├─ Update product stock
    ├─ Create dispensed record
    ├─ Update prescription status
    └─ Record user ID
    ↓
Success message
    ↓
Form resets
```

---

## Validation Flow

```
Client-Side Validation:
├─ Product selected? ✓
├─ Quantity entered? ✓
├─ Quantity > 0? ✓
└─ Confirmation given? ✓
    ↓
Server-Side Validation:
├─ CSRF token valid? ✓
├─ Prescription exists? ✓
├─ Product exists? ✓
├─ Stock available? ✓
└─ Quantity valid? ✓
    ↓
If all valid → Dispense
If any invalid → Show error
```

---

## Complete User Journey

```
START
  ↓
Pharmacist Assistant Dashboard
  ↓
Find prescription
  ↓
Click [View]
  ↓
File Viewer Modal
  ↓
Click [Check Availability]
  ↓
Check Product Availability Page
  ↓
See Selected Prescription Box
  ↓
See Dispensing Form
  ↓
Click on product
  ↓
Product selected (highlighted)
  ↓
Enter quantity
  ↓
Click [Dispense Medicine]
  ↓
Confirmation popup
  ↓
Click [OK]
  ↓
Processing...
  ↓
Success message
  ↓
Form resets
  ↓
Stock updated
  ↓
Prescription status: DISPENSED
  ↓
END
```

---

## Benefits

### Efficiency
✅ No page navigation needed
✅ Faster dispensing process
✅ Reduced steps
✅ Better workflow

### User Experience
✅ Clear product selection
✅ Visual feedback
✅ Easy quantity input
✅ Confirmation before action

### Data Integrity
✅ Stock validation
✅ Quantity validation
✅ CSRF protection
✅ Audit trail

---

**Status**: ✅ Complete and Ready
**Version**: 3.2.0 (Dispensing Added)
**Last Updated**: April 15, 2026
