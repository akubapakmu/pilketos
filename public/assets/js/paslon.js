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
(function(){'use strict';var reduced=window.App&&window.App.reducedMotion?window.App.reducedMotion():!1;var hasIO='IntersectionObserver' in window;function initNav(root){var nav=root.querySelector('[data-px-nav]');if(!nav){return}
var links=Array.prototype.slice.call(nav.querySelectorAll('[data-px-nav-link]'));var list=nav.querySelector('.px-nav__list');var progress=nav.querySelector('[data-px-progress]');var shell=nav.parentNode;var sections=[];links.forEach(function(link){var target=document.getElementById(link.getAttribute('href').slice(1));if(target){sections.push({link:link,el:target})}});if(sections.length===0){return}
if(progress){progress.hidden=!1}
var activeIndex=-1;function setActive(index){if(index===activeIndex){return}
activeIndex=index;sections.forEach(function(item,i){if(i===index){item.link.setAttribute('aria-current','true')}else{item.link.removeAttribute('aria-current')}});if(list&&list.scrollWidth>list.clientWidth){var link=sections[index].link;var left=link.offsetLeft-(list.clientWidth-link.offsetWidth)/2;list.scrollTo({left:Math.max(0,left),behavior:reduced?'auto':'smooth'})}}
var ticking=!1;function update(){ticking=!1;var line=Math.min(window.innerHeight*0.3,260);var index=0;for(var i=0;i<sections.length;i++){if(sections[i].el.getBoundingClientRect().top<=line){index=i}}
var doc=document.documentElement;if(window.innerHeight+window.pageYOffset>=doc.scrollHeight-4){index=sections.length-1}
setActive(index);if(progress){var rect=shell.getBoundingClientRect();var span=rect.height-window.innerHeight;var value=span>0?Math.min(1,Math.max(0,-rect.top/span)):1;progress.style.setProperty('--px-progress',value.toFixed(4))}}
function request(){if(!ticking){ticking=!0;window.requestAnimationFrame(update)}}
window.addEventListener('scroll',request,{passive:!0});window.addEventListener('resize',request);request()}
function initReveal(root){var items=root.querySelectorAll('[data-px-reveal]');var observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting){entry.target.classList.add('is-in');observer.unobserve(entry.target)}})},{rootMargin:'0px 0px -8% 0px',threshold:0.08});for(var i=0;i<items.length;i++){observer.observe(items[i])}}
function initLightbox(root){var dialog=root.querySelector('[data-px-lightbox]');if(!dialog||typeof dialog.showModal!=='function'){return}
var opener=null;root.querySelectorAll('[data-px-zoom]').forEach(function(el){el.addEventListener('click',function(event){event.preventDefault();opener=el.tagName==='A'?el:null;dialog.showModal()})});var close=dialog.querySelector('[data-px-close]');if(close){close.addEventListener('click',function(){dialog.close()})}
dialog.addEventListener('click',function(event){if(event.target===dialog){dialog.close()}});dialog.addEventListener('close',function(){if(opener){opener.focus()}})}
function upgradeThumb(img){var hd=img.getAttribute('data-hd')||'';if(hd.indexOf('https://i.ytimg.com/vi/')!==0){return}
var probe=new Image();probe.onload=function(){if(probe.naturalWidth>120){img.src=hd}};probe.src=hd}
function initVideo(root){root.querySelectorAll('[data-px-video]').forEach(function(box){var play=box.querySelector('[data-px-play]');var frame=box.querySelector('[data-px-video-frame]');var thumb=box.querySelector('.px-video__thumb');var src=box.getAttribute('data-embed')||'';if(thumb){if(thumb.complete&&thumb.naturalWidth>0){upgradeThumb(thumb)}else{thumb.addEventListener('load',function onLoad(){thumb.removeEventListener('load',onLoad);upgradeThumb(thumb)})}}
if(!play||!frame||src.indexOf('https://www.youtube-nocookie.com/embed/')!==0){return}
play.setAttribute('role','button');function start(event){event.preventDefault();var iframe=document.createElement('iframe');iframe.setAttribute('src',src);iframe.setAttribute('title',box.getAttribute('data-title')||'Video kampanye');iframe.setAttribute('allow','autoplay; encrypted-media; picture-in-picture; fullscreen');iframe.setAttribute('allowfullscreen','');iframe.setAttribute('referrerpolicy','strict-origin-when-cross-origin');iframe.setAttribute('loading','eager');frame.textContent='';frame.appendChild(iframe);iframe.focus()}
play.addEventListener('click',start);play.addEventListener('keydown',function(event){if(event.key===' '||event.key==='Spacebar'){start(event)}})})}
function init(){var root=document.querySelector('[data-paslon]');if(!root){return}
initNav(root);initLightbox(root);initVideo(root);if(reduced||!hasIO){return}
root.classList.add('px-motion');initReveal(root)}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init)}else{init()}})()