package com.theomeds.customerportal;

import android.animation.ValueAnimator;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.graphics.drawable.Drawable;
import android.view.View;
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
 *
 * Animates the change (ValueAnimator.ofArgb, same duration the web side
 * crossfades its own background-color via the .theme-transitioning class)
 * instead of snapping instantly. A native bridge round-trip has enough
 * latency that an instant snap tends to land only after the web CSS
 * transition has already finished, reading as a late, disjointed status
 * bar update instead of one that moved together with the rest of the
 * screen. Animating both sides over the same window keeps them looking
 * synchronized even though they start a few milliseconds apart.
 */
@CapacitorPlugin(name = "ThemeStatusBar")
public class ThemeStatusBarPlugin extends Plugin {
    private ValueAnimator currentAnimator;

    @PluginMethod
    public void setBackgroundColor(PluginCall call) {
        String colorHex = call.getString("color");
        if (colorHex == null) {
            call.reject("color is required");
            return;
        }
        int targetColor = Color.parseColor(colorHex);
        int durationMs = call.getInt("durationMs", 0);

        getActivity().runOnUiThread(() -> {
            View decorView = getActivity().getWindow().getDecorView();

            if (currentAnimator != null) {
                currentAnimator.cancel();
            }

            int startColor = currentBackgroundColor(decorView, targetColor);

            if (durationMs <= 0 || startColor == targetColor) {
                decorView.setBackgroundColor(targetColor);
                call.resolve();
                return;
            }

            currentAnimator = ValueAnimator.ofArgb(startColor, targetColor);
            currentAnimator.setDuration(durationMs);
            currentAnimator.addUpdateListener(animator ->
                decorView.setBackgroundColor((int) animator.getAnimatedValue())
            );
            currentAnimator.start();
            call.resolve();
        });
    }

    // The decorView's background is always a plain ColorDrawable here --
    // this class is the only thing that ever sets it -- so its current
    // color is the real animation start point when available.
    private int currentBackgroundColor(View decorView, int fallback) {
        Drawable background = decorView.getBackground();
        if (background instanceof ColorDrawable) {
            return ((ColorDrawable) background).getColor();
        }
        return fallback;
    }
}
