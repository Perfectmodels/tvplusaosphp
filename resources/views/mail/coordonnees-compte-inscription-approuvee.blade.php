<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription approuvée — TV+AOS</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F7F9;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F7F9;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#ffffff;border:1px solid #E6EBF2;border-radius:16px;">
                    <tr>
                        <td style="background-color:#0054cd;border-radius:16px 16px 0 0;padding:20px 28px;">
                            <span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-.5px;">TV+AOS</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 12px;font-size:20px;font-weight:700;color:#14171C;">Inscription approuvée</h1>
                            <p style="margin:0 0 18px;font-size:14px;line-height:22px;color:#41454D;">Bonjour {{ $nom }}, votre demande d’inscription au personnel de <strong>TV+AOS</strong> a été <strong>approuvée</strong>. Votre compte est prêt :</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F7F9;border:1px solid #E6EBF2;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 18px;font-size:14px;line-height:22px;color:#41454D;"><strong>📧 Adresse e-mail :</strong> <span style="font-weight:600;color:#14171C;">{{ $email }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 18px;border-top:1px solid #E6EBF2;font-size:14px;line-height:22px;color:#41454D;"><strong>🔑 Mot de passe temporaire :</strong> <span style="font-family:'Courier New',monospace;font-weight:700;font-size:16px;color:#0054cd;">{{ $motDePasseTemporaire }}</span></td>
                                </tr>
                            </table>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px auto 0;">
                                <tr>
                                    <td align="center" bgcolor="#0054cd" style="border-radius:10px;background-color:#0054cd;">
                                        <a href="{{ url(route('login')) }}" style="display:inline-block;padding:13px 34px;background-color:#0054cd;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;border-radius:10px;">Activer mon accès</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:22px 0 0;font-size:12px;line-height:18px;color:#6D717A;">Ce mot de passe temporaire devra être remplacé lors de votre première connexion. Conservez-le en lieu sûr.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
