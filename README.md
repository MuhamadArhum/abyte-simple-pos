# Abyte Simple POS & RMS

A modern, offline-first Windows desktop application for retail businesses and restaurants, built with Electron, React, TypeScript, and SQLite.

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
- SQLite (local database; no cloud database required)
- Zustand for client state where needed
- Recharts for analytics

## Development status

The repository is being initialized. The current foundation establishes the desktop app shell and navigation. Business modules and persistent SQLite storage will be implemented incrementally with migrations and tests.

## Requirements

- Node.js LTS
- npm
- Windows, macOS, or Linux for development (Windows is the primary release target)

## Getting started

```bash
npm install
npm run dev
```

## Build

```bash
npm run build
npm run dist
```

## Offline-first principles

- Normal business operations must not require internet access.
- Business data will be stored locally in SQLite.
- Database access will remain in Electron's main process and be exposed through a narrow, validated IPC API.
- Backups will be user-controlled and stored locally or on removable media.

## Security

- Renderer process Node integration is disabled.
- Context isolation is enabled.
- Renderer-to-main communication is exposed through a restricted preload API.
- Never store plaintext passwords or secrets in the database.
