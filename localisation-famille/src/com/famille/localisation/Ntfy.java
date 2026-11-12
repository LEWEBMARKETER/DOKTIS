package com.famille.localisation;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import org.json.JSONObject;

/** Relais de messages via le service public ntfy.sh (messages déjà chiffrés). */
final class Ntfy {
    static final String BASE = "https://ntfy.sh/";

    interface Listener {
        void onMessage(String body);
    }

    private Ntfy() {}

    static void publish(String topic, String body) throws IOException {
        HttpURLConnection c = (HttpURLConnection) new URL(BASE + topic).openConnection();
        try {
            c.setRequestMethod("POST");
            c.setDoOutput(true);
            c.setConnectTimeout(20000);
            c.setReadTimeout(20000);
            c.setRequestProperty("Content-Type", "text/plain; charset=utf-8");
            c.setRequestProperty("Firebase", "no");
            byte[] data = body.getBytes(StandardCharsets.UTF_8);
            c.setFixedLengthStreamingMode(data.length);
            OutputStream os = c.getOutputStream();
            os.write(data);
            os.close();
            int code = c.getResponseCode();
            drain(code >= 400 ? c.getErrorStream() : c.getInputStream());
            if (code >= 300) throw new IOException("ntfy HTTP " + code);
        } finally {
            c.disconnect();
        }
    }

    /** Récupère les messages en cache (ntfy.sh les garde ~12 h). */
    static List<String> poll(String topic, String since) throws IOException {
        List<String> out = new ArrayList<String>();
        HttpURLConnection c = (HttpURLConnection) new URL(BASE + topic + "/json?poll=1&since=" + since).openConnection();
        try {
            c.setConnectTimeout(20000);
            c.setReadTimeout(30000);
            int code = c.getResponseCode();
            if (code >= 300) {
                drain(c.getErrorStream());
                throw new IOException("ntfy HTTP " + code);
            }
            BufferedReader r = new BufferedReader(new InputStreamReader(c.getInputStream(), StandardCharsets.UTF_8));
            String line;
            while ((line = r.readLine()) != null) {
                String body = parse(line);
                if (body != null) out.add(body);
            }
            r.close();
        } finally {
            c.disconnect();
        }
        return out;
    }

    /** Connexion longue : appelle le listener pour chaque nouveau message. Bloquant. */
    static void stream(String topic, Listener l, ConnHolder holder) throws IOException {
        HttpURLConnection c = (HttpURLConnection) new URL(BASE + topic + "/json").openConnection();
        holder.conn = c;
        try {
            c.setConnectTimeout(20000);
            c.setReadTimeout(120000); // ntfy envoie un keepalive toutes les ~45 s
            int code = c.getResponseCode();
            if (code >= 300) throw new IOException("ntfy HTTP " + code);
            BufferedReader r = new BufferedReader(new InputStreamReader(c.getInputStream(), StandardCharsets.UTF_8));
            String line;
            while (!holder.closed && (line = r.readLine()) != null) {
                String body = parse(line);
                if (body != null) l.onMessage(body);
            }
        } finally {
            holder.conn = null;
            c.disconnect();
        }
    }

    static final class ConnHolder {
        volatile HttpURLConnection conn;
        volatile boolean closed;

        void close() {
            closed = true;
            HttpURLConnection c = conn;
            if (c != null) {
                try { c.disconnect(); } catch (Exception ignored) {}
            }
        }
    }

    private static String parse(String line) {
        try {
            line = line.trim();
            if (line.isEmpty()) return null;
            JSONObject o = new JSONObject(line);
            if (!"message".equals(o.optString("event"))) return null;
            return o.optString("message", null);
        } catch (Exception e) {
            return null;
        }
    }

    private static void drain(InputStream in) {
        if (in == null) return;
        try {
            byte[] b = new byte[1024];
            while (in.read(b) > 0) { /* vide */ }
            in.close();
        } catch (Exception ignored) {}
    }
}
