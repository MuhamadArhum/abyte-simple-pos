import { ipcMain } from 'electron'
import { getDatabase } from './database'

type ProductInput = {
  name: string
  sku?: string
  barcode?: string
  categoryId?: number | null
  purchasePrice?: number
  sellingPrice: number
  stockQuantity?: number
  minimumStock?: number
  unit?: string
}

function asNonNegativeInteger(value: unknown, label: string): number {
  if (typeof value !== 'number' || !Number.isSafeInteger(value) || value < 0) {
    throw new Error(`${label} must be a non-negative whole number`)
  }
  return value
}

function validateProduct(input: unknown): ProductInput {
  if (!input || typeof input !== 'object') throw new Error('Product data is required')
  const candidate = input as Record<string, unknown>
  const name = typeof candidate.name === 'string' ? candidate.name.trim() : ''
  if (!name || name.length > 160) throw new Error('Product name is required (maximum 160 characters)')

  const sellingPrice = asNonNegativeInteger(candidate.sellingPrice, 'Selling price')
  const purchasePrice = candidate.purchasePrice === undefined ? 0 : asNonNegativeInteger(candidate.purchasePrice, 'Purchase price')
  const stockQuantity = candidate.stockQuantity === undefined ? 0 : candidate.stockQuantity
  const minimumStock = candidate.minimumStock === undefined ? 0 : candidate.minimumStock

  if (typeof stockQuantity !== 'number' || !Number.isFinite(stockQuantity) || stockQuantity < 0) {
    throw new Error('Stock quantity must be a non-negative number')
  }
  if (typeof minimumStock !== 'number' || !Number.isFinite(minimumStock) || minimumStock < 0) {
    throw new Error('Minimum stock must be a non-negative number')
  }

  const optionalText = (value: unknown, label: string): string | undefined => {
    if (value === undefined || value === null || value === '') return undefined
    if (typeof value !== 'string' || value.length > 100) throw new Error(`${label} is invalid`)
    return value.trim()
  }

  const categoryId = candidate.categoryId
  if (categoryId !== undefined && categoryId !== null && (!Number.isSafeInteger(categoryId) || (categoryId as number) < 1)) {
    throw new Error('Category is invalid')
  }

  return {
    name,
    sku: optionalText(candidate.sku, 'SKU'),
    barcode: optionalText(candidate.barcode, 'Barcode'),
    categoryId: categoryId as number | null | undefined,
    purchasePrice,
    sellingPrice,
    stockQuantity,
    minimumStock,
    unit: optionalText(candidate.unit, 'Unit') ?? 'piece',
  }
}

export function registerIpcHandlers(): void {
  const db = getDatabase()

  ipcMain.handle('dashboard:get-summary', () => {
    const today = db.prepare("SELECT date('now', 'localtime') AS day").get() as { day: string }
    const sales = db.prepare(`
      SELECT
        COALESCE(SUM(total_amount), 0) AS revenue,
        COUNT(*) AS orders,
        COALESCE(SUM(total_amount - cost_amount - discount_amount), 0) AS gross_profit
      FROM sales
      WHERE status = 'completed' AND date(created_at, 'localtime') = ?
    `).get(today.day) as { revenue: number; orders: number; gross_profit: number }
    const inventory = db.prepare(`
      SELECT COUNT(*) AS low_stock
      FROM products
      WHERE is_active = 1 AND track_stock = 1 AND stock_quantity <= minimum_stock
    `).get() as { low_stock: number }

    return {
      revenue: sales.revenue,
      orders: sales.orders,
      grossProfit: sales.gross_profit,
      lowStockItems: inventory.low_stock,
      currency: (db.prepare("SELECT value FROM settings WHERE key = 'currency'").get() as { value: string } | undefined)?.value ?? 'PKR',
    }
  })

  ipcMain.handle('products:list', (_event, search?: unknown) => {
    if (search !== undefined && typeof search !== 'string') throw new Error('Search must be text')
    const term = typeof search === 'string' ? search.trim().slice(0, 100) : ''
    return db.prepare(`
      SELECT p.id, p.name, p.sku, p.barcode, p.category_id AS categoryId,
             c.name AS categoryName, p.unit, p.purchase_price AS purchasePrice,
             p.selling_price AS sellingPrice, p.stock_quantity AS stockQuantity,
             p.minimum_stock AS minimumStock, p.track_stock AS trackStock,
             p.is_active AS isActive
      FROM products p
      LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1 AND (? = '' OR p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
      ORDER BY p.name COLLATE NOCASE
      LIMIT 500
    `).all(term, `%${term}%`, `%${term}%`, `%${term}%`)
  })

  ipcMain.handle('products:create', (_event, rawInput: unknown) => {
    const input = validateProduct(rawInput)
    const createProduct = db.transaction(() => {
      const result = db.prepare(`
        INSERT INTO products
          (name, sku, barcode, category_id, purchase_price, selling_price, stock_quantity, minimum_stock, unit)
        VALUES
          (@name, @sku, @barcode, @categoryId, @purchasePrice, @sellingPrice, @stockQuantity, @minimumStock, @unit)
      `).run({
        ...input,
        sku: input.sku ?? null,
        barcode: input.barcode ?? null,
        categoryId: input.categoryId ?? null,
      })

      if (input.stockQuantity && input.stockQuantity > 0) {
        db.prepare(`
          INSERT INTO stock_movements (product_id, movement_type, quantity_change, notes)
          VALUES (?, 'opening', ?, 'Opening stock entered during product creation')
        `).run(result.lastInsertRowid, input.stockQuantity)
      }

      return db.prepare(`
        SELECT id, name, sku, barcode, category_id AS categoryId, unit,
               purchase_price AS purchasePrice, selling_price AS sellingPrice,
               stock_quantity AS stockQuantity, minimum_stock AS minimumStock
        FROM products WHERE id = ?
      `).get(result.lastInsertRowid)
    })
    return createProduct()
  })
}
