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

## Inventory

The inventory module has its own handbook, in the app and on paper: **Handbook**
at the top of the sidebar, and the **Printable PDF** button on it. Every screen,
every button and every field, with a picture of the real thing.

It is generated from one source and tested, so it does not drift. This page
keeps only the shape of the work; the handbook has the detail.

### Scanning — three modes, and the mode is the difference

| Mode | What a scan does |
|------|------------------|
| **Look Up** | Reads only. Tells you what the code is, what it costs and where it is. Nothing changes. |
| **Add Stock** | Books units into a location. Asks for location and quantity before it commits. |
| **Receive** | Works a delivery against a pallet you pick first. Each scan books one unit against its line. |

A gun scanner types into the code box and submits itself. For a phone, press
**Camera** and fill the frame with the barcode — it confirms a code across
several frames before accepting it, so a blurred read is refused rather than
guessed.

If a scan seems to do nothing, check the mode first. Look Up is doing exactly
what it promises.

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

## Receiving a delivery

1. **Stage the pallet** — vendor and PO reference are enough to start.
2. **Add the lines** — the Manifest Lines grid, one row per product: Tab moves
   across, Enter starts a new row. Or photograph the packing slip and let
   **Import Packing Slip** read it, then check it at the Verify step.
3. **Link each line to an item** — a line that is not linked cannot be received.
   Do it on the manifest, or scan the box at the station and it links and
   receives in one step.
4. **Receive** — scan box by box, or **Receive All** on a line once you have
   physically counted it. **Mark Short** records what did not arrive.
5. **Complete Receiving** when the counts match. **Pause — keep it open** is
   safe at any point; the next person picks it up and the session log says who
   did which part.

Receiving is where real cost enters the system: each receipt recalculates the
item's weighted average, so everything downstream follows what you actually
paid.

### Importing a product sheet

**Inventory → Import Sheet** reads an .xlsx, .xls or .csv into the catalogue —
and shows you every row it would touch before it writes anything: what would be
created, what would be updated and which field changes from what, what already
matches, and what needs a look. Costs already set are left alone unless you tick
the overwrite box.

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
- **Reason:** That code is on no item yet — a scan matches a barcode, not a SKU.
- **Fix:** Find the item by name, then **Replace Barcode** on its row menu and
  scan the box. It scans everywhere after that.
- **At the receiving station:** tap the line's **Scan** button instead — the code
  becomes that line's mapping and the box is received in the same step.

### Camera not working on mobile
- **iOS:** Use Safari (Firefox/Chrome don't have camera API)
- **Android:** Any browser with camera permission
- **Check:** Settings → Allow camera access

### Pallet won't receive
- **Reason:** A line is not linked to an inventory item yet.
- **Fix:** Open the pallet → **Review Manifest** → link the line. Or scan the box
  at the station, which links and receives in one step.

### A location dropdown is empty
- **Reason:** No active location of the type that screen needs. Mark Damaged only
  offers **Damaged** locations, Move to Returns only **Returned**, and a
  streamer's own stock needs a **Streamer Inventory** location tied to them.
- **Fix:** Inventory → Locations, and check the location's **Type**. A location
  with the wrong type looks exactly like a missing feature.

---

## Tips for Fast Operations

1. **Use Bluetooth scanner** — Faster than typing
2. **Use camera on mobile** — Hands-free, no scanner needed
3. **Batch operations** — Receive multiple items at once
4. **Keyboard shortcuts** — Cmd+E for quick access
5. **Save filters** — Inventory Search saves named search profiles; most other
   tables remember the filters you last applied

---

## Data Entry Best Practices

- **SKU:** Use vendor's SKU for consistency
- **Barcode:** Scan directly from item label
- **Unit Cost:** Enter per-unit wholesale cost
- **Location:** Be specific (e.g., "Shelf A-3", "Bin 12")
- **Notes:** Add context for future reference
- **Sold as:** Auction, Buy It Now or Both — set it and the catalogue can be
  filtered by it when you are planning a show

---

## Getting Help

- **Tooltip:** Hover **ⓘ** icons for hints
- **Empty states:** Guidance when no data to show
- **Error messages:** Red boxes explain what went wrong
- **Handbook:** Top of the sidebar — every inventory screen, button and field,
  searchable, with a printable PDF
- **Feedback:** Raise a ticket from the sidebar when something is wrong

---

## The handbook

The **Handbook** at the top of the sidebar covers the inventory module in full:
six sections, sixty-odd walkthroughs, every screen photographed on this
installation, a search across every field description, a troubleshooting page
and an index of every screen. The **Printable PDF** button on it produces the
same thing as a document.

Handbooks for Shows, Payouts and Fulfillment are listed there as coming.

