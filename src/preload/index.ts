import { contextBridge } from 'electron'

contextBridge.exposeInMainWorld('desktop', {
  appName: 'Abyte Simple POS',
  platform: process.platform,
  version: '0.1.0',
})
