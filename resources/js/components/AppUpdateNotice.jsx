import { BottomSheet } from '@/components/motion/bottom-sheet';
import { Button } from '@/components/ui/button';
import { useAppUpdate } from '@/hooks/useAppUpdate';
import { Capacitor, registerPlugin } from '@capacitor/core';
import { useState } from 'react';

const AppUpdate = registerPlugin('AppUpdate');

const ALLOW_INSTALLS_MESSAGE =
    'Turn on "Allow from this source" for this app in the screen that just opened, then come back and tap Update now.';
const DOWNLOAD_FAILED_MESSAGE = "The update couldn't be downloaded. Check your connection and try again.";

export default function AppUpdateNotice() {
    const { update, open, dismiss } = useAppUpdate();
    const [downloading, setDownloading] = useState(false);
    const [percent, setPercent] = useState(null);
    const [message, setMessage] = useState('');
    const [copied, setCopied] = useState(false);

    if (!update) return null;

    // Builds before 1.1 have no AppUpdate plugin and their WebView can't
    // download a file itself, so they get the link to open manually.
    const canInstallInApp = Capacitor.isPluginAvailable('AppUpdate');

    const installUpdate = async () => {
        setMessage('');
        setPercent(null);
        setDownloading(true);

        const progress = await AppUpdate.addListener('downloadProgress', (event) => setPercent(event.percent));
        try {
            await AppUpdate.downloadAndInstall({ url: update.downloadUrl });
        } catch (error) {
            setMessage(error?.code === 'INSTALL_PERMISSION_REQUIRED' ? ALLOW_INSTALLS_MESSAGE : DOWNLOAD_FAILED_MESSAGE);
        } finally {
            progress.remove();
            setDownloading(false);
        }
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(update.downloadUrl);
            setCopied(true);
        } catch {
            // Clipboard blocked: the link is shown as selectable text as well.
        }
    };

    return (
        <BottomSheet
            open={open}
            // A required update can't be swiped, tapped, or Escaped away, and
            // neither can any update while its download is running.
            onOpenChange={(next) => {
                if (!next && !downloading) dismiss();
            }}
            snapPoints={['auto']}
            title={update.required ? 'Update required' : 'Update available'}
            description={
                update.required
                    ? `This version of the app is no longer supported. Install version ${update.versionName} to keep using it.`
                    : `Version ${update.versionName} of the app is ready to install.`
            }
            footer={
                <>
                    {!update.required && (
                        <Button
                            type="button"
                            variant="tertiary"
                            className="h-10 rounded-md px-5 text-sm"
                            onClick={dismiss}
                            disabled={downloading}
                        >
                            Later
                        </Button>
                    )}
                    {canInstallInApp ? (
                        <Button
                            type="button"
                            variant="primary"
                            className="h-10 rounded-md px-5 text-sm"
                            onClick={installUpdate}
                            loading={downloading}
                        >
                            {downloading ? (percent === null ? 'Downloading' : `Downloading ${percent}%`) : 'Update now'}
                        </Button>
                    ) : (
                        <Button type="button" variant="primary" className="h-10 rounded-md px-5 text-sm" onClick={copyLink}>
                            {copied ? 'Link copied' : 'Copy download link'}
                        </Button>
                    )}
                </>
            }
        >
            <div className="space-y-3 text-sm text-muted-foreground">
                {canInstallInApp ? (
                    <>
                        <p>Your account and orders stay as they are. Android will ask you to confirm the install.</p>
                        {downloading && percent !== null && (
                            <div
                                role="progressbar"
                                aria-label="Update download"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={percent}
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div className="h-full rounded-full bg-primary transition-[width] duration-200" style={{ width: `${percent}%` }} />
                            </div>
                        )}
                        {message && <p className="text-destructive">{message}</p>}
                    </>
                ) : (
                    <>
                        <p>
                            Paste this link into your phone&rsquo;s browser, then open the downloaded file to install. If
                            Android asks, allow your browser to install apps. Your account and orders stay as they are.
                        </p>
                        <p className="select-all break-all rounded-md border border-border bg-muted/40 px-3 py-2 text-foreground">
                            {update.downloadUrl}
                        </p>
                    </>
                )}
            </div>
        </BottomSheet>
    );
}
