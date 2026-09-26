<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .header {
            background: linear-gradient(135deg, #1a237e 0%, #3949ab 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px 30px;
            color: #444444;
            line-height: 1.6;
        }
        .content p {
            margin-bottom: 20px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            background-color: #3949ab;
            color: #ffffff !important;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background-color: #1a237e;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #eeeeee;
        }
        .note {
            font-size: 13px;
            color: #777777;
            border-top: 1px solid #eeeeee;
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="EDUNOVA" style="max-width: 150px; margin-bottom: 10px;">
            <h1>EDUNOVA</h1>
        </div>
        <div class="content">
            <h2>Bonjour {{ $userName }},</h2>
            <p>Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte sur <strong>EDUNOVA</strong>.</p>
            
            <p>Utilisez le code de vérification ci-dessous pour réinitialiser votre mot de passe :</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <div style="background-color: #f0f4f8; border: 2px dashed #3949ab; border-radius: 8px; display: inline-block; padding: 20px 40px; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1a237e;">
                    {{ $code }}
                </div>
            </div>
            
            <p>Ce code expirera dans {{ $expire }} minutes.</p>
            <p>Si vous n'avez pas demandé de réinitialisation de mot de passe, aucune action supplémentaire n'est requise.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} EDUNOVA. Tous droits réservés.
        </div>
    </div>
</body>
</html>
