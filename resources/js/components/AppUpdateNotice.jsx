import { BottomSheet } from '@/components/motion/bottom-sheet';
import { Button } from '@/components/ui/button';
import { useAppUpdate } from '@/hooks/useAppUpdate';
import { Capacitor } from '@capacitor/core';
import { useState } from 'react';

export default function AppUpdateNotice() {
    const { update, open, dismiss } = useAppUpdate();
    const [copied, setCopied] = useState(false);

    if (!update) return null;

    // Builds before 1.1 don't have the AppUpdate plugin, and their WebView
    // can't start a download itself, so they get the link to open manually.
    const canOpenBrowser = Boolean(Capacitor.Plugins.AppUpdate?.openExternal);

    const download = () => {
        Capacitor.Plugins.AppUpdate.openExternal({ url: update.downloadUrl }).catch(() => {});
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
            // A required update can't be swiped, tapped, or Escaped away.
            onOpenChange={(next) => {
                if (!next) dismiss();
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
                        <Button type="button" variant="tertiary" className="h-10 rounded-md px-5 text-sm" onClick={dismiss}>
                            Later
                        </Button>
                    )}
                    {canOpenBrowser ? (
                        <Button type="button" variant="primary" className="h-10 rounded-md px-5 text-sm" onClick={download}>
                            Download update
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
                {canOpenBrowser ? (
                    <p>
                        Your account and orders stay as they are. After the download, open the file to install. If Android
                        asks, allow your browser to install apps.
                    </p>
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
