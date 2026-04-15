# Multi-Product Dispensing - Visual Guide

---

## Step 1: Select Prescription

```
┌─────────────────────────────────────────────────────────┐
│ PHARMACIST ASSISTANT DASHBOARD                          │
│                                                         │
│ Prescriptions Ready to Dispense                         │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Patient: John Doe                                   │ │
│ │ Dr. Smith · Apr 15, 2026                            │ │
│ │ Customer: Juan Customer (customer@pharmacy.local)   │ │
│ │                                                     │ │
│ │ [Verified] [View] [Dispense]                        │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 2: View Prescription File

```
┌─────────────────────────────────────────────────────────┐
│ PRESCRIPTION FILE VIEWER                                │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │                                                     │ │
│ │  [Prescription Image/PDF Display]                  │ │
│ │                                                     │ │
│ │  - Patient: John Doe                               │ │
│ │  - Doctor: Dr. Smith                               │ │
│ │  - Medicines: Paracetamol, Amoxicillin             │ │
│ │                                                     │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ [Check Availability] [Close]                            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 3: Check Product Availability

```
┌─────────────────────────────────────────────────────────┐
│ CHECK PRODUCT AVAILABILITY                              │
│                                                         │
│ Selected Prescription:                                  │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 📋 Selected Prescription                            │ │
│ │ Patient: John Doe · Dr. Smith                       │ │
│ │ Apr 15, 2026 · Status: [Approved]                  │ │
│ │                                                     │ │
│ │ [View File]                                         │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 4: Select First Product

```
┌─────────────────────────────────────────────────────────┐
│ DISPENSE MEDICINE                                       │
│                                                         │
│ Select Product to Dispense:                             │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ ┌─────────────────────────────────────────────────┐ │ │
│ │ │ PARA-001 Paracetamol 500mg                      │ │ │
│ │ │ Acetaminophen · Tablet · Pack: 10               │ │ │
│ │ │                                    [Adequate]   │ │ │
│ │ │                                    (150)        │ │ │
│ │ └─────────────────────────────────────────────────┘ │ │
│ │                                                     │ │
│ │ ┌─────────────────────────────────────────────────┐ │ │
│ │ │ AMOX-001 Amoxicillin 500mg                      │ │ │
│ │ │ Amoxicillin · Capsule · Pack: 10                │ │ │
│ │ │                                    [Adequate]   │ │ │
│ │ │                                    (200)        │ │ │
│ │ └─────────────────────────────────────────────────┘ │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Selected Product: [Paracetamol 500mg (Stock: 150)]     │
│ Quantity to Dispense: [2]                              │
│                                                         │
│ [Clear] [+ Add to Cart]                                │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 5: Product Added to Cart

```
┌─────────────────────────────────────────────────────────┐
│ ✓ Product added to dispensing cart.                     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ DISPENSING CART (1 items)                               │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ PARA-001 Paracetamol 500mg                          │ │
│ │ Acetaminophen · Tablet                              │ │
│ │                                                     │ │
│ │ Qty: 2 · ₱5.00 · Total: ₱10.00        [Remove]    │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Total Amount: ₱10.00                                    │
│                                                         │
│ [Clear Cart] [Dispense All & Proceed to Payment]       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 6: Add Second Product

```
┌─────────────────────────────────────────────────────────┐
│ DISPENSE MEDICINE                                       │
│                                                         │
│ Select Product to Dispense:                             │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ ┌─────────────────────────────────────────────────┐ │ │
│ │ │ PARA-001 Paracetamol 500mg                      │ │ │
│ │ │ Acetaminophen · Tablet · Pack: 10               │ │ │
│ │ │                                    [Adequate]   │ │ │
│ │ │                                    (150)        │ │ │
│ │ └─────────────────────────────────────────────────┘ │ │
│ │                                                     │ │
│ │ ┌─────────────────────────────────────────────────┐ │ │
│ │ │ AMOX-001 Amoxicillin 500mg ✓ SELECTED          │ │ │
│ │ │ Amoxicillin · Capsule · Pack: 10                │ │ │
│ │ │                                    [Adequate]   │ │ │
│ │ │                                    (200)        │ │ │
│ │ └─────────────────────────────────────────────────┘ │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Selected Product: [Amoxicillin 500mg (Stock: 200)]     │
│ Quantity to Dispense: [1]                              │
│                                                         │
│ [Clear] [+ Add to Cart]                                │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 7: Cart with Multiple Items

```
┌─────────────────────────────────────────────────────────┐
│ ✓ Product added to dispensing cart.                     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ DISPENSING CART (2 items)                               │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ PARA-001 Paracetamol 500mg                          │ │
│ │ Acetaminophen · Tablet                              │ │
│ │                                                     │ │
│ │ Qty: 2 · ₱5.00 · Total: ₱10.00        [Remove]    │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ AMOX-001 Amoxicillin 500mg                          │ │
│ │ Amoxicillin · Capsule                               │ │
│ │                                                     │ │
│ │ Qty: 1 · ₱15.00 · Total: ₱15.00       [Remove]    │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Total Amount: ₱25.00                                    │
│                                                         │
│ [Clear Cart] [✓ Dispense All & Proceed to Payment]     │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 8: Confirm Dispensing

```
┌─────────────────────────────────────────────────────────┐
│ CONFIRMATION                                            │
│                                                         │
│ Confirm dispensing all medicines?                       │
│                                                         │
│ [Cancel] [Confirm]                                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 9: Dispensed Medicines Page

```
┌─────────────────────────────────────────────────────────┐
│ VIEW DISPENSED MEDICINES                                │
│                                                         │
│ ✓ Medicines dispensed successfully. Awaiting payment.   │
│                                                         │
│ My Dispensed Medicines                                  │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 💊 Paracetamol 500mg                                │ │
│ │ Code: PARA-001 · Generic: Acetaminophen             │ │
│ │ Form: Tablet · Qty Dispensed: 2 PCS                 │ │
│ │ Date: Apr 15, 2026 10:30 AM                         │ │
│ │ Dispensed By: Ana Assistant                         │ │
│ │                                                     │ │
│ │ Stock: [Adequate] (150)                             │ │
│ │ Payment: [⏱ Pending] [Mark as Paid]                │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 💊 Amoxicillin 500mg                                │ │
│ │ Code: AMOX-001 · Generic: Amoxicillin               │ │
│ │ Form: Capsule · Qty Dispensed: 1 PCS                │ │
│ │ Date: Apr 15, 2026 10:30 AM                         │ │
│ │ Dispensed By: Ana Assistant                         │ │
│ │                                                     │ │
│ │ Stock: [Adequate] (200)                             │ │
│ │ Payment: [⏱ Pending] [Mark as Paid]                │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Step 10: Mark as Paid

```
┌─────────────────────────────────────────────────────────┐
│ Customer pays ₱25.00 for medicines                      │
│                                                         │
│ Pharmacist clicks [Mark as Paid] for each item          │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ ✓ Payment verified successfully.                        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ VIEW DISPENSED MEDICINES                                │
│                                                         │
│ My Dispensed Medicines                                  │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 💊 Paracetamol 500mg                                │ │
│ │ Code: PARA-001 · Generic: Acetaminophen             │ │
│ │ Form: Tablet · Qty Dispensed: 2 PCS                 │ │
│ │ Date: Apr 15, 2026 10:30 AM                         │ │
│ │ Dispensed By: Ana Assistant                         │ │
│ │                                                     │ │
│ │ Stock: [Adequate] (150)                             │ │
│ │ Payment: [✓ Verified]                               │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ 💊 Amoxicillin 500mg                                │ │
│ │ Code: AMOX-001 · Generic: Amoxicillin               │ │
│ │ Form: Capsule · Qty Dispensed: 1 PCS                │ │
│ │ Date: Apr 15, 2026 10:30 AM                         │ │
│ │ Dispensed By: Ana Assistant                         │ │
│ │                                                     │ │
│ │ Stock: [Adequate] (200)                             │ │
│ │ Payment: [✓ Verified]                               │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Payment Status Badges

### Pending (Before Payment)
```
┌──────────────────────┐
│ ⏱ PENDING            │
│ (Orange Badge)       │
│ [Mark as Paid]       │
└──────────────────────┘
```

### Verified (After Payment)
```
┌──────────────────────┐
│ ✓ VERIFIED           │
│ (Green Badge)        │
│ (No button)          │
└──────────────────────┘
```

---

## Key Features Highlighted

### 1. Multi-Product Selection
- ✅ Add multiple products to cart
- ✅ Review all items before dispensing
- ✅ Remove items if needed
- ✅ Clear entire cart

### 2. Cart Management
- ✅ Shows all selected products
- ✅ Displays quantities and prices
- ✅ Calculates total amount
- ✅ Remove individual items

### 3. Batch Dispensing
- ✅ Dispense all products at once
- ✅ Updates stock for all items
- ✅ Creates records for each product
- ✅ Automatic redirect to payment page

### 4. Payment Tracking
- ✅ Shows payment status for each item
- ✅ Pending badge (orange)
- ✅ Verified badge (green)
- ✅ Mark as Paid button

### 5. User Experience
- ✅ Clear visual feedback
- ✅ Confirmation popups
- ✅ Success messages
- ✅ Error handling
- ✅ Responsive design

---

## Summary

The multi-product dispensing workflow provides a seamless experience for pharmacist assistants to:

1. Select multiple products from a prescription
2. Review items in a cart before dispensing
3. Dispense all products at once
4. Track payment status
5. Mark items as paid after customer payment

All features are fully implemented, tested, and ready for production deployment.

