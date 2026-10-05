<?php
declare(strict_types=1);

/** Shared HTML email layout. The original message remains the plain-text version. */
function renderBrandedEmail(string $kind, string $subject, string $body, array $brand): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $name = $brand['name'] ?? 'BOU CSE Notes';
    $base = rtrim($brand['url'] ?? '', '/');
    $support = $brand['support'] ?? '';
    $logo = $brand['logo'] ?? '';
    $actions = [
        'verify' => ['Verify email address', 'This verification link expires in 24 hours.'],
        'reset' => ['Reset password', 'This reset link expires in 30 minutes. If you did not request it, you can ignore this email.'],
        'welcome' => ['Open your workspace', 'Your email is verified. You are ready to get started.'],
        'login' => ['Open your account', 'If this was not you, change your password and sign out all sessions.'],
        'password_changed' => ['Open your account', 'If you did not change your password, contact support immediately.'],
    ];
    $action = $actions[$kind] ?? ['Open your workspace', ''];
    $url = $base;
    $message = $body;
    $hasTokenLink = false;
    if (in_array($kind, ['verify', 'reset'], true)) {
        $url = '';
        // Only the exact application's token links may become an action button.
        $pattern = '~' . preg_quote($base . '/#' . $kind . '?token=', '~') . '[a-f0-9]+~i';
        if ($base !== '' && preg_match($pattern, $body, $matches)) {
            $url = $matches[0];
            $message = trim(str_replace($url, '', $body));
            $hasTokenLink = true;
        }
    }
    if (parse_url($url, PHP_URL_SCHEME) !== 'https') $url = '';
    if (parse_url($logo, PHP_URL_SCHEME) !== 'https') $logo = '';
    $heading = $escape($subject);
    $brandName = $escape($name);
    $content = nl2br($escape($message));
    $logoHtml = $logo !== '' ? '<img src="' . $escape($logo) . '" alt="' . $brandName . '" width="156" style="display:block;width:156px;max-width:100%;height:auto;border:0;margin-bottom:16px;">' : '';
    $button = '';
    if ($url !== '') {
        $safeUrl = $escape($url);
        $button = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0;"><tr><td bgcolor="#032f73" style="border-radius:8px;mso-padding-alt:14px 24px;"><a href="' . $safeUrl . '" style="display:inline-block;padding:14px 24px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:16px;">' . $escape($action[0]) . '</a></td></tr></table>';
        if ($hasTokenLink) $button .= '<p style="font-size:12px;line-height:20px;color:#64748b;">Button not working? Copy this link into your browser:<br><a href="' . $safeUrl . '" style="color:#032f73;word-break:break-all;overflow-wrap:anywhere;">' . $safeUrl . '</a></p>';
    }
    $note = $action[1] !== '' ? '<p style="margin-top:24px;padding:16px;background:#f1f5f9;border-radius:8px;font-size:13px;line-height:21px;color:#475569;">' . $escape($action[1]) . '</p>' : '';
    $contact = filter_var($support, FILTER_VALIDATE_EMAIL) ? 'Need help? <a href="mailto:' . $escape($support) . '" style="color:#032f73;text-decoration:underline;">' . $escape($support) . '</a><br>' : '';
    $preheader = $escape($subject . ' — ' . $name);
    $year = date('Y');
    return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$heading}</title></head>
<body style="margin:0;padding:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{$preheader}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f3f6fb"><tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;">
<tr><td bgcolor="#032f73" style="padding:28px 28px 24px;border-radius:12px 12px 0 0;color:#ffffff;">{$logoHtml}<div style="font-size:22px;font-weight:bold;line-height:30px;">{$brandName}</div><div style="margin-top:6px;font-size:13px;line-height:20px;color:#dbeafe;">Your study resources, together.</div></td></tr>
<tr><td bgcolor="#ffffff" style="padding:28px;border-radius:0 0 12px 12px;"><h1 style="margin:0 0 18px;font-size:24px;line-height:32px;color:#0f172a;">{$heading}</h1><p style="margin:0;font-size:16px;line-height:26px;">{$content}</p>{$button}{$note}<p style="margin:26px 0 0;font-size:14px;line-height:22px;">Best wishes,<br><strong>The {$brandName} team</strong></p></td></tr>
<tr><td align="center" style="padding:22px 18px;font-size:12px;line-height:20px;color:#64748b;">{$contact}This is an automated account email.<br>&copy; {$year} {$brandName}</td></tr>
</table></td></tr></table>
</body></html>
HTML;
}
