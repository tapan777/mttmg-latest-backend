<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1b5e20; padding: 26px 30px; text-align: center; }
        .header h1 { color: #fff; font-size: 24px; letter-spacing: 3px; margin: 0; }
        .header p { color: #a5d6a7; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #4caf50; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.75; font-size: 14px; }
        .greeting { font-size: 17px; color: #1b5e20; font-weight: bold; margin-bottom: 14px; }
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            padding: 14px 18px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-size: 13.5px;
        }
        .footer { background: #f9f9f9; border-top: 3px solid #4caf50; padding: 14px 30px; text-align: center; font-size: 11px; color: #999; }
        .footer span { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>MTTM GYM</h1>
            <p>EXCELLENCE IN FITNESS &nbsp;|&nbsp; YOUR HEALTH, OUR PRIORITY</p>
        </div>
        <div class="bar"></div>

        <div class="body">
            <div class="greeting">Dear {{ $memberName }},</div>

            <p>
                Your trainer has assigned you a personalized diet plan at <strong>MTTM GYM</strong>.
                Please follow it regularly for the best results.
            </p>

            <div class="info-box">
                &#127825; <strong>Diet Plan:</strong> {{ $dietName }}
                @if($attachmentPath)
                    <br>&#128206; Your diet plan document is attached to this email.
                @endif
            </div>

            <p>
                A balanced diet combined with regular exercise will help you achieve your fitness goals faster.
                Contact your trainer at the gym if you have any questions. 💪
            </p>

            <p>
                Best regards,<br>
                <strong style="color:#1b5e20;">MTTM GYM Team</strong>
            </p>
        </div>

        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply.
        </div>
    </div>
</body>
</html>
