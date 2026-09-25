package com.theomeds.customerportal;

import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import android.util.Log;
import androidx.core.content.FileProvider;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileOutputStream;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;

/**
 * Downloads the new APK inside the app and hands it to Android's package
 * installer. Android always shows its own "Install" confirmation; no app can
 * skip it. Only https is accepted, since an APK fetched over plain http
 * could be swapped in transit.
 *
 * Capacitor keeps same-host URLs inside the WebView, and the WebView has no
 * download handler, so this can't be done from the web side.
 *
 * The download is ~25 MB and phones are often on slow or unstable
 * connections, so a dropped connection is retried and resumed from where it
 * stopped (the server supports Range requests) instead of starting over.
 */
@CapacitorPlugin(name = "AppUpdate")
public class AppUpdatePlugin extends Plugin {
    private static final String TAG = "AppUpdate";
    private static final String APK_MIME = "application/vnd.android.package-archive";
    // Waits 2s, 4s, 8s, then 10s between tries: rides out roughly 24 seconds
    // without a connection (a Wi-Fi handoff, a lift) before giving up.
    private static final int MAX_ATTEMPTS = 5;

    private volatile boolean downloading = false;

    /** The server answered, but not with the file (busy, restarting, throttled...). */
    private static class HttpStatusException extends IOException {
        final int status;

        HttpStatusException(int status) {
            super("HTTP " + status);
            this.status = status;
        }
    }

    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        String url = call.getString("url");
        String expectedSha256 = call.getString("sha256");
        if (url == null || !url.startsWith("https://")) {
            call.reject("An https url is required");
            return;
        }
        if (expectedSha256 == null || !expectedSha256.matches("(?i)[0-9a-f]{64}")) {
            call.reject("A valid SHA-256 checksum is required", "INVALID_CHECKSUM");
            return;
        }

        // Android 8+ makes "install unknown apps" a per-app toggle the user
        // must switch on. Send them to that screen; they tap Update again.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                && !getContext().getPackageManager().canRequestPackageInstalls()) {
            Intent settings = new Intent(
                Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                Uri.parse("package:" + getContext().getPackageName())
            );
            settings.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(settings);
            call.reject("Allow installs from this app first", "INSTALL_PERMISSION_REQUIRED");
            return;
        }

        if (downloading) {
            call.reject("A download is already running", "ALREADY_DOWNLOADING");
            return;
        }
        downloading = true;

        new Thread(() -> {
            File directory = new File(getContext().getCacheDir(), "updates");
            File partial = new File(directory, "customer-portal.apk.part");
            File apk = new File(directory, "customer-portal.apk");

            try {
                directory.mkdirs();
                // A leftover from an earlier run could be a different build.
                partial.delete();
                apk.delete();

                download(url, partial);

                if (!sha256(partial).equalsIgnoreCase(expectedSha256)) {
                    throw new SecurityException("Downloaded APK checksum did not match");
                }

                if (!partial.renameTo(apk)) {
                    throw new IOException("Could not save the update");
                }

                Uri uri = FileProvider.getUriForFile(
                    getContext(),
                    getContext().getPackageName() + ".fileprovider",
                    apk
                );
                Intent install = new Intent(Intent.ACTION_VIEW);
                install.setDataAndType(uri, APK_MIME);
                install.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
                getContext().startActivity(install);

                call.resolve();
            } catch (Exception e) {
                partial.delete();
                Log.e(TAG, "Update download failed", e);

                // Tells the web side why, so it can say something useful.
                JSObject data = new JSObject();
                data.put("reason", reasonFor(e));
                if (e instanceof HttpStatusException) {
                    data.put("status", ((HttpStatusException) e).status);
                }
                call.reject("Download failed", "DOWNLOAD_FAILED", e, data);
            } finally {
                downloading = false;
            }
        }).start();
    }

    /** Fills `partial` with the whole file, retrying and resuming on failure. */
    private void download(String url, File partial) throws Exception {
        long total = -1;
        String validator = null;
        Exception last = null;

        for (int attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
            if (attempt > 1) Thread.sleep(Math.min(2000L << (attempt - 2), 10000L));

            long offset = partial.exists() ? partial.length() : 0;
            HttpURLConnection connection = null;

            try {
                connection = (HttpURLConnection) new URL(url).openConnection();
                connection.setConnectTimeout(15000);
                connection.setReadTimeout(60000);
                // Otherwise a gzip'd response reports its compressed length,
                // which would make the completeness check below misfire.
                connection.setRequestProperty("Accept-Encoding", "identity");
                if (offset > 0) {
                    connection.setRequestProperty("Range", "bytes=" + offset + "-");
                    // If the file changed on the server meanwhile, start over.
                    if (validator != null) connection.setRequestProperty("If-Range", validator);
                }

                int status = connection.getResponseCode();
                if (status == HttpURLConnection.HTTP_OK) {
                    offset = 0; // A full response: the server ignored the Range.
                    total = connection.getContentLengthLong();
                    String etag = connection.getHeaderField("ETag");
                    validator = etag != null ? etag : connection.getHeaderField("Last-Modified");
                } else if (status == HttpURLConnection.HTTP_PARTIAL) {
                    total = totalFromContentRange(connection.getHeaderField("Content-Range"), total);
                } else {
                    throw new HttpStatusException(status);
                }

                long received = offset;
                int lastPercent = -1;
                try (InputStream in = connection.getInputStream();
                     FileOutputStream out = new FileOutputStream(partial, offset > 0)) {
                    byte[] buffer = new byte[16384];
                    int read;
                    while ((read = in.read(buffer)) != -1) {
                        out.write(buffer, 0, read);
                        received += read;
                        if (total > 0) {
                            int percent = (int) (received * 100 / total);
                            if (percent != lastPercent) {
                                lastPercent = percent;
                                JSObject progress = new JSObject();
                                progress.put("percent", percent);
                                notifyListeners("downloadProgress", progress);
                            }
                        }
                    }
                }

                if (total > 0 && received != total) {
                    throw new IOException("Incomplete download: " + received + " of " + total + " bytes");
                }
                return;
            } catch (HttpStatusException e) {
                last = e;
                // A missing file or refused request won't fix itself; a busy
                // or restarting server (5xx) or a throttle (429) usually does.
                boolean retryable = e.status >= 500 || e.status == 429 || e.status == 408;
                if (!retryable) throw e;
            } catch (IOException e) {
                last = e;
            } finally {
                if (connection != null) connection.disconnect();
            }

            Log.w(TAG, "Download attempt " + attempt + " of " + MAX_ATTEMPTS + " failed", last);
        }

        throw last;
    }

    private static long totalFromContentRange(String contentRange, long fallback) {
        // "bytes 1000-2999/3000"
        if (contentRange != null) {
            int slash = contentRange.lastIndexOf('/');
            if (slash >= 0) {
                try {
                    return Long.parseLong(contentRange.substring(slash + 1).trim());
                } catch (NumberFormatException ignored) {
                    // Falls through to the size seen on the first response.
                }
            }
        }
        return fallback;
    }

    private static String reasonFor(Exception e) {
        if (e instanceof HttpStatusException) return "server";
        if (e instanceof SecurityException) return "integrity";

        String message = e.getMessage() == null ? "" : e.getMessage().toLowerCase();
        if (message.contains("enospc") || message.contains("no space")) return "storage";

        return "network";
    }

    private static String sha256(File file) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        try (InputStream in = new FileInputStream(file)) {
            byte[] buffer = new byte[16384];
            int read;
            while ((read = in.read(buffer)) != -1) {
                digest.update(buffer, 0, read);
            }
        }

        StringBuilder value = new StringBuilder(64);
        for (byte part : digest.digest()) {
            value.append(String.format("%02x", part & 0xff));
        }
        return value.toString();
    }
}
