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
(function(){'use strict';function csrfHeader(){var meta=document.querySelector('meta[name="X-CSRF-TOKEN"]');var headers={};if(meta){headers['X-CSRF-TOKEN']=meta.getAttribute('content')}
return headers}
function jsonHeaders(){var headers=csrfHeader();headers.Accept='application/json';headers['Content-Type']='application/json';headers['X-Requested-With']='XMLHttpRequest';return headers}
function postJson(url,body){return fetch(url,{method:'POST',headers:jsonHeaders(),credentials:'same-origin',body:JSON.stringify(body||{})}).then(function(response){return response.json().catch(function(){return{}}).then(function(data){if(response.status===401&&data&&data.redirect){window.location.assign(data.redirect)}
return{status:response.status,data:data||{}}})})}
function reducedMotion(){return!!(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)}
var PREF_PREFIX='osis2026.';function getPref(key){try{return window.localStorage.getItem(PREF_PREFIX+key)}catch(e){return null}}
function setPref(key,value){try{if(value===null){window.localStorage.removeItem(PREF_PREFIX+key)}else{window.localStorage.setItem(PREF_PREFIX+key,value)}}catch(e){}}
function initModals(){document.addEventListener('click',function(event){var opener=event.target.closest('[data-modal-open]');if(opener){var dialog=document.getElementById(opener.getAttribute('data-modal-open'));if(dialog&&typeof dialog.showModal==='function'){event.preventDefault();dialog.showModal()}
return}
var closer=event.target.closest('[data-modal-close]');if(closer){var parent=closer.closest('dialog');if(parent){event.preventDefault();parent.close()}
return}
if(event.target.tagName==='DIALOG'&&event.target.open&&!event.target.hasAttribute('data-modal-static')){var rect=event.target.getBoundingClientRect();var inside=event.clientX>=rect.left&&event.clientX<=rect.right&&event.clientY>=rect.top&&event.clientY<=rect.bottom;if(!inside){event.target.close()}}})}
function initSubmitGuard(){document.addEventListener('submit',function(event){var form=event.target;if(form.dataset.submitting==='1'){event.preventDefault();return}
form.dataset.submitting='1';var button=form.querySelector('button[type="submit"]');if(button){button.setAttribute('aria-disabled','true');if(button.dataset.loadingText){button.textContent=button.dataset.loadingText}}});window.addEventListener('pageshow',function(event){if(!event.persisted){return}
document.querySelectorAll('form[data-submitting="1"]').forEach(function(form){form.dataset.submitting='';var button=form.querySelector('button[type="submit"]');if(button){button.removeAttribute('aria-disabled')}})})}
function initFlash(){document.querySelectorAll('[data-flash-dismiss]').forEach(function(el){var timer=window.setTimeout(function(){el.setAttribute('hidden','hidden')},6000);el.addEventListener('mouseenter',function(){window.clearTimeout(timer)})})}
function initPasswordToggles(){document.querySelectorAll('[data-password-toggle]').forEach(function(button){var input=document.getElementById(button.getAttribute('aria-controls'));var label=button.querySelector('[data-password-toggle-label]');if(!input){return}
function set(visible){input.type=visible?'text':'password';button.setAttribute('aria-pressed',visible?'true':'false');button.classList.toggle('is-visible',visible);if(label){label.textContent=visible?'Sembunyikan kata sandi':'Tampilkan kata sandi'}}
button.hidden=!1;button.addEventListener('click',function(){var end=input.value.length;set(input.type==='password');input.focus();try{input.setSelectionRange(end,end)}catch(e){}});if(input.form){input.form.addEventListener('submit',function(){set(!1)})}})}
document.addEventListener('DOMContentLoaded',function(){initModals();initSubmitGuard();initFlash();initPasswordToggles()});window.App={csrfHeader:csrfHeader,jsonHeaders:jsonHeaders,postJson:postJson,reducedMotion:reducedMotion,getPref:getPref,setPref:setPref}})()