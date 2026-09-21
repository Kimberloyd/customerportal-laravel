package com.theomeds.customerportal;

import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import androidx.core.content.FileProvider;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * Downloads the new APK inside the app and hands it to Android's package
 * installer. Android always shows its own "Install" confirmation; no app can
 * skip it. Only https is accepted, since an APK fetched over plain http
 * could be swapped in transit.
 *
 * Capacitor keeps same-host URLs inside the WebView, and the WebView has no
 * download handler, so this can't be done from the web side.
 */
@CapacitorPlugin(name = "AppUpdate")
public class AppUpdatePlugin extends Plugin {
    private static final String APK_MIME = "application/vnd.android.package-archive";

    private volatile boolean downloading = false;

    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        String url = call.getString("url");
        if (url == null || !url.startsWith("https://")) {
            call.reject("An https url is required");
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
            HttpURLConnection connection = null;

            try {
                directory.mkdirs();
                connection = (HttpURLConnection) new URL(url).openConnection();
                connection.setConnectTimeout(15000);
                connection.setReadTimeout(30000);
                // Otherwise a gzip'd response reports its compressed length,
                // which would make the completeness check below misfire.
                connection.setRequestProperty("Accept-Encoding", "identity");

                if (connection.getResponseCode() != HttpURLConnection.HTTP_OK) {
                    throw new IOException("HTTP " + connection.getResponseCode());
                }

                long total = connection.getContentLengthLong();
                try (InputStream in = connection.getInputStream();
                     FileOutputStream out = new FileOutputStream(partial)) {
                    byte[] buffer = new byte[16384];
                    long received = 0;
                    int lastPercent = -1;
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
                    if (total > 0 && received != total) {
                        throw new IOException("Incomplete download");
                    }
                }

                if (apk.exists()) apk.delete();
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
                call.reject("Download failed", "DOWNLOAD_FAILED", e);
            } finally {
                if (connection != null) connection.disconnect();
                downloading = false;
            }
        }).start();
    }
}
