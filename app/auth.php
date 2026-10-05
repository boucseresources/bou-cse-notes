<?php
function authAction(string $action,array $in): never {
    $ip=$_SERVER['REMOTE_ADDR']??'local';
    $email=strtolower(trim($in['email']??''));
    if (in_array($action,['register','login','forgot','verify','reset','resend'],true)) limit('auth-ip-'.$ip,50,900);
    if ($action==='register') {
        limit('register-'.$ip,8,3600);
        $name=trim($in['name']??'');$pw=$in['password']??'';
        if (mb_strlen($name)<2 || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190 || mb_strlen($pw)<12 || strlen($pw)>72) fail('Enter a name, valid email, and a password of 12–72 characters.');
        if (one('SELECT id FROM users WHERE email=?',[$email])) fail('Unable to create account with these details. Try signing in or resetting your password.');
        query('INSERT INTO users(name,email,password_hash,created_at,updated_at) VALUES(?,?,?,?,?)',[$name,$email,password_hash($pw,PASSWORD_DEFAULT),now(),now()]);$id=(int)db()->lastInsertId();
        $raw=token($id,'verify',86400);
        $sent=sendMail($email,'verify','Verify your email',"Welcome, $name. Verify your account:\n".cfg('app_url').'/#verify?token='.$raw);
        session_regenerate_id(true);rotateCsrf();$_SESSION['user_id']=$id;$_SESSION['version']=1;$_SESSION['last_seen']=time();
        respond(['message'=>$sent?'Check your email to verify your account.':'Account created, but email delivery failed. Use Resend after SMTP is configured.','user'=>safeUser(one('SELECT * FROM users WHERE id=?',[$id]))]);
    }
    if ($action==='login') {
        limit('login-'.$ip.'-'.$email,8,900);$u=one('SELECT * FROM users WHERE email=?',[$email]);
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        if (!password_verify($in['password']??'',$u['password_hash']??$dummy) || !$u || $u['status']!=='active') fail('Email or password is incorrect.',401);
        session_regenerate_id(true);rotateCsrf();$_SESSION['user_id']=$u['id'];$_SESSION['version']=$u['session_version'];$_SESSION['last_seen']=time();
        if (!empty($in['remember'])) cookie('bou_remember',token((int)$u['id'],'remember',2592000),time()+2592000);
        notify((int)$u['id'],'New sign-in','Your account was signed in at '.now().' UTC.');
        $prefs=json_decode($u['preferences']??'{}',true)?:[];
        if (($prefs['security_email']??true)) sendMail($u['email'],'login','New sign-in','Your account was signed in at '.now().' UTC. If this was not you, change your password and sign out all sessions.');
        respond(['user'=>safeUser($u)]);
    }
    if ($action==='logout') {
        $u=user(false,false);if ($u) query("DELETE FROM tokens WHERE user_id=? AND kind='remember'",[$u['id']]);
        anonymousSession();respond(['ok'=>true]);
    }
    if ($action==='resend') {
        $u=user(true,false);limit('resend-'.$u['id'],1,60);
        if ($u['verified_at']) respond(['message'=>'Your email is already verified.']);
        $raw=token((int)$u['id'],'verify',86400);$sent=sendMail($u['email'],'verify','Verify your email','Verify your account: '.cfg('app_url').'/#verify?token='.$raw);
        respond(['message'=>$sent?'Verification email sent.':'Email delivery failed. Contact the administrator.']);
    }
    if ($action==='forgot') {
        limit('forgot-'.$ip,5,900);limit('forgot-email-'.$email,2,900);
        $u=one("SELECT * FROM users WHERE email=? AND status='active'",[$email]);
        if ($u) { $raw=token((int)$u['id'],'reset',1800);sendMail($email,'reset','Reset your password','Reset your password within 30 minutes: '.cfg('app_url').'/#reset?token='.$raw); }
        respond(['message'=>'If an active account uses that email, a reset link has been sent.']);
    }
    if ($action==='verify' || $action==='reset') {
        $kind=$action==='reset'?'reset':'verify';
        db()->beginTransaction();
        $suffix=cfg('database')['driver']==='mysql'?' FOR UPDATE':'';
        $t=one('SELECT * FROM tokens WHERE kind=? AND token_hash=? AND expires_at>?'.$suffix,[$kind,hash('sha256',$in['token']??''),now()]);
        if (!$t) { db()->rollBack();fail('This link is invalid or expired.'); }
        if ($action==='reset') {
            $pw=$in['password']??'';if (mb_strlen($pw)<12 || strlen($pw)>72) {db()->rollBack();fail('Use a password of 12–72 characters.');}
            query('UPDATE users SET password_hash=?,session_version=session_version+1,updated_at=? WHERE id=?',[password_hash($pw,PASSWORD_DEFAULT),now(),$t['user_id']]);
            query('DELETE FROM tokens WHERE user_id=?',[$t['user_id']]);
        } else {
            if ($t['payload']) {
                if (one('SELECT id FROM users WHERE email=? AND id<>?',[$t['payload'],$t['user_id']])) {db()->rollBack();fail('Email is unavailable.');}
                query('UPDATE users SET email=?,verified_at=?,updated_at=? WHERE id=?',[$t['payload'],now(),now(),$t['user_id']]);
            } else query('UPDATE users SET verified_at=?,updated_at=? WHERE id=?',[now(),now(),$t['user_id']]);
            query('DELETE FROM tokens WHERE id=?',[$t['id']]);
        }
        db()->commit();$u=one('SELECT * FROM users WHERE id=?',[$t['user_id']]);
        if ($action==='reset' && (int)($_SESSION['user_id']??0)===(int)$t['user_id']) anonymousSession();
        sendMail($u['email'],$action==='reset'?'password_changed':'welcome',$action==='reset'?'Password changed':'Welcome to BOU CSE Notes',$action==='reset'?'Your password was changed. All remembered sessions were revoked.':'Your email is verified. Your private workspace is ready.');
        respond(['message'=>$action==='reset'?'Password changed. Sign in with your new password.':'Email verified. You can now open your workspace.']);
    }
    fail('Unknown authentication action.',404);
}

