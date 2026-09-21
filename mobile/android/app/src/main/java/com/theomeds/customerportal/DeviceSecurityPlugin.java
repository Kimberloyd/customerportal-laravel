package com.theomeds.customerportal;

import android.os.Build;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;

/**
 * Best-effort "is this phone rooted?" for an informational warning, not a
 * security control: a rooted phone can hide these traces, so a clean result
 * proves nothing. Only signals that stock phones don't show are used. The
 * community device-security-detect plugin also flags any phone missing
 * /etc/security/otacerts.zip, which many stock devices lack, so it would
 * warn ordinary customers falsely.
 */
@CapacitorPlugin(name = "DeviceSecurity")
public class DeviceSecurityPlugin extends Plugin {
    private static final String[] SU_PATHS = {
        "/system/app/Superuser.apk",
        "/sbin/su",
        "/system/bin/su",
        "/system/xbin/su",
        "/data/local/xbin/su",
        "/data/local/bin/su",
        "/system/sd/xbin/su",
        "/system/bin/failsafe/su",
        "/data/local/su",
        "/su/bin/su",
    };

    @PluginMethod
    public void isRooted(PluginCall call) {
        JSObject result = new JSObject();
        result.put("rooted", hasTestKeys() || hasSuBinary());
        call.resolve(result);
    }

    // Set on custom ROMs and emulator images, never on manufacturer builds.
    private boolean hasTestKeys() {
        return Build.TAGS != null && Build.TAGS.contains("test-keys");
    }

    private boolean hasSuBinary() {
        for (String path : SU_PATHS) {
            if (new File(path).exists()) return true;
        }
        return false;
    }
}
