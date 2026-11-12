package com.famille.localisation;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ServiceInfo;
import android.location.Location;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.HandlerThread;
import android.os.IBinder;
import android.os.PowerManager;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import org.json.JSONObject;

/**
 * Service de partage de position. Il tourne au premier plan avec une
 * notification permanente visible : la personne sait toujours que sa
 * position est partagée et peut l'arrêter à tout moment.
 */
public class LocationService extends Service {
    static final String ACTION_START = "com.famille.localisation.START";
    static final String ACTION_STOP = "com.famille.localisation.STOP";
    static final String ACTION_NOW = "com.famille.localisation.NOW";

    static final String CH_SHARING = "partage";
    static final String CH_ALERTS = "alertes";
    private static final int NOTIF_ID = 1;

    private HandlerThread thread;
    private Handler handler;
    private final ExecutorService net = Executors.newSingleThreadExecutor();
    private PowerManager.WakeLock wakeLock;
    private volatile boolean running;
    private boolean acquiring;
    private long lastAcquire;
    private Thread streamThread;
    private Ntfy.ConnHolder holder;
    private String code;

    static void start(Context c, String action) {
        Intent i = new Intent(c, LocationService.class).setAction(action);
        if (Build.VERSION.SDK_INT >= 26) c.startForegroundService(i);
        else c.startService(i);
    }

    static void stop(Context c) {
        Prefs.setSharing(c, false);
        Intent i = new Intent(c, LocationService.class).setAction(ACTION_STOP);
        try { c.startService(i); } catch (Exception e) { c.stopService(new Intent(c, LocationService.class)); }
    }

    static void createChannels(Context c) {
        if (Build.VERSION.SDK_INT < 26) return;
        NotificationManager nm = (NotificationManager) c.getSystemService(NOTIFICATION_SERVICE);
        NotificationChannel a = new NotificationChannel(CH_SHARING, "Partage de position", NotificationManager.IMPORTANCE_LOW);
        a.setDescription("Indique que votre position est partagée avec votre famille");
        nm.createNotificationChannel(a);
        NotificationChannel b = new NotificationChannel(CH_ALERTS, "Alertes SOS", NotificationManager.IMPORTANCE_HIGH);
        b.setDescription("Alerte quand un membre de la famille appuie sur SOS");
        b.enableVibration(true);
        nm.createNotificationChannel(b);
    }

    @Override public void onCreate() {
        super.onCreate();
        createChannels(this);
        thread = new HandlerThread("localisation");
        thread.start();
        handler = new Handler(thread.getLooper());
        PowerManager pm = (PowerManager) getSystemService(POWER_SERVICE);
        wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "FamilleProche:loc");
        wakeLock.setReferenceCounted(false);
    }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        String action = intent == null ? ACTION_START : intent.getAction();
        if (ACTION_STOP.equals(action)) {
            // On prévient la famille que le partage est arrêté, puis on s'arrête.
            if (running) {
                final String c = code;
                final JSONObject bye = LocationHelper.message(this, "bye", null);
                net.execute(new Runnable() {
                    @Override public void run() {
                        try { Ntfy.publish(Crypto.topic(c), Crypto.encrypt(c, bye.toString())); } catch (Exception ignored) {}
                    }
                });
            }
            shutdown();
            if (Build.VERSION.SDK_INT >= 24) stopForeground(STOP_FOREGROUND_REMOVE);
            else stopForeground(true);
            stopSelf();
            return START_NOT_STICKY;
        }

        if (!Prefs.isConfigured(this) || !Prefs.sharing(this)) {
            // Démarré par le système ou au redémarrage alors que le partage est désactivé.
            try { startForegroundCompat(); } catch (Exception ignored) {}
            stopForegroundCompat();
            stopSelf();
            return START_NOT_STICKY;
        }

        try {
            startForegroundCompat();
        } catch (Exception e) {
            // Android refuse (ex. démarrage en arrière-plan sans « Toujours autoriser »).
            stopSelf();
            return START_NOT_STICKY;
        }

        String newCode = Prefs.code(this);
        if (running && !newCode.equals(code)) {
            shutdown(); // le code famille a changé : on repart de zéro
        }
        if (!running) {
            running = true;
            code = newCode;
            holder = new Ntfy.ConnHolder();
            handler.post(tick);
            startStream();
        } else if (ACTION_NOW.equals(action) || ACTION_START.equals(action)) {
            handler.post(new Runnable() { @Override public void run() { acquireAndSend("loc"); } });
        }
        return START_STICKY;
    }

    private void stopForegroundCompat() {
        if (Build.VERSION.SDK_INT >= 24) stopForeground(STOP_FOREGROUND_REMOVE);
        else stopForeground(true);
    }

    private void startForegroundCompat() {
        Notification n = buildNotification();
        if (Build.VERSION.SDK_INT >= 29) {
            startForeground(NOTIF_ID, n, ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION);
        } else {
            startForeground(NOTIF_ID, n);
        }
    }

    private Notification buildNotification() {
        PendingIntent open = PendingIntent.getActivity(this, 0,
                new Intent(this, MainActivity.class), PendingIntent.FLAG_IMMUTABLE);
        PendingIntent stop = PendingIntent.getService(this, 1,
                new Intent(this, LocationService.class).setAction(ACTION_STOP), PendingIntent.FLAG_IMMUTABLE);
        Notification.Builder b = Build.VERSION.SDK_INT >= 26
                ? new Notification.Builder(this, CH_SHARING)
                : new Notification.Builder(this);
        return b.setSmallIcon(R.drawable.ic_notif)
                .setContentTitle("Position partagée avec la famille")
                .setContentText("Mise à jour toutes les " + Prefs.intervalMin(this) + " min · touchez pour ouvrir")
                .setOngoing(true)
                .setContentIntent(open)
                .addAction(new Notification.Action.Builder(null, "Arrêter le partage", stop).build())
                .build();
    }

    private final Runnable tick = new Runnable() {
        @Override public void run() {
            if (!running) return;
            acquireAndSend("loc");
            handler.postDelayed(this, Prefs.intervalMin(LocationService.this) * 60_000L);
        }
    };

    /** S'exécute sur le thread "localisation". */
    private void acquireAndSend(final String type) {
        if (!running || acquiring) return;
        acquiring = true;
        lastAcquire = System.currentTimeMillis();
        wakeLock.acquire(90_000L);
        LocationHelper.acquire(this, handler, 45_000L, new LocationHelper.Callback() {
            @Override public void onResult(Location loc) {
                acquiring = false;
                if (!running) {
                    releaseWakeLock();
                    return;
                }
                if (loc == null) {
                    Prefs.setSendResult(LocationService.this, false, "Position introuvable (GPS désactivé ?)");
                    releaseWakeLock();
                    return;
                }
                final JSONObject msg = LocationHelper.message(LocationService.this, type, loc);
                Prefs.mergeMember(LocationService.this, msg);
                final String c = code;
                net.execute(new Runnable() {
                    @Override public void run() {
                        try {
                            Ntfy.publish(Crypto.topic(c), Crypto.encrypt(c, msg.toString()));
                            Prefs.setSendResult(LocationService.this, true, null);
                        } catch (Exception e) {
                            Prefs.setSendResult(LocationService.this, false, "Pas de connexion Internet");
                        } finally {
                            releaseWakeLock();
                        }
                    }
                });
            }
        });
    }

    private void releaseWakeLock() {
        try { if (wakeLock.isHeld()) wakeLock.release(); } catch (Exception ignored) {}
    }

    /** Écoute en direct les demandes de la famille ("Localiser maintenant", SOS). */
    private void startStream() {
        final String c = code;
        final Ntfy.ConnHolder h = holder;
        streamThread = new Thread(new Runnable() {
            @Override public void run() {
                long backoff = 5_000L;
                while (running && !h.closed) {
                    long started = System.currentTimeMillis();
                    try {
                        Ntfy.stream(Crypto.topic(c), new Ntfy.Listener() {
                            @Override public void onMessage(String body) { onIncoming(c, body); }
                        }, h);
                    } catch (Exception ignored) {}
                    if (!running || h.closed) break;
                    if (System.currentTimeMillis() - started > 60_000L) backoff = 5_000L;
                    try { Thread.sleep(backoff); } catch (InterruptedException e) { break; }
                    backoff = Math.min(backoff * 2, 300_000L);
                }
            }
        }, "ecoute-famille");
        streamThread.start();
    }

    private void onIncoming(String c, String body) {
        String plain = Crypto.decrypt(c, body);
        if (plain == null) return;
        try {
            final JSONObject o = new JSONObject(plain);
            if (Prefs.deviceId(this).equals(o.optString("id"))) return; // mon propre message
            String type = o.optString("type");
            if ("ping".equals(type)) {
                handler.post(new Runnable() {
                    @Override public void run() {
                        // limite : une réponse par minute maximum
                        if (System.currentTimeMillis() - lastAcquire > 60_000L) acquireAndSend("loc");
                    }
                });
            } else if ("sos".equals(type)) {
                Prefs.mergeMember(this, o);
                showSos(this, o);
            } else {
                Prefs.mergeMember(this, o);
            }
        } catch (Exception ignored) {}
    }

    static void showSos(Context ctx, JSONObject o) {
        createChannels(ctx);
        String name = o.optString("name", "Un proche");
        Intent open;
        if (o.has("lat")) {
            double lat = o.optDouble("lat"), lon = o.optDouble("lon");
            open = new Intent(Intent.ACTION_VIEW, Uri.parse("geo:" + lat + "," + lon + "?q=" + lat + "," + lon + "(" + Uri.encode(name) + ")"));
        } else {
            open = new Intent(ctx, MainActivity.class);
        }
        open.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        PendingIntent pi = PendingIntent.getActivity(ctx, o.optString("id").hashCode(), open,
                PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT);
        Notification.Builder b = Build.VERSION.SDK_INT >= 26
                ? new Notification.Builder(ctx, CH_ALERTS)
                : new Notification.Builder(ctx);
        Notification n = b.setSmallIcon(R.drawable.ic_notif)
                .setContentTitle("🚨 SOS de " + name)
                .setContentText("Touchez pour voir sa position sur la carte")
                .setCategory(Notification.CATEGORY_ALARM)
                .setPriority(Notification.PRIORITY_MAX)
                .setAutoCancel(true)
                .setContentIntent(pi)
                .build();
        try {
            NotificationManager nm = (NotificationManager) ctx.getSystemService(NOTIFICATION_SERVICE);
            nm.notify(1000 + (o.optString("id").hashCode() & 0xfff), n);
        } catch (Exception ignored) {}
    }

    private void shutdown() {
        running = false;
        if (holder != null) holder.close();
        if (handler != null) handler.removeCallbacks(tick);
        releaseWakeLock();
    }

    @Override public void onDestroy() {
        shutdown();
        net.shutdown();
        if (thread != null) thread.quitSafely();
        super.onDestroy();
    }

    @Override public IBinder onBind(Intent intent) { return null; }
}
