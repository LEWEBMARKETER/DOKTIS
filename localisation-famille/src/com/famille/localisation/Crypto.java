package com.famille.localisation;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import javax.crypto.Cipher;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;

/**
 * Chiffrement de bout en bout : seul un téléphone qui connaît le code famille
 * peut lire les messages. Le relais (ntfy.sh) ne voit que des données chiffrées.
 */
final class Crypto {
    private static final String ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    private static final SecureRandom RNG = new SecureRandom();

    private Crypto() {}

    /** Nouveau code famille : 20 caractères (100 bits d'aléa), ex. ABCD-EFGH-JKLM-NPQR-STUV. */
    static String newCode() {
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < 20; i++) {
            if (i > 0 && i % 4 == 0) sb.append('-');
            sb.append(ALPHABET.charAt(RNG.nextInt(ALPHABET.length())));
        }
        return sb.toString();
    }

    /** Normalise un code saisi (majuscules, sans espaces ni tirets). */
    static String normalize(String code) {
        if (code == null) return "";
        StringBuilder sb = new StringBuilder();
        for (char c : code.toUpperCase().toCharArray()) {
            if ((c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9')) sb.append(c);
        }
        return sb.toString();
    }

    static boolean isValid(String code) {
        return normalize(code).length() >= 12;
    }

    /** Remet un code normalisé en forme lisible XXXX-XXXX-... */
    static String pretty(String code) {
        String n = normalize(code);
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < n.length(); i++) {
            if (i > 0 && i % 4 == 0) sb.append('-');
            sb.append(n.charAt(i));
        }
        return sb.toString();
    }

    static byte[] sha256(String s) {
        try {
            return MessageDigest.getInstance("SHA-256").digest(s.getBytes(StandardCharsets.UTF_8));
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    /** Nom du canal ntfy dérivé du code (impossible de retrouver le code à partir du canal). */
    static String topic(String code) {
        byte[] h = sha256("famille-proche/topic/v1:" + normalize(code));
        StringBuilder sb = new StringBuilder("fp");
        for (int i = 0; i < 20; i++) sb.append(String.format("%02x", h[i] & 0xff));
        return sb.toString();
    }

    private static SecretKeySpec key(String code) {
        return new SecretKeySpec(sha256("famille-proche/key/v1:" + normalize(code)), "AES");
    }

    static String encrypt(String code, String plain) {
        try {
            byte[] iv = new byte[12];
            RNG.nextBytes(iv);
            Cipher c = Cipher.getInstance("AES/GCM/NoPadding");
            c.init(Cipher.ENCRYPT_MODE, key(code), new GCMParameterSpec(128, iv));
            byte[] ct = c.doFinal(plain.getBytes(StandardCharsets.UTF_8));
            byte[] out = new byte[iv.length + ct.length];
            System.arraycopy(iv, 0, out, 0, iv.length);
            System.arraycopy(ct, 0, out, iv.length, ct.length);
            return "fp1:" + java.util.Base64.getEncoder().encodeToString(out);
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    /** Renvoie null si le message n'est pas chiffré avec ce code (ou corrompu). */
    static String decrypt(String code, String msg) {
        try {
            if (msg == null || !msg.startsWith("fp1:")) return null;
            byte[] in = java.util.Base64.getDecoder().decode(msg.substring(4).trim());
            if (in.length < 12 + 16) return null;
            Cipher c = Cipher.getInstance("AES/GCM/NoPadding");
            c.init(Cipher.DECRYPT_MODE, key(code), new GCMParameterSpec(128, in, 0, 12));
            byte[] pt = c.doFinal(in, 12, in.length - 12);
            return new String(pt, StandardCharsets.UTF_8);
        } catch (Exception e) {
            return null;
        }
    }
}
