const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('cmoon', {
    getConfig: () => ipcRenderer.invoke('config:get'),
    saveConfig: (data) => ipcRenderer.invoke('config:save', data),
    activate: (data) => ipcRenderer.invoke('activate', data),
    licenseStatus: () => ipcRenderer.invoke('license:status'),
    refreshLicense: () => ipcRenderer.invoke('license:refresh'),
    getCatalog: () => ipcRenderer.invoke('catalog:get'),
    syncCatalog: () => ipcRenderer.invoke('catalog:sync'),
    pendingCount: () => ipcRenderer.invoke('sales:pending-count'),
    submitSale: (venta) => ipcRenderer.invoke('sales:submit', venta),
    syncSales: () => ipcRenderer.invoke('sales:sync'),
    openPos: () => ipcRenderer.invoke('app:open-pos'),
});
