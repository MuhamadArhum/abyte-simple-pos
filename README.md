# Simple POS

A small, clean **Point of Sale** web app. Plain **PHP 8.3** + **MySQL 8** + **PDO** + **Bootstrap 5.3.3**.
No framework, no Composer, no build step. Fast text-based food/item billing.

---

## Features

- **Auth** — register / login / logout (PHP sessions, `password_hash`, session regeneration, CSRF protection)
- **POS counter** (`pos.php`)
  - menu grid, **search** (name/SKU) + **category** filter
  - session cart, quantity +/-, remove, clear
  - payment **CASH / CARD / ONLINE**
  - **atomic checkout** (`orders` + `order_items` in a transaction)
  - checkout requires an open shift. Open one from the counter or Shifts page before saving a sale.
  - **printable receipt** (print CSS, 58/80mm-friendly layout)
  - **server re-validation** of price/availability at checkout (not trusting the session cart)
- **Order history + reprint** (`orders.php`)
- **Shifts** (`shifts.php`) — open and close shifts, review expected cash (opening cash plus cash sales), and compare it with the amount counted at closing
- **Menu management** (`menu_import.php`, `menu_edit.php`)
  - manual add, **CSV import** (`sku,name,category,price`, header/BOM handling, invalid-row skip, transaction)
  - **edit** + **remove** (soft delete via `is_active`)
- **Reports** (`reports.php`) — inclusive date range, shift, payment filters, totals. Reversed dates are put in chronological order.
- **CSV export** (`export_csv.php`) — filtered order export
- **Dashboard** (`index.php`) — today's sales, orders, active items, shift status, recent orders

---

## Requirements

- **PHP 8.3+** with `pdo_mysql`, `mbstring`, `openssl`, `json`
- **MySQL 8+**
- A web server (Apache/Nginx) **or** PHP's built-in server
- Recommended local stack: **Laragon** / **XAMPP**

---

## Quick start

1. **Create the database**

   ```sh
   mysql -u root -p < database/schema.sql
   ```
   (or create an empty DB named `abyte-simple-pos` and import `database/schema.sql`).

2. **Configure connection**

   ```sh
   cp config/db.example.php config/db.php
   ```
   Edit `config/db.php`:
   ```php
   $host = '127.0.0.1';
   $dbname = 'abyte-simple-pos';
   $username = 'root';
   $password = '';
   ```
   `config/db.php` is **git-ignored** (kept local).

3. **Serve**

   Laragon: place project in `www/` and open `http://abyte-simple-pos.test`
   **or** built-in server:
   ```sh
   php -S 127.0.0.1:8000 -t .
   ```
   then open `http://127.0.0.1:8000/login.php`.

4. **First account** — open `register.php`, create a user, then login.

---

## Project structure

```
abyte-simple-pos/
├── index.php            Dashboard
├── login.php            Login
├── register.php         Registration
├── logout.php           Logout
├── pos.php              POS counter + checkout + receipt
├── orders.php           Order history + reprint
├── shifts.php           Shift open/close
├── menu_import.php      Menu list + manual add + CSV import
├── menu_edit.php        Edit + remove menu item
├── reports.php          Sales reports
├── export_csv.php       CSV export (orders)
├── config/
│   ├── db.php           Local DB config (git-ignored)
│   └── db.example.php   Template
├── includes/
│   ├── auth.php         Auth guard
│   ├── csrf.php         CSRF token helpers
│   ├── header.php       Layout header + navbar
│   └── footer.php       Layout footer
├── assets/css/app.css   UI design layer (on top of Bootstrap)
├── database/schema.sql  MySQL schema
└── README.md
```

---

## Database

MySQL `abyte-simple-pos`, 5 InnoDB tables:

- **users** — id, name, email (unique), password_hash, created_at
- **menu_items** — id, sku, name, category, price, is_active, created_at
- **orders** — id, shift_id (FK→shifts SET NULL), order_no (**unique**), payment_method, total_amount, created_at
- **order_items** — id, order_id (FK→orders CASCADE), item_id (FK→menu_items SET NULL), item_name, qty, unit_price, line_total
- **shifts** — id, user_id (FK→users SET NULL), cashier_name, opened_at, closed_at, opening_cash, closing_cash, status

---

## Security notes

- All SQL uses **prepared statements**.
- All output is **escaped** (`htmlspecialchars`).
- **CSRF tokens** on every POST form (`includes/csrf.php`).
- Checkout **re-reads price/name/active from the DB**; quantities sanitized.
- Sessions regenerated on login; `config/db.php` is git-ignored.
- Soft-delete menu items keeps historical orders intact.

---

## Notes

- Currency amounts use `DECIMAL(10,2)`; no multi-currency.
- Print receipts via the browser print dialog (`window.print()`).
- Order numbers: `ORD-YYYYMMDD-HHMMSS-####` with a **unique** DB index + retry.
