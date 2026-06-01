<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
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
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .code {
            display: inline-block;
            padding: 14px 24px;
            margin: 20px 0;
            border: 1px solid #007bff;
            border-radius: 5px;
            color: #007bff;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 6px;
        }
        .footer {
            background-color: #f5f5f5;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 5px 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ __('mail.password_reset_title') }}</h1>
        </div>
        
        <div class="content">
            <p>{{ __('mail.password_reset_greeting') }} {{ $email }},</p>
            
            <p>{{ __('mail.password_reset_message') }}</p>
            
            <p class="code">{{ $code }}</p>
            
            <p>{{ __('mail.password_reset_expiry') }}</p>
            
            <p>{{ __('mail.password_reset_ignore') }}</p>
        </div>
        
        <div class="footer">
            <p>{{ __('mail.regards') }}<br/>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
