<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1b2e1b; font-size: 12.5px; background: #fff; }

        /* ── HEADER ── */
        .header {
            background: #1b5e20;
            padding: 0;
            margin-bottom: 0;
        }
        .header-top {
            background: #1b5e20;
            padding: 18px 30px 14px 30px;
        }
        .header-logo-row {
            width: 100%;
        }
        .header-logo-row td { vertical-align: middle; }
        .logo-cell { width: 70px; }
        .logo-cell img { width: 60px; height: 60px; border-radius: 50%; border: 2px solid #81c784; }
        .logo-placeholder {
            width: 60px; height: 60px; border-radius: 50%;
            background: #2e7d32; border: 2px solid #81c784;
            text-align: center; line-height: 60px;
            font-size: 22px; font-weight: bold; color: #fff;
        }
        .gym-name { color: #ffffff; font-size: 26px; font-weight: bold; letter-spacing: 3px; padding-left: 14px; }
        .gym-tagline { color: #a5d6a7; font-size: 10px; letter-spacing: 1.5px; padding-left: 14px; padding-top: 3px; }

        .header-bar {
            background: #4caf50;
            height: 6px;
        }

        /* ── BODY ── */
        .body-wrap { padding: 28px 35px; }

        .doc-meta { text-align: right; color: #555; font-size: 11px; margin-bottom: 20px; }
        .doc-ref { color: #2e7d32; font-weight: bold; }

        .to-block { margin-bottom: 18px; font-size: 12.5px; line-height: 1.8; }
        .to-block strong { color: #1b5e20; }

        .subject-line {
            background: #e8f5e9;
            border-left: 4px solid #2e7d32;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: bold;
            color: #1b5e20;
            margin-bottom: 18px;
            letter-spacing: 0.5px;
        }

        p { margin-bottom: 11px; line-height: 1.75; color: #2b2b2b; }

        /* ── DETAILS TABLE ── */
        .details-table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .details-table th {
            background: #1b5e20;
            color: #fff;
            padding: 9px 14px;
            text-align: left;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .details-table td {
            padding: 8px 14px;
            border-bottom: 1px solid #c8e6c9;
            font-size: 12px;
        }
        .details-table tr:nth-child(even) td { background: #f1f8e9; }
        .details-table .label { color: #2e7d32; font-weight: bold; width: 38%; }

        /* ── SALARY BADGE ── */
        .salary-badge {
            display: inline-block;
            background: #2e7d32;
            color: #fff;
            padding: 3px 10px;
            border-radius: 3px;
            font-weight: bold;
        }

        /* ── ACCEPTANCE ── */
        .acceptance-box {
            border: 1px dashed #4caf50;
            background: #f9fbe7;
            padding: 12px 16px;
            margin: 18px 0;
            font-size: 11.5px;
            color: #33691e;
            line-height: 1.6;
        }

        /* ── SIGNATURE ── */
        .sign-section { margin-top: 40px; }
        .sign-table { width: 100%; }
        .sign-table td { width: 50%; vertical-align: bottom; padding-top: 40px; }
        .sign-line {
            border-top: 1.5px solid #2e7d32;
            padding-top: 6px;
            font-size: 11px;
            color: #2e7d32;
            font-weight: bold;
        }
        .sign-sub { font-size: 10.5px; color: #555; font-weight: normal; }

        /* ── FOOTER ── */
        .footer {
            margin-top: 30px;
            border-top: 3px solid #4caf50;
            padding-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
        .footer span { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="header-top">
        <table class="header-logo-row">
            <tr>
                <td class="logo-cell">
                    @if(file_exists(public_path('images/logo.png')))
                        <img src="{{ public_path('images/logo.png') }}" alt="Logo">
                    @else
                        <div class="logo-placeholder">M</div>
                    @endif
                </td>
                <td>
                    <div class="gym-name">MTTM GYM</div>
                    <div class="gym-tagline">EXCELLENCE IN FITNESS &nbsp;|&nbsp; YOUR HEALTH, OUR PRIORITY</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="header-bar"></div>
</div>

{{-- BODY --}}
<div class="body-wrap">

    <div class="doc-meta">
        Date: <strong>{{ \Carbon\Carbon::now()->format('d F Y') }}</strong> &nbsp;&nbsp;
        Ref: <span class="doc-ref">MTTMG/EMP/{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>

    <div class="to-block">
        To,<br>
        <strong>{{ $employee->name }}</strong><br>
        {{ $employee->address }}
    </div>

    <div class="subject-line">OFFER LETTER &mdash; {{ strtoupper($employee->designation) }}</div>

    <p>Dear <strong>{{ $employee->name }}</strong>,</p>

    <p>
        We are pleased to offer you employment at <strong>MTTM GYM</strong> for the position of
        <strong>{{ $employee->designation }}</strong>. After reviewing your profile, we are confident
        that you will be a valuable addition to our team.
    </p>

    <table class="details-table">
        <tr>
            <th colspan="2">EMPLOYMENT DETAILS</th>
        </tr>
        <tr>
            <td class="label">Full Name</td>
            <td>{{ $employee->name }}</td>
        </tr>
        <tr>
            <td class="label">Designation</td>
            <td>{{ $employee->designation }}</td>
        </tr>
        <tr>
            <td class="label">Date of Joining</td>
            <td>{{ \Carbon\Carbon::parse($employee->joining_date)->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Monthly Salary</td>
            <td><span class="salary-badge">&#8377; {{ number_format($employee->salary, 2) }}</span></td>
        </tr>
        @if($employee->morning_slot)
        <tr>
            <td class="label">Morning Shift</td>
            <td>{{ $employee->morning_slot }}</td>
        </tr>
        @endif
        @if($employee->evening_slot)
        <tr>
            <td class="label">Evening Shift</td>
            <td>{{ $employee->evening_slot }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Blood Group</td>
            <td>{{ $employee->blood_group }}</td>
        </tr>
    </table>

    <p>
        You are expected to report for duty on
        <strong>{{ \Carbon\Carbon::parse($employee->joining_date)->format('d M Y') }}</strong>.
        Please carry your original documents on your joining day.
    </p>

    <div class="acceptance-box">
        &#9745; &nbsp;By joining MTTM GYM, you agree to abide by the company's rules, regulations,
        and code of conduct. This offer is valid for <strong>7 days</strong> from the date of this letter.
    </div>

    <p>We look forward to a long and productive association with you. Welcome to the MTTM GYM family! 💪</p>

    <div class="sign-section">
        <table class="sign-table">
            <tr>
                <td>
                    <div class="sign-line">
                        Authorised Signatory<br>
                        <span class="sign-sub">MTTM GYM Management</span>
                    </div>
                </td>
                <td style="text-align:right;">
                    <div class="sign-line">
                        Signature of Employee<br>
                        <span class="sign-sub">{{ $employee->name }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</div>

{{-- FOOTER --}}
<div class="footer">
    <span>MTTM GYM</span> &nbsp;|&nbsp; Excellence in Fitness &nbsp;|&nbsp;
    This is a system-generated offer letter.
</div>

</body>
</html>
