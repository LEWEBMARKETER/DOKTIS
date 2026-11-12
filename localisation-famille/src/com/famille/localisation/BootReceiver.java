package com.famille.localisation;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

/** Relance le partage après un redémarrage du téléphone, s'il était activé. */
public class BootReceiver extends BroadcastReceiver {
    @Override public void onReceive(Context context, Intent intent) {
        if (Prefs.sharing(context) && Prefs.isConfigured(context)) {
            try {
                LocationService.start(context, LocationService.ACTION_START);
            } catch (Exception ignored) {
                // Sans « Toujours autoriser » la localisation, Android peut refuser :
                // le partage reprendra à la prochaine ouverture de l'application.
            }
        }
    }
}
