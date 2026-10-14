import initSqlJs, { type Database as SqlJsDatabase, type SqlValue } from 'sql.js'
import { app } from 'electron'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

type Row = Record<string, unknown>
type RunResult = { changes: number; lastInsertRowid: number }

let database: SqlJsDatabase | null = null
let databasePath = ''
let transactionDepth = 0
let dirty = false

const schema = [
  `CREATE TABLE IF NOT EXISTS schema_migrations (
    version INTEGER PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    kind TEXT NOT NULL DEFAULT 'retail' CHECK (kind IN ('retail', 'restaurant', 'both')),
    description TEXT,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    sku TEXT UNIQUE,
    barcode TEXT UNIQUE,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    kind TEXT NOT NULL DEFAULT 'retail' CHECK (kind IN ('retail', 'ingredient', 'menu_item', 'both')),
    unit TEXT NOT NULL DEFAULT 'piece',
    purchase_price INTEGER NOT NULL DEFAULT 0 CHECK (purchase_price >= 0),
    selling_price INTEGER NOT NULL DEFAULT 0 CHECK (selling_price >= 0),
    stock_quantity REAL NOT NULL DEFAULT 0,
    minimum_stock REAL NOT NULL DEFAULT 0,
    track_stock INTEGER NOT NULL DEFAULT 1 CHECK (track_stock IN (0, 1)),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    address TEXT,
    credit_limit INTEGER NOT NULL DEFAULT 0 CHECK (credit_limit >= 0),
    opening_balance INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    address TEXT,
    opening_balance INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status TEXT NOT NULL DEFAULT 'completed' CHECK (status IN ('draft', 'held', 'completed', 'cancelled', 'refunded', 'partially_refunded')),
    subtotal INTEGER NOT NULL DEFAULT 0 CHECK (subtotal >= 0),
    discount_amount INTEGER NOT NULL DEFAULT 0 CHECK (discount_amount >= 0),
    tax_amount INTEGER NOT NULL DEFAULT 0 CHECK (tax_amount >= 0),
    total_amount INTEGER NOT NULL DEFAULT 0 CHECK (total_amount >= 0),
    cost_amount INTEGER NOT NULL DEFAULT 0 CHECK (cost_amount >= 0),
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS sale_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
    product_name TEXT NOT NULL,
    sku TEXT,
    quantity REAL NOT NULL CHECK (quantity > 0),
    unit_price INTEGER NOT NULL CHECK (unit_price >= 0),
    unit_cost INTEGER NOT NULL DEFAULT 0 CHECK (unit_cost >= 0),
    discount_amount INTEGER NOT NULL DEFAULT 0 CHECK (discount_amount >= 0),
    tax_amount INTEGER NOT NULL DEFAULT 0 CHECK (tax_amount >= 0),
    line_total INTEGER NOT NULL CHECK (line_total >= 0)
  )`,
  `CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
    method TEXT NOT NULL CHECK (method IN ('cash', 'card', 'bank_transfer', 'other', 'credit')),
    amount INTEGER NOT NULL CHECK (amount >= 0),
    reference TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    description TEXT,
    amount INTEGER NOT NULL CHECK (amount > 0),
    payment_method TEXT NOT NULL DEFAULT 'cash',
    expense_date TEXT NOT NULL DEFAULT (date('now')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  `CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
    movement_type TEXT NOT NULL CHECK (movement_type IN ('opening', 'purchase', 'sale', 'sale_return', 'purchase_return', 'adjustment', 'damage')),
    quantity_change REAL NOT NULL CHECK (quantity_change != 0),
    reference_type TEXT,
    reference_id INTEGER,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
  )`,
  'CREATE INDEX IF NOT EXISTS idx_products_name ON products(name)',
  'CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id)',
  'CREATE INDEX IF NOT EXISTS idx_sales_created_at ON sales(created_at)',
  'CREATE INDEX IF NOT EXISTS idx_sales_customer ON sales(customer_id)',
  'CREATE INDEX IF NOT EXISTS idx_sale_items_sale ON sale_items(sale_id)',
  'CREATE INDEX IF NOT EXISTS idx_stock_movements_product ON stock_movements(product_id, created_at)',
]

function requireDatabase(): SqlJsDatabase {
  if (!database) throw new Error('Database has not been initialized')
  return database
}

function toValues(sql: string, params?: unknown): SqlValue[] {
  if (params === undefined || params === null) return []
  if (Array.isArray(params)) return params as SqlValue[]
  if (typeof params !== 'object') return [params as SqlValue]

  const record = params as Record<string, SqlValue>
  const names = [...sql.matchAll(/[@:$]([A-Za-z_][A-Za-z0-9_]*)/g)].map((match) => match[1])
  return names.length ? names.map((name) => record[name] ?? null) : Object.values(record)
}

function callParams(args: unknown[]): unknown {
  return args.length > 1 ? args : args[0]
}

function rowsFromStatement(sql: string, params?: unknown): Row[] {
  const db = requireDatabase()
  const statement = db.prepare(sql)
  try {
    const values = toValues(sql, params)
    if (values.length) statement.bind(values)
    const rows: Row[] = []
    while (statement.step()) rows.push(statement.getAsObject() as Row)
    return rows
  } finally {
    statement.free()
  }
}

const adapter = {
  prepare(sql: string) {
    return {
      get(...params: unknown[]): Row | undefined {
        return rowsFromStatement(sql, callParams(params))[0]
      },
      all(...params: unknown[]): Row[] {
        return rowsFromStatement(sql, callParams(params))
      },
      run(...params: unknown[]): RunResult {
        const db = requireDatabase()
        db.run(sql, toValues(sql, callParams(params)))
        const changes = db.getRowsModified()
        const row = db.exec('SELECT last_insert_rowid() AS id')
        const lastInsertRowid = Number(row[0]?.values[0]?.[0] ?? 0)
        persist()
        return { changes, lastInsertRowid }
      },
    }
  },
  exec(sql: string): void {
    requireDatabase().exec(sql)
    persist()
  },
  pragma(pragma: string): void {
    if (/^journal_mode\s*=\s*WAL$/i.test(pragma.trim())) return
    requireDatabase().run(`PRAGMA ${pragma}`)
  },
  transaction<T>(callback: () => T): () => T {
    return () => {
      const db = requireDatabase()
      const outermost = transactionDepth === 0
      if (outermost) db.run('BEGIN TRANSACTION')
      transactionDepth += 1
      try {
        const result = callback()
        transactionDepth -= 1
        if (outermost) {
          db.run('COMMIT')
          if (dirty) persist()
        }
        return result
      } catch (error) {
        transactionDepth -= 1
        if (outermost) {
          db.run('ROLLBACK')
          dirty = false
        }
        throw error
      }
    }
  },
  close(): void {
    const db = database
    if (!db) return
    if (transactionDepth > 0) throw new Error('Cannot close the database during a transaction')
    writeFileSync(databasePath, Buffer.from(db.export()))
    db.close()
    database = null
  },
}

function persist(): void {
  if (!database) return
  if (transactionDepth > 0) {
    dirty = true
    return
  }
  writeFileSync(databasePath, Buffer.from(database.export()))
  dirty = false
}

export async function initializeDatabase(): Promise<void> {
  if (database) return
  const directory = join(app.getPath('userData'), 'data')
  mkdirSync(directory, { recursive: true })
  databasePath = join(directory, 'abyte-pos.sqlite')

  const SQL = await initSqlJs({
    locateFile: (file) => join(app.getAppPath(), 'node_modules', 'sql.js', 'dist', file),
  })
  const initialBytes = existsSync(databasePath) ? readFileSync(databasePath) : undefined
  database = initialBytes ? new SQL.Database(new Uint8Array(initialBytes)) : new SQL.Database()
  database.run('PRAGMA foreign_keys = ON')

  const migrate = adapter.transaction(() => {
    for (const statement of schema) adapter.exec(statement)
    adapter.prepare('INSERT OR IGNORE INTO schema_migrations (version) VALUES (?)').run(1)
    adapter.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)').run('currency', 'PKR')
    adapter.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)').run('business_type', 'universal')
    adapter.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)').run('business_name', 'My Business')
  })
  migrate()
  persist()
}

export function getDatabase() {
  if (!database) throw new Error('Database has not been initialized')
  return adapter
}

export function closeDatabase(): void {
  adapter.close()
}
