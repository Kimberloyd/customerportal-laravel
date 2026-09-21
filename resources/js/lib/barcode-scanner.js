import { Capacitor } from '@capacitor/core';

// Older installs of the Android app don't include the scanner plugin (the
// web deploy reaches them first), and browsers have no camera flow here.
export function canScanBarcodes() {
    return Capacitor.isNativePlatform() && Capacitor.isPluginAvailable('CapacitorBarcodeScanner');
}

// Loaded on demand: the package pulls in a web scanner library that only a
// tap on the scan button ever needs.
export async function scanBarcode() {
    const { CapacitorBarcodeScanner, CapacitorBarcodeScannerTypeHint, CapacitorBarcodeScannerAndroidScanningLibrary } =
        await import('@capacitor/barcode-scanner');

    const result = await CapacitorBarcodeScanner.scanBarcode({
        hint: CapacitorBarcodeScannerTypeHint.ALL,
        scanInstructions: 'Point the camera at the product barcode',
        android: { scanningLibrary: CapacitorBarcodeScannerAndroidScanningLibrary.MLKIT },
    });

    return String(result?.ScanResult ?? '').trim();
}

export function isScanCancelled(error) {
    return /cancel/i.test(error?.message ?? '');
}
