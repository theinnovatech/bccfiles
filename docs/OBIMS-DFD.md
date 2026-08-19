# OBIMS Data Flow Diagram (DFD)

**System:** Online Barcode Inventory Management System (OBIMS)  
**Organization:** Department of Education — Supply Unit  
**Notation:** Gane–Sarson style (external entity, process, data store, data flow)

## Lucidchart (import)

The editable diagrams are in **[OBIMS-DFD.drawio](OBIMS-DFD.drawio)** (same folder). Lucidchart imports this format.

1. Go to [lucid.app](https://lucid.app) and sign in.
2. **File → Import** (or **+ New → Import**).
3. Upload `docs/OBIMS-DFD.drawio`.
4. Each tab in Lucidchart is a page: Context, Diagram 0, Stock 3.0, Issuance 4.0, Returns 5.0, Inventory Cycle.

You can also open the same file in [diagrams.net](https://app.diagrams.net) (**File → Open from → Device**).

Mermaid versions below are for preview in GitHub / VS Code. Use the `.drawio` file as the source for Lucidchart.

---

## Legend

| Symbol | Meaning |
| --- | --- |
| Rectangle | External entity (person or office outside the system) |
| Rounded box | Process (work the system performs) |
| Cylinder | Data store (database table / file) |
| Arrow | Data flow (information moving in that direction) |

---

## 1. Context Diagram (Level 0)

The context diagram shows OBIMS as one process and the people who send data in or receive data out.

```mermaid
flowchart TB
    Admin[Admin]
    Officer[Supply Officer]
    DeptUser[Department User / Receiver]
    OBIMS((0<br/>OBIMS))

    Admin -->|"Login, users, permissions, backup"| OBIMS
    OBIMS -->|"User accounts, activity logs, reports"| Admin

    Officer -->|"Item/equipment records, stock, issuance, returns"| OBIMS
    OBIMS -->|"Stock status, cards, lookup results, reports"| Officer

    DeptUser -->|"Received By / Returned By name"| OBIMS
    OBIMS -->|"Issuance and return history"| DeptUser
```

**External entities**

- **Admin** — manages users, permissions, deleted data, and backups.
- **Supply Officer** — registers supplies, issues stock, records returns, and prints reports.
- **Department User / Receiver** — the person who receives or returns property; history is looked up by name.

---

## 2. Diagram 0 (Level 1)

This level splits OBIMS into the main processes used every day.

```mermaid
flowchart LR
    Admin[Admin]
    Officer[Supply Officer]
    Receiver[Receiver / Borrower]

    P1((1.0<br/>Authenticate<br/>and Authorize))
    P2((2.0<br/>Manage Master<br/>Data))
    P3((3.0<br/>Register and<br/>Maintain Stock))
    P4((4.0<br/>Issue Items<br/>and Equipment))
    P5((5.0<br/>Process<br/>Equipment Returns))
    P6((6.0<br/>Look Up Records<br/>and Catalog))
    P7((7.0<br/>Generate Reports<br/>and Logs))

    D1[(D1 Users)]
    D2[(D2 Employees / Departments)]
    D3[(D3 Items)]
    D4[(D4 Equipments)]
    D5[(D5 Issuances)]
    D6[(D6 Returns)]
    D7[(D7 Stock Movements)]
    D8[(D8 Activity Logs)]

    Admin --> P1
    Officer --> P1
    P1 --> D1
    D1 --> P1

    Admin --> P2
    Officer --> P2
    P2 --> D2
    D2 --> P2
    P2 --> D3
    P2 --> D4

    Officer --> P3
    P3 --> D3
    P3 --> D4
    P3 --> D7
    D3 --> P3
    D4 --> P3

    Officer --> P4
    Receiver -.->|"Received By name"| P4
    D2 --> P4
    D3 --> P4
    D4 --> P4
    P4 --> D5
    P4 --> D3
    P4 --> D4
    P4 --> D7
    P4 --> D8

    Officer --> P5
    Receiver -.->|"Returned By name"| P5
    D5 --> P5
    D4 --> P5
    D2 --> P5
    P5 --> D6
    P5 --> D4
    P5 --> D8

    Officer --> P6
    Admin --> P6
    D3 --> P6
    D4 --> P6
    D5 --> P6
    D6 --> P6
    P6 -->|"History and details"| Officer

    Officer --> P7
    Admin --> P7
    D3 --> P7
    D4 --> P7
    D5 --> P7
    D6 --> P7
    D7 --> P7
    D8 --> P7
    P7 -->|"Reports / logs / backup"| Admin
    P7 -->|"Reports"| Officer
```

### Process list (Level 1)

| No. | Process | Description |
| --- | --- | --- |
| 1.0 | Authenticate and Authorize | Login, session, role, and page permissions |
| 2.0 | Manage Master Data | Departments, employees, categories, units, locations, settings |
| 3.0 | Register and Maintain Stock | Register items/equipment, receive, adjust, stock/property cards |
| 4.0 | Issue Items and Equipment | Encode hard-copy issuance; update stock and outstanding property |
| 5.0 | Process Equipment Returns | Look up issued property; record well/damaged return; restock Used units |
| 6.0 | Look Up Records and Catalog | Person (Received By), item, equipment, catalog details |
| 7.0 | Generate Reports and Logs | Reports, activity logs, notifications, backup files |

### Data stores (Level 1)

| ID | Store | Main tables |
| --- | --- | --- |
| D1 | Users | `users` |
| D2 | Employees / Departments | `employees`, `departments` |
| D3 | Items | `items`, `categories`, `units`, `storage_locations` |
| D4 | Equipments | `equipments`, `equipment_categories` |
| D5 | Issuances | `issuances`, `issuance_details` |
| D6 | Returns | `returns` |
| D7 | Stock Movements | `stock_movements` |
| D8 | Activity Logs | `activity_logs` |

---

## 3. Level 2 — Process 3.0 Register and Maintain Stock

```mermaid
flowchart TB
    Officer[Supply Officer]

    P31((3.1<br/>Register Item))
    P32((3.2<br/>Register Equipment))
    P33((3.3<br/>Receive / Adjust Stock))
    P34((3.4<br/>Update Stock Card<br/>and Property Card))

    D3[(D3 Items)]
    D4[(D4 Equipments)]
    D7[(D7 Stock Movements)]
    D8[(D8 Activity Logs)]

    Officer -->|"Item name, barcode, qty, category"| P31
    P31 --> D3
    P31 --> D8

    Officer -->|"Name, type, category, life span, specs"| P32
    P32 -->|"Property No., Inventory No."| Officer
    P32 --> D4
    P32 --> D8

    Officer -->|"Barcode / property, qty"| P33
    D3 --> P33
    D4 --> P33
    P33 --> D3
    P33 --> D4
    P33 --> D7

    Officer --> P34
    D3 --> P34
    D4 --> P34
    D7 --> P34
    P34 -->|"Stock card / property card"| Officer
```

**Notes**

- New equipment is tagged **Fresh** (first-time supply stock).
- Barcode, property number, and inventory number identify records in Supply Master.

---

## 4. Level 2 — Process 4.0 Issue Items and Equipment

```mermaid
flowchart TB
    Officer[Supply Officer]
    Receiver[Receiver]

    P41((4.1<br/>Select Issuance Type))
    P42((4.2<br/>Encode Issuance Header))
    P43((4.3<br/>Encode Item Lines))
    P44((4.4<br/>Encode Equipment Lines))
    P45((4.5<br/>Save Issuance))

    D2[(D2 Employees / Departments)]
    D3[(D3 Items)]
    D4[(D4 Equipments)]
    D5[(D5 Issuances)]
    D7[(D7 Stock Movements)]
    D8[(D8 Activity Logs)]

    Officer --> P41
    P41 --> P42
    D2 --> P42
    Receiver -.->|"Received By name"| P42
    P42 --> P43
    P42 --> P44

    D3 -->|"Available stock"| P43
    P43 -->|"Qty, inventory no."| P45

    D4 -->|"Available / Fresh / Used"| P44
    Officer -->|"Paper Property No."| P44
    P44 -->|"Issued property, inventory, date acquired"| P45

    P45 --> D5
    P45 -->|"Reduce quantity"| D3
    P45 -->|"Reduce quantity"| D4
    P45 --> D7
    P45 --> D8
    P45 -->|"Issuance No."| Officer
```

**Equipment issuance rules captured in the flow**

- **New (Fresh):** Property No. must be changed from the Supply Master default to the number on the paper form.
- **Used (Returned):** Officer decides whether to keep or change Property No.
- Inventory No. is optional for equipment.
- Original Supply Master listing is not overwritten; issued numbers are stored on the issuance line.

---

## 5. Level 2 — Process 5.0 Process Equipment Returns

```mermaid
flowchart TB
    Officer[Supply Officer]
    Person[Returned By / Receiver]

    P51((5.1<br/>Look Up Issued Property))
    P52((5.2<br/>Show Issuance Details))
    P53((5.3<br/>Record Return))
    P54((5.4<br/>Apply Remaining Life Span))
    P55((5.5<br/>Restock or Log Only))

    D2[(D2 Employees / Departments)]
    D4[(D4 Equipments)]
    D5[(D5 Issuances)]
    D6[(D6 Returns)]
    D8[(D8 Activity Logs)]

    Officer -->|"Property No."| P51
    D5 -->|"Outstanding issued lines only"| P51
    P51 --> P52

    P52 -->|"Date issued, date acquired, specs, New/Used"| Officer
    P52 -->|"Remaining life span as of Date Returned"| Officer

    Officer --> P53
    Person -.->|"Returned By"| P53
    D2 --> P53
    P53 --> P54

    P54 -->|"Years left until expiry"| P55

    P55 -->|"Well + still within life span → Used stock"| D4
    P55 --> D6
    P55 --> D5
    P55 --> D8
    P55 -->|"Return recorded"| Officer
```

**Return rules captured in the flow**

- Search is limited to **outstanding issued** property numbers, not Available Supply Master stock.
- Remaining life span updates when **Date Returned** changes.
- **Returned well** before the life-span limit creates a separate **Used** equipment record.
- **Damaged** or **life span reached** is logged only and is not added back for re-issue.
- Custom Equipment (past data) is used when the return is not in issuance history.

---

## 6. Level 2 — Process 6.0 Look Up Records and Catalog

```mermaid
flowchart LR
    Officer[Supply Officer]

    P61((6.1<br/>Lookup by Person))
    P62((6.2<br/>Lookup by Item))
    P63((6.3<br/>Lookup by Equipment))
    P64((6.4<br/>Catalog Details))

    D2[(D2 Employees)]
    D3[(D3 Items)]
    D4[(D4 Equipments)]
    D5[(D5 Issuances)]
    D6[(D6 Returns)]

    Officer -->|"Received By name or employee"| P61
    D2 --> P61
    D5 --> P61
    D6 --> P61
    P61 -->|"Items received, borrowed, outstanding, returns"| Officer

    Officer -->|"Item name / barcode"| P62
    D3 --> P62
    D5 --> P62
    P62 -->|"Who received this item"| Officer

    Officer -->|"Property No. / name"| P63
    D4 --> P63
    D5 --> P63
    D6 --> P63
    P63 -->|"Borrowers and returns"| Officer

    Officer -->|"Name / barcode / property"| P64
    D3 --> P64
    D4 --> P64
    P64 -->|"Full record, Fresh or Returned"| Officer
```

Person lookup searches both **employees** and **typed Received By** names stored on issuances.

---

## 7. Level 2 — Process 7.0 Generate Reports and Logs

```mermaid
flowchart TB
    Admin[Admin]
    Officer[Supply Officer]

    P71((7.1<br/>Build Report))
    P72((7.2<br/>Record Activity))
    P73((7.3<br/>Backup / Restore))

    D3[(D3 Items)]
    D4[(D4 Equipments)]
    D5[(D5 Issuances)]
    D6[(D6 Returns)]
    D7[(D7 Stock Movements)]
    D8[(D8 Activity Logs)]

    Officer --> P71
    Admin --> P71
    D3 --> P71
    D4 --> P71
    D5 --> P71
    D6 --> P71
    D7 --> P71
    P71 -->|"PDF / preview"| Officer

    P72 --> D8
    D8 --> Admin

    Admin --> P73
    P73 -->|"Backup files"| Admin
```

---

## 8. End-to-end data path (summary)

```mermaid
flowchart LR
    A[Register Fresh stock] --> B[Issue to department]
    B --> C[Outstanding issued property]
    C --> D[Record return]
    D --> E{Condition and life span}
    E -->|"Well and still within limit"| F[Used stock in Supply Master]
    E -->|"Damaged or expired"| G[Logged only]
    F --> B
```

This is the main inventory cycle in OBIMS: **Fresh stock → Issuance → Return → Used stock (if still usable) → can be issued again**.

---

## 9. How this maps to the application

| DFD process | Screens / modules |
| --- | --- |
| 1.0 Authenticate | Login, Profile, Permissions |
| 2.0 Master data | Departments, Employees, Settings / Master Data, Users |
| 3.0 Stock | Registration, Supply Master, Stock Operations, Stock Card, Property Card |
| 4.0 Issuance | Item Issuance |
| 5.0 Returns | Equipment Returns |
| 6.0 Lookup | Records Lookup, Catalog Details |
| 7.0 Reports | Reports, Activity Logs, Backup Files, Estimated Stock |
