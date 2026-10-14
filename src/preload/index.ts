import { contextBridge, ipcRenderer } from 'electron'

contextBridge.exposeInMainWorld('desktop', {
  appName: 'Abyte Simple POS',
  platform: process.platform,
  version: '0.1.0',
  dashboard: {
    getSummary: () => ipcRenderer.invoke('dashboard:get-summary'),
  },
  products: {
    list: (search?: string) => ipcRenderer.invoke('products:list', search),
    create: (input: {
      name: string
      sku?: string
      barcode?: string
      categoryId?: number | null
      purchasePrice?: number
      sellingPrice: number
      stockQuantity?: number
      minimumStock?: number
      unit?: string
    }) => ipcRenderer.invoke('products:create', input),
  },
})
