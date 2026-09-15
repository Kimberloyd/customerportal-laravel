package com.theomeds.customerportal;

import android.graphics.Color;
import android.os.Bundle;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Match the status bar to the app's white header instead of the
        // platform-default tint, so it reads as one continuous surface.
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().getDecorView().setBackgroundColor(Color.WHITE);

        WindowInsetsControllerCompat controller =
            WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        controller.setAppearanceLightStatusBars(true);
    }
}
