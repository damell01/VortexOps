# VortexOps User Guide

Easy-to-follow workflows and clear step-by-step guidance for every task.

## Dashboard Overview

**What to do:** Start here to see your business at a glance.

- **Total Shows** — All live and past shows
- **Active Streamers** — Team members with recent activity
- **Pending Payouts** — Amounts owed to streamers this month
- **Low Stock Alerts** — Items below reorder level
- **Recent Orders** — Latest shipments and receipts

**Quick Actions:**
- Press **Cmd+E** to open Quick Actions menu
- Press **Cmd+K** to search anything
- Press **?** to see all keyboard shortcuts

---

## Inventory Scanner (3 Modes)

### Mode 1: Look Up
**Purpose:** Find an item and check its stock across all locations.

**Steps:**
1. Click **Look Up** tab
2. Scan barcode OR type SKU
3. View: stock by location, recent movements, reorder level
4. Optional: Click **Adjust Stock** to add/remove units

**Pro Tips:**
- Use camera button for hands-free scanning
- Works with Bluetooth scanners too
- Filters by location on adjustment

---

### Mode 2: Quick Add
**Purpose:** Receive new inventory quickly during unboxing.

**Steps:**
1. Select destination **Location** (dropdown)
2. Set **Units per Scan** (default: 1)
3. Scan each item barcode
4. Each scan adds units to chosen location instantly

**When to use:**
- Receiving new stock from vendor
- Putting items into storage
- Physical inventory count

---

### Mode 3: Receive Pallet
**Purpose:** Receive entire shipments with detailed tracking.

**Steps:**
1. **Review Pallet** — Check vendor, reference, expected items
2. **Map Lines** — Link each line item to your inventory
   - Missing mapping? Item won't be receivable yet
   - Ask supervisor to add/map items first
3. **Receive Items** — Scan barcodes one-by-one
   - Progress bar shows completion per line
   - Green checkmark = line fully received
4. **Confirm** — Click "Finalize Receipt" when done

**Status:**
- 🔵 Receiving — In progress
- 🟢 Received — All items accounted for

---

## Shows Management

### View Shows
**Purpose:** Manage your live show schedule and metadata.

**Quick Filters:**
- **Status** — Active, Upcoming, Archived
- **Streamer** — Filter by specific person
- **Date Range** — This week, this month, all time

**In Each Show:**
- Show title and date
- Assigned streamer
- Break type (Pokemon, Sports, etc.)
- View/Edit details
- See related orders

---

## Streamers Management

### View Streamers
**Purpose:** Manage team members and their payouts.

**Key Info:**
- Name and email
- Active shows count
- Total earnings YTD
- Payout status
- Contact info

**Common Actions:**
- Click row to edit profile
- View payout history
- Adjust payment method
- See performance stats

---

## Payouts Workflow

### View Payouts
**Purpose:** Track money owed to streamers.

**Filter & Sort:**
- **Status** — Pending, Paid, Overdue
- **Streamer** — See individual earnings
- **Date** — This week/month/all time
- **Sort by:** Amount, date, streamer name

### How Payouts Work
1. **Calculate** — System calculates based on payout rules
   - Profit share (%)
   - Hourly rate ($)
   - Flat fee ($)
   - Tips & bonuses
2. **Review** — Check breakdown for accuracy
3. **Approve** — Mark as approved
4. **Pay** — Send via payment method on file
5. **Confirm** — Update status to "Paid"

---

## Receiving & Inventory

### Create Pallet
1. Click **+ Add Pallet**
2. Select **Vendor** (dropdown)
3. Enter **PO Reference** (optional tracking number)
4. Click **Create**

### Add Items to Pallet
1. Click pallet to open
2. Click **Add Line Item**
3. Select **Item** from inventory
4. Set **Expected Cases** (boxes expected)
5. Set **Unit Cost** ($)
6. Select **Location** (storage location)
7. Click **Add Line**

### Map Lines (Important!)
**What:** Link pallet line items to your inventory items

**Why:** System needs to know which item each line contains

**How:**
1. Open pallet
2. Look for **⚠ Needs mapping** indicator
3. Click line → Select item from dropdown
4. Click **Map** → Done!

### Receive Inventory
**Option 1: Barcode Scanner**
- Go to **Inventory → Scan Inventory**
- Switch to **Receive Pallet** mode
- Select pallet
- Scan each item's barcode

**Option 2: Manual Entry**
- Open pallet
- Click **Receive All** on each line
- Confirms receipt of all expected units

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| **Cmd+K** or **/** | Global search |
| **Cmd+E** | Quick actions menu |
| **Esc** | Close modals/dropdowns |
| **?** | Show this help |

---

## Common Issues & Solutions

### "Item not found" when scanning
- **Solution:** Check barcode matches item SKU in system
- **Fallback:** Type SKU manually in scanner

### Camera not working on mobile
- **iOS:** Use Safari (Firefox/Chrome don't have camera API)
- **Android:** Any browser with camera permission
- **Check:** Settings → Allow camera access

### Pallet won't receive
- **Reason:** Lines aren't mapped to items
- **Fix:** Go to pallet detail → Map each line first

### Can't find inventory location
- **Reason:** Location may not be created yet
- **Fix:** Ask admin to create location (one per streamer)

---

## Tips for Fast Operations

1. **Use Bluetooth scanner** — Faster than typing
2. **Use camera on mobile** — Hands-free, no scanner needed
3. **Batch operations** — Receive multiple items at once
4. **Keyboard shortcuts** — Cmd+E for quick access
5. **Save filters** — Browser remembers your last filter

---

## Data Entry Best Practices

- **SKU:** Use vendor's SKU for consistency
- **Barcode:** Scan directly from item label
- **Unit Cost:** Enter per-unit wholesale cost
- **Location:** Be specific (e.g., "Shelf A-3", "Bin 12")
- **Notes:** Add context for future reference

---

## Getting Help

- **Tooltip:** Hover **ⓘ** icons for hints
- **Empty states:** Guidance when no data to show
- **Error messages:** Red boxes explain what went wrong
- **In-app chat:** Ask supervisor real-time questions

---

## Video Tutorials

Coming soon! Link to YouTube walkthroughs for:
- First-time setup
- Receiving a pallet
- Scanner setup on mobile
- Running reports
- Managing team

