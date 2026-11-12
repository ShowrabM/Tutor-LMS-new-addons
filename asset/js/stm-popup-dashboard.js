(function(){
 'use strict';
 function init(){
  var root=document.querySelector('.pm-workspace');if(!root||root.dataset.ready)return;root.dataset.ready='true';
  var config=window.stmPopupDashboard,alertBox=root.querySelector('#pm-alert'),form=root.querySelector('#pm-editor'),pending=0;
  function alertMessage(message){alertBox.textContent=message;alertBox.hidden=false;alertBox.scrollIntoView({behavior:'smooth',block:'center'});}
  function request(data){data.set('nonce',config.nonce);return fetch(config.ajaxUrl,{method:'POST',body:data,credentials:'same-origin'}).then(function(response){return response.json();}).then(function(result){if(!result.success)throw new Error(result.data&&result.data.message||'Please try again.');return result.data;});}
  function go(params){var url=new URL(config.baseUrl);Object.keys(params).forEach(function(key){url.searchParams.set(key,params[key]);});location.assign(url.href);}
  var dialog=root.querySelector('#pm-preview-dialog');
  root.querySelectorAll('[data-preview]').forEach(function(button){button.addEventListener('click',function(){dialog.querySelector('iframe').src=button.dataset.preview;dialog.showModal();});});
  root.querySelector('#pm-close-preview').addEventListener('click',function(){dialog.close();});
  dialog.addEventListener('close',function(){dialog.querySelector('iframe').src='about:blank';});
  root.querySelectorAll('[data-operation]').forEach(function(button){button.addEventListener('click',function(){
   if(button.dataset.operation==='trash'&&!confirm('Move this popup to trash? Its leads and media will be kept.'))return;
   button.disabled=true;var data=new FormData();data.set('action','stm_pm_campaign_action');data.set('popup_id',button.dataset.id);data.set('operation',button.dataset.operation);
   request(data).then(function(result){go({notice:result.operation});}).catch(function(error){alertMessage(error.message);button.disabled=false;});
  });});
  var filter=root.querySelector('#pm-status-filter');if(filter)filter.addEventListener('change',function(){var visible=0;root.querySelectorAll('.pm-campaign').forEach(function(card){card.hidden=filter.value!=='all'&&card.dataset.status!==filter.value;if(!card.hidden)visible++;});root.querySelector('#pm-filter-empty').hidden=visible>0;});
  if(!form)return;
  var save=form.querySelector('#pm-save'),saveStatus=form.querySelector('#pm-save-status');
  var docMimes=['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.oasis.opendocument.text','text/plain'];
  function setAsset(group,asset){
   var image=group.dataset.asset==='image';
   if(image){group.querySelector('[name=banner_id]').value=asset?asset.id:0;group.querySelector('[name=banner_url]').value=asset?asset.url:'';var preview=group.querySelector('.pm-image-preview');preview.hidden=!asset;var img=preview.querySelector('img');if(asset)img.src=asset.url;else img.removeAttribute('src');group.querySelector('[data-remove-asset]').hidden=!asset;}
   else{group.querySelector('[name=gift_id]').value=asset?asset.id:0;group.querySelector('.pm-document-preview').hidden=!asset;group.querySelector('[data-document-name]').textContent=asset?asset.filename||asset.url.split('/').pop():'';}
   group.querySelector('[data-asset-file]').value='';saveStatus.textContent='Unsaved changes. Click Save popup to apply them.';
  }
  root.querySelectorAll('[data-asset]').forEach(function(group){
   var kind=group.dataset.asset,status=group.querySelector('.pm-upload-status'),input=group.querySelector('[data-asset-file]'),frame;
   group.querySelector('[data-device-upload]').addEventListener('click',function(){input.click();});
   group.querySelector('[data-remove-asset]').addEventListener('click',function(){setAsset(group,null);status.textContent=kind==='image'?'Image removed. Save to apply the change.':'Document removed. Save to apply the change.';});
   group.querySelector('[data-media-library]').addEventListener('click',function(){
    if(!window.wp||!wp.media){alertMessage('The Media Library could not load. Refresh this page and try again.');return;}
    if(!frame){
     frame=wp.media({title:kind==='image'?'Choose a popup banner':'Choose the free gift document',button:{text:kind==='image'?'Use this image':'Use this document'},library:{type:kind==='image'?'image':docMimes,posts_per_page:20},multiple:false});
     frame.on('open',function(){frame.content.mode('browse');});
     frame.on('select',function(){
      var asset=frame.state().get('selection').first().toJSON();
      if((kind==='image'&&!/^image\/(jpeg|png|webp|gif)$/.test(asset.mime))||(kind==='document'&&!docMimes.includes(asset.mime))){alertMessage(kind==='image'?'Choose a JPG, PNG, WebP or GIF image.':'Choose a PDF, DOC, DOCX, ODT or TXT document.');return;}
      setAsset(group,asset);status.classList.remove('is-error');status.textContent='Selected from WordPress Media Library. Save to apply.';
     });
    }
    frame.open();
   });
   input.addEventListener('change',function(){
    var file=input.files[0];if(!file)return;
    if(kind==='image'&&!/^image\/(jpeg|png|webp|gif)$/.test(file.type)){status.textContent='Choose a JPG, PNG, WebP or GIF image.';status.classList.add('is-error');input.value='';return;}
    if(kind==='document'&&!/\.(pdf|doc|docx|odt|txt)$/i.test(file.name)){status.textContent='Choose a PDF, DOC, DOCX, ODT or TXT file.';status.classList.add('is-error');input.value='';return;}
    pending++;save.disabled=true;group.querySelectorAll('button,input[type=file]').forEach(function(el){el.disabled=true;});status.textContent='Uploading to your Media Library…';status.classList.remove('is-error');
    var data=new FormData();data.set('action','stm_pm_upload_asset');data.set('kind',kind);data.set('asset_file',file);
    request(data).then(function(asset){setAsset(group,asset);status.textContent='Uploaded to WordPress Media Library. Save to apply.';}).catch(function(error){status.textContent=error.message;status.classList.add('is-error');input.value='';}).finally(function(){pending--;save.disabled=pending>0;group.querySelectorAll('button,input[type=file]').forEach(function(el){el.disabled=false;});});
   });
  });
  form.addEventListener('input',function(){saveStatus.textContent='Unsaved changes. Click Save popup to apply them.';});
  form.addEventListener('submit',function(event){
   event.preventDefault();if(pending||save.disabled)return;
   save.disabled=true;saveStatus.textContent='Saving your popup…';alertBox.hidden=true;
   var data=new FormData(form);data.set('action','stm_save_dashboard_popup_settings');
   request(data).then(function(result){form.querySelector('[name=popup_id]').value=result.popup_id;go({popup:result.popup_id,notice:'saved'});}).catch(function(error){alertMessage(error.message);saveStatus.textContent='Your changes have not been saved.';save.disabled=false;});
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
