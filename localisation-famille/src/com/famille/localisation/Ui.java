package com.famille.localisation;

import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.util.TypedValue;
import android.view.Gravity;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;

/** Petits utilitaires d'interface (l'interface est construite en code). */
final class Ui {
    private Ui() {}

    static int dp(Context c, float v) {
        return Math.round(TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, v, c.getResources().getDisplayMetrics()));
    }

    static TextView text(Context c, String s, float sp, int color, boolean bold) {
        TextView t = new TextView(c);
        t.setText(s);
        t.setTextSize(TypedValue.COMPLEX_UNIT_SP, sp);
        t.setTextColor(color);
        if (bold) t.setTypeface(t.getTypeface(), android.graphics.Typeface.BOLD);
        return t;
    }

    static Button button(Context c, String s, int bg, int fg) {
        Button b = new Button(c);
        b.setText(s);
        b.setAllCaps(false);
        b.setTextColor(fg);
        b.setTextSize(TypedValue.COMPLEX_UNIT_SP, 15);
        GradientDrawable d = new GradientDrawable();
        d.setColor(bg);
        d.setCornerRadius(dp(c, 10));
        b.setBackground(d);
        b.setPadding(dp(c, 12), dp(c, 10), dp(c, 12), dp(c, 10));
        b.setGravity(Gravity.CENTER);
        return b;
    }

    static GradientDrawable rounded(Context c, int bg, float radiusDp) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(bg);
        d.setCornerRadius(dp(c, radiusDp));
        return d;
    }

    static LinearLayout.LayoutParams weight(float w, int heightPx) {
        return new LinearLayout.LayoutParams(0, heightPx, w);
    }

    static LinearLayout.LayoutParams matchWrap(Context c, int topMarginDp) {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT);
        lp.topMargin = dp(c, topMarginDp);
        return lp;
    }

    static String ago(long t) {
        if (t <= 0) return "jamais";
        long s = (System.currentTimeMillis() - t) / 1000;
        if (s < 60) return "à l'instant";
        long m = s / 60;
        if (m < 60) return "il y a " + m + " min";
        long h = m / 60;
        if (h < 24) return "il y a " + h + " h" + (m % 60 >= 10 && h < 5 ? " " + (m % 60) : "");
        long d = h / 24;
        return "il y a " + d + " j";
    }

    static final int BLUE = Color.parseColor("#1565C0");
    static final int RED = Color.parseColor("#E53935");
    static final int GREEN = Color.parseColor("#2E7D32");
    static final int GREY = Color.parseColor("#607D8B");
    static final int DARK = Color.parseColor("#263238");
    static final int LIGHT_BG = Color.parseColor("#F1F5F9");
}
