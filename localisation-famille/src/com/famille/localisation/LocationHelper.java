package com.famille.localisation;

import android.content.Context;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.BatteryManager;
import android.os.Bundle;
import android.os.Handler;
import org.json.JSONObject;

/** Obtient une position fraîche (GPS + réseau) sans dépendre des services Google. */
final class LocationHelper {
    interface Callback {
        void onResult(Location loc); // peut être null
    }

    private LocationHelper() {}

    static void acquire(Context ctx, Handler handler, long timeoutMs, Callback cb) {
        new Request(ctx.getApplicationContext(), handler, cb).start(timeoutMs);
    }

    private static final class Request implements LocationListener, Runnable {
        final Context ctx;
        final Handler handler;
        final Callback cb;
        final LocationManager lm;
        Location best;
        boolean done;

        Request(Context ctx, Handler handler, Callback cb) {
            this.ctx = ctx;
            this.handler = handler;
            this.cb = cb;
            this.lm = (LocationManager) ctx.getSystemService(Context.LOCATION_SERVICE);
        }

        void start(long timeoutMs) {
            int listening = 0;
            String[] providers = {LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER};
            for (String p : providers) {
                try {
                    if (lm.isProviderEnabled(p)) {
                        lm.requestLocationUpdates(p, 0, 0f, this, handler.getLooper());
                        listening++;
                    }
                } catch (SecurityException e) {
                    // permission manquante
                } catch (Exception e) {
                    // fournisseur absent
                }
            }
            if (listening == 0) {
                handler.post(this);
            } else {
                handler.postDelayed(this, timeoutMs);
            }
        }

        /** Délai écoulé. */
        @Override public void run() { finish(); }

        void finish() {
            if (done) return;
            done = true;
            handler.removeCallbacks(this);
            try { lm.removeUpdates(this); } catch (Exception ignored) {}
            Location result = best;
            if (result == null) result = lastKnown(lm);
            cb.onResult(result);
        }

        @Override public void onLocationChanged(Location l) {
            if (l == null || done) return;
            if (isBetter(l, best)) best = l;
            if (best.hasAccuracy() && best.getAccuracy() <= 25f) finish();
        }

        @Override public void onStatusChanged(String provider, int status, Bundle extras) {}
        @Override public void onProviderEnabled(String provider) {}
        @Override public void onProviderDisabled(String provider) {}
    }

    static Location lastKnown(LocationManager lm) {
        Location best = null;
        String[] providers = {LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER, LocationManager.PASSIVE_PROVIDER};
        for (String p : providers) {
            try {
                Location l = lm.getLastKnownLocation(p);
                if (l != null && (best == null || l.getTime() > best.getTime())) best = l;
            } catch (Exception ignored) {}
        }
        return best;
    }

    static boolean isBetter(Location l, Location cur) {
        if (cur == null) return true;
        float a = l.hasAccuracy() ? l.getAccuracy() : 9999f;
        float b = cur.hasAccuracy() ? cur.getAccuracy() : 9999f;
        return a <= b;
    }

    static int battery(Context ctx) {
        try {
            BatteryManager bm = (BatteryManager) ctx.getSystemService(Context.BATTERY_SERVICE);
            return bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY);
        } catch (Exception e) {
            return -1;
        }
    }

    /** Construit le message (en clair, avant chiffrement). */
    static JSONObject message(Context ctx, String type, Location l) {
        JSONObject o = new JSONObject();
        try {
            o.put("v", 1);
            o.put("type", type);
            o.put("id", Prefs.deviceId(ctx));
            o.put("name", Prefs.name(ctx));
            o.put("t", System.currentTimeMillis());
            if (l != null) {
                o.put("lat", l.getLatitude());
                o.put("lon", l.getLongitude());
                o.put("acc", l.hasAccuracy() ? Math.round(l.getAccuracy()) : 0);
                o.put("fix", l.getTime());
            }
            o.put("bat", battery(ctx));
        } catch (Exception ignored) {}
        return o;
    }
}
