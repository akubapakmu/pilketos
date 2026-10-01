/*!
   ███████╗ █████╗      ██╗ █████╗ ██████╗
   ██╔════╝██╔══██╗     ██║██╔══██╗██╔══██╗
   █████╗  ███████║     ██║███████║██████╔╝
   ██╔══╝  ██╔══██║██   ██║██╔══██║██╔══██╗
   ██║     ██║  ██║╚█████╔╝██║  ██║██║  ██║
   ╚═╝     ╚═╝  ╚═╝ ╚════╝ ╚═╝  ╚═╝╚═╝  ╚═╝

╭┌─┐╮  ╔═╗╔╦╗╔═╗  ╗  ╔╦╗╔═╗╦ ╦╔═╗  ╔═╗╔═╗╔═╗╔═╗
││  │  ╚═╗║║║╠═╝  ║   ║║╠═╣║║║║╣   ╔═╝║ ║╔═╝╠═╗
╰└─┘╯  ╚═╝╩ ╩╩    ╩  ═╩╝╩ ╩╚╩╝╚═╝  ╚═╝╚═╝╚═╝╚═╝
*/
(function(){'use strict';var PAPER='#FAF9F6';var INK='#15141A';var WHITE='#FFFFFF';function formatBytes(bytes){if(bytes>=1048576){return(bytes/1048576).toLocaleString('id-ID',{maximumFractionDigits:1})+' MB'}
return Math.max(1,Math.round(bytes/1024))+' KB'}
function initDrawer(){var shell=document.querySelector('[data-admin-shell]');var nav=document.getElementById('admin-nav');var main=document.querySelector('[data-admin-main]');var scrim=document.querySelector('[data-admin-scrim]');var openButton=document.querySelector('[data-admin-menu]');var closeButton=document.querySelector('[data-admin-menu-close]');if(!shell||!nav||!openButton){return}
function isOpen(){return shell.classList.contains('is-nav-open')}
function open(){shell.classList.add('is-nav-open');openButton.setAttribute('aria-expanded','true');if(scrim){scrim.hidden=!1}
if(main){main.setAttribute('inert','')}
document.documentElement.style.overflow='hidden';var first=nav.querySelector('[aria-current="page"]')||nav.querySelector('a, button');if(first){first.focus()}}
function close(restoreFocus){if(!isOpen()){return}
shell.classList.remove('is-nav-open');openButton.setAttribute('aria-expanded','false');if(scrim){scrim.hidden=!0}
if(main){main.removeAttribute('inert')}
document.documentElement.style.overflow='';if(restoreFocus){openButton.focus()}}
openButton.addEventListener('click',open);if(closeButton){closeButton.addEventListener('click',function(){close(!0)})}
if(scrim){scrim.addEventListener('click',function(){close(!0)})}
document.addEventListener('keydown',function(event){if(event.key==='Escape'&&isOpen()){close(!0)}});if(window.matchMedia){var wide=window.matchMedia('(min-width: 1024px)');var onChange=function(event){if(event.matches){close(!1)}};if(wide.addEventListener){wide.addEventListener('change',onChange)}else if(wide.addListener){wide.addListener(onChange)}}}
function initConfirm(){var dialog=document.getElementById('admin-confirm');var supported=dialog&&typeof dialog.showModal==='function';var text=dialog?dialog.querySelector('#admin-confirm-text'):null;var accept=dialog?dialog.querySelector('[data-confirm-accept]'):null;var cancel=dialog?dialog.querySelector('[data-modal-close]'):null;var pending=null;document.addEventListener('submit',function(event){var form=event.target;if(!form.matches||!form.matches('form[data-confirm]')||form.dataset.confirmed==='1'){return}
if(form.noValidate&&typeof form.checkValidity==='function'&&!form.checkValidity()){event.preventDefault();event.stopImmediatePropagation();form.reportValidity();return}
event.preventDefault();event.stopImmediatePropagation();var message=form.getAttribute('data-confirm');if(!supported){if(window.confirm(message)){form.dataset.confirmed='1';form.submit()}
return}
pending={form:form,submitter:event.submitter||null};text.textContent=message;accept.textContent=form.getAttribute('data-confirm-button')||'Lanjutkan';var danger=form.hasAttribute('data-confirm-danger');dialog.classList.toggle('is-danger',danger);dialog.showModal();(danger?cancel:accept).focus()},!0);if(!supported){return}
accept.addEventListener('click',function(){if(!pending){return}
var form=pending.form;var submitter=pending.submitter;pending=null;dialog.close();form.dataset.confirmed='1';if(typeof form.requestSubmit==='function'){if(submitter){form.requestSubmit(submitter)}else{form.requestSubmit()}}else{form.submit()}});dialog.addEventListener('close',function(){pending=null});window.addEventListener('pageshow',function(event){if(event.persisted){document.querySelectorAll('form[data-confirmed]').forEach(function(form){delete form.dataset.confirmed})}})}
function checkFile(input){var file=input.files&&input.files[0];var max=parseInt(input.getAttribute('data-max-bytes'),10)||0;var kind=input.getAttribute('data-file-kind')||'image';if(!file){return''}
if(max&&file.size>max){return'Ukuran file '+formatBytes(file.size)+' melebihi batas '+formatBytes(max)+'.'}
if(kind==='xlsx'&&!/\.xlsx$/i.test(file.name)){return'Pilih file Excel dengan ekstensi .xlsx.'}
if(kind==='image'&&file.type&&!/^image\/(jpeg|png|webp)$/.test(file.type)){return'Pilih gambar JPG, PNG, atau WebP.'}
return''}
function initFileInputs(){document.querySelectorAll('input[type="file"][data-file-input]').forEach(function(input){var holder=input.closest('[data-slot]')||input.closest('.field');var error=holder?holder.querySelector('[data-file-error]'):null;var preview=holder?holder.querySelector('[data-file-preview]'):null;var objectUrl=null;input.addEventListener('change',function(){var message=checkFile(input);var file=input.files&&input.files[0];if(error){error.textContent=message;error.hidden=message===''}
input.setAttribute('aria-invalid',message?'true':'false');if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null}
if(preview){if(file&&!message&&window.URL&&URL.createObjectURL){objectUrl=URL.createObjectURL(file);preview.src=objectUrl;preview.alt='Pratinjau file baru';preview.hidden=!1}else{preview.hidden=!0;preview.removeAttribute('src')}}
if(holder){holder.classList.toggle('is-pending',!!file&&!message)}})});document.addEventListener('submit',function(event){var form=event.target;if(!form.matches||!form.matches('form[data-upload-form]')){return}
var inputs=form.querySelectorAll('input[type="file"][data-file-input]');var total=0;var firstBad=null;inputs.forEach(function(input){if(input.files&&input.files[0]){total+=input.files[0].size}
if(!firstBad&&checkFile(input)){firstBad=input}});var limit=parseInt(form.getAttribute('data-post-max'),10)||0;var totalError=form.querySelector('[data-upload-total-error]');var message='';if(!firstBad&&limit&&total>limit*0.95){message='Total ukuran file ('+formatBytes(total)+') melebihi batas server '+formatBytes(limit)+'. Unggah sebagian dulu, simpan, lalu lanjutkan.'}
if(totalError){totalError.textContent=message;totalError.hidden=message===''}
if(firstBad||message){event.preventDefault();event.stopImmediatePropagation();var focusTarget=firstBad||form.querySelector('[type="submit"]');if(focusTarget){focusTarget.focus()}}},!0)}
function luminance(hex){var channels=[1,3,5].map(function(offset){var c=parseInt(hex.substr(offset,2),16)/255;return c<=0.03928?c/12.92:Math.pow((c+0.055)/1.055,2.4)});return 0.2126*channels[0]+0.7152*channels[1]+0.0722*channels[2]}
function contrast(a,b){var la=luminance(a);var lb=luminance(b);return(Math.max(la,lb)+0.05)/(Math.min(la,lb)+0.05)}
function initAccent(){var picker=document.querySelector('[data-accent-picker]');var input=document.querySelector('[data-accent-input]');var preview=document.querySelector('[data-accent-preview]');if(!picker||!input||!preview){return}
function normalize(value){var v=(value||'').trim();if(/^[0-9a-f]{6}$/i.test(v)){v='#'+v}
return/^#[0-9a-f]{6}$/i.test(v)?v.toUpperCase():null}
function apply(hex){preview.style.setProperty('--accent',hex);preview.style.setProperty('--accent-ink',contrast(hex,WHITE)>=contrast(hex,INK)?WHITE:INK);preview.style.setProperty('--accent-text',contrast(hex,PAPER)>=3?hex:INK)}
picker.addEventListener('input',function(){input.value=picker.value.toUpperCase();apply(input.value)});input.addEventListener('input',function(){var hex=normalize(input.value);if(hex){picker.value=hex.toLowerCase();apply(hex)}});var number=document.getElementById('nomor_urut');var name=document.getElementById('nama_ketua');var block=preview.querySelector('.accent-preview__block');var strong=preview.querySelector('.accent-preview__text strong');if(number&&block){number.addEventListener('input',function(){var n=parseInt(number.value,10);block.textContent=n>0&&n<100?(n<10?'0'+n:String(n)):'00'})}
if(name&&strong){name.addEventListener('input',function(){strong.textContent=name.value.trim()||'Nama ketua'})}}
function initSecrets(root){(root||document).querySelectorAll('[data-secret-toggle]').forEach(function(button){var target=document.getElementById(button.getAttribute('aria-controls'));var label=button.querySelector('[data-secret-toggle-label]');if(!target||!label||button.dataset.secretReady==='1'){return}
button.dataset.secretReady='1';var showText=label.textContent;button.hidden=!1;button.addEventListener('click',function(){var revealed=target.classList.toggle('is-revealed');button.setAttribute('aria-pressed',revealed?'true':'false');label.textContent=revealed?showText.replace('Tampilkan','Samarkan'):showText})})}
function initAutosubmit(){document.addEventListener('change',function(event){var select=event.target;var form=select&&select.tagName==='SELECT'?select.closest('form[data-autosubmit]'):null;if(!form){return}
if(typeof form.requestSubmit==='function'){form.requestSubmit()}else{form.submit()}})}
function initLiveSearch(){if(!window.fetch||!window.DOMParser||!window.URLSearchParams){return}
var DELAY=300;var timer=null;var controller=null;var requestId=0;var status=null;var pushed=!1;function announce(text){if(!status){status=document.createElement('p');status.className='visually-hidden';status.setAttribute('role','status');status.setAttribute('aria-live','polite');document.body.appendChild(status)}
status.textContent='';window.setTimeout(function(){status.textContent=text},50)}
function buildUrl(form){var url=actionUrl(form);var params=new URLSearchParams();new FormData(form).forEach(function(value,key){if(typeof value==='string'&&value.trim()!==''){params.append(key,value.trim())}});url.search=params.toString();return url}
function actionUrl(form){return new URL(form.getAttribute('action')||window.location.href,window.location.href)}
function sameOrigin(url){try{return url.origin===window.location.origin}catch(e){return!1}}
function enhanced(form){try{return sameOrigin(actionUrl(form))}catch(e){return!1}}
function regions(){return Array.prototype.slice.call(document.querySelectorAll('[data-live-region]'))}
function setBusy(owner,busy){if(owner){owner.classList.toggle('is-searching',busy)}
regions().forEach(function(region){region.classList.toggle('is-stale',busy);if(busy){region.setAttribute('aria-busy','true')}else{region.removeAttribute('aria-busy')}})}
function syncNavs(doc){document.querySelectorAll('[data-live-nav]:not([data-live-region])').forEach(function(nav){var name=nav.getAttribute('data-live-nav');var fresh=name?doc.querySelector('[data-live-nav="'+name+'"]'):null;if(!fresh){return}
var freshLinks=fresh.querySelectorAll('a[href]');nav.querySelectorAll('a[href]').forEach(function(link,i){var current=freshLinks[i]?freshLinks[i].getAttribute('aria-current'):null;if(current){link.setAttribute('aria-current',current)}else{link.removeAttribute('aria-current')}});revealCurrent(nav)})}
function revealCurrent(nav){var link=nav.querySelector('[aria-current="page"]');if(!link||nav.scrollWidth<=nav.clientWidth){return}
var box=nav.getBoundingClientRect();var tab=link.getBoundingClientRect();nav.scrollLeft+=(tab.left-box.left)-(nav.clientWidth-tab.width)/2}
function load(url,options){var owner=options.owner||null;if(controller){controller.abort()}
controller=window.AbortController?new AbortController():null;var id=++requestId;var timedOut=!1;var abortTimer=window.setTimeout(function(){timedOut=!0;if(controller){controller.abort()}},12000);var headers={Accept:'text/html','X-Requested-With':'XMLHttpRequest'};if(owner&&owner.hasAttribute('data-pane-form')){headers['X-Analytics-Pane']='1'}
setBusy(owner,!0);window.fetch(url.href,{method:'GET',credentials:'same-origin',cache:'no-store',headers:headers,signal:controller?controller.signal:undefined}).then(function(response){if(id!==requestId){return null}
if(!response.ok||response.redirected){window.location.assign(url.href);return null}
return response.text()}).then(function(html){if(html===null||html===undefined||id!==requestId||(owner&&!document.contains(owner))){return}
var doc=new DOMParser().parseFromString(html,'text/html');var current=regions();var fresh=current.map(function(region){return doc.querySelector('[data-live-region="'+region.getAttribute('data-live-region')+'"]')});if(current.length===0||fresh.indexOf(null)!==-1){window.location.assign(url.href);return}
var replaced=current.map(function(region,i){var node=document.importNode(fresh[i],!0);region.parentNode.replaceChild(node,region);initSecrets(node);return node});if(options.history==='push'){window.history.pushState({liveNav:!0},'',url.href);pushed=!0}else if(options.history!=='none'){window.history.replaceState(window.history.state,'',url.href)}
syncNavs(doc);if(options.focus&&replaced[0]){replaced[0].setAttribute('tabindex','-1');replaced[0].focus({preventScroll:!0})}
if(options.scrollTo&&options.scrollTo.getBoundingClientRect().top<0){options.scrollTo.scrollIntoView({block:'start'})}
var count=document.querySelector('[data-live-region] .result-count, [data-live-region] [data-live-announce]');if(count){announce(count.textContent.replace(/\s+/g,' ').trim())}}).catch(function(error){if(id!==requestId||(error&&error.name==='AbortError'&&!timedOut)){return}
window.location.assign(url.href)}).then(function(){window.clearTimeout(abortTimer);if(id===requestId){setBusy(owner,!1)}})}
function search(form){window.clearTimeout(timer);var url=buildUrl(form);if(url.href===window.location.href){return}
load(url,{owner:form})}
document.addEventListener('input',function(event){var input=event.target;var form=input&&input.type==='search'?input.closest('form[data-live-search]'):null;if(!form||!enhanced(form)){return}
window.clearTimeout(timer);var length=input.value.trim().length;if(length===1){return}
timer=window.setTimeout(function(){search(form)},DELAY)});document.addEventListener('submit',function(event){var form=event.target;if(!form.matches||!form.matches('form[data-live-search]')||form.hasAttribute('data-pane-form')||!enhanced(form)){return}
event.preventDefault();event.stopImmediatePropagation();search(form)},!0);document.addEventListener('click',function(event){var link=event.target.closest?event.target.closest('[data-live-nav] a[href]'):null;if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||(link.target&&link.target!=='_self')||link.hasAttribute('download')||regions().length===0){return}
var url=new URL(link.href,window.location.href);if(!sameOrigin(url)){return}
event.preventDefault();if(url.href===window.location.href){return}
var nav=link.closest('[data-live-nav]');var inRegion=nav.hasAttribute('data-live-region');load(url,{owner:inRegion?null:nav,history:'push',focus:inRegion,scrollTo:document.querySelector('[data-live-nav]:not([data-live-region])')||null})});window.addEventListener('popstate',function(){if(!pushed||regions().length===0){return}
load(new URL(window.location.href),{history:'none'})});document.querySelectorAll('[data-live-nav]:not([data-live-region])').forEach(revealCurrent)}
function initPresentation(){document.querySelectorAll('[data-print]').forEach(function(button){if(typeof window.print!=='function'){return}
button.hidden=!1;button.addEventListener('click',function(){window.print()})});document.querySelectorAll('[data-fullscreen]').forEach(function(button){var target=document.querySelector(button.getAttribute('data-fullscreen'));var label=button.querySelector('[data-fullscreen-label]');var request=target?(target.requestFullscreen||target.webkitRequestFullscreen):null;if(!request||!(document.fullscreenEnabled||document.webkitFullscreenEnabled)){return}
function current(){return document.fullscreenElement||document.webkitFullscreenElement||null}
function sync(){var on=current()===target;button.setAttribute('aria-pressed',on?'true':'false');if(label){label.textContent=on?'Keluar layar penuh':'Layar penuh'}}
button.hidden=!1;button.addEventListener('click',function(){if(current()){(document.exitFullscreen||document.webkitExitFullscreen).call(document);return}
var pending=request.call(target);if(pending&&typeof pending.catch==='function'){pending.catch(function(){})}});document.addEventListener('fullscreenchange',sync);document.addEventListener('webkitfullscreenchange',sync);sync()})}
function initLede(){var button=document.querySelector('[data-lede-toggle]');var lede=document.getElementById('admin-head-lede');if(!button){return}
if(!lede){button.hidden=!0;return}
button.addEventListener('click',function(){var open=!lede.classList.contains('is-open');lede.classList.toggle('is-open',open);button.setAttribute('aria-expanded',open?'true':'false')})}
function initNoteTips(){document.addEventListener('keydown',function(event){if(event.key!=='Escape'){return}
document.querySelectorAll('[data-note-tip]').forEach(function(tip){tip.classList.add('is-dismissed')})});document.querySelectorAll('[data-note-tip]').forEach(function(tip){['mouseenter','focusin'].forEach(function(type){tip.addEventListener(type,function(){tip.classList.remove('is-dismissed')})})})}
document.addEventListener('DOMContentLoaded',function(){initDrawer();initLede();initNoteTips();initConfirm();initFileInputs();initAccent();initSecrets();initAutosubmit();initLiveSearch();initPresentation()})})()