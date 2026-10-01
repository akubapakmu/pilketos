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
(function(){'use strict';var script=document.currentScript;if(!script||!window.fetch||!window.JSON){return}
var url=script.getAttribute('data-presence-url');var interval=Math.max(30,parseInt(script.getAttribute('data-presence-interval'),10)||60)*1000;var timer=null;var last=0;var busy=!1;var stopped=!url;var format=window.Intl&&window.Intl.NumberFormat?new window.Intl.NumberFormat('id-ID'):null;function size(width,height){return Math.round(width)+'x'+Math.round(height)}
function info(){var connection=navigator.connection||{};var zone='';var dark=!1;try{zone=window.Intl.DateTimeFormat().resolvedOptions().timeZone||''}catch(e){zone=''}
try{dark=window.matchMedia('(prefers-color-scheme: dark)').matches}catch(e){dark=!1}
return{screen:size(window.screen.width,window.screen.height),viewport:size(window.innerWidth,window.innerHeight),dpr:window.devicePixelRatio||1,tz:zone,touch:navigator.maxTouchPoints||0,net:connection.effectiveType||'',scheme:dark?'dark':'light'}}
function headers(){if(window.App&&typeof window.App.jsonHeaders==='function'){return window.App.jsonHeaders()}
var meta=document.querySelector('meta[name="X-CSRF-TOKEN"]');var result={Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'};if(meta){result['X-CSRF-TOKEN']=meta.getAttribute('content')}
return result}
function render(online){document.querySelectorAll('[data-online-count]').forEach(function(node){var value=online[node.getAttribute('data-online-count')];if(typeof value==='number'){var text=format?format.format(value):String(value);if(node.textContent!==text){node.textContent=text}}})}
function schedule(){window.clearTimeout(timer);if(!stopped){timer=window.setTimeout(beat,interval)}}
function beat(){if(stopped||busy||document.visibilityState==='hidden'){return}
busy=!0;last=Date.now();window.fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',headers:headers(),body:JSON.stringify(info())}).then(function(response){if(response.status===401||response.status===403||response.status===404){stopped=!0;return null}
return response.ok?response.json():null}).then(function(data){if(!data){return}
if(typeof data.interval==='number'&&data.interval>=30){interval=data.interval*1000}
if(data.online){render(data.online)}}).catch(function(){}).then(function(){busy=!1;schedule()})}
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'&&Date.now()-last>interval/2){beat()}});window.setTimeout(beat,1500);var countUrl=script.getAttribute('data-online-url');var refresh=Math.max(1,parseInt(script.getAttribute('data-online-refresh'),10)||1)*1000;var countTimer=null;var counting=!1;var countStopped=!countUrl||!document.querySelector('[data-online-count]');function scheduleCount(delay){window.clearTimeout(countTimer);if(!countStopped&&document.visibilityState!=='hidden'){countTimer=window.setTimeout(count,delay)}}
function count(){if(countStopped||counting||document.visibilityState==='hidden'){return}
counting=!0;var delay=refresh;window.fetch(countUrl,{method:'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}).then(function(response){if(response.status===404){countStopped=!0;return null}
if(!response.ok){delay=5000;return null}
return response.json()}).then(function(data){if(data&&data.online){render(data.online)}}).catch(function(){delay=5000}).then(function(){counting=!1;scheduleCount(delay)})}
if(!countStopped){document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'){scheduleCount(0)}else{window.clearTimeout(countTimer)}});scheduleCount(refresh)}})()