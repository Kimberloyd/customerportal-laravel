package com.theomeds.customerportal;

import android.os.Build;
import android.os.Bundle;
import android.view.WindowManager;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(ThemeStatusBarPlugin.class);
        registerPlugin(AppUpdatePlugin.class);
        registerPlugin(DeviceSecurityPlugin.class);
        super.onCreate(savedInstanceState);

        // The status bar's live color/icon-style is owned by the JS theme
        // system (resources/js/lib/theme-context.jsx, via @capacitor/status-bar)
        // so it can follow the user's light/dark choice. A decorView/status-bar
        // color pinned here at startup would sit underneath the plugin's
        // Window.setStatusBarColor() call in edge-to-edge mode and silently
        // win, which is exactly what made the bar stay white -- with white
        // icons on it -- no matter what the app's theme was.

        // Tap-jacking: another app drawing a transparent overlay over ours
        // can trick a tap into landing on it. Set here rather than from JS so
        // it applies from the first frame and page scripts can't switch it off.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            getWindow().setHideOverlayWindows(true);
        } else {
            getBridge().getWebView().setFilterTouchesWhenObscured(true);
        }

        // Keep order and customer data out of the recent-apps thumbnail. Android
        // 13+ has a switch for exactly that. FLAG_SECURE would work on every
        // version but also blocks screenshots and screen recording, which
        // customers use to share orders, so older versions only set it while
        // the app is in the background (see onPause).
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            setRecentsScreenshotEnabled(false);
        }
    }

    @Override
    public void onPause() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            getWindow().addFlags(WindowManager.LayoutParams.FLAG_SECURE);
        }
        super.onPause();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            getWindow().clearFlags(WindowManager.LayoutParams.FLAG_SECURE);
        }
    }
}
