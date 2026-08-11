# Pallet Receiving System - Implementation Plan

## Overview
Comprehensive pallet staging and receiving workflow with media attachments, case/unit hierarchies, mobile scanning, and missing items reporting.

---

## Phase 1: Database & Models (Priority: HIGH)

### 1.1 Add Media Support to Pallets
**Table:** `pallet_attachments` (or use polymorphic `media` table)
```sql
- id
- pallet_id
- type (photo, document, signature, receipt)
- file_path
- file_name
- file_size
- mime_type
- description
- uploaded_by
- uploaded_at
- created_at
```

**Model: PalletAttachment**
- Relationship to Pallet (HasMany)
- Support for multiple file types
- Track who uploaded and when

### 1.2 Add Case/Unit Quantity Relationships
**Table:** `inventory_item_contents`
```sql
- id
- parent_inventory_item_id (the case/container)
- child_inventory_item_id (the individual item inside)
- quantity_per_parent (20 if case contains 20 boxes)
- unit_type (case, box, pallet, bundle, etc.)
- barcode (the barcode that scans this container)
- created_at
```

**Model: InventoryItemContent**
- Parent item (case)
- Child items (contents)
- Quantities
- Barcode override (case barcode vs individual item barcodes)

### 1.3 Enhance Pallet Model
Add fields for tracking:
- `signature_path` - signature of receiver
- `signature_timestamp`
- `received_by_name` - optional text name
- `media_attachments_count` - for quick UI rendering

---

## Phase 2: Pallet Staging Page (Priority: HIGH)

### 2.1 Dedicated Receiving Workflow Page
**Route:** `/admin/pallets/{pallet}/receive` or custom page

**Components:**
1. **Pallet Header Section**
   - Pallet reference, vendor, PO
   - Expected vs received counts
   - Progress bar

2. **Media Upload Area**
   - Drag & drop or camera for photos
   - Document upload
   - Signature pad (digital signature)
   - Thumbnail gallery

3. **Scanner Interface**
   - Live barcode input field
   - Scanner mode indicator (Case vs Item)
   - Real-time count updates
   - Feedback (✓ received, ✗ unexpected, ⚠ duplicate)

4. **Cases/Lines List**
   - Show all pallet lines
   - Received count vs expected
   - Case details expandable
   - Associated SKU info

5. **Actions**
   - Complete Receiving button
   - Save & Continue
   - Cancel

### 2.2 Scanner Integration
- Detect barcode type (case vs individual item)
- Auto-lookup case contents if scanning case
- Display full hierarchy
- Increment correct counters

---

## Phase 3: Mobile Scanner Enhancement (Priority: HIGH)

### 3.1 Scanning Logic
```
IF barcode matches InventoryItemContent (parent):
  → Show case info + list of contents
  → Increment case count
  → Show all SKUs in case
ELSE IF barcode matches InventoryItem:
  → Show item info
  → Check if it's part of a case
  → Increment item count
  → Show parent case if applicable
```

### 3.2 Mobile UI
- Large tap targets for scanner feedback
- Highlight success/error states
- Show current count progress
- Voice/haptic feedback (optional)

---

## Phase 4: Missing Items Report (Priority: MEDIUM)

### 4.1 Report Query
- Get all orders with status != fulfilled
- Match items to pallet/inventory
- Calculate missing qty and value
- Show by: pallet, vendor, order, item

### 4.2 Report Features
- Filter by date range
- Group by pallet/vendor
- Export to Excel/PDF
- Show cost impact
- Flag high-value missing items

---

## Implementation Steps

### Step 1: Create Migrations & Models
- [ ] `PalletAttachment` model + migration
- [ ] `InventoryItemContent` model + migration
- [ ] Add fields to Pallet model
- [ ] Add relationships

### Step 2: Create Pallet Staging Page
- [ ] Custom Filament page
- [ ] Media upload component
- [ ] Scanner interface
- [ ] Real-time state management

### Step 3: Scanner Enhancement
- [ ] Update scanning logic in ReceivingService
- [ ] Add hierarchy lookup
- [ ] Update feedback messages
- [ ] Mobile UI refinements

### Step 4: Missing Items Report
- [ ] Create report query/service
- [ ] Filament page + table
- [ ] Export functionality
- [ ] Dashboard widget

### Step 5: Testing & Polish
- [ ] End-to-end testing
- [ ] Mobile device testing
- [ ] Performance optimization
- [ ] UI/UX refinement

---

## Files to Create/Modify

### New Files
- `app/Models/PalletAttachment.php`
- `app/Models/InventoryItemContent.php`
- `app/Filament/Resources/PalletResource/Pages/ReceivePallet.php` (enhanced)
- `app/Services/PalletScanningService.php`
- `app/Filament/Resources/MissingItemsResource.php`
- `resources/views/filament/pallet-staging.blade.php`
- `resources/css/pallet-receiving.css`

### Modified Files
- `app/Models/Pallet.php`
- `app/Models/InventoryItem.php`
- `app/Services/ReceivingService.php`
- `database/migrations/*`

---

## Database Migrations

### Migration 1: Create pallet_attachments Table
```php
Schema::create('pallet_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pallet_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['photo', 'document', 'signature', 'receipt']);
    $table->string('file_path');
    $table->string('file_name');
    $table->integer('file_size');
    $table->string('mime_type');
    $table->text('description')->nullable();
    $table->foreignId('uploaded_by')->constrained('users');
    $table->timestamp('uploaded_at')->useCurrent();
    $table->timestamps();
});
```

### Migration 2: Create inventory_item_contents Table
```php
Schema::create('inventory_item_contents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_inventory_item_id')->constrained('inventory_items');
    $table->foreignId('child_inventory_item_id')->constrained('inventory_items');
    $table->integer('quantity_per_parent')->default(1);
    $table->string('unit_type')->nullable(); // case, box, bundle, etc.
    $table->string('barcode')->nullable()->index();
    $table->timestamps();
    $table->unique(['parent_inventory_item_id', 'child_inventory_item_id']);
});
```

---

## Example Usage

### Define Case Contents
```php
// Create relationship: Case contains 20 boxes
InventoryItemContent::create([
    'parent_inventory_item_id' => $caseItem->id,  // Case SKU
    'child_inventory_item_id' => $boxItem->id,     // Box SKU
    'quantity_per_parent' => 20,
    'barcode' => $caseBarcode,
    'unit_type' => 'case'
]);
```

### Scanning Logic
```php
// User scans barcode
$barcode = '123456789ABC';

// Check if it's a case
$caseContent = InventoryItemContent::where('barcode', $barcode)->first();
if ($caseContent) {
    return [
        'type' => 'case',
        'parent' => $caseContent->parent,
        'contents' => $caseContent->parent->childItems,
        'quantities' => $caseContent->quantity_per_parent
    ];
}

// Otherwise, check if it's an individual item
$item = InventoryItem::findByBarcode($barcode);
if ($item) {
    $parent = $item->parentItem; // If it's inside a case
    return [
        'type' => 'item',
        'item' => $item,
        'parent_case' => $parent
    ];
}
```

---

## Timeline Estimate
- **Phase 1 (Models):** 1-2 hours
- **Phase 2 (Staging Page):** 3-4 hours
- **Phase 3 (Scanner):** 2-3 hours
- **Phase 4 (Reporting):** 2-3 hours
- **Total:** 8-12 hours

---

## Success Criteria
✅ Can create case→unit relationships  
✅ Scanner handles both case and individual items  
✅ Pallet staging page with media uploads  
✅ Real-time receiving progress  
✅ Missing items report generated  
✅ Mobile-friendly interface  
✅ All scanned items properly tracked  

