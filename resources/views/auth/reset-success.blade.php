<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Succès - GSCO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --success: #10b981;
            --primary: #3949ab;
            --bg: #f4f7f6;
            --card-bg: #ffffff;
            --text: #333;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .card {
            background: var(--card-bg);
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }

        .icon {
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 1.5rem;
        }

        h1 {
            color: var(--text);
            margin-bottom: 1rem;
            font-size: 1.75rem;
        }

        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: opacity 0.3s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ asset('images/logo.png') }}" alt="EDUNOVA" style="max-width: 100px; margin-bottom: 1.5rem;">
        <div class="icon">✓</div>
        <h1>Mot de passe réinitialisé !</h1>
        <p>Votre mot de passe a été modifié avec succès sur <strong>EDUNOVA</strong>. Vous pouvez maintenant vous connecter à votre compte en utilisant votre nouveau mot de passe.</p>
        <a href="/" class="btn">Retour à l'accueil</a>
    </div>
</body>
</html>
