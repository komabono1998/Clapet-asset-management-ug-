(function(){
  'use strict';
  var $=function(s,c){return (c||document).querySelector(s)};
  var $$=function(s,c){return Array.prototype.slice.call((c||document).querySelectorAll(s))};

  /* mobile nav + dropdowns */
  var burger=$('.burger'),nav=$('.nav');
  if(burger&&nav){
    burger.addEventListener('click',function(){
      var o=nav.classList.toggle('open');
      burger.setAttribute('aria-expanded',o);
      document.body.style.overflow=o?'hidden':'';
      document.body.classList.toggle('menu-open',o);
      if(o)window.scrollTo(0,0);
    });
  }
  $$('.has-dd > button.dd').forEach(function(b){
    b.addEventListener('click',function(e){
      e.preventDefault();
      if(window.innerWidth<=760){location.href='services.php';return}
      var p=b.parentNode,o=p.classList.toggle('open');
      b.setAttribute('aria-expanded',o);
    });
  });

  /* scroll reveal */
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){
      es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target)}});
    },{threshold:.12});
    $$('.rv').forEach(function(el){io.observe(el)});
  }else{$$('.rv').forEach(function(el){el.classList.add('in')})}

  /* tabs */
  $$('[data-tabs]').forEach(function(root){
    var tabs=$$('.tab',root),panels=$$('.tabpanel',root);
    tabs.forEach(function(t,i){
      t.addEventListener('click',function(){
        tabs.forEach(function(x){x.setAttribute('aria-selected','false')});
        panels.forEach(function(p){p.classList.remove('on')});
        t.setAttribute('aria-selected','true');panels[i].classList.add('on');
      });
    });
  });

  /* before / after slider */
  $$('.ba').forEach(function(ba){
    var r=$('input',ba),after=$('.after-wrap',ba),h=$('.handle',ba);
    function set(v){after.style.clipPath='inset(0 0 0 '+v+'%)';h.style.left=v+'%'}
    r.addEventListener('input',function(){set(r.value)});
    set(r.value);
  });

  /* filters on listing / project pages */
  $$('[data-filter-form]').forEach(function(f){
    var out=$(f.getAttribute('data-filter-form'));
    f.addEventListener('submit',function(e){e.preventDefault();if(out)out.hidden=false});
  });

  /* multi-step brief form */
  var form=$('#brief-form');
  if(form){
    var steps=$$('.fstep',form),bars=$$('.prog i',form),cur=0,msg=$('.form-msg',form);
    function show(n){
      cur=n;steps.forEach(function(s,i){s.classList.toggle('on',i===n)});
      bars.forEach(function(b,i){b.classList.toggle('on',i<=n)});
      var t=$('.stepttl',form);if(t)t.textContent='Step '+(n+1)+' of '+steps.length;
      if(n===steps.length-1)buildReview();
      form.scrollIntoView({behavior:'smooth',block:'start'});
    }
    function svc(){var c=$('input[name=service]:checked',form);return c?c.value:''}
    function syncSvc(){
      var v=svc();
      $$('.svc-fields',form).forEach(function(g){g.classList.toggle('on',g.getAttribute('data-svc')===v)});
    }
    $$('input[name=service]',form).forEach(function(r){r.addEventListener('change',syncSvc)});
    var pre=(location.hash.match(/service=([a-z-]+)/)||[])[1]||new URLSearchParams(location.search).get('service');
    if(pre){var el=$('input[name=service][value="'+pre+'"]',form);if(el){el.checked=true}}
    var qp=new URLSearchParams(location.search);
    ['location','timeline'].forEach(function(k){var v=qp.get(k),f=form.elements[k];if(v&&f)f.value=v});
    syncSvc();
    function valid(n){
      var ok=true;
      if(n===0&&!svc()){flash('Please select a service to continue.');return false}
      $$('input,select,textarea',steps[n]).forEach(function(f){
        if(f.offsetParent===null)return;
        if(!f.checkValidity()){ok=false;f.reportValidity();return}
      });
      return ok;
    }
    function flash(t){msg.className='form-msg err';msg.textContent=t}
    function buildReview(){
      var d=new FormData(form),dl=document.createElement('dl');
      d.forEach(function(v,k){
        if(!v||v instanceof File||k==='website'||k==='consent'||k==='form')return;
        var dt=document.createElement('dt'),dd=document.createElement('dd');
        dt.textContent=k.replace(/_/g,' ');dd.textContent=v;dl.appendChild(dt);dl.appendChild(dd);
      });
      var box=$('.review',form);box.innerHTML='';box.appendChild(dl);
    }
    $$('[data-next]',form).forEach(function(b){b.addEventListener('click',function(){msg.className='form-msg';if(valid(cur))show(cur+1)})});
    $$('[data-prev]',form).forEach(function(b){b.addEventListener('click',function(){show(cur-1)})});
    form.addEventListener('submit',function(e){
      e.preventDefault();
      if(!valid(cur))return;
      var btn=$('[type=submit]',form);btn.disabled=true;btn.textContent='Submitting…';
      fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)})
        .then(function(r){return r.json()})
        .then(function(j){
          if(j.ok){
            form.reset();syncSvc();
            msg.className='form-msg ok';
            msg.innerHTML='<b>Thank you — your brief has been received.</b><br>Your reference number is <b>'+j.reference+'</b>. Please quote it in any follow-up. A member of our team will respond to the contact details you provided.';
            steps.forEach(function(s){s.classList.remove('on')});
            $('.prog',form).style.display='none';
          }else{flash(j.error||'We could not submit your brief. Please check the fields and try again.');btn.disabled=false;btn.textContent='Submit brief'}
        })
        .catch(function(){
          flash('The submission service is unavailable right now. Please reach us on WhatsApp or by email and quote your requirement.');
          btn.disabled=false;btn.textContent='Submit brief';
        });
    });
    show(0);
  }

  /* simple enquiry / consultation forms */
  $$('form[data-ajax]').forEach(function(f){
    var m=$('.form-msg',f);
    f.addEventListener('submit',function(e){
      e.preventDefault();
      var b=$('[type=submit]',f);b.disabled=true;
      fetch(f.getAttribute('action'),{method:'POST',body:new FormData(f)})
        .then(function(r){return r.json()})
        .then(function(j){
          if(j.ok){f.reset();m.className='form-msg ok';m.innerHTML='<b>Received.</b> Your reference number is <b>'+j.reference+'</b>. We will respond using your preferred contact method.'}
          else{m.className='form-msg err';m.textContent=j.error||'Submission failed. Please check the fields.'}
          b.disabled=false;
        })
        .catch(function(){m.className='form-msg err';m.textContent='The submission service is unavailable. Please contact us on WhatsApp or by email.';b.disabled=false});
    });
  });

  /* privacy-friendly analytics: time on page + key clicks (no cookies) */
  (function(){
    if(!navigator.sendBeacon||navigator.doNotTrack==='1')return;
    var page=(location.pathname.split('/').pop()||'index').replace(/\.(php|html)$/,'')||'index',t0=Date.now(),vis=0,last=Date.now(),sent=false;
    function send(o){try{var f=new FormData();for(var k in o)f.append(k,o[k]);f.append('p',page);navigator.sendBeacon('track.php',f)}catch(e){}}
    document.addEventListener('visibilitychange',function(){if(document.hidden){vis+=Date.now()-last;if(!sent&&vis>1000){sent=true;send({t:'dwell',ms:vis})}}else{last=Date.now();sent=false}});
    window.addEventListener('pagehide',function(){vis+=Date.now()-last;if(!sent&&vis>1000){sent=true;send({t:'dwell',ms:vis})}});
    document.addEventListener('click',function(e){
      var a=e.target.closest&&e.target.closest('a');if(!a)return;var h=a.getAttribute('href')||'',l='';
      if(h.indexOf('wa.me')>-1)l='WhatsApp';else if(h.indexOf('tel:')===0)l='Call';else if(h.indexOf('mailto:')===0)l='Email';
      else if(/contact\.(php|html)#brief/.test(h))l='Submit a brief';else if(/contact\.(php|html)#consultation/.test(h))l='Consultation';
      else if(/portal\.(php|html)/.test(h))l='Client portal';
      if(l)send({t:'click',l:l});
    });
  })();
})();
