package com.famille.localisation;

import android.content.Context;
import android.content.SharedPreferences;
import java.util.ArrayList;
import java.util.Collections;
import java.util.Comparator;
import java.util.Iterator;
import java.util.List;
import java.util.UUID;
import org.json.JSONObject;

/** Réglages et dernières positions connues, stockés sur le téléphone. */
final class Prefs {
    private Prefs() {}

    private static SharedPreferences sp(Context c) {
        return c.getApplicationContext().getSharedPreferences("famille", Context.MODE_PRIVATE);
    }

    static String name(Context c) { return sp(c).getString("name", ""); }
    static void setName(Context c, String v) { sp(c).edit().putString("name", v.trim()).apply(); }

    static String code(Context c) { return sp(c).getString("code", ""); }
    static void setCode(Context c, String v) {
        sp(c).edit().putString("code", Crypto.pretty(v)).putString("members", "{}").apply();
    }

    static int intervalMin(Context c) { return sp(c).getInt("interval", 15); }
    static void setIntervalMin(Context c, int v) { sp(c).edit().putInt("interval", v).apply(); }

    static boolean sharing(Context c) { return sp(c).getBoolean("sharing", false); }
    static void setSharing(Context c, boolean v) { sp(c).edit().putBoolean("sharing", v).apply(); }

    static long lastSent(Context c) { return sp(c).getLong("lastSent", 0); }
    static String lastError(Context c) { return sp(c).getString("lastError", ""); }
    static void setSendResult(Context c, boolean ok, String err) {
        SharedPreferences.Editor e = sp(c).edit();
        if (ok) e.putLong("lastSent", System.currentTimeMillis()).putString("lastError", "");
        else e.putString("lastError", err == null ? "erreur" : err);
        e.apply();
    }

    static synchronized String deviceId(Context c) {
        String id = sp(c).getString("deviceId", null);
        if (id == null) {
            id = UUID.randomUUID().toString().replace("-", "").substring(0, 16);
            sp(c).edit().putString("deviceId", id).apply();
        }
        return id;
    }

    static boolean isConfigured(Context c) {
        return name(c).length() > 0 && Crypto.isValid(code(c));
    }

    /** Intègre un message (déjà déchiffré) dans la liste des membres. Renvoie true si nouveau. */
    static synchronized boolean mergeMember(Context c, JSONObject msg) {
        try {
            String id = msg.optString("id", "");
            String type = msg.optString("type", "");
            if (id.isEmpty()) return false;
            if (!type.equals("loc") && !type.equals("sos") && !type.equals("bye")) return false;
            JSONObject all = new JSONObject(sp(c).getString("members", "{}"));
            JSONObject old = all.optJSONObject(id);
            long t = msg.optLong("t", 0);
            if (old != null && old.optLong("t", 0) >= t) return false;
            JSONObject m = old == null ? new JSONObject() : old;
            m.put("id", id);
            m.put("name", msg.optString("name", m.optString("name", "?")));
            m.put("t", t);
            if (type.equals("bye")) {
                m.put("stopped", true);
            } else {
                m.put("stopped", false);
                m.put("lat", msg.optDouble("lat"));
                m.put("lon", msg.optDouble("lon"));
                m.put("acc", msg.optDouble("acc", 0));
                m.put("fix", msg.optLong("fix", t));
                m.put("bat", msg.optInt("bat", -1));
                m.put("sos", type.equals("sos"));
            }
            all.put(id, m);
            sp(c).edit().putString("members", all.toString()).apply();
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    static synchronized void removeMember(Context c, String id) {
        try {
            JSONObject all = new JSONObject(sp(c).getString("members", "{}"));
            all.remove(id);
            sp(c).edit().putString("members", all.toString()).apply();
        } catch (Exception ignored) {}
    }

    static synchronized List<JSONObject> members(Context c) {
        List<JSONObject> list = new ArrayList<JSONObject>();
        try {
            JSONObject all = new JSONObject(sp(c).getString("members", "{}"));
            Iterator<String> it = all.keys();
            while (it.hasNext()) {
                JSONObject m = all.optJSONObject(it.next());
                if (m != null && m.has("lat")) list.add(m);
            }
        } catch (Exception ignored) {}
        Collections.sort(list, new Comparator<JSONObject>() {
            @Override public int compare(JSONObject a, JSONObject b) {
                return a.optString("name").compareToIgnoreCase(b.optString("name"));
            }
        });
        return list;
    }
}
