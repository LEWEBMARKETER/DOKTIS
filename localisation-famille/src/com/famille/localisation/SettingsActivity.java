package com.famille.localisation;

import android.Manifest;
import android.app.Activity;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.PowerManager;
import android.provider.Settings;
import android.text.InputType;
import android.view.MenuItem;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

public class SettingsActivity extends Activity {
    private static final int[] INTERVALS = {10, 15, 30, 60};

    private EditText name, code;
    private RadioGroup interval;
    private TextView permStatus;

    @Override protected void onCreate(Bundle b) {
        super.onCreate(b);
        if (getActionBar() != null) getActionBar().setDisplayHomeAsUpEnabled(true);
        int pad = Ui.dp(this, 18);

        ScrollView sv = new ScrollView(this);
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(pad, pad, pad, pad);
        sv.addView(root);

        // Prénom
        root.addView(Ui.text(this, "Votre prénom", 15, Ui.DARK, true));
        root.addView(Ui.text(this, "C'est le nom que verra votre famille sur la carte.", 13, Ui.GREY, false));
        name = new EditText(this);
        name.setSingleLine(true);
        name.setHint("Ex. : Maman, Léa, Papi…");
        name.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_WORDS);
        name.setText(Prefs.name(this));
        root.addView(name, Ui.matchWrap(this, 4));

        // Code famille
        TextView t2 = Ui.text(this, "Code famille", 15, Ui.DARK, true);
        root.addView(t2, Ui.matchWrap(this, 22));
        root.addView(Ui.text(this,
                "Tous les membres doivent avoir exactement le même code. Seuls les téléphones qui le connaissent "
                + "peuvent voir les positions (elles sont chiffrées). Ne le donnez qu'à vos proches.",
                13, Ui.GREY, false));
        code = new EditText(this);
        code.setSingleLine(true);
        code.setTypeface(Typeface.MONOSPACE);
        code.setHint("XXXX-XXXX-XXXX-XXXX-XXXX");
        code.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS | InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS);
        code.setText(Prefs.code(this));
        root.addView(code, Ui.matchWrap(this, 4));

        LinearLayout codeBtns = new LinearLayout(this);
        codeBtns.setOrientation(LinearLayout.HORIZONTAL);
        Button gen = Ui.button(this, "Créer un code", Ui.LIGHT_BG, Ui.DARK);
        Button share = Ui.button(this, "Envoyer aux proches", Ui.LIGHT_BG, Ui.BLUE);
        gen.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) {
                code.setText(Crypto.newCode());
                toast("Nouveau code créé. Pensez à l'envoyer à vos proches puis à Enregistrer.");
            }
        });
        share.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) {
                if (!Crypto.isValid(code.getText().toString())) {
                    toast("Créez ou saisissez d'abord un code");
                    return;
                }
                shareCode(SettingsActivity.this, code.getText().toString());
            }
        });
        LinearLayout.LayoutParams l1 = Ui.weight(1, LinearLayout.LayoutParams.WRAP_CONTENT);
        LinearLayout.LayoutParams l2 = Ui.weight(1.3f, LinearLayout.LayoutParams.WRAP_CONTENT);
        l2.leftMargin = Ui.dp(this, 10);
        codeBtns.addView(gen, l1);
        codeBtns.addView(share, l2);
        root.addView(codeBtns, Ui.matchWrap(this, 6));

        // Fréquence
        root.addView(Ui.text(this, "Fréquence d'envoi de ma position", 15, Ui.DARK, true), Ui.matchWrap(this, 22));
        root.addView(Ui.text(this, "Plus c'est fréquent, plus la batterie est sollicitée. "
                + "Le bouton « Localiser » permet toujours d'obtenir une position immédiate.", 13, Ui.GREY, false));
        interval = new RadioGroup(this);
        int cur = Prefs.intervalMin(this);
        for (int i = 0; i < INTERVALS.length; i++) {
            RadioButton rb = new RadioButton(this);
            rb.setId(1000 + i);
            rb.setText("Toutes les " + INTERVALS[i] + " minutes" + (INTERVALS[i] == 15 ? " (recommandé)" : ""));
            interval.addView(rb);
            if (INTERVALS[i] == cur) rb.setChecked(true);
        }
        if (interval.getCheckedRadioButtonId() == -1) interval.check(1001);
        root.addView(interval);

        Button save = Ui.button(this, "Enregistrer", Ui.BLUE, 0xFFFFFFFF);
        save.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { save(); }
        });
        root.addView(save, Ui.matchWrap(this, 18));

        // Autorisations
        root.addView(Ui.text(this, "Fiabilité du partage", 15, Ui.DARK, true), Ui.matchWrap(this, 28));
        permStatus = Ui.text(this, "", 13, Ui.GREY, false);
        root.addView(permStatus);
        Button bg = Ui.button(this, "Localisation : « Toujours autoriser »", Ui.LIGHT_BG, Ui.DARK);
        bg.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { openAppSettings(SettingsActivity.this); }
        });
        root.addView(bg, Ui.matchWrap(this, 6));
        Button bat = Ui.button(this, "Ne pas restreindre la batterie", Ui.LIGHT_BG, Ui.DARK);
        bat.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { requestIgnoreBattery(SettingsActivity.this); }
        });
        root.addView(bat, Ui.matchWrap(this, 6));

        root.addView(Ui.text(this,
                "Confidentialité : chaque personne choisit elle-même de partager sa position et une notification "
                + "permanente l'indique sur son téléphone. Elle peut arrêter à tout moment. Les positions sont chiffrées "
                + "avec le code famille avant d'être transmises par le relais public ntfy.sh, qui ne peut pas les lire "
                + "et les efface après 12 heures.",
                12, Ui.GREY, false), Ui.matchWrap(this, 24));

        setContentView(sv);
    }

    @Override protected void onResume() {
        super.onResume();
        StringBuilder s = new StringBuilder();
        boolean fine = checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        boolean coarse = checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
        boolean bgOk = Build.VERSION.SDK_INT < 29
                || checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION) == PackageManager.PERMISSION_GRANTED;
        PowerManager pm = (PowerManager) getSystemService(POWER_SERVICE);
        boolean batOk = pm.isIgnoringBatteryOptimizations(getPackageName());
        s.append(fine ? "✅ Localisation précise" : coarse ? "⚠️ Localisation approximative seulement" : "❌ Localisation non autorisée");
        s.append('\n').append(bgOk ? "✅ Autorisée en permanence" : "⚠️ Pas « Toujours autoriser » (le partage peut s'arrêter au redémarrage)");
        s.append('\n').append(batOk ? "✅ Batterie non restreinte" : "⚠️ Optimisation de batterie active (positions parfois en retard)");
        permStatus.setText(s.toString());
    }

    private void save() {
        String n = name.getText().toString().trim();
        String c = code.getText().toString();
        if (n.isEmpty()) {
            toast("Indiquez votre prénom");
            return;
        }
        if (!Crypto.isValid(c)) {
            toast("Code famille invalide : créez-en un ou collez celui reçu d'un proche");
            return;
        }
        boolean codeChanged = !Crypto.normalize(c).equals(Crypto.normalize(Prefs.code(this)));
        int checked = interval.getCheckedRadioButtonId() - 1000;
        int minutes = checked >= 0 && checked < INTERVALS.length ? INTERVALS[checked] : 15;
        boolean intervalChanged = minutes != Prefs.intervalMin(this);
        boolean nameChanged = !n.equals(Prefs.name(this));

        Prefs.setName(this, n);
        if (codeChanged) Prefs.setCode(this, c);
        Prefs.setIntervalMin(this, minutes);

        if (Prefs.sharing(this) && (codeChanged || intervalChanged || nameChanged)) {
            try { LocationService.start(this, LocationService.ACTION_START); } catch (Exception ignored) {}
        }
        toast("Enregistré");
        finish();
    }

    @Override public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == android.R.id.home) {
            finish();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    private void toast(String s) {
        Toast.makeText(this, s, Toast.LENGTH_LONG).show();
    }

    // ---------------------------------------------------------------- utilitaires partagés

    static void shareCode(Context ctx, String c) {
        if (!Crypto.isValid(c)) {
            Toast.makeText(ctx, "Aucun code famille : allez dans Réglages", Toast.LENGTH_LONG).show();
            return;
        }
        String pretty = Crypto.pretty(c);
        try {
            ClipboardManager cm = (ClipboardManager) ctx.getSystemService(CLIPBOARD_SERVICE);
            cm.setPrimaryClip(ClipData.newPlainText("Code famille", pretty));
        } catch (Exception ignored) {}
        String text = "📍 Rejoins notre groupe « Famille Proche »\n\n"
                + "1. Installe l'application Famille Proche (je te l'envoie)\n"
                + "2. Ouvre Réglages, mets ton prénom et colle ce code famille :\n\n"
                + pretty + "\n\n"
                + "3. Appuie sur « Partager ». Tu pourras arrêter quand tu veux.\n"
                + "⚠️ Ne transmets ce code à personne d'autre.";
        Intent i = new Intent(Intent.ACTION_SEND);
        i.setType("text/plain");
        i.putExtra(Intent.EXTRA_TEXT, text);
        ctx.startActivity(Intent.createChooser(i, "Envoyer le code famille"));
    }

    static void openAppSettings(Context ctx) {
        Intent i = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                Uri.parse("package:" + ctx.getPackageName()));
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        ctx.startActivity(i);
    }

    static void requestIgnoreBattery(Context ctx) {
        try {
            Intent i = new Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS,
                    Uri.parse("package:" + ctx.getPackageName()));
            ctx.startActivity(i);
        } catch (Exception e) {
            try {
                ctx.startActivity(new Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS));
            } catch (Exception ignored) {}
        }
    }
}
