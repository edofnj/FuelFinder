<?php
// Widget account nell'header (incluso da index.php e route.php).
$ffUser = function_exists('currentUser') ? currentUser() : null;
$ffDe   = (function_exists('currentLang') && currentLang() === 'de');
?>
<div class="acct-wrap">
<?php if ($ffUser): ?>
    <button type="button" class="acct-btn" id="acctBtn" aria-haspopup="true" title="<?= htmlspecialchars($ffUser['email']) ?>"><?= htmlspecialchars(strtoupper(mb_substr($ffUser['email'], 0, 1))) ?></button>
    <div class="acct-menu" id="acctMenu" hidden>
        <div class="acct-email"><?= htmlspecialchars($ffUser['email']) ?></div>
        <a href="/profile" class="acct-link">&#9881; <?= $ffDe ? 'Konto verwalten' : 'Gestisci account' ?></a>
        <?php if (!empty($ffUser['is_admin'])): ?><a href="/stats" class="acct-link">&#128202; <?= $ffDe ? 'Statistiken' : 'Metriche' ?></a><?php endif; ?>
        <a href="#" class="acct-link" id="acctLogout"><?= $ffDe ? 'Abmelden' : 'Esci' ?></a>
    </div>
<?php else: ?>
    <button type="button" class="acct-btn acct-login" onclick="ffOpenAuth('login')"><?= $ffDe ? 'Anmelden' : 'Accedi' ?></button>
<?php endif; ?>
</div>
<script>
(function(){
  // auth = vera pagina /account (niente modal). Porta dietro il "next".
  window.ffOpenAuth=function(t){ location.href='/account?tab='+(t||'login')+'&next='+encodeURIComponent(location.pathname+location.search); };
  var ab=document.getElementById('acctBtn'), am=document.getElementById('acctMenu');
  if(ab&&am){ ab.addEventListener('click',function(e){e.stopPropagation();am.hidden=!am.hidden;}); document.addEventListener('click',function(){am.hidden=true;}); }
  function wire(id,action){ var el=document.getElementById(id); if(!el) return; el.addEventListener('click',function(e){e.preventDefault();
    var fd=new FormData(); fd.append('action',action); fd.append('csrf',window.FF_CSRF||'');
    fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){ location.href='/'; }); }); }
  wire('acctLogout','logout');
})();
</script>
