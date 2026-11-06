# Retail Shop Inventory & POS – Requirements (Draft v2)

**Shop type:** Mobile accessories, spare parts, electronics, plus device repair service. Kitchen & home appliances later.
**Stack:** Laravel + MySQL. Frontend: Blade + Livewire + Alpine.js + Tailwind (see section 12).
**Hosting goal:** Cheapest option that works (shared hosting with PHP + MySQL).

### Confirmed decisions
| Topic | Decision |
|---|---|
| Branches | Multi-branch system. **1 branch at launch**, more added later from admin panel |
| Cashiers | **1 cashier per branch** (1 POS computer per branch) |
| Phones / IMEI | Not sold. No IMEI tracking. Serial tracking only later for appliances (Phase 3) |
| Repair service | **Yes – in Phase 1** (section 8) |
| VAT | **Optional** – admin switch, off by default |
| Credit | **No credit** – no customer due, no supplier due. Every sale and purchase is paid in full |
| Printer | **Rongta thermal printer** for receipts. Label printer, A4 labels, cash drawer: all optional settings |
| Technicians, SMS | Optional settings (off by default) |
| Language | **Bangla + English** in Phase 1 – UI and receipts |
| Hosting | Online (needed for multi-branch) |

---

## 1. Users & Roles

| Role | Can do |
|---|---|
| **Admin / Owner** | Everything: settings, branches, users, prices, reports, delete/void, stock adjust |
| **Manager** (optional) | Products, purchases, stock, repairs, reports. No system settings or user management |
| **Cashier** | POS sales, repair job intake/delivery, returns (if allowed), view product price/stock |

- Admin can turn individual permissions on/off (e.g. "cashier can give discount", "cashier can do returns").
- Login with username/email + password. Optional PIN login for cashier at POS.
- Each user picks their language (Bangla / English).
- Activity log: who did what and when (price change, stock adjust, sale void, discount given).

## 1A. Branches

- Starts with 1 branch; admin can add more branches any time with no code change.
- Branch fields: name, address, phone, receipt header/footer.
- **Products are shared** across all branches (one catalog, same barcodes). **Stock is separate** per branch.
- Selling price: one common price, with optional price override per branch.
- Each user belongs to a branch. Cashier sees and sells only from their branch. Admin sees all branches and can switch between them.
- Every sale, purchase, return, repair job, expense, cash shift and stock movement belongs to a branch.
- Separate invoice number series per branch (e.g. B1-INV-00001, B2-INV-00001).
- **Stock transfer** between branches: create transfer (scan items) → "in transit" → receiving branch scans/confirms → stock moves. Differences are recorded.
- Customers and suppliers are shared across branches.
- POS product lookup shows stock in other branches ("2 pcs available at Branch B").
- Reports: per branch, all branches combined, and branch comparison.
- Settings can be global or per branch (receipt printing ON/OFF, printer paper size, VAT).

## 2. Product Management

### 2.1 Product fields
- Name (English) and name in Bangla (optional), SKU (auto), category, brand, model / compatible devices (e.g. "Samsung A15 / A25")
- Unit of measure (piece, box, pack, meter, set…)
- Purchase (cost) price, selling price, optional minimum selling price
- VAT % (only used when VAT is switched on)
- Reorder level (low stock alert)
- Image (optional)
- Warranty period (days/months), if any
- Active / inactive
- (Phase 3) Product type "serialized" for appliances with serial numbers. The database is designed so this can be added later.

### 2.2 Categories
- Nested categories, e.g. *Accessories → Chargers*, *Spare Parts → Display*, *Electronics*, later *Kitchen Appliances*, *Home Appliances*.
- New categories can be added any time, so appliances fit in later with no code change.

### 2.3 Variants (Phase 3)
- One product with variants by color / size / capacity (e.g. "Back cover – A15 – Black/Blue").

## 3. Barcode System (core requirement)

### 3.1 Barcode sources
During product/stock entry, the user can either:
1. **Scan an existing barcode** already on the product (manufacturer EAN/UPC), or
2. **Generate a new barcode** in the system (internal Code128 with a shop prefix), then print labels.

- A barcode must be unique across the system.
- One product can have **multiple barcodes** (e.g. the same charger from two suppliers with different printed codes).

### 3.2 Packs / boxes (e.g. a box of 10 small pins)
Stock is always stored in the **base unit** (piece). Each product can have **packaging units**:

| Unit | Contains | Barcode | Sell price |
|---|---|---|---|
| Piece (base) | 1 | optional | 10 |
| Box | 10 pieces | 8901234567890 | 90 |

Rules:
- Scanning the **box barcode** at POS adds 1 box (stock goes down by 10 pieces) at box price.
- If single pieces have **no barcode of their own**, the cashier scans the box barcode and switches the cart line unit to "piece", selling 1 piece at piece price.
- No "open box" step needed (stock = total pieces). Display shows e.g. "3 boxes + 4 pcs".
- Purchase entry can be done in boxes or pieces.

### 3.3 Label printing
- Print barcode labels after purchase entry or from the product list (choose quantity per product).
- Label shows: shop name (optional), product name, price, barcode.
- Supports thermal label sizes (e.g. 38×25 mm, 50×25 mm) and A4 sticker sheets (e.g. 3×8 = 24 labels).
- Admin setting: label size and which fields to show.

### 3.4 Scanner
- Works with any USB / Bluetooth barcode scanner (they type like a keyboard). No special software needed.
- Optional (Phase 2): phone camera scanning for stock checking.

## 4. Purchase & Stock In

- **Suppliers:** name, phone, address, notes.
- **Purchase entry:** supplier, supplier invoice no, date, branch, items (scan or search), qty (box or piece), cost price, discount, total paid (always full payment), payment method.
- Option to update selling price during purchase.
- After saving: option to print barcode labels for the received items.
- **Purchase return** to supplier (refund received in cash/other).
- Purchase history per supplier.

## 5. Inventory / Stock Control

- Real-time stock per product per branch (in pieces, with box breakdown).
- **Stock adjustment** with reason: damaged, lost, internal use, found, count correction (needs permission; logged).
- **Stock count (stock-take):** scan items on the shelf, compare with system, adjust differences (Phase 2).
- **Low stock alerts** (dashboard + list) based on reorder level.
- **Stock movement history** per product: every purchase, sale, return, repair usage, transfer, adjustment.
- **Stock valuation** using average cost (qty × cost).

## 6. POS (Sales)

### 6.1 Sale screen
- Full-screen, keyboard-friendly, works on a normal PC/laptop + scanner.
- Cursor always in the scan box. **Scan → product is added**; scanning the same item again increases qty.
- Search by name (English or Bangla) / SKU / model if no barcode.
- Change qty, unit (box/piece), price (if allowed), line discount.
- Bill discount (amount or %), VAT (only if switched on).
- Customer: walk-in by default, or pick/add customer by phone number (optional, for history and warranty).
- **Payment methods:** cash, card, mobile banking (bKash/Nagad/Rocket or any you set), split payment across methods.
- **Full payment required** – sale cannot be completed until paid in full (no due).
- Shows change to return.
- **Hold / park sale** and resume later.
- Keyboard shortcuts (e.g. F2 search, F8 hold, F10 pay).

### 6.2 Receipt
- **Admin setting:** printing **ON / OFF / Ask each time**.
- Rongta thermal printer, 80 mm (58 mm option). A4 invoice also available.
- Receipt language: Bangla or English (admin default; cashier can switch per receipt).
- Receipt shows: shop logo/name, branch address, phone, invoice no, date, cashier, items, discounts, VAT (if on), total, paid, change, warranty info, footer note (return policy).
- Reprint any old receipt.
- Optional: send receipt via SMS (if SMS setting is ON).

### 6.3 Returns & exchanges
- Return by scanning/entering invoice no; pick items to return.
- Refund as cash/original method, or use against a new sale (exchange).
- Returned item goes back to stock or to "damaged" stock (user chooses).
- Admin setting: return allowed within X days.

### 6.4 Void / cancel
- Void a sale only with permission; stock goes back; logged.

## 7. Customers

- Name, phone, address, notes.
- Purchase history and repair history.
- No credit / due tracking.
- (Phase 3) Loyalty points.

## 8. Repair / Service (Phase 1)

### 8.1 Job intake
- Customer name + phone (search existing by phone).
- Device: type (phone, tablet, etc.), brand, model, color, IMEI/serial (optional, for identification only), password/pattern (optional), accessories received (charger, cover, SIM…), device condition notes (scratches, broken back etc.).
- Problem description, estimated cost, estimated delivery date.
- Assigned technician (only if "Technicians" setting is ON; otherwise this field is hidden).
- Advance payment (optional). This is not credit: the rest is paid in full when the device is delivered.
- **Print job slip** (Rongta 80 mm): job no, customer copy, terms. Optional sticker/tag for the device with job no.

### 8.2 Job status flow
`Received → Diagnosing → Waiting for parts → Repairing → Ready → Delivered`
Also: `Cancelled / Not repairable` (device returned to customer).
- Status history with time and user.
- Search jobs by job no, phone, or customer name.

### 8.3 Parts & charges
- Add spare parts used from stock (scan barcode) – deducted from branch stock automatically.
- Add service/labour charge.
- Final bill = parts + service charge − discount − advance.

### 8.4 Delivery
- Take remaining payment (cash/card/mobile banking), print final bill.
- Repair warranty (e.g. 7/30 days on the repair) printed on the bill.
- Warranty claim: reopen a delivered job as a warranty job (no charge, or charged).

### 8.5 Repair reports
- Jobs by status, pending/overdue jobs, repair income, parts used, technician performance.

## 9. Expenses & Cash

- Daily expenses per branch: rent, salary, electricity, transport, etc. (by category).
- **Cash register / shift:** opening cash, cash sales, repair payments, cash refunds, expenses paid from drawer, expected vs counted closing cash.

## 10. Reports & Dashboard

**Dashboard (per branch or all):** today's sales, repair income, profit, invoices count, pending repair jobs, ready-for-delivery jobs, low stock count, top products this week.

**Reports** (filter by date, branch, category, brand, cashier; print; export Excel/PDF in Phase 2):
- Sales report (daily / monthly / by product / by category / by cashier)
- Profit report (sales + repair income − cost of goods − expenses)
- Purchase report
- Stock report & stock valuation
- Low stock / reorder report
- Dead stock (not sold in X days)
- Best-selling / slow-moving products
- Payment method summary (cash vs card vs mobile banking)
- Repair reports (section 8.5)
- Expense report
- Returns report
- Stock adjustment and transfer reports

## 11. Admin Settings

- Shop info: name, logo, phone, currency (৳ BDT), VAT/BIN no.
- Branches (section 1A).
- Language: default language for UI and receipts (Bangla / English).
- Receipt: print ON/OFF/Ask, paper size (80/58 mm), header/footer text in both languages, show/hide logo.
- Barcode: prefix, label size and fields.
- **VAT: ON/OFF switch** (default OFF), default rate, price includes VAT or not.
- Invoice and repair job number format.
- POS rules: allow price edit, max discount %, allow negative stock (yes/no).
- Repair: default repair warranty days, job slip terms text, status list.

### 11.1 Optional features (admin ON/OFF switches)
| Setting | Options | Default |
|---|---|---|
| Barcode label output | Thermal label printer (choose size, e.g. 38×25 / 50×25 mm) **or** A4 sticker sheet (choose layout, e.g. 3×8) | A4 sheet |
| Cash drawer | ON / OFF. When ON, a "drawer opens after cash sale" note is shown in setup guide (drawer is opened by the Rongta driver) and a "No sale – open drawer" button appears (needs permission, logged) | OFF |
| Technicians | ON / OFF. When ON: technician list, assign jobs, technician performance report. When OFF: all jobs belong to the shop | OFF |
| SMS notifications | ON / OFF. When ON: enter SMS gateway API details, choose which events send SMS (repair received, repair ready, sale receipt), editable SMS text in Bangla/English, SMS log | OFF |
| VAT | ON / OFF (see above) | OFF |
| Receipt printing | ON / OFF / Ask each time | Ask |
- Return policy days.
- Payment methods list (add/remove).
- Users & roles/permissions.
- **Backup:** one-click database backup download + automatic daily backup (to email or Google Drive).

## 12. Technology Decision

### Recommendation: Laravel + Blade + Livewire + Alpine.js + Tailwind CSS (no React/Vue)

| Option | Verdict | Why |
|---|---|---|
| **Blade + Livewire + Alpine** | ✅ **Recommended** | Everything stays in Laravel/PHP. Livewire handles the dynamic parts (POS cart, live search) without a separate JS app. Runs on the cheapest shared hosting. One codebase, faster to build. |
| Inertia + Vue/React | ⚠️ OK but more work | Two languages to maintain, larger build, no real benefit for this project. |
| Separate SPA + API | ❌ Not needed | Double the work, needs more hosting setup. |

- Assets (CSS/JS) are built on your computer with `npm run build` and uploaded, so **the server does not need Node.js**.

### Bangla + English
- Laravel's built-in translation files (`lang/en`, `lang/bn`) for all UI text.
- Bangla font: Hind Siliguri or Noto Sans Bengali (free, from Google Fonts).
- Option to show numbers and dates in Bangla digits (১২৩) or English digits.

### Printing (Rongta thermal)
- Receipts and job slips are printed from the browser using print-specific CSS for 80 mm paper, through the Rongta Windows driver.
- This prints the page as an image, so **Bangla text prints correctly**. Raw ESC/POS text mode does not support Bangla well, so it is not used.
- Set Rongta as the default printer and use Chrome "kiosk printing" mode to print without the print dialog (free, no extra software).
- Cash drawer (if connected to the printer) can be opened through the driver setting "open drawer after print".

### Packages
- `picqer/php-barcode-generator` – barcode images for labels
- `barryvdh/laravel-dompdf` or `mpdf/mpdf` – PDF invoices/reports (mPDF handles Bangla better)
- `maatwebsite/excel` – Excel export
- `spatie/laravel-permission` – roles & permissions
- `spatie/laravel-activitylog` – audit log
- `spatie/laravel-backup` – database backups

### Hosting (cheapest that works)
- **Shared cPanel hosting** with PHP 8.2+, MySQL, SSH access and cron jobs – about **$2–4 per month** (e.g. Hostinger, Namecheap, or a local BD provider, often cheaper in BDT).
- Check before buying: PHP 8.2+, SSH access, Composer, cron jobs, at least 1 GB storage, free SSL.
- Shared-hosting-friendly design:
  - No WebSockets, no Redis, no always-running queue worker.
  - Background tasks (backups, alerts) run via one cron job: `php artisan schedule:run`.
- With 1 cashier per branch, shared hosting handles many branches easily. Can move to a small VPS ($4–6/month) later with no code changes.

### Risk: internet outage
The online POS stops working if the shop's internet goes down. Keep a mobile hotspot as backup. Offline mode is not planned for v1.

## 13. Non-Functional Requirements

- POS scan-to-cart response under 0.5 s.
- Works on Chrome/Edge on Windows PC; admin pages usable on mobile.
- Stock never goes wrong: every stock change goes through one "stock movement" record inside a database transaction.
- All money stored as decimals (no floating-point errors).
- Daily automatic backup.
- HTTPS, hashed passwords, login rate limiting.

## 14. Phases

**Phase 1 – MVP (start selling)**
- Branches (1 at launch) & stock transfer
- Users/roles, settings, Bangla + English
- Categories/brands, products with box/piece units
- Barcode: scan existing or generate + print labels
- Suppliers, purchases (full payment), purchase returns
- Stock, adjustments, low stock alerts
- POS: scan to sell, hold sale, split payments incl. mobile banking, optional VAT, receipt print ON/OFF/Ask on Rongta
- Returns & exchanges, customers
- **Repair jobs**: intake, job slip, status, parts from stock, delivery bill, repair warranty
- Expenses & cash register shift
- Dashboard, basic reports, backups
- Optional features with ON/OFF switches: label printer or A4 labels, cash drawer, technicians, SMS (section 11.1)

**Phase 2**
Stock-take by scanning, phone camera scanning, advanced reports, Excel/PDF export.

**Phase 3**
Serial number tracking (appliances), variants, loyalty points, appliance-specific fields (installation, extended warranty), simple online catalog page.

## 15. Remaining Questions

None. Printer type, cash drawer, technicians and SMS are all optional settings (section 11.1), so they can be decided later without code changes.
