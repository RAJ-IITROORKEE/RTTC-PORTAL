# Payment Components

Reusable components for payment receipt and application form PDF generation.

## Files

- `payment-receipt.php` - Payment receipt layout (A4 format)
- `application-form.php` - Complete application form layout (A4 format)

## Usage

Both components are used in `payment/confirmation.php`:

```php
<?php include __DIR__ . '/components/payment-receipt.php'; ?>
<?php include __DIR__ . '/components/application-form.php'; ?>
```

## Required Variables

Before including either component, ensure these variables are set:

| Variable | Type | Description |
|----------|------|-------------|
| `$data` | array | All user, personal, academic, and payment data from database |
| `$userId` | int | Current user ID |
| `$appNumber` | string | Application number (format: `RTTC2026-00001`) |
| `$paidAmt` | string | Formatted payment amount (e.g., `₹500.00`) |
| `$paidAt` | string | Formatted payment date (e.g., `26 May 2026, 10:30 AM`) |
| `$fullName` | string | Pre-computed full name (for application form only) |

## Customization Guide

### To Modify Payment Receipt Layout

1. Open `payment/components/payment-receipt.php`
2. Edit the HTML/CSS within the file
3. The layout uses **inline styles** with fixed width (794px = A4 at 96dpi)
4. Uses table-based layouts to prevent overflow during PDF generation

### To Modify Application Form Layout

1. Open `payment/components/application-form.php`
2. Edit the HTML/CSS within the file
3. Same fixed-width, table-based approach as receipt
4. Contains 5 sections:
   - Personal Details
   - Category & Contact
   - Photo & Signature
   - Academic Details
   - GUBEDCET Details
   - GU Details + Payment Summary
   - Declaration

### Key Design Principles

1. **Fixed Width**: Both components use `max-width: 794px` (A4 paper width at 96dpi)
2. **No Bootstrap Grid**: Uses flexbox and tables instead of Bootstrap responsive classes
3. **Inline Styles**: All styling is inline for html2canvas compatibility
4. **Table Layouts**: Tables guarantee no overflow during PDF generation
5. **Print-Safe**: Designed to render identically in browser and PDF

### Common Customizations

#### Change Header Color
```php
// In both files, find the header div:
<div style="background:linear-gradient(135deg,#27276d,#4a4ab0);...">
// Replace #27276d and #4a4ab0 with your brand colors
```

#### Change Logo
```php
// Replace in both files:
<img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" ...>
// With your logo path
```

#### Adjust Fonts
```php
// Change font-family in the main container:
style="font-family:'Segoe UI',Arial,sans-serif;"
// To: style="font-family:'Roboto',Arial,sans-serif;"
```

#### Modify Receipt Footer
```php
// In payment-receipt.php, find footer div:
<div style="background:#f8f8fc;border-top:1px solid #e9ecef;...">
// Edit the text content as needed
```

#### Add New Field to Application Form
```php
// In application-form.php, add to appropriate section:
<tr>
  <td style="color:#6c757d;padding:2px 6px 2px 0;">Field Label</td>
  <td style="font-weight:600;padding:2px 0;">
    <?= htmlspecialchars($data['new_field'] ?? '-') ?>
  </td>
</tr>
```

## PDF Generation

The components are converted to PDF using:
- **html2canvas** - Captures the HTML as canvas
- **jsPDF** - Converts canvas to PDF

Both libraries are loaded in `payment/confirmation.php`:
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
```

## Testing Changes

1. Make changes to component file
2. Complete a test payment
3. Visit `/payment/confirmation.php`
4. Click "Download Payment Receipt" or "Download Application Form"
5. Verify the PDF reflects your changes

## Troubleshooting

### PDF Shows Blank/Incomplete Content
- Ensure all required variables are set before including component
- Check that `$data` array contains all needed fields
- Verify no PHP errors in browser console

### Layout Overflows in PDF
- Avoid Bootstrap responsive classes (col-md-*, etc.)
- Use table layouts instead of div grids
- Keep fixed widths (no percentages for main containers)

### Images Not Showing in PDF
- Ensure images are fully loaded before PDF generation
- Check that `useCORS: true` is set in html2canvas options
- Verify image URLs are absolute (include BASE_URL)

## File Structure

```
payment/
├── components/
│   ├── payment-receipt.php    # Receipt layout
│   └── application-form.php   # Application layout
├── confirmation.php           # Main page using components
└── index.php                  # Payment page
```
