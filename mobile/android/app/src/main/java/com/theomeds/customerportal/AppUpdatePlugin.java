package com.theomeds.customerportal;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.net.Uri;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Hands the APK download to the phone's own browser. Capacitor keeps every
 * same-host URL inside the WebView and the WebView has no download handler,
 * so a plain link to this site's /app/download would silently do nothing.
 * Only https is accepted: an APK fetched over plain http could be swapped
 * in transit.
 */
@CapacitorPlugin(name = "AppUpdate")
public class AppUpdatePlugin extends Plugin {
    @PluginMethod
    public void openExternal(PluginCall call) {
        String url = call.getString("url");
        if (url == null) {
            call.reject("url is required");
            return;
        }

        Uri uri = Uri.parse(url);
        if (!"https".equals(uri.getScheme())) {
            call.reject("Only https URLs can be opened");
            return;
        }

        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, uri);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(intent);
            call.resolve();
        } catch (ActivityNotFoundException e) {
            call.reject("No browser available to open the download");
        }
    }
}
