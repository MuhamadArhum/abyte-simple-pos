export {}

type DashboardSummary = {
  revenue: number
  orders: number
  grossProfit: number
  lowStockItems: number
  currency: string
}

type ProductRecord = {
  id: number
  name: string
  sku: string | null
  barcode: string | null
  categoryId: number | null
  categoryName?: string | null
  unit: string
  purchasePrice: number
  sellingPrice: number
  stockQuantity: number
  minimumStock: number
  trackStock?: number
  isActive?: number
}

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

declare global {
  interface Window {
    desktop: {
      appName: string
      platform: string
      version: string
      dashboard: {
        getSummary: () => Promise<DashboardSummary>
      }
      products: {
        list: (search?: string) => Promise<ProductRecord[]>
        create: (input: ProductInput) => Promise<ProductRecord>
      }
    }
  }
}
