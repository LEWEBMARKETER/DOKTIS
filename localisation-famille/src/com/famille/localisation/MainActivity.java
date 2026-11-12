package com.famille.localisation;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.ProgressDialog;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.location.Location;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import android.provider.Settings;
import android.view.Gravity;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import org.json.JSONArray;
import org.json.JSONObject;

public class MainActivity extends Activity {
    private static final int REQ_LOC = 1;
    private static final int REQ_BG = 2;
    private static final int REQ_SOS = 3;
    private static final long STALE_MS = 60 * 60_000L; // position "ancienne" après 1 h

    private final Handler ui = new Handler(Looper.getMainLooper());
    private final ExecutorService net = Executors.newSingleThreadExecutor();
    private WebView map;
    private boolean mapReady;
    private TextView statusTitle, statusDetail;
    private Button shareBtn;
    private LinearLayout list;
    private boolean resumed;
    private boolean openedSettingsOnce;

    @Override protected void onCreate(Bundle b) {
        super.onCreate(b);
        LocationService.createChannels(this);
        int pad = Ui.dp(this, 10);

        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(0xFFFFFFFF);

        // --- Bandeau d'état du partage ---
        LinearLayout status = new LinearLayout(this);
        status.setOrientation(LinearLayout.HORIZONTAL);
        status.setGravity(Gravity.CENTER_VERTICAL);
        status.setPadding(pad + pad / 2, pad, pad, pad);
        status.setBackgroundColor(Ui.LIGHT_BG);
        LinearLayout texts = new LinearLayout(this);
        texts.setOrientation(LinearLayout.VERTICAL);
        statusTitle = Ui.text(this, "", 15, Ui.DARK, true);
        statusDetail = Ui.text(this, "", 12, Ui.GREY, false);
        texts.addView(statusTitle);
        texts.addView(statusDetail);
        status.addView(texts, Ui.weight(1, LinearLayout.LayoutParams.WRAP_CONTENT));
        shareBtn = Ui.button(this, "", Ui.BLUE, 0xFFFFFFFF);
        shareBtn.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { toggleSharing(); }
        });
        status.addView(shareBtn);
        root.addView(status);

        // --- Carte ---
        map = new WebView(this);
        WebSettings ws = map.getSettings();
        ws.setJavaScriptEnabled(true);
        ws.setDomStorageEnabled(true);
        map.setWebChromeClient(new WebChromeClient());
        map.setWebViewClient(new WebViewClient() {
            @Override public void onPageFinished(WebView view, String url) {
                mapReady = true;
                render(true);
            }
        });
        map.loadUrl("file:///android_asset/map.html");
        root.addView(map, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1.3f));

        // --- Boutons ---
        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);
        actions.setPadding(pad, pad, pad, pad / 2);
        Button refresh = Ui.button(this, "⟳ Actualiser", Ui.LIGHT_BG, Ui.DARK);
        Button ping = Ui.button(this, "📍 Localiser", Ui.BLUE, 0xFFFFFFFF);
        Button sos = Ui.button(this, "SOS", Ui.RED, 0xFFFFFFFF);
        refresh.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { poll(true); }
        });
        ping.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { askEveryone(); }
        });
        sos.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { confirmSos(); }
        });
        LinearLayout.LayoutParams lp1 = Ui.weight(1, LinearLayout.LayoutParams.WRAP_CONTENT);
        LinearLayout.LayoutParams lp2 = Ui.weight(1.2f, LinearLayout.LayoutParams.WRAP_CONTENT);
        lp2.leftMargin = pad;
        LinearLayout.LayoutParams lp3 = Ui.weight(0.7f, LinearLayout.LayoutParams.WRAP_CONTENT);
        lp3.leftMargin = pad;
        actions.addView(refresh, lp1);
        actions.addView(ping, lp2);
        actions.addView(sos, lp3);
        root.addView(actions);

        // --- Liste des proches ---
        ScrollView sv = new ScrollView(this);
        list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        list.setPadding(pad, 0, pad, pad);
        sv.addView(list);
        root.addView(sv, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));

        setContentView(root);
    }

    @Override protected void onResume() {
        super.onResume();
        resumed = true;
        if (!Prefs.isConfigured(this)) {
            if (!openedSettingsOnce) {
                openedSettingsOnce = true;
                startActivity(new Intent(this, SettingsActivity.class));
            }
        } else {
            poll(false);
        }
        render(false);
        ui.removeCallbacks(autoRefresh);
        ui.postDelayed(autoRefresh, 30_000L);
    }

    @Override protected void onPause() {
        super.onPause();
        resumed = false;
        ui.removeCallbacks(autoRefresh);
    }

    @Override protected void onDestroy() {
        net.shutdownNow();
        super.onDestroy();
    }

    private final Runnable autoRefresh = new Runnable() {
        private int n;
        @Override public void run() {
            if (!resumed) return;
            if (++n % 2 == 0 && Prefs.isConfigured(MainActivity.this)) poll(false); // toutes les 60 s
            else render(false);
            ui.postDelayed(this, 30_000L);
        }
    };

    // ---------------------------------------------------------------- Menu

    @Override public boolean onCreateOptionsMenu(Menu menu) {
        menu.add(0, 1, 0, "Inviter un proche").setShowAsAction(MenuItem.SHOW_AS_ACTION_NEVER);
        menu.add(0, 2, 1, "Réglages").setShowAsAction(MenuItem.SHOW_AS_ACTION_NEVER);
        return true;
    }

    @Override public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == 1) {
            SettingsActivity.shareCode(this, Prefs.code(this));
            return true;
        }
        if (item.getItemId() == 2) {
            startActivity(new Intent(this, SettingsActivity.class));
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    // ---------------------------------------------------------------- Affichage

    private void render(boolean fit) {
        boolean sharing = Prefs.sharing(this);
        if (!Prefs.isConfigured(this)) {
            statusTitle.setText("Configuration requise");
            statusDetail.setText("Indiquez votre prénom et le code famille");
            shareBtn.setText("Réglages");
        } else if (sharing) {
            statusTitle.setText("✅ Ma position est partagée");
            String err = Prefs.lastError(this);
            long last = Prefs.lastSent(this);
            String d = last > 0 ? "Dernier envoi " + Ui.ago(last) : "Premier envoi en cours…";
            if (err.length() > 0) d += " · " + err;
            statusDetail.setText(d);
            shareBtn.setText("Arrêter");
        } else {
            statusTitle.setText("⏸ Ma position n'est pas partagée");
            statusDetail.setText("Vous voyez quand même les autres membres");
            shareBtn.setText("Partager");
        }

        List<JSONObject> members = Prefs.members(this);
        String me = Prefs.deviceId(this);
        list.removeAllViews();
        if (members.isEmpty()) {
            TextView empty = Ui.text(this,
                    "Aucune position reçue pour l'instant.\n\nChaque proche doit installer l'application, "
                    + "entrer le même code famille (menu ⋮ › Inviter un proche) et activer « Partager ».",
                    14, Ui.GREY, false);
            empty.setPadding(Ui.dp(this, 6), Ui.dp(this, 12), Ui.dp(this, 6), 0);
            list.addView(empty);
        }
        JSONArray forMap = new JSONArray();
        long now = System.currentTimeMillis();
        for (final JSONObject m : members) {
            final String id = m.optString("id");
            final String name = m.optString("name", "?") + (id.equals(me) ? " (moi)" : "");
            long fix = m.optLong("fix", m.optLong("t"));
            boolean stale = now - fix > STALE_MS;
            boolean stopped = m.optBoolean("stopped", false);
            boolean isSos = m.optBoolean("sos", false) && now - m.optLong("t") < 6 * 3600_000L;
            try {
                JSONObject p = new JSONObject();
                p.put("id", id);
                p.put("name", name);
                p.put("lat", m.optDouble("lat"));
                p.put("lon", m.optDouble("lon"));
                p.put("acc", m.optDouble("acc", 0));
                p.put("sos", isSos);
                p.put("stale", stale || stopped);
                p.put("me", id.equals(me));
                forMap.put(p);
            } catch (Exception ignored) {}
            list.addView(memberRow(m, id, name, fix, stale, stopped, isSos));
        }
        if (mapReady) map.evaluateJavascript("setMembers(" + forMap.toString() + "," + fit + ")", null);
    }

    private View memberRow(final JSONObject m, final String id, final String name, long fix,
                           boolean stale, boolean stopped, boolean isSos) {
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setGravity(Gravity.CENTER_VERTICAL);
        int p = Ui.dp(this, 10);
        row.setPadding(p, p, p, p);
        row.setBackground(Ui.rounded(this, isSos ? 0xFFFFEBEE : Ui.LIGHT_BG, 10));

        LinearLayout texts = new LinearLayout(this);
        texts.setOrientation(LinearLayout.VERTICAL);
        int dot = isSos ? Ui.RED : (stale || stopped ? Ui.GREY : Ui.GREEN);
        TextView title = Ui.text(this, "● " + name + (isSos ? "  🚨 SOS" : ""), 16, Ui.DARK, true);
        title.setText(colorDot(title.getText().toString(), dot));
        StringBuilder d = new StringBuilder();
        d.append(Ui.ago(fix));
        int acc = (int) Math.round(m.optDouble("acc", 0));
        if (acc > 0) d.append(" · ±").append(acc).append(" m");
        int bat = m.optInt("bat", -1);
        if (bat >= 0) d.append(" · 🔋 ").append(bat).append(" %");
        if (stopped) d.append("\nA arrêté le partage ").append(Ui.ago(m.optLong("t")));
        texts.addView(title);
        texts.addView(Ui.text(this, d.toString(), 13, Ui.GREY, false));
        row.addView(texts, Ui.weight(1, LinearLayout.LayoutParams.WRAP_CONTENT));

        Button go = Ui.button(this, "Itinéraire", 0xFFFFFFFF, Ui.BLUE);
        go.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) {
                double lat = m.optDouble("lat"), lon = m.optDouble("lon");
                Uri u = Uri.parse("geo:" + lat + "," + lon + "?q=" + lat + "," + lon + "(" + Uri.encode(name) + ")");
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, u));
                } catch (Exception e) {
                    startActivity(new Intent(Intent.ACTION_VIEW,
                            Uri.parse("https://www.openstreetmap.org/?mlat=" + lat + "&mlon=" + lon + "#map=17/" + lat + "/" + lon)));
                }
            }
        });
        row.addView(go);

        row.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) {
                if (mapReady) map.evaluateJavascript("focusMember(" + JSONObject.quote(id) + ")", null);
            }
        });
        row.setOnLongClickListener(new View.OnLongClickListener() {
            @Override public boolean onLongClick(View v) {
                new AlertDialog.Builder(MainActivity.this)
                        .setTitle(name)
                        .setMessage("Retirer cette personne de la liste ? Elle réapparaîtra si elle envoie à nouveau sa position.")
                        .setPositiveButton("Retirer", new DialogInterface.OnClickListener() {
                            @Override public void onClick(DialogInterface dlg, int w) {
                                Prefs.removeMember(MainActivity.this, id);
                                render(false);
                            }
                        })
                        .setNegativeButton("Annuler", null)
                        .show();
                return true;
            }
        });
        LinearLayout.LayoutParams lp = Ui.matchWrap(this, 8);
        row.setLayoutParams(lp);
        return row;
    }

    private CharSequence colorDot(String s, int color) {
        android.text.SpannableString sp = new android.text.SpannableString(s);
        sp.setSpan(new android.text.style.ForegroundColorSpan(color), 0, 1, 0);
        return sp;
    }

    // ---------------------------------------------------------------- Réseau

    /** Récupère les dernières positions de la famille (12 dernières heures). */
    private void poll(final boolean userAction) {
        if (!Prefs.isConfigured(this)) return;
        final String code = Prefs.code(this);
        net.execute(new Runnable() {
            @Override public void run() {
                boolean ok = true;
                try {
                    List<String> bodies = Ntfy.poll(Crypto.topic(code), "12h");
                    for (String body : bodies) {
                        String plain = Crypto.decrypt(code, body);
                        if (plain == null) continue;
                        try { Prefs.mergeMember(MainActivity.this, new JSONObject(plain)); } catch (Exception ignored) {}
                    }
                } catch (Exception e) {
                    ok = false;
                }
                final boolean fOk = ok;
                ui.post(new Runnable() {
                    @Override public void run() {
                        if (isFinishing()) return;
                        render(userAction);
                        if (!fOk && userAction) toast("Pas de connexion Internet");
                    }
                });
            }
        });
    }

    private void publish(final JSONObject msg, final String okText, final Runnable after) {
        final String code = Prefs.code(this);
        net.execute(new Runnable() {
            @Override public void run() {
                boolean ok = true;
                try {
                    Ntfy.publish(Crypto.topic(code), Crypto.encrypt(code, msg.toString()));
                } catch (Exception e) {
                    ok = false;
                }
                final boolean fOk = ok;
                ui.post(new Runnable() {
                    @Override public void run() {
                        toast(fOk ? okText : "Échec de l'envoi : vérifiez la connexion Internet");
                        if (after != null) after.run();
                    }
                });
            }
        });
    }

    /** Demande à tous les téléphones de la famille d'envoyer leur position tout de suite. */
    private void askEveryone() {
        if (!Prefs.isConfigured(this)) {
            startActivity(new Intent(this, SettingsActivity.class));
            return;
        }
        publish(LocationHelper.message(this, "ping", null),
                "Demande envoyée. Les positions arrivent dans quelques secondes…", null);
        ui.postDelayed(new Runnable() { @Override public void run() { poll(false); } }, 20_000L);
        ui.postDelayed(new Runnable() { @Override public void run() { poll(true); } }, 50_000L);
    }

    // ---------------------------------------------------------------- SOS

    private void confirmSos() {
        if (!Prefs.isConfigured(this)) {
            startActivity(new Intent(this, SettingsActivity.class));
            return;
        }
        new AlertDialog.Builder(this)
                .setTitle("Envoyer une alerte SOS ?")
                .setMessage("Votre position sera envoyée immédiatement à toute la famille avec une alerte.")
                .setPositiveButton("ENVOYER SOS", new DialogInterface.OnClickListener() {
                    @Override public void onClick(DialogInterface d, int w) { sendSos(); }
                })
                .setNegativeButton("Annuler", null)
                .show();
    }

    private void sendSos() {
        if (!hasLocationPermission()) {
            requestPermissions(new String[]{Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION}, REQ_SOS);
            return;
        }
        final ProgressDialog pd = ProgressDialog.show(this, null, "Localisation en cours…", true, false);
        LocationHelper.acquire(this, ui, 20_000L, new LocationHelper.Callback() {
            @Override public void onResult(Location loc) {
                try { pd.dismiss(); } catch (Exception ignored) {}
                JSONObject msg = LocationHelper.message(MainActivity.this, "sos", loc);
                if (loc != null) Prefs.mergeMember(MainActivity.this, msg);
                publish(msg, loc != null ? "🚨 Alerte SOS envoyée à la famille"
                        : "🚨 Alerte envoyée (position introuvable : activez la localisation)", null);
                render(true);
            }
        });
    }

    // ---------------------------------------------------------------- Partage & autorisations

    private boolean hasLocationPermission() {
        return checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
                || checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    private void toggleSharing() {
        if (!Prefs.isConfigured(this)) {
            startActivity(new Intent(this, SettingsActivity.class));
            return;
        }
        if (Prefs.sharing(this)) {
            LocationService.stop(this);
            ui.postDelayed(new Runnable() { @Override public void run() { render(false); } }, 300);
            render(false);
            return;
        }
        List<String> need = new ArrayList<String>();
        if (!hasLocationPermission()) {
            need.add(Manifest.permission.ACCESS_FINE_LOCATION);
            need.add(Manifest.permission.ACCESS_COARSE_LOCATION);
        }
        if (Build.VERSION.SDK_INT >= 33
                && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            need.add(Manifest.permission.POST_NOTIFICATIONS);
        }
        if (!need.isEmpty()) {
            requestPermissions(need.toArray(new String[0]), REQ_LOC);
        } else {
            startSharing();
        }
    }

    @Override public void onRequestPermissionsResult(int req, String[] perms, int[] results) {
        if (req == REQ_LOC) {
            if (hasLocationPermission()) startSharing();
            else explainDenied();
        } else if (req == REQ_SOS) {
            sendSos();
        } else if (req == REQ_BG) {
            askBatteryOptimization();
        }
    }

    private void explainDenied() {
        new AlertDialog.Builder(this)
                .setTitle("Autorisation nécessaire")
                .setMessage("Sans l'autorisation de localisation, l'application ne peut pas partager votre position. "
                        + "Vous pouvez l'activer dans les paramètres de l'application.")
                .setPositiveButton("Ouvrir les paramètres", new DialogInterface.OnClickListener() {
                    @Override public void onClick(DialogInterface d, int w) { SettingsActivity.openAppSettings(MainActivity.this); }
                })
                .setNegativeButton("Plus tard", null)
                .show();
    }

    private void startSharing() {
        Prefs.setSharing(this, true);
        try {
            LocationService.start(this, LocationService.ACTION_START);
        } catch (Exception e) {
            Prefs.setSharing(this, false);
            toast("Impossible de démarrer le partage");
        }
        render(false);
        ui.postDelayed(new Runnable() { @Override public void run() { render(false); } }, 8_000L);
        ui.postDelayed(new Runnable() { @Override public void run() { render(false); } }, 50_000L);

        if (Build.VERSION.SDK_INT >= 29
                && checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            new AlertDialog.Builder(this)
                    .setTitle("Partage fiable en arrière-plan")
                    .setMessage("Pour que le partage continue quand le téléphone redémarre ou que l'appli est fermée, "
                            + "choisissez « Toujours autoriser » sur l'écran suivant.")
                    .setPositiveButton("Continuer", new DialogInterface.OnClickListener() {
                        @Override public void onClick(DialogInterface d, int w) {
                            requestPermissions(new String[]{Manifest.permission.ACCESS_BACKGROUND_LOCATION}, REQ_BG);
                        }
                    })
                    .setNegativeButton("Plus tard", new DialogInterface.OnClickListener() {
                        @Override public void onClick(DialogInterface d, int w) { askBatteryOptimization(); }
                    })
                    .show();
        } else {
            askBatteryOptimization();
        }
    }

    private void askBatteryOptimization() {
        PowerManager pm = (PowerManager) getSystemService(POWER_SERVICE);
        if (pm.isIgnoringBatteryOptimizations(getPackageName())) return;
        new AlertDialog.Builder(this)
                .setTitle("Économie de batterie")
                .setMessage("Certains téléphones coupent les applications en veille. "
                        + "Autorisez Famille Proche à fonctionner en arrière-plan pour des positions à jour.")
                .setPositiveButton("Autoriser", new DialogInterface.OnClickListener() {
                    @Override public void onClick(DialogInterface d, int w) { SettingsActivity.requestIgnoreBattery(MainActivity.this); }
                })
                .setNegativeButton("Plus tard", null)
                .show();
    }

    private void toast(String s) {
        Toast.makeText(this, s, Toast.LENGTH_LONG).show();
    }
}
