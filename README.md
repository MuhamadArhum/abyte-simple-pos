# Abyte Simple POS & RMS

A modern, offline-first Windows desktop POS and restaurant management application built with Electron, React, TypeScript, and SQLite.

## Planned modules

- Dashboard and business overview
- Point of sale, invoices, discounts, payments, and receipts
- Products, categories, and inventory movements
- Customers, suppliers, purchases, and ledgers
- Expenses and cash-register shifts
- Sales, inventory, and financial reports
- Restaurant tables, orders, kitchen display, menu, and recipes
- User roles, permissions, audit history, and local backups

## Technology

- Electron + electron-vite
- React + TypeScript
- Tailwind CSS
- **SQLite via sql.js/WebAssembly** (local SQLite file; no native SQLite addon or cloud database required)
- Zustand for client state where needed
- Recharts for analytics

The database remains a standard SQLite database file. sql.js loads it in the Electron main process and persists updates to the local app data directory. Because sql.js uses WebAssembly rather than a native Node addon, this database setup does not require compiling `better-sqlite3` with Visual Studio C++ Build Tools.

## Requirements

- Node.js 24.x (the current project is designed to avoid a native SQLite build step)
- npm
- Windows is the primary release target

## Getting started

From the project folder:

```powershell
npm install
npm run dev
```

No `npm run postinstall` step is required for SQLite native compilation.

## Build

```powershell
npm run typecheck
npm run build
npm run dist
```

## Offline-first principles

- Normal business operations must not require internet access.
- Business data is stored locally in SQLite at Electron's per-user application data directory.
- Database access stays in Electron's main process and is exposed through a narrow, validated IPC API.
- Database snapshots are written to disk after mutations and on close; backups and restore workflows should be added before production use.

## Current implementation status

The current repository contains the desktop shell, dashboard summary, product listing/creation IPC, and the initial schema for products, categories, customers, suppliers, sales, payments, expenses, and stock movements. Several navigation entries are still placeholders; the full POS checkout, reporting, permissions, backup/restore, and restaurant workflows are not complete yet.

## Security

- Renderer process Node integration is disabled.
- Context isolation is enabled.
- Renderer-to-main communication is exposed through a restricted preload API.
- Never store plaintext passwords or secrets in the database.
