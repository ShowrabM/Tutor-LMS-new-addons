(function(){
 'use strict';
 function init(){
  var popups=Array.from(document.querySelectorAll('.stm-offer[data-popup-id]'));
  var current=null,previousFocus=null,timer=null;
  function suppressed(modal){
   if(modal.dataset.preview==='true')return false;
   try{return Number(localStorage.getItem('stm_pm_suppress_'+modal.dataset.popupId)||0)>Date.now();}catch(e){return false;}
  }
  var queue=popups.filter(function(modal){return !suppressed(modal);});
  function next(afterClose){
   if(!queue.length)return;
   var modal=queue.shift();
   timer=setTimeout(function(){
    if(suppressed(modal)){next(false);return;}
    previousFocus=document.activeElement;current=modal;
    modal.querySelectorAll('img[data-src]').forEach(function(img){img.src=img.dataset.src;img.removeAttribute('data-src');});
    modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.classList.add('stm-offer-locked');
    var first=modal.querySelector('input[type=email]')||modal.querySelector('.stm-offer-close');
    first.focus({preventScroll:true});
   },modal.dataset.preview==='true'?0:Math.max(afterClose?2:0,Number(modal.dataset.delay)||0)*1000);
  }
  function close(){
   if(!current)return;
   var modal=current;current=null;modal.hidden=true;modal.setAttribute('aria-hidden','true');
   document.body.classList.remove('stm-offer-locked');
   if(modal.dataset.preview!=='true'){
    var days=Number(modal.dataset.frequency)||0;
    if(days>0){try{localStorage.setItem('stm_pm_suppress_'+modal.dataset.popupId,String(Date.now()+days*86400000));}catch(e){}}
   }
   if(previousFocus&&previousFocus.isConnected)previousFocus.focus({preventScroll:true});
   next(true);
  }
  popups.forEach(function(modal){
   modal.querySelectorAll('[data-offer-close]').forEach(function(button){button.addEventListener('click',close);});
   var form=modal.querySelector('form'),submit=form.querySelector('button[type=submit]'),error=modal.querySelector('.stm-offer-error');
   form.addEventListener('submit',function(event){
    event.preventDefault();if(submit.disabled)return;
    error.hidden=true;submit.disabled=true;var text=submit.querySelector('span'),label=text.textContent;text.textContent='Sending…';
    var data=new FormData(form);data.append('action','stm_submit_popup_lead');data.append('page_url',location.href);
    var promise=modal.dataset.preview==='true'?Promise.resolve({success:true,data:{message:'This is a preview. No details have been saved.'}}):fetch(stmPopupMakerData.ajaxUrl,{method:'POST',body:data,credentials:'same-origin'}).then(function(response){return response.json();});
    promise.then(function(result){
     if(!result.success)throw new Error(result.data&&result.data.message||'Please try again.');
     form.hidden=true;modal.querySelector('.stm-offer-intro').hidden=true;
     var success=modal.querySelector('.stm-offer-success');success.hidden=false;
     success.querySelector('.stm-offer-success-message').textContent=result.data.message;
     if(result.data.gift_url){var link=success.querySelector('.stm-offer-gift');link.href=result.data.gift_url;link.querySelector('span').textContent=result.data.gift_label||'Download your gift';link.hidden=false;success.querySelector('.stm-offer-gift-note').hidden=false;}
     success.focus({preventScroll:true});
    }).catch(function(err){error.textContent=err.message||'Something went wrong. Please try again.';error.hidden=false;}).finally(function(){submit.disabled=false;text.textContent=label;});
   });
  });
  document.addEventListener('keydown',function(event){
   if(!current)return;
   if(event.key==='Escape'){event.preventDefault();close();return;}
   if(event.key==='Tab'){
    var controls=Array.from(current.querySelectorAll('button,a[href],input:not([type=hidden]),textarea')).filter(function(el){return !el.disabled&&el.getClientRects().length&&!el.closest('[hidden]')&&!el.closest('.stm-offer-honey');});
    var first=controls[0],last=controls[controls.length-1];
    if(event.shiftKey&&(document.activeElement===first||!controls.includes(document.activeElement))){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&(document.activeElement===last||!controls.includes(document.activeElement))){event.preventDefault();first.focus();}
   }
  });
  next(false);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
