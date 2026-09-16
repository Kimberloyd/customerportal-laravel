package com.theomeds.customerportal;

import android.graphics.Color;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Paints the decorView background directly -- the layer that's actually
 * visible behind the status bar under Capacitor 8's edge-to-edge window
 * setup. @capacitor/status-bar's setBackgroundColor() calls the deprecated
 * Window.setStatusBarColor(), which has no visible effect here, and
 * Capacitor core's own built-in SystemBars plugin repaints the decorView
 * back to the static android:windowBackground theme attribute on load and
 * on every config change -- so neither can be used to follow the app's
 * live (JS-driven) light/dark toggle. This plugin exists only to give that
 * toggle a direct, reliable way to repaint the real background.
 */
@CapacitorPlugin(name = "ThemeStatusBar")
public class ThemeStatusBarPlugin extends Plugin {
    @PluginMethod
    public void setBackgroundColor(PluginCall call) {
        String colorHex = call.getString("color");
        if (colorHex == null) {
            call.reject("color is required");
            return;
        }
        getActivity().runOnUiThread(() -> {
            getActivity().getWindow().getDecorView().setBackgroundColor(Color.parseColor(colorHex));
        });
        call.resolve();
    }
}
