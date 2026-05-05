<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure sign-in link</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Sign in to FKMP Smart Lab</h2>
    <p>Hello <?= esc($user->username ?? 'there') ?>,</p>
    <p>Use this one-time link to sign in. It expires in <?= esc((string) ($expiresIn ?? 15)) ?> minutes and can only be used once.</p>
    <p>
        <a href="<?= url_to('verify-magic-link') ?>?token=<?= esc($token, 'url') ?>"
           style="display: inline-block; padding: 10px 16px; background: #0d6efd; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Sign in securely
        </a>
    </p>
    <p>If you did not request this link, you can ignore this email.</p>
    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">
    <p style="font-size: 13px; color: #6b7280;">
        Request IP: <?= esc($ipAddress ?? '') ?><br>
        Device: <?= esc($userAgent ?? '') ?><br>
        Time: <?= esc($date ?? '') ?>
    </p>
</body>
</html>
