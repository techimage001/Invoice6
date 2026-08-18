/* InvoiceGeneratorNow — email verification capture.
   Works on ANY page that contains #signupForm: the homepage gate, /register.php and /login.php.
   Posts to the ported Card Maker Messages API and always shows the server's own message,
   so a failure names itself instead of disappearing. */
(function(){
  'use strict';
  var form = document.getElementById('signupForm');
  if(!form) return;

  var statusEl  = document.getElementById('signupStatus');
  var emailEl   = document.getElementById('signupEmail');
  var startedEl = document.getElementById('signupStarted');

  function say(msg){ if(statusEl) statusEl.textContent = msg; }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var email = (emailEl && emailEl.value || '').trim();
    if(!/^\S+@\S+\.\S+$/.test(email)){ say('Please enter a valid email address.'); return; }

    var btn = form.querySelector('button[type="submit"]');
    var original = btn ? btn.textContent : '';
    if(btn){ btn.disabled = true; btn.textContent = 'Sending verification link…'; }
    say('Sending a private verification link…');

    // The server requires the form to have been open a few seconds. The field is stamped
    // server-side, so if it is ever missing we use a time in the past rather than now.
    var startedRaw = Number((startedEl && startedEl.value) || 0);
    var started = startedRaw > 0 ? startedRaw : (Date.now() - 10000);
    var honeypot = (form.querySelector('[name="company"]') || {}).value || '';

    fetch('/api/subscribe.php', {
      method: 'POST',
      body: JSON.stringify({ email: email, ts: started, website: honeypot, page: location.pathname }),
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
    .then(function(r){ return r.json().catch(function(){ return {}; }).then(function(p){ return { r: r, p: p }; }); })
    .then(function(res){
      var r = res.r, p = res.p;
      if(!r.ok || p.ok === false){
        throw new Error((p.message || 'The verification email could not be sent.') + (p.reason ? (' [' + p.reason + ']') : ''));
      }
      say(p.message || 'Check your inbox and open the verification link.');
      if(btn) btn.textContent = 'Send another verification link';
      if(startedEl) startedEl.value = String(Date.now() - 10000);
    })
    .catch(function(err){ say(err.message || 'Please try again.'); })
    .finally(function(){
      if(btn){ btn.disabled = false; if(btn.textContent === 'Sending verification link…') btn.textContent = original || 'Email my verification link'; }
    });
  });
})();

/* Header state: "Sign up" before verification, avatar with sign out after.
   Reads the same /api/status.php the gate uses, so it reflects the server, not a guess. */
(function(){
  'use strict';
  var nav = document.getElementById('accessNav');
  if(!nav) return;
  var signUp = document.getElementById('accessSignUp');
  var account= document.getElementById('accessAccount');
  var avatar = document.getElementById('accessAvatar');
  var menu   = document.getElementById('accessMenu');
  var emailEl= document.getElementById('accessEmail');
  var signOut= document.getElementById('accessSignOut');

  function out(){ nav.dataset.state='out'; if(signUp) signUp.hidden=false; if(account) account.hidden=true; }
  function inn(email){
    nav.dataset.state='in';
    if(signUp) signUp.hidden=true;
    if(account) account.hidden=false;
    if(emailEl) emailEl.textContent=email||'';
    if(avatar) avatar.textContent=(email||'?').charAt(0).toUpperCase();
  }
  fetch('/api/status.php',{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'})
    .then(function(r){return r.json();})
    .then(function(p){ if(p&&p.verified){inn(p.email);} else {out();} })
    .catch(out);

  if(avatar) avatar.addEventListener('click',function(){
    var open = menu && !menu.hidden;
    if(menu) menu.hidden = open;
    avatar.setAttribute('aria-expanded', open?'false':'true');
  });
  document.addEventListener('click',function(e){
    if(menu && !menu.hidden && !menu.contains(e.target) && e.target!==avatar){
      menu.hidden=true; if(avatar) avatar.setAttribute('aria-expanded','false');
    }
  });
  if(signOut) signOut.addEventListener('click',function(){
    fetch('/api/logout.php',{method:'POST',credentials:'same-origin',cache:'no-store'})
      .catch(function(){}).then(function(){ location.reload(); });
  });
})();
