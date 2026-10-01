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
(function(){'use strict';var page=document.querySelector('[data-visitors]');if(!page||!window.fetch||!window.DOMParser){return}
var seconds=Math.max(10,parseInt(page.getAttribute('data-visitors-refresh'),10)||20);var timer=null;var busy=!1;function regions(){return Array.prototype.slice.call(document.querySelectorAll('[data-live-region]'))}
function idle(){var active=document.activeElement;var typing=active&&active.closest&&active.closest('form.filters')&&active.matches('input, select');return document.visibilityState==='visible'&&!typing&&!document.querySelector('[data-live-region][aria-busy]')}
function openDetails(){return Array.prototype.map.call(document.querySelectorAll('details[data-visitor-detail][open]'),function(node){return node.getAttribute('data-visitor-detail')})}
function refresh(){if(busy||!idle()){schedule();return}
busy=!0;var keep=openDetails();window.fetch(window.location.href,{method:'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'}}).then(function(response){return response.ok&&!response.redirected?response.text():null}).then(function(html){if(!html||!idle()){return}
var doc=new DOMParser().parseFromString(html,'text/html');regions().forEach(function(region){var fresh=doc.querySelector('[data-live-region="'+region.getAttribute('data-live-region')+'"]');if(fresh){region.parentNode.replaceChild(document.importNode(fresh,!0),region)}});keep.forEach(function(id){var node=document.querySelector('details[data-visitor-detail="'+id+'"]');if(node){node.open=!0}})}).catch(function(){}).then(function(){busy=!1;schedule()})}
function schedule(){window.clearTimeout(timer);timer=window.setTimeout(refresh,seconds*1000)}
var summaryUrl=page.getAttribute('data-visitors-summary');var tick=Math.max(1,parseInt(page.getAttribute('data-visitors-tick'),10)||1)*1000;var tickTimer=null;var ticking=!1;function scheduleTick(delay){window.clearTimeout(tickTimer);if(summaryUrl&&document.visibilityState!=='hidden'){tickTimer=window.setTimeout(readSummary,delay)}}
function applySummary(fresh){var region=document.querySelector('[data-live-region="ringkas"]');if(!region||region.hasAttribute('aria-busy')){return}
var board=region.querySelector('dl');var freshBoard=fresh.querySelector('dl');if(board&&freshBoard&&board.innerHTML!==freshBoard.innerHTML){region.replaceChild(document.importNode(freshBoard,!0),board)}
var time=region.querySelector('.visitor-updated time');var freshTime=fresh.querySelector('.visitor-updated time');if(time&&freshTime){time.setAttribute('datetime',freshTime.getAttribute('datetime')||'');time.textContent=freshTime.textContent}}
function readSummary(){if(ticking||document.visibilityState==='hidden'){return}
ticking=!0;var delay=tick;window.fetch(summaryUrl,{method:'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'}}).then(function(response){if(!response.ok||response.redirected){delay=5000;return null}
return response.text()}).then(function(html){if(!html){return}
var fresh=new DOMParser().parseFromString(html,'text/html').querySelector('[data-live-region="ringkas"]');if(fresh){applySummary(fresh)}}).catch(function(){delay=5000}).then(function(){ticking=!1;scheduleTick(delay)})}
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'){refresh();scheduleTick(0)}else{window.clearTimeout(tickTimer)}});schedule();scheduleTick(tick)})()