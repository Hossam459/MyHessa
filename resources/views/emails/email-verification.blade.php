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
            background-color: #28a745;
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
            background-color: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
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
            <h1>{{ __('mail.email_verification_title') }}</h1>
        </div>
        
        <div class="content">
            <p>{{ __('mail.email_verification_greeting') }} {{ $email }},</p>
            
            <p>{{ __('mail.email_verification_message') }}</p>
            
            <p>
                <a href="{{ $verificationUrl }}" class="button">{{ __('mail.email_verification_button') }}</a>
            </p>
            
            <p>{{ __('mail.email_verification_expiry') }}</p>
            
            <p>{{ __('mail.email_verification_link') }}<br/>
            {{ $verificationUrl }}</p>
            
            <p>{{ __('mail.email_verification_no_action') }}</p>
        </div>
        
        <div class="footer">
            <p>{{ __('mail.regards') }}<br/>{{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
