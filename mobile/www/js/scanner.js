function getScanner() {
    return window.Capacitor?.Plugins?.BarcodeScanner || null;
}

let scanning = false;
let onBarcodeCallback = null;
let listenerHandle = null;

async function ensureReady() {
    const BarcodeScanner = getScanner();
    if (! BarcodeScanner) {
        throw new Error('Escaneo por cámara solo funciona en la app Android instalada (APK).');
    }

    const { supported } = await BarcodeScanner.isSupported();
    if (! supported) throw new Error('Este dispositivo no soporta escaneo por cámara.');

    if (BarcodeScanner.isGoogleBarcodeScannerModuleAvailable) {
        const mod = await BarcodeScanner.isGoogleBarcodeScannerModuleAvailable();
        if (! mod.available && BarcodeScanner.installGoogleBarcodeScannerModule) {
            await BarcodeScanner.installGoogleBarcodeScannerModule();
        }
    }

    return BarcodeScanner;
}

async function requestCameraPermission() {
    const BarcodeScanner = getScanner();
    if (! BarcodeScanner?.checkPermissions) return true;
    const status = await BarcodeScanner.checkPermissions();
    if (status.camera === 'granted') return true;
    const req = await BarcodeScanner.requestPermissions();
    return req.camera === 'granted';
}

/** Escaneo nativo de Google (pantalla completa) — un código por vez */
async function scanOne() {
    const BarcodeScanner = await ensureReady();
    const ok = await requestCameraPermission();
    if (! ok) throw new Error('Permiso de cámara denegado.');

    const { barcodes } = await BarcodeScanner.scan({});
    const code = barcodes?.[0]?.rawValue || barcodes?.[0]?.displayValue || '';
    if (! code) throw new Error('No se detectó ningún código.');
    return code.trim();
}

/** Modo continuo: cámara detrás del WebView (varios productos seguidos) */
async function startScanMode(onBarcode) {
    const BarcodeScanner = await ensureReady();
    const ok = await requestCameraPermission();
    if (! ok) throw new Error('Permiso de cámara denegado.');

    onBarcodeCallback = onBarcode;
    scanning = true;
    document.body.classList.add('barcode-scanner-active', 'scan-active');

    listenerHandle = await BarcodeScanner.addListener('barcodesScanned', async (result) => {
        if (! scanning || ! onBarcodeCallback) return;
        for (const code of result.barcodes || []) {
            const raw = (code.rawValue || code.displayValue || '').trim();
            if (raw) await onBarcodeCallback(raw);
        }
    });

    await BarcodeScanner.startScan({});
}

async function stopScanMode() {
    const BarcodeScanner = getScanner();
    scanning = false;
    onBarcodeCallback = null;
    document.body.classList.remove('barcode-scanner-active', 'scan-active');

    if (listenerHandle?.remove) await listenerHandle.remove().catch(() => {});
    listenerHandle = null;

    if (BarcodeScanner?.stopScan) await BarcodeScanner.stopScan().catch(() => {});
}

window.cmoonScanner = { scanOne, startScanMode, stopScanMode, isScanning: () => scanning };
