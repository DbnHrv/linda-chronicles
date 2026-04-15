# Pharmacy Assistant User Guide

## Welcome to the Enhanced Pharmacy Assistant Dashboard

This guide will help you use the new features to efficiently manage prescription dispensing and check product availability.

---

## Dashboard Overview

### Top Section: Welcome & Statistics
```
┌─────────────────────────────────────────────────────────────┐
│ Welcome, [Your Name]                                        │
│ [Your Email] · Pharmacist Assistant                         │
│                                                             │
│ Ready to Dispense: 5  │  Dispensed Today: 12               │
│ My Total Dispensed: 248  │  Low Stock Items: 3             │
└─────────────────────────────────────────────────────────────┘
```

**What it shows:**
- Your name and role
- Number of prescriptions ready to dispense
- Medicines dispensed today
- Your total dispensed count
- Products with low stock

---

## Feature 1: Search Product Availability

### How to Use

1. **Locate the Search Bar**
   - Find "Search Product Availability" section
   - See the search input field

2. **Enter Search Term**
   - Type product name: "Paracetamol"
   - Type product code: "PARA-001"
   - Type generic name: "Acetaminophen"

3. **View Results**
   - Results appear instantly as you type
   - See product details and stock status

4. **Interpret Stock Status**
   - 🟢 **Adequate** (Green) - Sufficient stock available
   - 🟡 **Medium** (Orange) - Moderate stock, monitor levels
   - 🔴 **Low** (Red) - Low stock, reorder soon

### Example Search

```
Search: "Paracetamol"

Results:
┌─────────────────────────────────────────────────────────┐
│ PARA-001                                                │
│ Paracetamol 500mg                                       │
│ Acetaminophen · Tablet · Pack: 10                       │
│ Stock: 150 PCS  [Adequate Stock] ✓                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ PARA-002                                                │
│ Paracetamol 1000mg                                      │
│ Acetaminophen · Tablet · Pack: 20                       │
│ Stock: 5 PCS  [Low Stock] ⚠️                            │
└─────────────────────────────────────────────────────────┘
```

### Tips
💡 Search is case-insensitive (search for "paracetamol" or "PARACETAMOL")
💡 Partial matches work (search for "para" finds all paracetamol products)
💡 Click "Clear" button to reset search

---

## Feature 2: View Prescriptions Ready to Dispense

### Prescription Card Layout

```
┌─────────────────────────────────────────────────────────┐
│ 📋 Patient: John Doe                                    │
│    Dr. Smith · Mar 15, 2026                             │
│    Customer: Jane Doe (jane@email.com)                  │
│    Status: [Verified]                                   │
│    [Dispense] [View] ✓                                  │
└─────────────────────────────────────────────────────────┘
```

### Information Shown
- **Patient Name** - Name of the patient
- **Doctor Name** - Prescribing doctor
- **Date** - When prescription was uploaded
- **Customer Name & Email** - Who submitted the prescription
- **Status** - Current prescription status
- **Action Buttons** - Dispense or View options

### Prescription Statuses
- **Pending** - Waiting for verification
- **Verified** - Checked and approved by pharmacist
- **Approved** - Ready for dispensing
- **Dispensed** - Already dispensed to customer

---

## Feature 3: View Prescription File

### How to View

1. **Find the Prescription**
   - Locate prescription in the list
   - Look for the "View" button

2. **Click "View" Button**
   - Modal window opens
   - Prescription file displays

3. **Review the File**
   - See prescription details
   - Verify patient information
   - Check medication requirements

4. **Close Modal**
   - Click "Close" button
   - Return to dashboard

### Supported File Types
- 📄 **PDF** - Embedded viewer
- 🖼️ **JPG/JPEG** - Image display
- 🖼️ **PNG** - Image display

### Example Modal

```
┌─────────────────────────────────────────────────────────┐
│ Prescription File                              [Close]   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  [PDF/Image displayed here]                            │
│                                                         │
│  [Close]                                                │
└─────────────────────────────────────────────────────────┘
```

---

## Feature 4: Dispense Prescription

### Quick Dispense (Recommended)

1. **Find Prescription**
   - Locate prescription in the list
   - Click "Dispense" button

2. **Prescription Auto-Selected**
   - You're taken to dispensing page
   - Prescription is already selected
   - No need to search for it

3. **Select Product**
   - Choose the medication from the list
   - See current stock quantity

4. **Enter Quantity**
   - Type number of units to dispense
   - System validates against available stock

5. **Confirm Dispensing**
   - Click "Dispense Medicine"
   - Confirm in popup
   - Success message appears

### Manual Dispense

1. **Go to Dispense Products Page**
   - Click "Dispense Product" process card
   - Or use search to find product

2. **Select Prescription**
   - Choose from list of verified prescriptions
   - See patient and doctor details

3. **Select Product**
   - Choose medication to dispense
   - Verify stock availability

4. **Enter Quantity**
   - Type number of units
   - Check maximum available

5. **Dispense**
   - Click "Dispense Medicine"
   - Confirm action
   - See success message

---

## Feature 5: Search and Dispense Product

### Quick Product Dispensing

1. **Search for Product**
   - Use search bar on dashboard
   - Type product name, code, or generic name

2. **Click Product in Results**
   - Product is highlighted
   - You're taken to dispensing page
   - Product is pre-selected

3. **Select Prescription**
   - Choose prescription from list
   - See patient details

4. **Enter Quantity**
   - Type number of units
   - Verify against stock

5. **Dispense**
   - Click "Dispense Medicine"
   - Confirm action
   - Success message appears

---

## Common Tasks

### Task 1: Dispense a Prescription

**Fastest Method:**
1. Find prescription on dashboard
2. Click "Dispense" button
3. Select product and quantity
4. Click "Dispense Medicine"
5. Done! ✓

**Time Saved:** 30 seconds per prescription

### Task 2: Check Product Availability

**Method:**
1. Use search bar
2. Type product name
3. See stock status immediately
4. No need to navigate away

**Time Saved:** 1 minute per check

### Task 3: Verify Prescription Before Dispensing

**Method:**
1. Click "View" on prescription
2. See prescription file
3. Verify details
4. Close modal
5. Proceed with dispensing

**Time Saved:** 2 minutes per prescription

### Task 4: Find Low Stock Products

**Method:**
1. Check "Low Stock Items" stat
2. See count of low stock products
3. Use search to find specific product
4. Plan reordering

**Time Saved:** 5 minutes per inventory check

---

## Tips & Tricks

### 💡 Pro Tips

1. **Use Keyboard Shortcuts**
   - Tab to navigate between fields
   - Enter to submit forms
   - Escape to close modals

2. **Search Efficiently**
   - Search by product code for exact matches
   - Search by generic name for alternatives
   - Use partial names for quick lookup

3. **Batch Dispensing**
   - Process similar prescriptions together
   - Use search to find products quickly
   - Reduces navigation time

4. **Monitor Stock**
   - Check low stock items regularly
   - Use search to verify availability
   - Plan reordering in advance

5. **Verify Before Dispensing**
   - Always view prescription file
   - Check patient name matches
   - Verify medication requirements
   - Confirm quantity needed

### ⚠️ Important Notes

- **Always verify** prescription before dispensing
- **Check stock** before confirming quantity
- **Confirm patient** information matches
- **Double-check** medication name and dosage
- **Update quantity** if stock is insufficient

---

## Troubleshooting

### Problem: Search Results Not Showing

**Solution:**
1. Check spelling of product name
2. Try searching by product code
3. Try searching by generic name
4. Click "Clear" and try again

### Problem: Can't Find Prescription

**Solution:**
1. Verify prescription status is "Verified" or "Approved"
2. Check if prescription is already dispensed
3. Refresh page to see latest prescriptions
4. Contact supervisor if prescription is missing

### Problem: Insufficient Stock

**Solution:**
1. Check current stock quantity
2. Reduce dispensing quantity
3. Check if alternative product available
4. Contact supervisor for stock issues

### Problem: File Won't Display

**Solution:**
1. Check file format (PDF, JPG, PNG supported)
2. Try refreshing page
3. Check file size (should be < 10MB)
4. Contact IT support if issue persists

### Problem: Pre-selection Not Working

**Solution:**
1. Check URL parameters are correct
2. Refresh page
3. Try manual selection
4. Contact IT support if issue persists

---

## Keyboard Navigation

### Dashboard
- **Tab** - Move between elements
- **Enter** - Click buttons/links
- **Escape** - Close modals

### Search
- **Type** - Enter search term
- **Arrow Down** - Navigate results
- **Enter** - Select result
- **Escape** - Close results

### Forms
- **Tab** - Move between fields
- **Shift+Tab** - Move backward
- **Enter** - Submit form
- **Escape** - Cancel

---

## Performance Tips

### Faster Dispensing
1. Use "Dispense" button on prescription (saves 30 seconds)
2. Use search for quick product lookup (saves 1 minute)
3. Pre-verify prescriptions (saves 2 minutes)
4. Batch similar prescriptions (saves 5 minutes)

### Faster Searching
1. Use product code for exact matches
2. Use partial names for quick lookup
3. Use generic names for alternatives
4. Clear search between queries

### Faster Navigation
1. Use pre-selection features
2. Minimize modal opening
3. Use keyboard shortcuts
4. Batch similar tasks

---

## Best Practices

### ✅ Do's
- ✓ Always verify prescription before dispensing
- ✓ Check stock before confirming quantity
- ✓ Use search for quick lookups
- ✓ Use pre-selection features
- ✓ Monitor low stock items
- ✓ Keep prescription files organized
- ✓ Update stock regularly

### ❌ Don'ts
- ✗ Don't dispense without verification
- ✗ Don't ignore low stock warnings
- ✗ Don't dispense more than available
- ✗ Don't skip patient verification
- ✗ Don't forget to update stock
- ✗ Don't ignore error messages
- ✗ Don't dispense expired medications

---

## Support & Help

### Getting Help
- **Quick Questions** - Check this guide
- **Technical Issues** - Contact IT Support
- **Workflow Questions** - Ask your supervisor
- **Product Issues** - Contact pharmacy manager

### Contact Information
- **IT Support**: [Support Email/Phone]
- **Pharmacy Manager**: [Manager Email/Phone]
- **Supervisor**: [Supervisor Email/Phone]

---

## Feedback

We'd love to hear your feedback on the new features!

**What's Working Well?**
- Share positive feedback
- Help us improve

**What Could Be Better?**
- Report issues
- Suggest improvements
- Request features

**How to Provide Feedback:**
- Email: [Feedback Email]
- Form: [Feedback Form URL]
- In-person: Talk to your supervisor

---

## Quick Reference Card

```
┌─────────────────────────────────────────────────────────┐
│ PHARMACY ASSISTANT - QUICK REFERENCE                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ SEARCH PRODUCT                                          │
│ 1. Type in search bar                                   │
│ 2. See results instantly                                │
│ 3. Click to dispense                                    │
│                                                         │
│ VIEW PRESCRIPTION                                       │
│ 1. Click "View" button                                  │
│ 2. See prescription file                                │
│ 3. Close modal                                          │
│                                                         │
│ DISPENSE PRESCRIPTION                                   │
│ 1. Click "Dispense" button                              │
│ 2. Select product & quantity                            │
│ 3. Confirm dispensing                                   │
│                                                         │
│ STOCK STATUS                                            │
│ 🟢 Adequate  │  🟡 Medium  │  🔴 Low                    │
│                                                         │
│ KEYBOARD SHORTCUTS                                      │
│ Tab: Navigate  │  Enter: Submit  │  Esc: Close         │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

**Last Updated**: April 15, 2026
**Version**: 1.0.0
**Status**: Ready to Use ✅
