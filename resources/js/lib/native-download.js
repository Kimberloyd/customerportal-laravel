import { Capacitor } from '@capacitor/core';
import { Directory, Encoding, Filesystem } from '@capacitor/filesystem';
import { Share } from '@capacitor/share';

const EXPORT_DIRECTORY = 'exports';

// Older installs of the Android app don't include these plugins, and the
// web deploy reaches them first -- callers must fall back to a normal
// browser download when this is false.
export function canShareDownloads() {
    return (
        Capacitor.isNativePlatform() &&
        Capacitor.isPluginAvailable('Filesystem') &&
        Capacitor.isPluginAvailable('Share')
    );
}

function fileNameFrom(response, fallback) {
    const header = response.headers.get('Content-Disposition') ?? '';
    const match = /filename="?([^";]+)"?/i.exec(header);

    return (match?.[1] ?? fallback).replace(/[^\w.-]/g, '_');
}

/**
 * The Android WebView can't save a downloaded file, so fetch it (with the
 * session cookie), keep a copy in the app's private cache, and hand it to
 * Android's share sheet to save or send. Text files only.
 */
export async function shareTextDownload(url, fallbackName) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`Download failed with HTTP ${response.status}`);

    const text = await response.text();
    const name = fileNameFrom(response, fallbackName);

    // The share target may still be reading the file after the sheet
    // closes, so it can't be deleted right away. Clearing the previous
    // export here keeps personal data from lingering past the next one.
    await Filesystem.rmdir({ path: EXPORT_DIRECTORY, directory: Directory.Cache, recursive: true }).catch(() => {});
    const { uri } = await Filesystem.writeFile({
        path: `${EXPORT_DIRECTORY}/${name}`,
        data: text,
        directory: Directory.Cache,
        encoding: Encoding.UTF8,
        recursive: true,
    });

    await Share.share({ title: name, files: [uri], dialogTitle: 'Save or send' });
}

export function isShareCancelled(error) {
    return /cancel/i.test(error?.message ?? '');
}
