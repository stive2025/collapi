<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .code-box {
            background-color: #fff;
            border: 2px dashed #4CAF50;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
            letter-spacing: 5px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recuperación de Contraseña</h1>
        </div>
        <div class="content">
            <h2>Hola, {{ $user_name }}</h2>
            <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
            <p>Tu código de verificación es:</p>
            
            <div class="code-box">
                <div class="code">{{ $code }}</div>
            </div>
            
            <p><strong>Este código expirará en 15 minutos.</strong></p>
            <p>Si no solicitaste este cambio, por favor ignora este correo.</p>
        </div>
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas.</p>
        </div>
    </div>
</body>
</html>