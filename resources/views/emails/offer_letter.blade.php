<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1b5e20; padding: 28px 30px 18px; text-align: center; }
        .header h1 { color: #fff; font-size: 26px; letter-spacing: 3px; margin: 0; }
        .header p { color: #a5d6a7; font-size: 11px; letter-spacing: 1.5px; margin: 5px 0 0; }
        .bar { background: #4caf50; height: 5px; }
        .body { padding: 30px 35px; color: #333; line-height: 1.7; }
        .greeting { font-size: 17px; color: #1b5e20; font-weight: bold; margin-bottom: 14px; }
        .highlight { color: #2e7d32; font-weight: bold; }
        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            padding: 14px 18px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-size: 13.5px;
        }
        .info-box p { margin: 4px 0; }
        .btn {
            display: inline-block;
            background: #2e7d32;
            color: #fff;
            padding: 12px 28px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            margin: 18px 0 10px;
        }
        .footer {
            background: #f9f9f9;
            border-top: 3px solid #4caf50;
            padding: 16px 30px;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
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
            <div class="greeting">Welcome to the MTTM GYM Family! 🎉</div>

            <p>Dear <span class="highlight">{{ $employee->name }}</span>,</p>

            <p>
                We are delighted to inform you that you have been selected for the position of
                <span class="highlight">{{ $employee->designation }}</span> at <strong>MTTM GYM</strong>.
            </p>

            <div class="info-box">
                <p>&#128197; <strong>Date of Joining:</strong> {{ \Carbon\Carbon::parse($employee->joining_date)->format('d M Y') }}</p>
                <p>&#128188; <strong>Designation:</strong> {{ $employee->designation }}</p>
                <p>&#128181; <strong>Monthly Salary:</strong> &#8377; {{ number_format($employee->salary, 2) }}</p>
            </div>

            <p>
                Please find your <strong>Offer Letter</strong> attached as a PDF. Kindly sign and return
                a copy on your joining day.
            </p>

            <p>
                We look forward to working with you and wish you a great career ahead. 💪
            </p>

            <p>
                Warm regards,<br>
                <strong style="color:#1b5e20;">MTTM GYM Management</strong>
            </p>
        </div>

        <div class="footer">
            This is an auto-generated email from <span>MTTM GYM</span>. Please do not reply to this email.
        </div>

    </div>
</body>
</html>
