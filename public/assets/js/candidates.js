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
(function(){'use strict';var reduced=window.App&&window.App.reducedMotion?window.App.reducedMotion():!1;var hasIO='IntersectionObserver' in window;function initJourney(journey){if(!journey){return}
var steps=journey.querySelectorAll('[data-journey-step]');var current=0;function setStep(index){current=Math.max(0,Math.min(steps.length-1,index));for(var i=0;i<steps.length;i++){steps[i].classList.toggle('is-done',i<current);steps[i].classList.toggle('is-current',i===current);if(i===current){steps[i].setAttribute('aria-current','step')}else{steps[i].removeAttribute('aria-current')}}}
var ballot=document.getElementById('surat-suara');if(ballot&&hasIO){var observer=new IntersectionObserver(function(entries){for(var i=0;i<entries.length;i++){if(entries[i].isIntersecting){if(current<1){setStep(1)}
observer.disconnect();return}}},{rootMargin:'0px 0px -40% 0px'});observer.observe(ballot)}
document.addEventListener('osis:journey',function(event){if(event.detail&&typeof event.detail.step==='number'){setStep(event.detail.step)}})}
function initLineupAutoplay(list){if(!list||reduced||!window.matchMedia){return}
var cards=list.querySelectorAll('.pair-card');if(cards.length<2){return}
var mobile=window.matchMedia('(max-width: 719px)');var DELAY=2000;var IDLE=5000;var timer=null;var holdUntil=0;var inView=!hasIO;var focused=!1;function offsetOf(i){return cards[i].getBoundingClientRect().left-cards[0].getBoundingClientRect().left}
function currentIndex(){var left=list.scrollLeft;var best=0;for(var i=1;i<cards.length;i++){if(Math.abs(offsetOf(i)-left)<Math.abs(offsetOf(best)-left)){best=i}}
return best}
function advance(){var max=list.scrollWidth-list.clientWidth;var next=currentIndex()+1;if(next>=cards.length||list.scrollLeft>=max-2){next=0}
list.scrollTo({left:Math.min(offsetOf(next),max),behavior:'smooth'})}
function stop(){window.clearTimeout(timer);timer=null}
function schedule(){stop();if(!mobile.matches||!inView||focused||document.hidden){return}
timer=window.setTimeout(function(){timer=null;if(Date.now()>=holdUntil){advance()}
schedule()},Math.max(DELAY,holdUntil-Date.now()))}
function hold(){holdUntil=Date.now()+IDLE;schedule()}['pointerdown','touchstart','touchend','wheel'].forEach(function(type){list.addEventListener(type,hold,{passive:!0})});list.addEventListener('focusin',function(){focused=!0;stop()});list.addEventListener('focusout',function(event){if(!list.contains(event.relatedTarget)){focused=!1;hold()}});document.addEventListener('visibilitychange',schedule);if(mobile.addEventListener){mobile.addEventListener('change',schedule)}else if(mobile.addListener){mobile.addListener(schedule)}
if(hasIO){new IntersectionObserver(function(entries){inView=entries[entries.length-1].isIntersecting;schedule()},{threshold:0.6}).observe(list)}
schedule()}
function init(){initJourney(document.querySelector('[data-journey]'));initLineupAutoplay(document.querySelector('[data-lineup]'))}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init)}else{init()}})()