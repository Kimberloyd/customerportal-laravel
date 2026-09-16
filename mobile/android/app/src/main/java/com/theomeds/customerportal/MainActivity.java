package com.theomeds.customerportal;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(ThemeStatusBarPlugin.class);
        super.onCreate(savedInstanceState);

        // The status bar's live color/icon-style is owned by the JS theme
        // system (resources/js/lib/theme-context.jsx, via @capacitor/status-bar)
        // so it can follow the user's light/dark choice. A decorView/status-bar
        // color pinned here at startup would sit underneath the plugin's
        // Window.setStatusBarColor() call in edge-to-edge mode and silently
        // win, which is exactly what made the bar stay white -- with white
        // icons on it -- no matter what the app's theme was.
    }
}
